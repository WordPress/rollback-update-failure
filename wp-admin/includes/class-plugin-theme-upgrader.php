<?php
/**
 * Stuff that belongs in 'wp-admin/includes/...'
 * 'class-plugin-upgrader.php and class-theme-upgrader.php'.
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
 * Class WP_Plugin_Theme_Upgrader.
 *
 * These data are set in WP_Plugin_Upgrader and WP_Theme_Upgrader
 * in the PR.
 */
class WP_Plugin_Theme_Upgrader {
	/**
	 * Set class $options variable with data for callbacks.
	 *
	 * Not necessary in PR as this set in WP_Upgrader::run().
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 * @param array $hook_extra Array of data for plugin/theme being updated.
	 *
	 * @return array
	 */
	public function set_callback_options( $hook_extra ) {
		global $wp_filesystem;

		$options = array();
		if ( isset( $hook_extra['plugin'] ) || isset( $hook_extra['theme'] ) ) {
			$options['hook_extra']['temp_backup'] = array(
				'dir'  => isset( $hook_extra['plugin'] ) ? 'plugins' : 'themes',
				'slug' => isset( $hook_extra['plugin'] ) ? dirname( $hook_extra['plugin'] ) : $hook_extra['theme'],
				'src'  => isset( $hook_extra['plugin'] ) ? $wp_filesystem->wp_plugins_dir() : get_theme_root( $hook_extra['theme'] ),
			);
		}

		return $options;
	}
}
