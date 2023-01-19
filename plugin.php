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
 * Version: 4.1.0
 * Network: true
 * License: MIT
 * Text Domain: rollback-update-failure
 * Requires PHP: 5.6
 * Requires at least: 6.0
 * GitHub Plugin URI: https://github.com/WordPress/rollback-update-failure
 * Primary Branch: main
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


// Add to wp-admin/includes/admin-filters.php.
add_action( 'init', array( 'WP_Rollback_Auto_Update', 'init' ) );
