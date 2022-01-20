<?php
/**
 * Rollback Update Failure
 *
 * @package rollback-update-failure
 * @author Andy Fragen <andy@thefragens.com>, Ari Stathopolous <aristath@gmail.com>
 * @license MIT
 */

/**
 * Plugin Name: Rollback Update Failure
 * Author: Andy Fragen, Ari Stathopolous
 * Description: Feature plugin to test plugin/theme update failures and rollback to previous installed packages.
 * Version: 1.3.1
 * Network: true
 * License: MIT
 * Text Domain: rollback-update-failure
 * Requires PHP: 5.6
 * Requires at least: 5.2
 * GitHub Plugin URI: https://github.com/WordPress/rollback-update-failure
 * Primary Branch: main
 */

/**
 * Class Rollback_Update_Failure.
 */
class Rollback_Update_Failure {

	/**
	 * The error/notification strings used to update the user on the progress.
	 *
	 * @since 2.8.0
	 * @var array $strings
	 */
	public $strings = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Deactivate plugin when committed to core.
		if ( version_compare( get_bloginfo( 'version' ), '5.9-alpha-51272', '>=' )
			&& version_compare( get_bloginfo( 'version' ), '6.0-beta1', '>=' )
		) {
			deactivate_plugins( __FILE__ );
		}

		// Add generic strings to Rollback_Update_Failure::$strings.
		$this->strings['temp_backup_mkdir_failed']   = __( 'Could not create temp-backup directory.', 'rollback-update-failure' );
		$this->strings['temp_backup_move_failed']    = __( 'Could not move old version to the temp-backup directory.', 'rollback-update-failure' );
		$this->strings['temp_backup_restore_failed'] = __( 'Could not restore original version.', 'rollback-update-failure' );

		// Move the plugin/theme being updated to rollback directory.
		add_filter( 'upgrader_pre_install', array( $this, 'upgrader_pre_install' ), 15, 2 );

		// Restore backup if install_package returns WP_Error.
		add_filter( 'upgrader_install_package_result', array( $this, 'upgrader_install_package_result' ), 15, 2 );

		// Add extra tests for site-health.
		add_filter( 'site_status_tests', array( $this, 'site_status_tests' ) );

		// Clean up.
		add_action( 'wp_delete_temp_updater_backups', array( $this, 'wp_delete_all_temp_backups' ) );
	}

	/**
	 * Move the plugin/theme being upgraded into a rollback directory.
	 *
	 * @since 6.0.0
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
		global $wp_filesystem;

		// Early exit if $hook_extra is empty,
		// or if this is an installation and not update.
		if ( empty( $hook_extra ) || ( isset( $hook_extra['action'] ) && 'install' === $hook_extra['action'] ) ) {
			return $response;
		}

		$args = array();

		if ( isset( $hook_extra['plugin'] ) || isset( $hook_extra['theme'] ) ) {
			$args = array(
				'dir'  => isset( $hook_extra['plugin'] ) ? 'plugins' : 'themes',
				'slug' => isset( $hook_extra['plugin'] ) ? dirname( $hook_extra['plugin'] ) : $hook_extra['theme'],
				'src'  => isset( $hook_extra['plugin'] ) ? $wp_filesystem->wp_plugins_dir() : get_theme_root( $hook_extra['theme'] ),
			);

			$temp_backup = $this->move_to_temp_backup_dir( $args );
			if ( is_wp_error( $temp_backup ) ) {
				return $temp_backup;
			}
		}
		return $response;
	}

	/**
	 * Restore backup to original location if update failed.
	 *
	 * @since 6.0.0
	 * @uses 'upgrader_install_package_result' filter.
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 * @param bool|WP_Error $result     Result from `WP_Upgrader::install_package()`.
	 * @param array         $hook_extra Array of data for plugin/theme being updated.
	 *
	 * @return bool|WP_Error
	 */
	public function upgrader_install_package_result( $result, $hook_extra ) {
		global $wp_filesystem;

		// Early exit if $hook_extra is empty,
		// or if this is an installation and not update.
		if ( empty( $hook_extra ) || ( isset( $hook_extra['action'] ) && 'install' === $hook_extra['action'] ) ) {
			return $result;
		}

		if ( ! isset( $hook_extra['plugin'] ) && ! isset( $hook_extra['theme'] ) ) {
			return $result;
		}

		$args = array(
			'dir'  => isset( $hook_extra['plugin'] ) ? 'plugins' : 'themes',
			'slug' => isset( $hook_extra['plugin'] ) ? dirname( $hook_extra['plugin'] ) : $hook_extra['theme'],
			'src'  => isset( $hook_extra['plugin'] ) ? $wp_filesystem->wp_plugins_dir() : get_theme_root( $hook_extra['theme'] ),
		);
		if ( is_wp_error( $result ) ) {
			$this->restore_temp_backup( $args );
		} else {
			// Clean up the backup kept in the temp-backup directory.
			// Delete the backup on `shutdown` to avoid a PHP timeout.
			add_action(
				'shutdown',
				function() use ( $args ) {
					$this->delete_temp_backup( $args );
				}
			);
		}

		return $result;
	}

	/**
	 * Move the plugin/theme being upgraded into a temp-backup directory.
	 *
	 * @since 6.0.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param array $args Array of data for the temp_backup. Must include a slug, the source and directory.
	 *
	 * @return bool|WP_Error
	 */
	public function move_to_temp_backup_dir( $args ) {
		if ( empty( $args['slug'] ) || empty( $args['src'] ) || empty( $args['dir'] ) ) {
			return false;
		}
		global $wp_filesystem;

		$dest_folder = $wp_filesystem->wp_content_dir() . 'upgrade/temp-backup/';
		// Create the temp-backup dir if it doesn't exist.
		if (
			(
				! $wp_filesystem->is_dir( $dest_folder ) &&
				! $wp_filesystem->mkdir( $dest_folder )
			) ||
			(
				! $wp_filesystem->is_dir( $dest_folder . $args['dir'] . '/' ) &&
				! $wp_filesystem->mkdir( $dest_folder . $args['dir'] . '/' )
			)
		) {
			return new WP_Error( 'fs_temp_backup_mkdir', $this->strings['temp_backup_mkdir_failed'] );
		}

		$src  = trailingslashit( $args['src'] ) . $args['slug'];
		$dest = $dest_folder . $args['dir'] . '/' . $args['slug'];

		// Delete temp-backup folder if it already exists.
		if ( $wp_filesystem->is_dir( $dest ) ) {
			$wp_filesystem->delete( $dest, true );
		}

		// Move to the temp-backup folder.
		if ( ! $this->move_dir( $src, $dest ) ) {
			return new WP_Error( 'fs_temp_backup_move', $this->strings['temp_backup_move_failed'] );
		}

		return true;
	}

	/**
	 * Restore the plugin/theme from the temp-backup directory.
	 *
	 * @since 6.0.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param array $args Array of data for the temp_backup. Must include a slug, the source and directory.
	 *
	 * @return bool|WP_Error
	 */
	public function restore_temp_backup( $args ) {
		if ( empty( $args['slug'] ) || empty( $args['src'] ) || empty( $args['dir'] ) ) {
			return false;
		}

		global $wp_filesystem;
		$src  = $wp_filesystem->wp_content_dir() . 'upgrade/temp-backup/' . $args['dir'] . '/' . $args['slug'];
		$dest = trailingslashit( $args['src'] ) . $args['slug'];

		if ( $wp_filesystem->is_dir( $src ) ) {

			// Cleanup.
			if ( $wp_filesystem->is_dir( $dest ) && ! $wp_filesystem->delete( $dest, true ) ) {
				return new WP_Error( 'fs_temp_backup_delete', $this->strings['temp_backup_restore_failed'] );
			}

			// Move it.
			if ( ! $this->move_dir( $src, $dest ) ) {
				return new WP_Error( 'fs_temp_backup_delete', $this->strings['temp_backup_restore_failed'] );
			}
		}
		return true;
	}

	/**
	 * Deletes a temp-backup.
	 *
	 * @since 6.0.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param array $args Array of data for the temp_backup. Must include a slug, the source and directory.
	 *
	 * @return bool
	 */
	public function delete_temp_backup( $args ) {
		global $wp_filesystem;
		if ( empty( $args['slug'] ) || empty( $args['dir'] ) ) {
			return false;
		}

		return $wp_filesystem->delete(
			$wp_filesystem->wp_content_dir() . "upgrade/temp-backup/{$args['dir']}/{$args['slug']}",
			true
		);
	}

	/**
	 * Moves a directory from one location to another via the rename() PHP function.
	 * If the renaming failed, falls back to copy_dir().
	 *
	 * Assumes that WP_Filesystem() has already been called and setup.
	 *
	 * @since 6.0.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param string $from        Source directory.
	 * @param string $to          Destination directory.
	 * @param string $working_dir Remote file source directory. Optional.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function move_dir( $from, $to, $working_dir = '' ) {
		global $wp_filesystem;
		$result = false;

		if ( 'direct' === $wp_filesystem->method && ! $this->is_virtual_box() ) {
			$wp_filesystem->rmdir( $to );
			$result = @rename( $from, $to );
		}

		if ( ! $result && ! $wp_filesystem->is_dir( $to ) ) {
			if ( ! $wp_filesystem->mkdir( $to, FS_CHMOD_DIR ) ) {
				return new WP_Error( 'mkdir_failed_move_dir', __( 'Could not create directory.' ), $to );
			}
			$result = copy_dir( $from, $to );
		}

		// Clear the working directory?
		if ( ! empty( $working_dir ) ) {
			$wp_filesystem->delete( $working_dir, true );
		}

		return $result;
	}

	/**
	 * Test available disk-space for updates/upgrades.
	 *
	 * @since 6.0.0
	 *
	 * @return array The test results.
	 */
	public function get_test_available_updates_disk_space() {
		$available_space       = function_exists( 'disk_free_space' ) ? @disk_free_space( WP_CONTENT_DIR . '/upgrade/' ) : false;
		$available_space_in_mb = $available_space / MB_IN_BYTES;

		$result = array(
			'label'       => __( 'Disk-space available to safely perform updates', 'rollback-update-failure' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'rollback-update-failure' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				/* Translators: %s: Available disk-space in MB or GB. */
				'<p>' . __( '%s available disk space was detected, update routines can be performed safely.', 'rollback-update-failure' ),
				size_format( $available_space )
			),
			'actions'     => '',
			'test'        => 'available_updates_disk_space',
		);

		if ( 100 > $available_space_in_mb ) {
			$result['description'] = __( 'Available disk space is low, less than 100MB available.', 'rollback-update-failure' );
			$result['status']      = 'recommended';
		}

		if ( 20 > $available_space_in_mb ) {
			$result['description'] = __( 'Available disk space is critically low, less than 20MB available. Proceed with caution, updates may fail.', 'rollback-update-failure' );
			$result['status']      = 'critical';
		}

		if ( ! $available_space ) {
			$result['description'] = __( 'Could not determine available disk space for updates.', 'rollback-update-failure' );
			$result['status']      = 'recommended';
		}

		return $result;
	}

	/**
	 * Test if plugin and theme updates temp-backup folders are writable or can be created.
	 *
	 * @since 6.0.0
	 *
	 * @return array The test results.
	 */
	public function get_test_update_temp_backup_writable() {
		$result = array(
			'label'       => __( 'Plugin and theme update temp-backup folder is writable', 'rollback-update-failure' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'rollback-update-failure' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				/* Translators: %s: "wp-content/upgrade/temp-backup". */
				'<p>' . __( 'The %s folder used to improve the stability of plugin and theme updates is writable.', 'rollback-update-failure' ),
				'<code>wp-content/upgrade/temp-backup</code>'
			),
			'actions'     => '',
			'test'        => 'update_temp_backup_writable',
		);

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
			}
			WP_Filesystem();
		}
		$wp_content = $wp_filesystem->wp_content_dir();

		$upgrade_folder_exists      = $wp_filesystem->is_dir( "$wp_content/upgrade" );
		$upgrade_folder_is_writable = $wp_filesystem->is_writable( "$wp_content/upgrade" );
		$backup_folder_exists       = $wp_filesystem->is_dir( "$wp_content/upgrade/temp-backup" );
		$backup_folder_is_writable  = $wp_filesystem->is_writable( "$wp_content/upgrade/temp-backup" );
		$plugins_folder_exists      = $wp_filesystem->is_dir( "$wp_content/upgrade/temp-backup/plugins" );
		$plugins_folder_is_writable = $wp_filesystem->is_writable( "$wp_content/upgrade/temp-backup/plugins" );
		$themes_folder_exists       = $wp_filesystem->is_dir( "$wp_content/upgrade/temp-backup/themes" );
		$themes_folder_is_writable  = $wp_filesystem->is_writable( "$wp_content/upgrade/temp-backup/themes" );

		if ( $plugins_folder_exists && ! $plugins_folder_is_writable && $themes_folder_exists && ! $themes_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Plugins and themes temp-backup folders exist but are not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade/temp-backup/plugins</code>' */
				'<p>' . __( 'The %1$s and %2$s folders exist but are not writable. These folders are used to improve the stability of plugin updates. Please make sure the server has write permissions to these folders.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade/temp-backup/plugins</code>',
				'<code>wp-content/upgrade/temp-backup/themes</code>'
			);
			return $result;
		}

		if ( $plugins_folder_exists && ! $plugins_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Plugins temp-backup folder exists but is not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade/temp-backup/plugins</code>' */
				'<p>' . __( 'The %s folder exists but is not writable. This folder is used to improve the stability of plugin updates. Please make sure the server has write permissions to this folder.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade/temp-backup/plugins</code>'
			);
			return $result;
		}

		if ( $themes_folder_exists && ! $themes_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Themes temp-backup folder exists but is not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade/temp-backup/themes</code>' */
				'<p>' . __( 'The %s folder exists but is not writable. This folder is used to improve the stability of theme updates. Please make sure the server has write permissions to this folder.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade/temp-backup/themes</code>'
			);
			return $result;
		}

		if ( ( ! $plugins_folder_exists || ! $themes_folder_exists ) && $backup_folder_exists && ! $backup_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'The temp-backup folder exists but is not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade/temp-backup</code>' */
				'<p>' . __( 'The %s folder exists but is not writable. This folder is used to improve the stability of plugin and theme updates. Please make sure the server has write permissions to this folder.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade/temp-backup</code>'
			);
			return $result;
		}

		if ( ! $backup_folder_exists && $upgrade_folder_exists && ! $upgrade_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'The upgrade folder exists but is not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade</code>' */
				'<p>' . __( 'The %s folder exists but is not writable. This folder is used to for plugin and theme updates. Please make sure the server has write permissions to this folder.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade</code>'
			);
			return $result;
		}

		if ( ! $upgrade_folder_exists && ! $wp_filesystem->is_writable( $wp_content ) ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'The upgrade folder can not be created', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %1$s: <code>wp-content/upgrade</code>. %2$s: <code>wp-content</code>. */
				'<p>' . __( 'The %1$s folder does not exist, and the server does not have write permissions in %2$s to create it. This folder is used to for plugin and theme updates. Please make sure the server has write permissions in %2$s.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade</code>',
				'<code>wp-content</code>'
			);
			return $result;
		}

		return $result;
	}

	/**
	 * Additional tests for site-health.
	 *
	 * @since 6.0.0
	 *
	 * @param array $tests Available site-health tests.
	 *
	 * @return array
	 */
	public function site_status_tests( $tests ) {

		$tests['direct']['update_temp_backup_writable']  = array(
			'label' => __( 'Updates temp-backup folder access' ),
			'test'  => array( $this, 'get_test_update_temp_backup_writable' ),
		);
		$tests['direct']['available_updates_disk_space'] = array(
			'label' => __( 'Available disk space' ),
			'test'  => array( $this, 'get_test_available_updates_disk_space' ),
		);
		return $tests;
	}

	/**
	 * Deletes all contents of the temp-backup directory.
	 *
	 * @since 6.0.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	public function wp_delete_all_temp_backups() {
		/*
		* Check if there's a lock, or if currently performing an Ajax request,
		* in which case there's a chance we're doing an update.
		* Reschedule for an hour from now and exit early.
		*/
		if ( get_option( 'core_updater.lock' ) || get_option( 'auto_updater.lock' ) || wp_doing_ajax() ) {
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'wp_delete_temp_updater_backups' );
			return;
		}

		/*
		* This action runs on shutdown to make sure there's no plugin updates currently running.
		* Using a closure in this case is OK since the action can be removed by removing the parent hook.
		*/
		add_action(
			'shutdown',
			function() {
				global $wp_filesystem;

				if ( ! $wp_filesystem ) {
					include_once ABSPATH . '/wp-admin/includes/file.php';
					WP_Filesystem();
				}

				$dirlist = $wp_filesystem->dirlist( $wp_filesystem->wp_content_dir() . 'upgrade/temp-backup/' );

				foreach ( array_keys( (array) $dirlist ) as $dir ) {
					if ( '.' === $dir || '..' === $dir ) {
						continue;
					}

					$wp_filesystem->delete( $wp_filesystem->wp_content_dir() . 'upgrade/temp-backup/' . $dir, true );
				}
			}
		);
	}

	/**
	 * Return whether constant or environmental variable indicates using VirtualBox.
	 * This could be added to class WP_Filesystem_Base.
	 *
	 * @return bool
	 */
	public function is_virtual_box() {
		if ( defined( 'ENV_VB' ) && ENV_VB && 'false' !== ENV_VB ) {
			return true;
		}

		$WP_ENV_VB = getenv( 'WP_ENV_VB' );
		if ( $WP_ENV_VB && 'false' !== $WP_ENV_VB ) {
			return true;
		}

		return false;
	}
}

new Rollback_Update_Failure();
