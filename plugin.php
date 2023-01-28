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
 * Version: 4.1.2.1
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

if ( ! function_exists( 'is_plugin_active' ) && ! function_exists( 'deactivate_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( ! is_plugin_active( 'faster-updates/faster-updates.php' ) ) {
	echo '<div class="error notice is-dismissible"><p>';
	print(
		wp_kses_post( __( '<strong>Rollback Update Failure</strong> cannot run unless the <strong>Faster Updates</strong> plugin is active. Please refer to the readme.', 'rollback-update-failure' ) )
	);
	echo '</p></div>';
	deactivate_plugins( __FILE__ );
}
