<?php
/**
 * Rollback Auto Update
 *
 * @package rollback-update-failure
 * @license MIT
 */

/**
 * Plugin Name: Rollback Auto Update
 * Author: WP Core Contributors
 * Description: A feature plugin now only for testing Rollback Auto Update, aka Rollback part 3. Manual Rollback of update failures has been committed in WordPress 6.3.
 * Version: 6.3.1.5
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

require_once __DIR__ . '/src/testing/failure-simulator.php';

add_action(
	'plugins_loaded',
	function () {
		if ( version_compare( get_bloginfo( 'version' ), '6.5-beta1', '<' ) ) {
			class WP_Error extends \WP_Error {}
			class Automatic_Upgrader_Skin extends \Automatic_Upgrader_Skin {}
			class Theme_Upgrader extends \Theme_Upgrader {}
			class Core_Upgrader extends \Core_Upgrader {}
			class Language_Pack_Upgrader extends \Language_Pack_Upgrader {}

			require_once __DIR__ . '/src/wp-admin/includes/class-wp-upgrader.php';
			require_once __DIR__ . '/src/wp-admin/includes/class-wp-automatic-updater.php';
			require_once __DIR__ . '/src/wp-admin/includes/class-plugin-upgrader.php';

			remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
			add_action(
				'wp_maybe_auto_update',
				function () {
					if ( ! function_exists( 'wp_is_auto_update_enabled_for_type' ) ) {
						require_once \ABSPATH . 'wp-admin/includes/update.php';
					}
					add_filter( 'upgrader_source_selection', __NAMESPACE__ . '\fix_mangled_source', 10, 4 );
					$upgrader = new WP_Automatic_Updater();
					delete_option( 'option_auto_updater.lock' );
					WP_Upgrader::release_lock( 'auto_updater' );
					$upgrader->run();
				}
			);
		}
	}
);

/**
 * Correctly rename dependency for activation.
 *
 * @param string      $source        File source location.
 * @param string      $remote_source Remote file source location.
 * @param WP_Upgrader $upgrader      WP_Upgrader instance.
 * @param array       $hook_extra    Extra arguments passed to hooked filters.
 * @return string
 */
function fix_mangled_source( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( isset( $hook_extra['temp_backup']['slug'] ) ) {
		$new_source = trailingslashit( $remote_source ) . $hook_extra['temp_backup']['slug'];
		move_dir( $source, $new_source, true );

		return trailingslashit( $new_source );
	}

	return $source;
}
