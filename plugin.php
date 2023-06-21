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
 * Version: 5.3.1
 * Network: true
 * License: MIT
 * Text Domain: rollback-update-failure
 * Requires PHP: 5.6
 * Requires at least: 6.2
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

if ( version_compare( get_bloginfo( 'version' ), '6.2-beta1', '>=' ) ) {
	define( 'WP_ROLLBACK_MOVE_DIR', true );
} else {
	define( 'WP_ROLLBACK_MOVE_DIR', false );
}

if ( version_compare( get_bloginfo( 'version' ), '6.3-alpha-55720', '>=' ) ) {
	define( 'WP_ROLLBACK_COMMITTED', true );
} else {
	define( 'WP_ROLLBACK_COMMITTED', false );
}

if ( ! WP_ROLLBACK_MOVE_DIR ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	deactivate_plugins( __FILE__ );
	return;
}

if ( ! WP_ROLLBACK_COMMITTED ) {
	require_once __DIR__ . '/src/wp-admin/includes/class-wp-site-health.php';
	require_once __DIR__ . '/src/wp-admin/includes/class-plugin-theme-upgrader.php';
	require_once __DIR__ . '/src/wp-admin/includes/class-wp-upgrader.php';
	require_once __DIR__ . '/src/wp-includes/update.php';
}

// Insert at end of wp-admin/includes/class-wp-upgrader.php.
/** WP_Rollback_Auto_Update class */
require_once __DIR__ . '/src/wp-admin/includes/class-rollback-auto-update.php';
require_once __DIR__ . '/src/testing/failure-simulator.php';

add_filter( 'upgrader_source_selection', array( new \WP_Rollback_Auto_Update(), 'set_plugin_upgrader' ), 10, 3 );
add_filter( 'upgrader_install_package_result', array( new \WP_Rollback_Auto_Update(), 'auto_update_check' ), 15, 2 );
/**
 * TODO: For PR add $this as passed parameter for `upgrader_install_package_result` hook.
 *
 * WP_Upgrader::init.
 * add_filter( 'upgrader_install_package_result', array( new \WP_Rollback_Auto_Update(), 'auto_update_check' ), 15, 3 );
 */
