<?php
/**
 * Stuff that belongs in 'wp-includes/functions.php'.
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
 * Attempt to detect a VirtualBox environment.
 *
 * This attempts all known methods of detecting VirtualBox.
 *
 * @global $wp_filesystem The filesystem.
 *
 * @since 6.1.0
 *
 * @return bool Whether or not VirtualBox was detected.
 */
function is_virtualbox() {
	global $wp_filesystem;
	static $is_virtualbox;

	if ( ! defined( 'WP_RUN_CORE_TESTS' ) && null !== $is_virtualbox ) {
		return $is_virtualbox;
	}

	/*
	 * Filters whether the current environment uses VirtualBox.
	 *
	 * @since 6.1.0
	 *
	 * @param bool $is_virtualbox Whether the current environment uses VirtualBox.
	 *                            Default: false.
	 */
	if ( apply_filters( 'is_virtualbox', false ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

		// Detection via Composer.
	if ( function_exists( 'getenv' ) && 'virtualbox' === getenv( 'COMPOSER_RUNTIME_ENV' ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

		$virtualbox_unames = array( 'vvv' );

		// Detection via `php_uname()`.
	if ( function_exists( 'php_uname' ) && in_array( php_uname( 'n' ), $virtualbox_unames, true ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

		/*
		 * Vagrant can use alternative providers.
		 * This isn't reliable without some additional check(s).
		 */
		$virtualbox_usernames = array( 'vagrant' );

		// Detection via user name with POSIX.
	if ( function_exists( 'posix_getpwuid' ) && function_exists( 'posix_geteuid' ) ) {
		$user = posix_getpwuid( posix_geteuid() );

		if ( $user && in_array( $user['name'], $virtualbox_usernames, true ) ) {
			$is_virtualbox = true;
			return $is_virtualbox;
		}
	}

		// Initialize the filesystem if not set.
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

		// Detection via file owner.
	if ( in_array( $wp_filesystem->owner( __FILE__ ), $virtualbox_usernames, true ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

		// Detection via file group.
	if ( in_array( $wp_filesystem->group( __FILE__ ), $virtualbox_usernames, true ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

		// Give up.
		$is_virtualbox = false;

		return $is_virtualbox;
}
