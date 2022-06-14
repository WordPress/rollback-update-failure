<?php
/**
 * Rollback Update Failure
 *
 * @package rollback-update-failure
 * @author Andy Fragen <andy@thefragens.com>, Ari Stathopolous <aristath@gmail.com>
 * @license MIT
 */

/**
 * Plugin Name: Rollback Update Failure
 * Author: Andy Fragen, Ari Stathopolous, Colin Stewart, Paul Biron
 * Description: Feature plugin to test plugin/theme update failures and rollback to previous installed packages.
 * Version: 3.0.0
 * Network: true
 * License: MIT
 * Text Domain: rollback-update-failure
 * Requires PHP: 5.6
 * Requires at least: 5.2
 * GitHub Plugin URI: https://github.com/WordPress/rollback-update-failure
 * Primary Branch: main
 */

namespace Rollback_Update_Failure;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Deactivate plugin when committed to core.
if ( version_compare( get_bloginfo( 'version' ), '6.1-beta1', '>=' ) ) {
	deactivate_plugins( __FILE__ );
}

// Load files.
require_once __DIR__ . '/wp-admin/includes/class-wp-site-health.php';
require_once __DIR__ . '/wp-admin/includes/class-plugin-theme-upgrader.php';
require_once __DIR__ . '/wp-admin/includes/class-wp-upgrader.php';
require_once __DIR__ . '/wp-admin/includes/file.php';
require_once __DIR__ . '/wp-includes/update.php';
