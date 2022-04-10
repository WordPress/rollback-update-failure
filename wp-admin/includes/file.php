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
 * If the renaming failed, falls back to move_dir_fallback().
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

	/*
	 * Skip the rename() call on VirtualBox environments.
	 * There are some known issues where rename() can fail on shared folders
	 * without reporting an error properly.
	 *
	 * More details:
	 * https://www.virtualbox.org/ticket/8761#comment:24
	 * https://www.virtualbox.org/ticket/17971
	 */
	if ( 'direct' === $wp_filesystem->method && ! is_virtualbox() ) {
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

		$result = move_dir_fallback( $from, $to );
	}

	return $result;
}

/**
 * Recursive file/directory copy and delete.
 *
 * Functions more like `rename()` in that the $source is deleted after copying.
 * More versatile than WP Core `copy_dir()`.
 * Allows for copying into first level subfolder.
 *
 * @since 6.1.0
 *
 * @global \WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
 *
 * @param string $source      File path of source.
 * @param string $destination File path of destination.
 * @return bool|\WP_Error True for success, \WP_Error for failure.
 */
function move_dir_fallback( $source, $destination ) {
	global $wp_filesystem;

	$dir = @opendir( $source );
	if ( $dir ) {
		if ( ! $wp_filesystem->is_dir( $destination ) ) {
			if ( ! $wp_filesystem->mkdir( $destination, FS_CHMOD_DIR ) ) {
				return new \WP_Error( 'mkdir_failed_move_dir_fallback', __( 'Could not create directory.' ), $destination );
			}
		}
		$source = untrailingslashit( $source );
		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( false !== ( $file = readdir( $dir ) ) ) {
			if ( ( '.' !== $file ) && ( '..' !== $file ) && "{$source}/{$file}" !== $destination ) {
				if ( $wp_filesystem->is_dir( "{$source}/{$file}" ) ) {
					move_dir( "{$source}/{$file}", "{$destination}/{$file}" );
				} else {
					if ( ! $wp_filesystem->copy( "{$source}/{$file}", "{$destination}/{$file}", true, FS_CHMOD_FILE ) ) {
						// If copy failed, chmod file to 0644 and try again.
						$wp_filesystem->chmod( "{$destination}/{$file}", FS_CHMOD_FILE );
						if ( ! $wp_filesystem->copy( "{$source}/{$file}", "{$destination}/{$file}", true, FS_CHMOD_FILE ) ) {
							return new \WP_Error( 'copy_failed_move_dir_fallback', __( 'Could not copy file.' ), $destination );
						}
					}
					if ( ! $wp_filesystem->delete( "{$source}/{$file}" ) ) {
						return new \WP_Error( 'delete_failed_move_dir_fallback', __( 'Unable to delete file.' ), "{$source}/{$file}" );
					}
				}
			}
		}

		$iterator = new \FilesystemIterator( $source );
		if ( ! $iterator->valid() ) { // True if directory is empty.
			if ( ! $wp_filesystem->rmdir( $source ) ) {
				new \WP_Error( 'rmdir_failed_move_dir_fallback', __( 'Could not remove directory.' ), $source );
			}
		}
			closedir( $dir );

			return true;
	}

	return new \WP_Error( 'opendir_failed_move_dir_fallback', __( 'Could not open directory.' ), array( $source, $destination ) );
}
