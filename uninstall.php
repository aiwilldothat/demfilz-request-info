<?php
/**
 * Uninstall: remove the plugin settings and all stored requests.
 *
 * @package Demfilz_Request_Info
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'drif_settings' );

$drif_requests = get_posts(
	array(
		'post_type'   => 'drif_request',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

foreach ( $drif_requests as $drif_request_id ) {
	wp_delete_post( $drif_request_id, true );
}
