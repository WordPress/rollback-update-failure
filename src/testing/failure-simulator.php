<?php
/**
 * Allows quickly toggling a plugin's simulated failure status when testing.
 *
 * @package rollback-update-failure
 */

namespace Rollback_Update_Failure\Testing;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	return;
}

if ( ! class_exists( '\Rollback_Update_Failure\Testing\Failure_Simulator' ) ) {
	/**
	 * Class Failure_Simulator.
	 */
	class Failure_Simulator {

		/**
		 * Update files.
		 *
		 * @var array
		 */
		protected static $updates = array();

		/**
		 * Adds hooks to the 'init' action hook for wp-cli.
		 *
		 * @return void
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'hooks' ) );
		}

		/**
		 * Hooks.
		 */
		public function hooks() {
			add_action( 'admin_head', array( $this, 'handle_simulated_failure' ) );
			add_filter( 'plugin_action_links', array( $this, 'add_simulated_failure_link' ), 10, 4 );
			add_filter( 'handle_bulk_actions_plugins', array( $this, 'handle_simulated_failure' ), 10, 3 );
			add_filter( 'upgrader_install_package_result', array( $this, 'simulate_failure' ), 10, 2 );
		}

		/**
		 * Adds a plugin action link to toggle simulated failures for each plugin.
		 *
		 * @param string[] $actions     An array of plugin action links. By default this can include
		 *                              'activate', 'deactivate', and 'delete'. With Multisite active
		 *                              this can also include 'network_active' and 'network_only' items.
		 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
		 * @param array    $plugin_data An array of plugin data. See get_plugin_data()
		 *                              and the {@see 'plugin_row_meta'} filter for the list
		 *                              of possible values.
		 * @param string   $context     The plugin context. By default this can include 'all',
		 *                              'active', 'inactive', 'recently_activated', 'upgrade',
		 *                              'mustuse', 'dropins', and 'search'.
		 *
		 * @return array The modified plugin action links.
		 */
		public function add_simulated_failure_link( $actions, $plugin_file, $plugin_data, $context ) {
			$should_fail              = false;
			$plugin_file_decoded      = urldecode( $plugin_file );
			$simulate_failure_plugins = get_option( 'rollback_simulate_failure_plugins' );
			if ( empty( static::$updates ) ) {
				$current         = get_site_transient( 'update_plugins' );
				static::$updates = property_exists( (object) $current, 'response' ) ? array_keys( $current->response ) : array();
			}

			if ( ! \in_array( $plugin_file_decoded, static::$updates, true ) ) {
				return $actions;
			}

			if ( $simulate_failure_plugins && in_array( $plugin_file_decoded, $simulate_failure_plugins, true ) ) {
				$should_fail = true;
			}

			list( $action, $link ) = $this->get_simulated_failure_link(
				$should_fail,
				$plugin_file,
				$plugin_data,
				$context
			);

			$actions[ $action ] = $link;

			return $actions;
		}

		/**
		 * Gets the appropriate action link to toggle simulated failures.
		 *
		 * @param bool   $should_fail Should the update fail.
		 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
		 * @param array  $plugin_data An array of plugin data. See get_plugin_data()
		 *                            and the {@see 'plugin_row_meta'} filter for the list
		 *                            of possible values.
		 * @param string $context     The plugin context. By default this can include 'all',
		 *                            'active', 'inactive', 'recently_activated', 'upgrade',
		 *                            'mustuse', 'dropins', and 'search'.
		 *
		 * @return array The action name and action link.
		 */
		private function get_simulated_failure_link( $should_fail, $plugin_file, $plugin_data, $context ) {
			global $page, $s;

			$action         = 'simulate_failure';
			$label          = 'Simulate failure';
			$plugin_slug    = isset( $plugin_data['slug'] ) ? $plugin_data['slug'] : sanitize_title( $plugin_data['Name'] );
			$plugin_id_attr = $plugin_slug;

			if ( $should_fail ) {
				$action = 'do_not_' . $action;
				$label  = 'Remove ' . strtolower( $label );
			}

			$link = sprintf(
				'<a href="%s" id="%s-%s" aria-label="%s">%s</a>',
				wp_nonce_url( '?action=' . $action . '&amp;plugin=' . rawurlencode( $plugin_file ) . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, $action . '_' . $plugin_file ),
				$action,
				esc_attr( $plugin_id_attr ),
				/* translators: %s: Plugin name. */
				esc_attr( sprintf( _x( '%1$s for %2$s', 'plugin' ), $label, $plugin_data['Name'] ) ),
				$label
			);

			return array( $action, $link );
		}

		/**
		 * Toggles a plugin's simulated failure option.
		 *
		 * @return void
		 */
		public function handle_simulated_failure() {
			if ( ! isset( $_GET['_wpnonce'], $_GET['plugin'], $_GET['action'] ) ) {
				return;
			}

			if ( ! ( wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'simulate_failure_' . sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) )
				|| wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'do_not_simulate_failure_' . sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) ) )
			) {
				return;
			}

			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
			$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );

			$simulate_failure_plugins = get_option( 'rollback_simulate_failure_plugins' );

			if ( ! $simulate_failure_plugins ) {
				$simulate_failure_plugins = array();
			}

			if ( 'simulate_failure' === $action ) {
				$simulate_failure_plugins[] = $plugin;
			}

			if ( 'do_not_simulate_failure' === $action ) {
				$found = array_search( $plugin, $simulate_failure_plugins, true );

				if ( false !== $found ) {
					unset( $simulate_failure_plugins[ $found ] );
				}
			}

			update_option( 'rollback_simulate_failure_plugins', array_unique( $simulate_failure_plugins ) );
		}

		/**
		 * Simulates a failure during plugin update if the plugin is in the list
		 * of plugins to simulate failures.
		 *
		 * @param array|WP_Error $result     Result from WP_Upgrader::install_package().
		 * @param array          $hook_extra Extra arguments passed to hooked filters.
		 *
		 * @return array|WP_Error The original result, or WP_Error for a simulated failure.
		 */
		public function simulate_failure( $result, $hook_extra ) {
			$simulate_failure_plugins = get_option( 'rollback_simulate_failure_plugins' );

			if ( $simulate_failure_plugins
				&& isset( $hook_extra['plugin'] ) && in_array( $hook_extra['plugin'], $simulate_failure_plugins, true )
			) {
				return new \WP_Error( 'simulated_error', 'Simulated Error' );
			}

			return $result;
		}
	}
}

new Failure_Simulator();
