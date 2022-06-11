<?php
/**
 * Stuff that belongs in 'wp-admin/includes/class-wp-upgrader.php'.
 *
 * @package rollback-update-failure
 */

namespace Rollback_Update_Failure;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class WP_Upgrader
 */
class WP_Upgrader {
	/**
	 * The error/notification strings used to update the user on the progress.
	 *
	 * @since 2.8.0
	 * @var array $strings
	 */
	public $strings = array();

	/**
	 * Store options for rollback callbacks.
	 *
	 * @since 6.1.0
	 * @var array
	 */
	private $options = array();

	/**
	 * Store list of plugins/themes added to temp-backup directory.
	 *
	 * Used by rollback functions.
	 *
	 * @since 6.1.0
	 * @var array
	 */
	private $temp_backups = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add generic strings to Rollback_Update_Failure::$strings.
		$this->strings['temp_backup_mkdir_failed'] = __( 'Could not create temp-backup directory.', 'rollback-update-failure' );
		$this->strings['temp_backup_move_failed']  = __( 'Could not move old version to the temp-backup directory.', 'rollback-update-failure' );
		/* translators: %s: The theme/plugin slug. */
		$this->strings['temp_backup_restore_failed'] = __( 'Could not restore original version of %s.', 'rollback-update-failure' );
		$this->strings['fs_no_content_dir']          = __( 'Unable to locate WordPress content directory (wp-content).' );

		/* translators: %s: The theme/plugin slug. */
		$this->strings['temp_backup_delete_failed'] = __( 'Could not delete the temporary backup directory for %s.' );

		// Move the plugin/theme being updated to rollback directory.
		add_filter( 'upgrader_pre_install', array( $this, 'upgrader_pre_install' ), 15, 2 );

		// Restore backup if install_package returns WP_Error.
		add_filter( 'upgrader_install_package_result', array( $this, 'upgrader_install_package_result' ), 15, 2 );

		// WP_Upgrader::init.
		if ( ! wp_installing() ) {
			$this->schedule_temp_backup_cleanup();
		}
	}

	/**
	 * Move the plugin/theme being upgraded into a rollback directory.
	 *
	 * @since 6.1.0
	 * @uses 'upgrader_pre_install' filter.
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 * @param bool  $response   Boolean response to 'upgrader_pre_install' filter.
	 *                          Default is true.
	 * @param array $hook_extra Array of data for plugin/theme being updated.
	 *
	 * @return bool|WP_Error
	 */
	public function upgrader_pre_install( $response, $hook_extra ) {
		$this->options = ( new WP_Plugin_Theme_Upgrader() )->set_callback_options( $hook_extra );

		// Early exit if $hook_extra is empty,
		// or if this is an installation and not update.
		if ( empty( $hook_extra ) || ( isset( $hook_extra['action'] ) && 'install' === $hook_extra['action'] ) ) {
			return $response;
		}

		$args = $this->options['hook_extra']['temp_backup'];

		if ( isset( $hook_extra['plugin'] ) || isset( $hook_extra['theme'] ) ) {
			$temp_backup = $this->move_to_temp_backup_dir( $args );
			if ( is_wp_error( $temp_backup ) ) {
				return $temp_backup;
			}
			$this->temp_backups[] = $this->options['hook_extra']['temp_backup'];
		}

		return $response;
	}

	/**
	 * Restore backup to original location if update failed.
	 *
	 * @since 6.1.0
	 * @uses 'upgrader_install_package_result' filter.
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 * @param bool|WP_Error $result     Result from `WP_Upgrader::install_package()`.
	 * @param array         $hook_extra Array of data for plugin/theme being updated.
	 *
	 * @return bool|WP_Error
	 */
	public function upgrader_install_package_result( $result, $hook_extra ) {
		// Early exit if $hook_extra is empty,
		// or if this is an installation and not update.
		if ( empty( $hook_extra ) || ( isset( $hook_extra['action'] ) && 'install' === $hook_extra['action'] ) ) {
			return $result;
		}

		if ( ! isset( $hook_extra['plugin'] ) && ! isset( $hook_extra['theme'] ) ) {
			return $result;
		}

		if ( is_wp_error( $result ) ) {
			if ( ! empty( $this->options['hook_extra']['temp_backup'] ) ) {
				/*
				 * Restore the backup on shutdown.
				 * Actions running on `shutdown` are immune to PHP timeouts,
				 * so in case the failure was due to a PHP timeout,
				 * it will still be able to properly restore the previous version.
				 */
				add_action( 'shutdown', array( $this, 'restore_temp_backup' ) );
			}
		}

		// Clean up the backup kept in the temp-backup directory.
		if ( ! empty( $this->options['hook_extra']['temp_backup'] ) ) {
			// Delete the backup on `shutdown` to avoid a PHP timeout.
			add_action( 'shutdown', array( $this, 'delete_temp_backup' ) );
		}

		return $result;
	}

	/**
	 * Schedule cleanup of the temp-backup directory.
	 *
	 * @since 6.1.0
	 */
	protected function schedule_temp_backup_cleanup() {
		if ( false === wp_next_scheduled( 'wp_delete_temp_updater_backups' ) ) {
			wp_schedule_event( time(), 'weekly', 'wp_delete_temp_updater_backups' );
		}
	}

	/**
	 * Move the plugin/theme being upgraded into a temp-backup directory.
	 *
	 * @since 6.1.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param array|string $args {
	 *     Array of data for the temp-backup.
	 *
	 *     @type string $slug Plugin slug.
	 *     @type string $src  File path to directory.
	 *     @type string $dir  Directory name.
	 * }
	 *
	 * @return bool|WP_Error
	 */
	public function move_to_temp_backup_dir( $args ) {
		global $wp_filesystem;

		if ( empty( $args['slug'] ) || empty( $args['src'] ) || empty( $args['dir'] ) ) {
			return false;
		}

		/*
		 * Skip any plugin that has "." as its slug.
		 * A slug of "." will result in a `$src` value ending in a period.
		 *
		 * On Windows, this will cause the 'plugins' folder to be moved,
		 * and will cause a failure when attempting to call `mkdir()`.
		 */
		if ( '.' === $args['slug'] ) {
			return false;
		}

		if ( ! $wp_filesystem->wp_content_dir() ) {
			return new \WP_Error( 'fs_no_content_dir', $this->strings['fs_no_content_dir'] );
		}

		$dest_dir = $wp_filesystem->wp_content_dir() . 'upgrade/temp-backup/';
		// Create the temp-backup directory if it does not exist.
		if ( (
				! $wp_filesystem->is_dir( $dest_dir )
				&& ! $wp_filesystem->mkdir( $dest_dir )
			) || (
				! $wp_filesystem->is_dir( $dest_dir . $args['dir'] . '/' )
				&& ! $wp_filesystem->mkdir( $dest_dir . $args['dir'] . '/' )
			)
		) {
			return new \WP_Error( 'fs_temp_backup_mkdir', $this->strings['temp_backup_mkdir_failed'] );
		}

		$src_dir = $wp_filesystem->find_folder( $args['src'] );
		$src     = trailingslashit( $src_dir ) . $args['slug'];
		$dest    = $dest_dir . trailingslashit( $args['dir'] ) . $args['slug'];

		// Delete the temp-backup directory if it already exists.
		if ( $wp_filesystem->is_dir( $dest ) ) {
			$wp_filesystem->delete( $dest, true );
		}

		// Move to the temp-backup directory.
		if ( ! move_dir( $src, $dest ) ) {
			return new \WP_Error( 'fs_temp_backup_move', $this->strings['temp_backup_move_failed'] );
		}

		return true;
	}

	/**
	 * Restore the plugin/theme from the temp-backup directory.
	 *
	 * @since 6.1.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @return bool|WP_Error
	 */
	public function restore_temp_backup() {
		global $wp_filesystem;

		$errors = new \WP_Error();

		foreach ( $this->temp_backups as $args ) {
			if ( empty( $args['slug'] ) || empty( $args['src'] ) || empty( $args['dir'] ) ) {
				return false;
			}

			if ( ! $wp_filesystem->wp_content_dir() ) {
				$errors->add( 'fs_no_content_dir', $this->strings['fs_no_content_dir'] );
				return $errors;
			}

			$src      = $wp_filesystem->wp_content_dir() . 'upgrade/temp-backup/' . $args['dir'] . '/' . $args['slug'];
			$dest_dir = $wp_filesystem->find_folder( $args['src'] );
			$dest     = trailingslashit( $dest_dir ) . $args['slug'];

			if ( $wp_filesystem->is_dir( $src ) ) {
				// Cleanup.
				if ( $wp_filesystem->is_dir( $dest ) && ! $wp_filesystem->delete( $dest, true ) ) {
					$errors->add(
						'fs_temp_backup_delete',
						sprintf( $this->strings['temp_backup_restore_failed'], $args['slug'] )
					);
					continue;
				}

				// Move it.
				if ( ! move_dir( $src, $dest ) ) {
					$errors->add(
						'fs_temp_backup_delete',
						sprintf( $this->strings['temp_backup_restore_failed'], $args['slug'] )
					);
					continue;
				}
			}
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Deletes a temp-backup.
	 *
	 * @since 6.1.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @return bool
	 */
	public function delete_temp_backup() {
		global $wp_filesystem;

		$errors = new \WP_Error();

		foreach ( $this->temp_backups as $args ) {
			if ( empty( $args['slug'] ) || empty( $args['dir'] ) ) {
				return false;
			}

			if ( ! $wp_filesystem->wp_content_dir() ) {
				$errors->add( 'fs_no_content_dir', $this->strings['fs_no_content_dir'] );
				return $errors;
			}

			$temp_backup_dir = $wp_filesystem->wp_content_dir() . "upgrade/temp-backup/{$args['dir']}/{$args['slug']}";

			if ( ! $wp_filesystem->delete( $temp_backup_dir, true ) ) {
				$errors->add(
					'temp_backup_delete_failed',
					sprintf( $this->strings['temp_backup_delete_failed'] ),
					$args['slug']
				);
				continue;
			}
		}

		return $errors->has_errors() ? $errors : true;
	}

}

new WP_Upgrader();
