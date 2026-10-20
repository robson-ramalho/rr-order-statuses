<?php
/**
 * Removes the plugin's option. Orders keep their stored status.
 *
 * @package RR_Order_Statuses
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'rros_statuses' );
