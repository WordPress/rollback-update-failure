<?php
/**
 * Stuff that belongs in 'wp-includes/update.php'.
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
 * Deletes all contents of the temp-backup directory.
 *
 * @since 6.1.0
 *
 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
 */
function wp_delete_all_temp_backups() {
	/*
	* Check if there's a lock, or if currently performing an Ajax request,
	* in which case there's a chance we're doing an update.
	* Reschedule for an hour from now and exit early.
	*/
	if ( get_option( 'core_updater.lock' ) || get_option( 'auto_updater.lock' ) || wp_doing_ajax() ) {
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'wp_delete_temp_updater_backups' );
		return;
	}

	// This action runs on shutdown to make sure there is no plugin updates currently running.
	// TODO: Remove namespacing for PR.
	add_action( 'shutdown', __NAMESPACE__ . '\\_wp_delete_all_temp_backups' );
}

/**
 * Remove `temp-backup` directory.
 *
 * @since 6.1.0
 *
 * @access private
 *
 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
 *
 * @return void|WP_Error
 */
function _wp_delete_all_temp_backups() {
	global $wp_filesystem;

	if ( ! $wp_filesystem ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

	if ( ! $wp_filesystem->wp_content_dir() ) {
		return new WP_Error( 'fs_no_content_dir', __( 'Unable to locate WordPress content directory (wp-content).' ) );
	}

	$temp_backup_dir = $wp_filesystem->wp_content_dir() . 'upgrade/temp-backup/';
	$dirlist         = $wp_filesystem->dirlist( $temp_backup_dir );
	$dirlist         = $dirlist ? $dirlist : array();

	foreach ( array_keys( $dirlist ) as $dir ) {
		if ( '.' === $dir || '..' === $dir ) {
			continue;
		}

		$wp_filesystem->delete( $temp_backup_dir . $dir, true );
	}
}

// Clean up.
// TODO: Remove namespacing for PR.
add_action( 'wp_delete_temp_updater_backups', __NAMESPACE__ . '\\wp_delete_all_temp_backups' );
