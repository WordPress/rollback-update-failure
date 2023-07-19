<?php
/**
 * WordPress Plugin Administration API: WP_Rollback_Auto_Update class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 6.4.0
 */

/**
 * Core class for rolling back auto-update plugin failures.
 */
class WP_Rollback_Auto_Update {

	/**
	 * Stores handler parameters.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	private $handler_args = array();

	/**
	 * Stores successfully updated plugins.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	private static $processed = array();

	/**
	 * Stores fataling plugins.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	private static $fatals = array();

	/**
	 * Stores `update_plugins` transient.
	 *
	 * @since 6.4.0
	 *
	 * @var stdClass
	 */
	private static $current_plugins;

	/**
	 * Stores `update_themes` transient.
	 *
	 * @since 6.4.0
	 *
	 * @var stdClass
	 */
	private static $current_themes;

	/**
	 * Stores boolean for no error from check_plugin_for_errors().
	 *
	 * @since 6.4.0
	 *
	 * @var bool
	 */
	private $update_is_safe = false;

	/**
	 * Stores instance of Plugin_Upgrader.
	 * TODO: remove before commit.
	 *
	 * @var Plugin_Upgrader
	 */
	private static $plugin_upgrader;

	/**
	 * Stores error codes.
	 *
	 * @since 6.4.0
	 *
	 * @var int
	 */
	public $error_types = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

	/**
	 * Get Plugin_Upgrader
	 *
	 * TODO: remove before commit.
	 *
	 * @param string      $source        File source location.
	 * @param string      $remote_source Remote file source location.
	 * @param WP_Upgrader $obj           WP_Upgrader or child class instance.
	 *
	 * @return string
	 */
	public function set_plugin_upgrader( $source, $remote_source, $obj ) {
		if ( ! isset( static::$plugin_upgrader ) && $obj instanceof Plugin_Upgrader ) {
			static::$plugin_upgrader = $obj;
		}

		return $source;
	}

	/**
	 * Checks the validity of the updated plugin.
	 * TODO: Add $this to passed parameter for 'upgrader_install_package_result' hook.
	 *       Remove $upgrader default value for PR.
	 *
	 * @since 6.4.0
	 *
	 * @param array|WP_Error $result     Result from WP_Upgrader::install_package().
	 * @param array          $hook_extra Extra arguments passed to hooked filters.
	 * @param WP_Upgrader    $upgrader   WP_Upgrader or child class instance.
	 * @return array|WP_Error
	 */
	public function auto_update_check( $result, $hook_extra, $upgrader = null ) {
		if ( is_wp_error( $result ) || ! wp_doing_cron() || ! isset( $hook_extra['plugin'] ) ) {
			return $result;
		}

		// Checking our own plugin will cause a PHP Fatal redeclaration error.
		if ( 'rollback-update-failure/plugin.php' === $hook_extra['plugin'] ) {
			return $result;
		}

		// Already processed.
		if ( in_array( $hook_extra['plugin'], self::$processed, true ) ) {
			return $result;
		}

		// TODO: remove before commit.
		error_log( $hook_extra['plugin'] . ' processing...' );

		/*
		 * This possibly helps to avoid a potential race condition on servers that may start to
		 * process the next plugin for auto-updating before the handler can pick up an error from
		 * the previously processed plugin.
		 */
		sleep( 2 );

		$this->update_is_safe = false;
		$this->handler_args   = array(
			'handler_error' => '',
			'result'        => $result,
			'hook_extra'    => $hook_extra,
		);

		// Register exception and shutdown handlers.
		$this->initialize_handlers();

		self::$processed[] = $hook_extra['plugin'];

		if ( is_plugin_inactive( $hook_extra['plugin'] ) ) {
			// Working parts of plugin_sandbox_scrape().
			wp_register_plugin_realpath( WP_PLUGIN_DIR . '/' . $hook_extra['plugin'] );
			include WP_PLUGIN_DIR . '/' . $hook_extra['plugin'];
		}

		// Needs to run for both active and inactive plugins. Don't ask why, just accept it.
		$this->check_plugin_for_errors( $hook_extra['plugin'], $upgrader );
		// TODO: remove before commit.
		error_log( $hook_extra['plugin'] . ' auto updated.' );

		return $result;
	}

	/**
	 * Checks a new plugin version for errors.
	 *
	 * If an error is found, the previously installed version will be reinstalled
	 * and an email will be sent to the site administrator.
	 *
	 * @since 6.4.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param string      $plugin The plugin to check.
	 * @param WP_Upgrader $upgrader WP_Upgrader or child class instance.
	 *
	 * @throws Exception If errors are present.
	 *
	 * @return void
	 */
	private function check_plugin_for_errors( $plugin, $upgrader ) {
		global $wp_filesystem;

		if ( $wp_filesystem->exists( ABSPATH . '.maintenance' ) ) {
			$wp_filesystem->delete( ABSPATH . '.maintenance' );
		}

		$nonce    = wp_create_nonce( 'plugin-activation-error_' . $plugin );
		$response = wp_remote_get(
			add_query_arg(
				array(
					'action'   => 'error_scrape',
					'plugin'   => $plugin,
					'_wpnonce' => $nonce,
				),
				admin_url( 'plugins.php' )
			),
			array( 'timeout' => 60 )
		);

		if ( is_wp_error( $response ) ) {
			// If it isn't possible to run the check, assume an error.
			throw new Exception( $response->get_error_message() );
		}

		$code                 = wp_remote_retrieve_response_code( $response );
		$body                 = wp_remote_retrieve_body( $response );
		$this->update_is_safe = 200 === $code;

		if ( str_contains( $body, 'wp-die-message' ) || 200 !== $code ) {
			// TODO: remove before commit.
			$upgrader = $upgrader instanceof Plugin_Upgrader ? $upgrader : static::$plugin_upgrader;

			/*
			 * If a plugin upgrade fails prior to a theme upgrade running, the plugin upgrader will have
			 * hooked the 'Plugin_Upgrader::delete_old_plugin()' method to 'upgrader_clear_destination',
			 * which will return a `WP_Error` object and prevent the process from continuing.
			 *
			 * To resolve this, the hook must be removed using the original plugin upgrader instance.
			 */
			remove_filter( 'upgrader_clear_destination', array( $upgrader, 'delete_old_plugin' ) );

			throw new Exception(
				sprintf(
					/* translators: %s: The name of the plugin. */
					__( 'The new version of %s contains an error' ),
					get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin )['Name']
				)
			);
		}
	}

	/**
	 * Initializes handlers.
	 *
	 * @since 6.4.0
	 */
	private function initialize_handlers() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( array( $this, 'error_handler' ), ( E_ALL ^ $this->error_types ) );
		set_exception_handler( array( $this, 'exception_handler' ) );
	}

	/**
	 * Handles Errors.
	 *
	 * @since 6.4.0
	 */
	public function error_handler() {
		$this->handler_args['handler_error'] = 'Error Caught';
		$this->handler( $this->non_fatal_errors() );
	}

	/**
	 * Handles Exceptions.
	 *
	 * @since 6.4.0
	 */
	public function exception_handler() {
		$this->handler_args['handler_error'] = 'Exception Caught';
		$this->handler( false );
	}

	/**
	 * Handles errors by running Rollback.
	 *
	 * @since 6.4.0
	 *
	 * @param bool $skip If false, assume fatal and process.
	 *                   Default false.
	 */
	private function handler( $skip = false ) {
		if ( $skip ) {
			return;
		}
		self::$fatals[] = $this->handler_args['hook_extra']['plugin'];

		$this->cron_rollback();

		/*
		 * This possibly helps to avoid a potential race condition on servers that may start to
		 * process the next plugin for auto-updating before the handler can pick up an error from
		 * the previously processed plugin.
		 */
		sleep( 2 );

		static::$current_plugins = get_site_transient( 'update_plugins' );
		static::$current_themes  = get_site_transient( 'update_themes' );

		$this->restart_updates();
		$this->restart_core_updates();
		$this->send_update_result_email();
	}

	/**
	 * Return whether to skip (exit handler() early) for non-fatal errors or non-errors.
	 *
	 * @since 6.4.0
	 *
	 * @return bool Whether to skip for non-fatal errors or non-errors.
	 */
	private function non_fatal_errors() {
		$last_error       = error_get_last();
		$non_fatal_errors = ( ! empty( $last_error ) && $this->error_types !== $last_error['type'] );
		$skip             = is_plugin_active( $this->handler_args['hook_extra']['plugin'] ) || $this->update_is_safe;
		$skip             = $skip ? $skip : $non_fatal_errors;

		return $skip;
	}

	/**
	 * Rolls back during cron.
	 *
	 * @since 6.4.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	private function cron_rollback() {
		global $wp_filesystem;

		$temp_backup = array(
			'temp_backup' => array(
				'dir'  => 'plugins',
				'slug' => dirname( $this->handler_args['hook_extra']['plugin'] ),
				'src'  => $wp_filesystem->wp_plugins_dir(),
			),
		);

		// TODO: change for core.
		if ( WP_ROLLBACK_COMMITTED ) {
			$rollback_updater = new WP_Upgrader();
		} else {
			$rollback_updater = new \Rollback_Update_Failure\WP_Upgrader();
		}

		// Set private $temp_restores variable.
		$ref_temp_restores = new ReflectionProperty( $rollback_updater, 'temp_restores' );
		$ref_temp_restores->setAccessible( true );
		$ref_temp_restores->setValue( $rollback_updater, $temp_backup );

		// Set private $temp_backups variable.
		$ref_temp_backups = new ReflectionProperty( $rollback_updater, 'temp_backups' );
		$ref_temp_backups->setAccessible( true );
		$ref_temp_backups->setValue( $rollback_updater, $temp_backup );

		// Call Rollback's restore_temp_backup().
		$rollback_updater->restore_temp_backup();

		// Call Rollback's delete_temp_backup().
		$rollback_updater->delete_temp_backup();

		// TODO: remove before commit.
		error_log( $this->handler_args['hook_extra']['plugin'] . ' rolled back' );
	}

	/**
	 * Restart update process for plugins that remain after a fatal.
	 *
	 * @since 6.4.0
	 */
	private function restart_updates() {
		$remaining_plugin_auto_updates = $this->get_remaining_plugin_auto_updates();
		$remaining_theme_auto_updates  = $this->get_remaining_theme_auto_updates();
		$skin                          = new Automatic_Upgrader_Skin();

		if ( ! empty( $remaining_plugin_auto_updates ) ) {
			$plugin_upgrader = new Plugin_Upgrader( $skin );
			$plugin_upgrader->bulk_upgrade( $remaining_plugin_auto_updates );
		}

		if ( ! empty( $remaining_theme_auto_updates ) ) {
			$theme_upgrader = new Theme_Upgrader( $skin );
			$results        = $theme_upgrader->bulk_upgrade( $remaining_theme_auto_updates );

			foreach ( array_keys( $results ) as $theme ) {
				self::$processed[] = $theme;
			}
		}

		// TODO: change for core.
		if ( WP_ROLLBACK_COMMITTED ) {
			remove_action( 'shutdown', array( new WP_Upgrader(), 'delete_temp_backup' ), 100 );
		} else {
			remove_action( 'shutdown', array( new \Rollback_Update_Failure\WP_Upgrader(), 'delete_temp_backup' ), 100 );
		}
	}

	/**
	 * Restart update process for core.
	 *
	 * @since 6.4.0
	 */
	private function restart_core_updates() {
		if ( ! function_exists( 'find_core_auto_update' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$core_update = find_core_auto_update();
		if ( $core_update ) {
			$core_updater = new WP_Automatic_Updater();
			$core_updater->update( 'core', $core_update );
		}
	}

	/**
	 * Get array of non-fataling plugin auto-updates remaining.
	 *
	 * @since 6.4.0
	 *
	 * @return array
	 */
	private function get_remaining_plugin_auto_updates() {
		if ( empty( $this->handler_args ) ) {
			return array();
		}

		// Get array of plugins set for auto-updating.
		$auto_updates    = (array) get_site_option( 'auto_update_plugins', array() );
		$current_plugins = array_keys( static::$current_plugins->response );

		// Get all auto-updating plugins that have updates available.
		$current_auto_updates = array_intersect( $auto_updates, $current_plugins );

		// Get array of non-fatal auto-updates remaining.
		$remaining_auto_updates = array_diff( $current_auto_updates, self::$processed, self::$fatals );

		return $remaining_auto_updates;
	}

	/**
	 * Get array of non-fataling theme auto-updates remaining.
	 *
	 * @since 6.4.0
	 *
	 * @return array
	 */
	private function get_remaining_theme_auto_updates() {
		if ( empty( $this->handler_args ) ) {
			return array();
		}

		// Get array of themes set for auto-updating.
		$auto_updates   = (array) get_site_option( 'auto_update_themes', array() );
		$current_themes = array_keys( static::$current_themes->response );

		// Get all auto-updating plugins that have updates available.
		$current_auto_updates = array_intersect( $auto_updates, $current_themes );

		// Get array of non-fatal auto-updates remaining.
		$remaining_auto_updates = array_diff( $current_auto_updates, self::$processed, self::$fatals );

		return $remaining_auto_updates;
	}

	/**
	 * Sends an email noting successful and failed updates.
	 *
	 * @since 6.4.0
	 */
	private function send_update_result_email() {
		add_filter( 'auto_plugin_theme_update_email', array( $this, 'auto_update_rollback_message' ), 10, 4 );
		$successful = array();
		$failed     = array();

		$plugin_theme_email_data = array(
			'plugin' => array( 'data' => get_plugins() ),
			'theme'  => array( 'data' => wp_get_themes() ),
		);

		foreach ( $plugin_theme_email_data as $type => $data ) {
			$current_items = 'plugin' === $type ? static::$current_plugins : static::$current_themes;

			foreach ( array_keys( $current_items->response ) as $file ) {
				$item            = $current_items->response[ $file ];
				$current_version = $current_items->checked[ $file ];

				if ( 'plugin' === $type ) {
					$name                  = $data['data'][ $file ]['Name'];
					$item->current_version = $current_version;
					$type_result           = (object) array(
						'name' => $name,
						'item' => $item,
					);
				}

				if ( 'theme' === $type ) {
					$name                    = $data['data'][ $file ]->get( 'Name' );
					$item['current_version'] = $current_version;
					$type_result             = (object) array(
						'name' => $name,
						'item' => (object) $item,
					);
				}

				$success = array_diff( self::$processed, self::$fatals );

				if ( in_array( $file, $success, true ) ) {
					$successful[ $type ][] = $type_result;
					continue;
				}

				if ( in_array( $file, self::$fatals, true ) ) {
					$failed[ $type ][] = $type_result;
				}
			}
		}

		$automatic_upgrader      = new WP_Automatic_Updater();
		$send_plugin_theme_email = new ReflectionMethod( $automatic_upgrader, 'send_plugin_theme_email' );
		$send_plugin_theme_email->setAccessible( true );
		$send_plugin_theme_email->invoke( $automatic_upgrader, 'mixed', $successful, $failed );

		remove_filter( 'auto_plugin_theme_update_email', array( $this, 'auto_update_rollback_message' ), 10 );
	}

	/**
	 * Add auto-update failure message to email.
	 *
	 * @since 6.4.0
	 *
	 * @param array  $email {
	 *     Array of email arguments that will be passed to wp_mail().
	 *
	 *     @type string $to      The email recipient. An array of emails
	 *                           can be returned, as handled by wp_mail().
	 *     @type string $subject The email's subject.
	 *     @type string $body    The email message body.
	 *     @type string $headers Any email headers, defaults to no headers.
	 * }
	 * @param string $type               The type of email being sent. Can be one of 'success', 'fail', 'mixed'.
	 * @param array  $successful_updates A list of updates that succeeded.
	 * @param array  $failed_updates     A list of updates that failed.
	 *
	 * @return array
	 */
	public function auto_update_rollback_message( $email, $type, $successful_updates, $failed_updates ) {
		if ( empty( $failed_updates ) ) {
			return $email;
		}
		$body   = explode( "\n", $email['body'] );
		$failed = __( 'These plugins failed to update or may have been restored from a temporary backup due to detection of a fatal error:' );
		array_splice( $body, 6, 1, $failed );
		$props = __( 'The WordPress Rollbackenberg Team' );
		array_splice( $body, -1, 1, $props );
		$body          = implode( "\n", $body );
		$email['body'] = $body;

		return $email;
	}
}
