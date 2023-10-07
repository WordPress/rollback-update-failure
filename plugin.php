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
 * Version: 6.3.1.1
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

if ( version_compare( get_bloginfo( 'version' ), '6.3', '<' ) ) {
	require_once __DIR__ . '/src/wp-admin/includes/class-wp-site-health.php';
	require_once __DIR__ . '/src/wp-admin/includes/class-plugin-theme-upgrader.php';
	require_once __DIR__ . '/src/wp-includes/update.php';
}

add_action(
	'plugins_loaded',
	function () {
		if ( version_compare( get_bloginfo( 'version' ), '6.5-beta1', '<' ) ) {
			class WP_Error extends \WP_Error {}
			require_once __DIR__ . '/src/wp-admin/includes/class-wp-upgrader.php';
			require_once __DIR__ . '/src/wp-admin/includes/class-wp-automatic-updater.php';
			require_once __DIR__ . '/src/wp-admin/includes/class-plugin-upgrader.php';

			remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
			add_action(
				'wp_maybe_auto_update',
				function () {
					$upgrader = new WP_Automatic_Updater();
					$upgrader->run();
				}
			);

			add_filter( 'upgrader_source_selection', __NAMESPACE__ . '\upgrader_source_selection', 10, 4 );

			// add_filter( 'upgrader_source_selection', array( new \WP_Rollback_Auto_Update(), 'set_plugin_upgrader' ), 10, 3 );
			// add_filter( 'upgrader_install_package_result', array( new \WP_Rollback_Auto_Update(), 'check_plugin_for_errors' ), 15, 2 );
		}
	}
);

require_once __DIR__ . '/src/testing/failure-simulator.php';

/**
 * Correctly rename dependency for activation.
 *
 * @param string $source        Path fo $source.
 * @param string $remote_source Path of $remote_source.
 *
 * @return string $new_source
 */
function upgrader_source_selection( $source, $remote_source, $obj, $hook_extra ) {
	if ( isset( $hook_extra['temp_backup']['slug'] ) ) {
		$new_source = trailingslashit( $remote_source ) . $hook_extra['temp_backup']['slug'];
		move_dir( $source, $new_source, true );

		return trailingslashit( $new_source );
	}

	return $source;
}
