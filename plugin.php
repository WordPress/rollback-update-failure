<?php
/**
 * Rollback Update Failure
 *
 * @package rollback-update-failure
 * @license MIT
 */

/**
 * Plugin Name: Rollbackenberg (neé Rollback Update Failure)
 * Author: WP Core Contributors
 * Description: Feature plugin to test plugin/theme update failures and rollback to previous installed packages.
 * Version: 4.0.0-beta
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

// Deactivate plugin when committed to core.
if ( version_compare( get_bloginfo( 'version' ), '6.3-beta1', '>=' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	deactivate_plugins( __FILE__ );
}

// Load files.
require_once __DIR__ . '/wp-admin/includes/class-wp-site-health.php';
require_once __DIR__ . '/wp-admin/includes/class-plugin-theme-upgrader.php';
require_once __DIR__ . '/wp-admin/includes/class-wp-upgrader.php';
require_once __DIR__ . '/wp-admin/includes/file.php';
require_once __DIR__ . '/wp-includes/update.php';
require_once __DIR__ . '/wp-admin/includes/class-rollback-auto-update.php';

// Add to wp-admin/includes/admin-filters.php.
add_action( 'init', array( 'WP_Rollback_Auto_Update', 'init' ) );

// For testing.
require_once __DIR__ . '/testing/failure-simulator.php';
