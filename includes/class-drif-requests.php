<?php
/**
 * The "Info Requests" custom post type: registration, admin list columns and
 * the read-only request-details meta box.
 *
 * @package Demfilz_Request_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores every submitted enquiry as a (non-public) drif_request post.
 */
class DRIF_Requests {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_filter( 'manage_drif_request_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_drif_request_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'add_meta_boxes_drif_request', array( $this, 'add_meta_box' ) );
	}

	/**
	 * Register the private custom post type that holds submissions.
	 */
	public function register_cpt() {
		register_post_type(
			DRIF_CPT,
			array(
				'labels'             => array(
					'name'          => __( 'Info Requests', 'demfilz-request-info' ),
					'singular_name' => __( 'Info Request', 'demfilz-request-info' ),
					'menu_name'     => __( 'Request Info', 'demfilz-request-info' ),
					'all_items'     => __( 'All Requests', 'demfilz-request-info' ),
					'search_items'  => __( 'Search Requests', 'demfilz-request-info' ),
					'not_found'     => __( 'No requests received yet.', 'demfilz-request-info' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => false,
				'menu_icon'          => 'dashicons-info-outline',
				'menu_position'      => 26,
				'supports'           => array( 'title' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'capabilities'       => array(
					'create_posts' => 'do_not_allow',
				),
			)
		);
	}

	/**
	 * Admin list columns.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public function columns( $columns ) {
		return array(
			'cb'            => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'title'         => __( 'Request', 'demfilz-request-info' ),
			'drif_email'    => __( 'Email', 'demfilz-request-info' ),
			'drif_phone'    => __( 'Phone', 'demfilz-request-info' ),
			'drif_location' => __( 'Suburb / Postcode', 'demfilz-request-info' ),
			'drif_page'     => __( 'Page / Product', 'demfilz-request-info' ),
			'date'          => __( 'Received', 'demfilz-request-info' ),
		);
	}

	/**
	 * Admin list column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( $column, $post_id ) {
		if ( 'drif_email' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, 'drif_email', true ) );
		} elseif ( 'drif_phone' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, 'drif_phone', true ) );
		} elseif ( 'drif_location' === $column ) {
			$suburb   = (string) get_post_meta( $post_id, 'drif_suburb', true );
			$postcode = (string) get_post_meta( $post_id, 'drif_postcode', true );
			echo esc_html( trim( $suburb . ' ' . $postcode ) );
		} elseif ( 'drif_page' === $column ) {
			$title = (string) get_post_meta( $post_id, 'drif_page_title', true );
			$url   = (string) get_post_meta( $post_id, 'drif_page_url', true );
			if ( '' !== $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( '' !== $title ? $title : $url ) . '</a>';
			} else {
				echo esc_html( $title );
			}
		}
	}

	/**
	 * Register the request-details meta box on the edit screen.
	 */
	public function add_meta_box() {
		add_meta_box(
			'drif-request-details',
			__( 'Request Details', 'demfilz-request-info' ),
			array( $this, 'render_meta_box' ),
			DRIF_CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Render the (read-only) request details.
	 *
	 * @param WP_Post $post The request post.
	 */
	public function render_meta_box( $post ) {
		$fields = array(
			'drif_name'     => __( 'Name', 'demfilz-request-info' ),
			'drif_company'  => __( 'Company', 'demfilz-request-info' ),
			'drif_phone'    => __( 'Phone', 'demfilz-request-info' ),
			'drif_email'    => __( 'Email', 'demfilz-request-info' ),
			'drif_suburb'   => __( 'Suburb', 'demfilz-request-info' ),
			'drif_postcode' => __( 'Postcode', 'demfilz-request-info' ),
		);

		echo '<table class="widefat striped" style="max-width:720px">';
		foreach ( $fields as $key => $label ) {
			$value = (string) get_post_meta( $post->ID, $key, true );
			echo '<tr><th style="width:160px;text-align:left">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}

		$page_title = (string) get_post_meta( $post->ID, 'drif_page_title', true );
		$page_url   = (string) get_post_meta( $post->ID, 'drif_page_url', true );
		echo '<tr><th style="width:160px;text-align:left">' . esc_html__( 'Page / Product', 'demfilz-request-info' ) . '</th><td>';
		if ( '' !== $page_url ) {
			echo '<a href="' . esc_url( $page_url ) . '" target="_blank" rel="noopener">' . esc_html( '' !== $page_title ? $page_title : $page_url ) . '</a>';
		} else {
			echo esc_html( $page_title );
		}
		echo '</td></tr>';
		echo '</table>';

		echo '<h4>' . esc_html__( 'Details', 'demfilz-request-info' ) . '</h4>';
		echo wp_kses_post( wpautop( esc_html( $post->post_content ) ) );
	}
}
