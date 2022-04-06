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
 * Class WP_Debug_Data
 */
class WP_Debug_Data {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add extra info for site-health.
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
	}

	/**
	 * Additional site health data.
	 *
	 * @param array $info Array of site health info.
	 *
	 * @return array
	 */
	public function debug_information( $info ) {
		$info['wp-server']['fields']['virtualbox_environment'] = array(
			'label' => __( 'VirtualBox Environment' ),
			'value' => is_virtualbox() ? 'true' : 'false',
			'debug' => is_virtualbox(),
		);

		return $info;
	}

}

new WP_Debug_Data();
