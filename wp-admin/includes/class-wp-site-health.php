<?php
/**
 * Stuff that belongs in 'wp-admin/includes/class-debug-data.php'.
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
 * Class WP_Site_Health
 */
class WP_Site_Health {

	/**
	 * The error/notification strings used to update the user on the progress.
	 *
	 * @since 2.8.0
	 * @var array $strings
	 */
	public $strings = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->strings['fs_no_content_dir'] = __( 'Unable to locate WordPress content directory (wp-content).' );

		// Add extra tests for site-health.
		add_filter( 'site_status_tests', array( $this, 'site_status_tests' ) );
	}

	/**
	 * Additional tests for site-health.
	 *
	 * @since 6.1.0
	 *
	 * @param array $tests Available site-health tests.
	 *
	 * @return array
	 */
	public function site_status_tests( $tests ) {
		$tests['direct']['update_temp_backup_writable']  = array(
			'label' => __( 'Updates temp-backup folder access' ),
			'test'  => array( $this, 'get_test_update_temp_backup_writable' ),
		);
		$tests['direct']['available_updates_disk_space'] = array(
			'label' => __( 'Available disk space' ),
			'test'  => array( $this, 'get_test_available_updates_disk_space' ),
		);

		return $tests;
	}

	/**
	 * Test available disk-space for updates/upgrades.
	 *
	 * @since 6.1.0
	 *
	 * @return array The test results.
	 */
	public function get_test_available_updates_disk_space() {
		$available_space = function_exists( 'disk_free_space' ) ? @disk_free_space( WP_CONTENT_DIR . '/upgrade/' ) : false;

		$available_space = false !== $available_space
			? (int) $available_space
			: 0;

		$result = array(
			'label'       => __( 'Disk-space available to safely perform updates', 'rollback-update-failure' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'rollback-update-failure' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				/* Translators: %s: Available disk-space in MB or GB. */
				'<p>' . __( '%s available disk space was detected, update routines can be performed safely.', 'rollback-update-failure' ) . '</p>',
				size_format( $available_space )
			),
			'actions'     => '',
			'test'        => 'available_updates_disk_space',
		);

		if ( $available_space < 100 * MB_IN_BYTES ) {
			$result['description'] = __( 'Available disk space is low, less than 100MB available.', 'rollback-update-failure' );
			$result['status']      = 'recommended';
		}

		if ( $available_space < 20 * MB_IN_BYTES ) {
			$result['description'] = __( 'Available disk space is critically low, less than 20MB available. Proceed with caution, updates may fail.', 'rollback-update-failure' );
			$result['status']      = 'critical';
		}

		if ( ! $available_space ) {
			$result['description'] = __( 'Could not determine available disk space for updates.', 'rollback-update-failure' );
			$result['status']      = 'recommended';
		}

		return $result;
	}

	/**
	 * Test if plugin and theme updates temp-backup folders are writable or can be created.
	 *
	 * @since 6.1.0
	 *
	 * @return array The test results.
	 */
	public function get_test_update_temp_backup_writable() {
		$result = array(
			'label'       => __( 'Plugin and theme update temp-backup folder is writable', 'rollback-update-failure' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'rollback-update-failure' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				/* Translators: %s: "wp-content/upgrade/temp-backup". */
				'<p>' . __( 'The %s folder used to improve the stability of plugin and theme updates is writable.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade/temp-backup</code>'
			),
			'actions'     => '',
			'test'        => 'update_temp_backup_writable',
		);

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->wp_content_dir() ) {
			return new WP_Error( 'fs_no_content_dir', $this->strings['fs_no_content_dir'] );
		}

		$wp_content = $wp_filesystem->wp_content_dir();

		$upgrade_folder_exists      = $wp_filesystem->is_dir( "$wp_content/upgrade" );
		$upgrade_folder_is_writable = $wp_filesystem->is_writable( "$wp_content/upgrade" );
		$backup_folder_exists       = $wp_filesystem->is_dir( "$wp_content/upgrade/temp-backup" );
		$backup_folder_is_writable  = $wp_filesystem->is_writable( "$wp_content/upgrade/temp-backup" );
		$plugins_folder_exists      = $wp_filesystem->is_dir( "$wp_content/upgrade/temp-backup/plugins" );
		$plugins_folder_is_writable = $wp_filesystem->is_writable( "$wp_content/upgrade/temp-backup/plugins" );
		$themes_folder_exists       = $wp_filesystem->is_dir( "$wp_content/upgrade/temp-backup/themes" );
		$themes_folder_is_writable  = $wp_filesystem->is_writable( "$wp_content/upgrade/temp-backup/themes" );

		if ( $plugins_folder_exists && ! $plugins_folder_is_writable && $themes_folder_exists && ! $themes_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Plugins and themes temp-backup folders exist but are not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade/temp-backup/plugins</code>' */
				'<p>' . __( 'The %1$s and %2$s folders exist but are not writable. These folders are used to improve the stability of plugin updates. Please make sure the server has write permissions to these folders.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade/temp-backup/plugins</code>',
				'<code>wp-content/upgrade/temp-backup/themes</code>'
			);

			return $result;
		}

		if ( $plugins_folder_exists && ! $plugins_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Plugins temp-backup folder exists but is not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade/temp-backup/plugins</code>' */
				'<p>' . __( 'The %s folder exists but is not writable. This folder is used to improve the stability of plugin updates. Please make sure the server has write permissions to this folder.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade/temp-backup/plugins</code>'
			);

			return $result;
		}

		if ( $themes_folder_exists && ! $themes_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Themes temp-backup folder exists but is not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade/temp-backup/themes</code>' */
				'<p>' . __( 'The %s folder exists but is not writable. This folder is used to improve the stability of theme updates. Please make sure the server has write permissions to this folder.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade/temp-backup/themes</code>'
			);

			return $result;
		}

		if ( ( ! $plugins_folder_exists || ! $themes_folder_exists ) && $backup_folder_exists && ! $backup_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'The temp-backup folder exists but is not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade/temp-backup</code>' */
				'<p>' . __( 'The %s folder exists but is not writable. This folder is used to improve the stability of plugin and theme updates. Please make sure the server has write permissions to this folder.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade/temp-backup</code>'
			);

			return $result;
		}

		if ( ! $backup_folder_exists && $upgrade_folder_exists && ! $upgrade_folder_is_writable ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'The upgrade folder exists but is not writable', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %s: '<code>wp-content/upgrade</code>' */
				'<p>' . __( 'The %s folder exists but is not writable. This folder is used to for plugin and theme updates. Please make sure the server has write permissions to this folder.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade</code>'
			);

			return $result;
		}

		if ( ! $upgrade_folder_exists && ! $wp_filesystem->is_writable( $wp_content ) ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'The upgrade folder can not be created', 'rollback-update-failure' );
			$result['description'] = sprintf(
				/* translators: %1$s: <code>wp-content/upgrade</code>. %2$s: <code>wp-content</code>. */
				'<p>' . __( 'The %1$s folder does not exist, and the server does not have write permissions in %2$s to create it. This folder is used to for plugin and theme updates. Please make sure the server has write permissions in %2$s.', 'rollback-update-failure' ) . '</p>',
				'<code>wp-content/upgrade</code>',
				'<code>wp-content</code>'
			);

			return $result;
		}

		return $result;
	}
}

new WP_Site_Health();
