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
 * Version: 6.2.2
 * Network: true
 * License: MIT
 * Text Domain: rollback-update-failure
 * Requires PHP: 7.0
 * Requires at least: 6.3
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

// No longer required with WP 6.3.
// require_once __DIR__ . '/src/wp-admin/includes/class-wp-site-health.php';
// require_once __DIR__ . '/src/wp-admin/includes/class-plugin-theme-upgrader.php';
// require_once __DIR__ . '/src/wp-admin/includes/class-wp-upgrader.php';
// require_once __DIR__ . '/src/wp-includes/update.php';

// Insert at end of wp-admin/includes/class-wp-upgrader.php.
/** WP_Rollback_Auto_Update class */
if ( version_compare( get_bloginfo( 'version' ), '6.4-beta1', '<=' ) ) {
	require_once __DIR__ . '/src/wp-admin/includes/class-rollback-auto-update.php';
	add_filter( 'upgrader_source_selection', array( new \WP_Rollback_Auto_Update(), 'set_plugin_upgrader' ), 10, 3 );
	add_filter( 'upgrader_install_package_result', array( new \WP_Rollback_Auto_Update(), 'check_plugin_for_errors' ), 15, 2 );
	/**
	 * TODO: For PR add $this as passed parameter for `upgrader_install_package_result` hook.
	 *
	 * WP_Upgrader::init.
	 * add_filter( 'upgrader_install_package_result', array( new \WP_Rollback_Auto_Update(), 'auto_update_check' ), 15, 3 );
	 */
}

require_once __DIR__ . '/src/testing/failure-simulator.php';
