<?php
/**
 * Rollback Update Failure
 *
 * @package rollback-update-failure
 * @author Andy Fragen <andy@thefragens.com>
 * @license MIT
 */

/**
 * Plugin Name: Rollback Update Failure
 * Author: Andy Fragen
 * Description: Feature plugin to test plugin/theme update failures and rollback to previous installed packages via zip/unzip.
 * Version: 0.5.3
 * Network: true
 * License: MIT
 * Text Domain: rollback-update-failure
 * Requires PHP: 5.6
 * Requires at least: 5.2
 * GitHub Plugin URI: https://github.com/afragen/rollback-update-failure
 * Primary Branch: main
 */

/**
 * Class Rollback_Update_Failure.
 *
 * Feature plugin to test feasibility of using zip/unzip for plugin/theme update failures.
 *
 * These to be added to `wp-admin/includes/class-wp-upgrader.php`.
 * WP_Upgrader::run()
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

		// Add generic strings to Rollback_Update_Failure::$strings.
		$this->strings['temp_backup_mkdir_failed']   = __( 'Could not create temp-backup directory.', 'rollback-update-failure' );
		$this->strings['temp_backup_move_failed']    = __( 'Could not move old version to the temp-backup directory.', 'rollback-update-failure' );
		$this->strings['temp_backup_restore_failed'] = __( 'Could not restore original version.', 'rollback-update-failure' );

		// Move the plugin/theme being updated to rollback directory.
		add_filter( 'upgrader_pre_install', array( $this, 'upgrader_pre_install' ), 15, 2 );

		// Restore backup if install_package returns WP_Error.
		add_filter( 'upgrader_install_package_result', array( $this, 'upgrader_install_package_result' ), 15, 2 );
	}

	/**
	 * Move the plugin/theme being upgraded into a rollback directory.
	 *
	 * @since 5.9.0
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
	 * @since 5.9.0
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
			$this->delete_temp_backup( $args );
		}

		return $result;
	}

	/**
	 * Move the plugin/theme being upgraded into a temp-backup directory.
	 *
	 * @since 5.9.0
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
		if ( ! $wp_filesystem->move( $src, $dest, true ) ) {
			return new WP_Error( 'fs_temp_backup_move', $this->strings['temp_backup_move_failed'] );
		}

		return true;
	}

	/**
	 * Restore the plugin/theme from the temp-backup directory.
	 *
	 * @since 5.9.0
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
			if ( ! $wp_filesystem->move( $src, $dest, true ) ) {
				return new WP_Error( 'fs_temp_backup_delete', $this->strings['temp_backup_restore_failed'] );
			}
		}
		return true;
	}

	/**
	 * Deletes a temp-backup.
	 *
	 * @since 5.9.0
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
}

new Rollback_Update_Failure();
