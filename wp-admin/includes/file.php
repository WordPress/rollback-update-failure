<?php
/**
 * Stuff that belongs in 'wp-admin/includes/file.php'.
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
 * Moves a directory from one location to another via the rename() PHP function.
 * If the renaming failed, falls back to copy_dir().
 *
 * Assumes that WP_Filesystem() has already been called and setup.
 *
 * @since 6.1.0
 *
 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
 *
 * @param string $from        Source directory.
 * @param string $to          Destination directory.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function move_dir( $from, $to ) {
	global $wp_filesystem;

	$result = false;

	/**
	 * Fires before move_dir().
	 *
	 * @since 6.1.0
	 */
	do_action( 'pre_move_dir' );

	if ( 'direct' === $wp_filesystem->method ) {
		$wp_filesystem->rmdir( $to );

		$result = @rename( $from, $to );
	}

	// Non-direct filesystems use some version of rename without a fallback.
	if ( 'direct' !== $wp_filesystem->method ) {
		$result = $wp_filesystem->move( $from, $to );
	}

	if ( ! $result ) {
		if ( ! $wp_filesystem->is_dir( $to ) ) {
			if ( ! $wp_filesystem->mkdir( $to, FS_CHMOD_DIR ) ) {
				return new \WP_Error( 'mkdir_failed_move_dir', __( 'Could not create directory.' ), $to );
			}
		}

		$result = copy_dir( $from, $to, array( basename( $to ) ) );

		// Clear the source directory.
		if ( ! is_wp_error( $result ) ) {
			$wp_filesystem->delete( $from, true );
		}
	}

	/**
	 * Fires after move_dir().
	 *
	 * @since 6.1.0
	 */
	do_action( 'post_move_dir' );

	return $result;
}
