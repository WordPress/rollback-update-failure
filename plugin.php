<?php
/**
 * Rollback Update Failure
 *
 * @package rollback-update-failure
 * @author Andy Fragen <andy@thefragens.com>
 * @license MIT
 */

/**
 * Plugin Name: Rollback Update Failure
 * Author: Andy Fragen
 * Description: Feature plugin to test plugin/theme update failures and rollback to previous installed packages via zip/unzip.
 * Version: 0.5.1
 * Network: true
 * License: MIT
 * Text Domain: rollback-update-failure
 * Requires PHP: 5.6
 * Requires at least: 5.2
 * GitHub Plugin URI: https://github.com/afragen/rollback-update-failure
 * Primary Branch: main
 */

/**
 * Class Rollback_Update_Failure.
 *
 * Feature plugin to test feasibility of using zip/unzip for plugin/theme update failures.
 *
 * These to be added to `wp-admin/includes/class-wp-upgrader.php`.
 * WP_Upgrader::run()
 */
class Rollback_Update_Failure {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Zip the plugin/theme being updated to rollback directory.
		add_filter( 'upgrader_pre_install', array( $this, 'zip_to_rollback_dir' ), 15, 2 );

		// Extract zip rollback if install_package returns WP_Error.
		add_filter( 'upgrader_install_package_result', array( $this, 'extract_rollback' ), 15, 2 );
	}

	/**
	 * Create a zip archive of the plugin/theme being upgraded into a rollback directory.
	 *
	 * @since 5.x.0
	 * @uses 'upgrader_pre_install' filter.
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 * @param bool  $response   Boolean response to 'upgrader_pre_install' filter.
	 *                          Default is true.
	 * @param array $hook_extra Array of data for plugin/theme being updated.
	 *
	 * @return bool|WP_Error
	 */
	public function zip_to_rollback_dir( $response, $hook_extra ) {
		global $wp_filesystem;

		// Exit early on plugin/theme installation.
		if ( isset( $hook_extra['action'] ) && 'install' === $hook_extra['action'] ) {
			return $response;
		}

		// Setup variables.
		if ( isset( $hook_extra['plugin'] ) ) {
			$slug = dirname( $hook_extra['plugin'] );
			$src  = WP_PLUGIN_DIR . '/' . $slug;
		}
		if ( isset( $hook_extra['theme'] ) ) {
			$slug = $hook_extra['theme'];
			$src  = get_theme_root() . '/' . $slug;
		}
		$rollback_dir = $wp_filesystem->wp_content_dir() . 'upgrade/rollback/';

		// Zip can use a lot of memory. From `unzip_file()`.
		wp_raise_memory_limit( 'admin' );

		if ( $wp_filesystem->mkdir( $rollback_dir ) ) {
			$path_prefix = strlen( $src ) + 1;
			$zip         = new ZipArchive();

			if ( true === $zip->open( "{$rollback_dir}{$slug}.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				$files = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $src ),
					RecursiveIteratorIterator::LEAVES_ONLY
				);

				foreach ( $files as $file ) {
					// Skip directories (they would be added automatically).
					if ( ! $file->isDir() ) {
						// Get real and relative path for current file.
						$file_path     = $file->getRealPath();
						$relative_path = substr( $file_path, $path_prefix );

						// Add current file to archive.
						$zip->addFile( $file_path, $relative_path );
					}
				}

				$zip->close();
			} else {
				return new WP_Error( 'zip_rollback_failed', __( 'Zip plugin/theme to rollback directory failed.', 'rollback-update-failure' ) );
			}
		}

		return $response;
	}

	/**
	 * Extract zipped rollback to original location.
	 *
	 * @since 5.x.0
	 * @uses 'upgrader_install_package_result' filter.
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 * @param bool|WP_Error $result     Result from `WP_Upgrader::install_package()`.
	 * @param array         $hook_extra Array of data for plugin/theme being updated.
	 *
	 * @return bool|WP_Error
	 */
	public function extract_rollback( $result, $hook_extra ) {
		global $wp_filesystem;

		if ( apply_filters( 'rollback_update_testing', false ) && ! is_wp_error( $result ) ) {
			$result = new WP_Error( 'test rollback', 'Just testing rollback' );
		}

		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		// Exit early on plugin/theme installation.
		if ( isset( $hook_extra['action'] ) && 'install' === $hook_extra['action'] ) {
			return new WP_Error( 'extract_rollback_install', __( 'Rollback Update Failure exit for installation not update', 'rollback-update-failure' ) );
		}

		// Setup variables.
		$slug = false;
		if ( isset( $hook_extra['plugin'] ) ) {
			$type            = 'plugin';
			$slug            = dirname( $hook_extra['plugin'] );
			$destination_dir = "plugins/{$slug}";
		}
		if ( isset( $hook_extra['theme'] ) ) {
			$type            = 'theme';
			$slug            = $hook_extra['theme'];
			$destination_dir = "themes/{$slug}";
		}

		if ( ! $slug ) {
			return new WP_Error( 'extract_rollback_failed_slug', __( '$slug not identified.', 'rollback-update-failure' ) );
		}
		$destination  = $wp_filesystem->wp_content_dir() . trailingslashit( $destination_dir );
		$rollback_dir = $wp_filesystem->wp_content_dir() . 'upgrade/rollback/';
		$rollback     = $rollback_dir . "{$slug}.zip";

		// Start with a clean slate.
		if ( $wp_filesystem->is_dir( $destination ) ) {
			$wp_filesystem->delete( $destination, true );
		}

		$unzip = unzip_file( $rollback, $destination );
		if ( is_wp_error( $unzip ) ) {
			/* translators: %1: plugin|theme, %2: plugin/theme slug */
			return new WP_Error( 'extract_rollback_failed_unzip', sprintf( __( 'Rollback of %1$s %2$s failed.', 'rollback-update-failure' ), $type, $slug ) );
		}

		/* translators: %1: plugin|theme, %2: plugin/theme slug */
		$success_message = sprintf( __( 'Rollback of %1$s %2$s succeeded.', 'rollback-update-failure' ), $type, $slug );

		$error_code = $result->get_error_code();
		$result->add( $error_code, $success_message );
		$error_messages = $result->get_error_messages( $error_code );

		// Recreate WP_Error messages.
		unset( $result->errors[ $error_code ] );
		$result->add( $error_code, implode( ', ', $error_messages ) );

		return $result;
	}
}

new Rollback_Update_Failure();
