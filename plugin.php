<?php
/**
 * Rollback Update Failure
 *
 * @package rollback-update-failure
 * @license MIT
 */

/**
 * Plugin Name: Rollback Update Failure
 * Author: WP Core Contributors
 * Description: Feature plugin to test plugin/theme update failures and rollback to previous installed packages.
 * Version: 4.0.0
 * Network: true
 * License: MIT
 * Text Domain: rollback-update-failure
 * Requires PHP: 5.6
 * Requires at least: 6.0
 * GitHub Plugin URI: https://github.com/WordPress/rollback-update-failure
 * Primary Branch: main
 * Requires Plugins: faster-updates
 */

namespace Rollback_Update_Failure;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load the Composer autoloader.
require __DIR__ . '/vendor/autoload.php';

add_action(
	'plugins_loaded',
	function() {
		\WP_Dependency_Installer::instance( __DIR__ )->run();
	}
);

// TODO: Deactivate plugin when/if committed to core.
// if ( version_compare( get_bloginfo( 'version' ), '6.3-beta1', '>=' ) ) {
// require_once ABSPATH . 'wp-admin/includes/plugin.php';
// deactivate_plugins( __FILE__ );
// }

// Add to wp-admin/includes/admin-filters.php.
add_action( 'init', array( 'WP_Rollback_Auto_Update', 'init' ) );

// Hopefully add some VirtualBox compatibility.
add_action(
	'post_move_dir',
	function() {
		/*
		 * VirtualBox has a bug when PHP's rename() is followed by an unlink().
		 *
		 * The bug is caused by delayed clearing of the filesystem cache, and
		 * the solution is to clear dentries and inodes at the system level.
		 *
		 * Most hosts add shell_exec() to the disable_function directive.
		 * function_exists() is usually sufficient to detect this.
		 */
		if ( function_exists( 'shell_exec' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
			shell_exec( 'sync; echo 2 > /proc/sys/vm/drop_caches' );
		}
	}
);
