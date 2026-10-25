<?php
/**
 * Plugin Name:       Demfilz Request Info
 * Plugin URI:        https://demfilz.com/request-info
 * Description:       Adds a "Request Info" button to WooCommerce products, pages and posts that opens a popup enquiry form (Name, Company, Phone, Email, Suburb, Postcode, Details). Logged-in users are pre-filled automatically and every enquiry records the page or product it came from. Requests are emailed to you and stored in the dashboard.
 * Version:           1.0.1
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Demfilz
 * Author URI:        https://demfilz.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       demfilz-request-info
 *
 * @package Demfilz_Request_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DRIF_VERSION', '1.0.1' );
define( 'DRIF_FILE', __FILE__ );
define( 'DRIF_PATH', plugin_dir_path( __FILE__ ) );
define( 'DRIF_URL', plugin_dir_url( __FILE__ ) );
define( 'DRIF_OPTION_KEY', 'drif_settings' );
define( 'DRIF_CPT', 'drif_request' );

require_once DRIF_PATH . 'includes/class-drif-requests.php';
require_once DRIF_PATH . 'includes/class-drif-admin.php';
require_once DRIF_PATH . 'includes/class-drif-frontend.php';
require_once DRIF_PATH . 'includes/class-drif-handler.php';

/**
 * Default settings used on activation and as fallbacks.
 *
 * @return array
 */
function drif_default_settings() {
	return array(
		'show_on_products'  => 1,
		'show_on_pages'     => 1,
		'show_on_posts'     => 0,
		'button_text'       => __( 'Request Info', 'demfilz-request-info' ),
		'button_color'      => '#4f46e5',
		'button_text_color' => '#ffffff',
		'recipient'         => '',
		'success_message'   => __( 'Thanks - your request has been sent. We will be in touch shortly.', 'demfilz-request-info' ),
	);
}

/**
 * Get a single plugin setting (or the whole settings array when $key is null).
 *
 * @param string|null $key      Setting key.
 * @param mixed       $fallback Fallback value (defaults to the built-in default for $key).
 * @return mixed
 */
function drif_get_option( $key = null, $fallback = null ) {
	$defaults = drif_default_settings();
	$options  = get_option( DRIF_OPTION_KEY, array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}
	$options = array_merge( $defaults, $options );

	if ( null === $key ) {
		return $options;
	}
	if ( isset( $options[ $key ] ) ) {
		return $options[ $key ];
	}
	return ( null === $fallback && isset( $defaults[ $key ] ) ) ? $defaults[ $key ] : $fallback;
}

/**
 * Validate a hex colour (#rgb or #rrggbb). Returns '' when invalid.
 *
 * @param string $color Candidate colour.
 * @return string
 */
function drif_sanitize_hex_color( $color ) {
	$color = trim( (string) $color );
	if ( '' === $color ) {
		return '';
	}
	if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ) {
		return $color;
	}
	return '';
}

/**
 * The email address enquiries are sent to (settings recipient or the site admin email).
 *
 * @return string
 */
function drif_recipient_email() {
	$recipient = drif_get_option( 'recipient' );
	if ( '' !== $recipient && is_email( $recipient ) ) {
		return $recipient;
	}
	return (string) get_option( 'admin_email' );
}

/**
 * Boot the plugin.
 */
function drif_init() {
	new DRIF_Requests();
	new DRIF_Admin();
	new DRIF_Frontend();
	new DRIF_Handler();
}
add_action( 'plugins_loaded', 'drif_init' );

/**
 * Activation - seed default settings the first time the plugin runs.
 */
function drif_activate() {
	if ( false === get_option( DRIF_OPTION_KEY, false ) ) {
		add_option( DRIF_OPTION_KEY, drif_default_settings() );
	}
}
register_activation_hook( __FILE__, 'drif_activate' );

/**
 * "Settings" link on the Plugins screen.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'edit.php?post_type=' . DRIF_CPT . '&page=drif-settings' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'demfilz-request-info' ) . '</a>' );
		return $links;
	}
);
