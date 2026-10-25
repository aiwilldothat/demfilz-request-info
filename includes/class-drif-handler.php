<?php
/**
 * AJAX handler: validates the submission, stores it as an Info Request and
 * emails it to the configured recipient.
 *
 * @package Demfilz_Request_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes popup form submissions (logged-in and guest).
 */
class DRIF_Handler {

	/**
	 * Max submissions per IP per hour.
	 */
	const RATE_LIMIT = 5;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_drif_submit', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_drif_submit', array( $this, 'handle' ) );
	}

	/**
	 * Handle a form submission.
	 */
	public function handle() {
		check_ajax_referer( 'drif_submit', 'nonce' );

		$success_message = (string) drif_get_option( 'success_message' );

		// Honeypot: bots that fill the hidden field get a fake success and are dropped.
		if ( ! empty( $_POST['drif_website'] ) ) {
			wp_send_json_success( array( 'message' => $success_message ) );
		}

		// Simple per-IP rate limit.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'drif_rl_' . md5( $ip );
		$hit = (int) get_transient( $key );
		if ( $hit >= self::RATE_LIMIT ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'demfilz-request-info' ) ) );
		}
		set_transient( $key, $hit + 1, HOUR_IN_SECONDS );

		// Fields.
		$name     = isset( $_POST['drif_name'] ) ? sanitize_text_field( wp_unslash( $_POST['drif_name'] ) ) : '';
		$company  = isset( $_POST['drif_company'] ) ? sanitize_text_field( wp_unslash( $_POST['drif_company'] ) ) : '';
		$phone    = isset( $_POST['drif_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['drif_phone'] ) ) : '';
		$email    = isset( $_POST['drif_email'] ) ? sanitize_email( wp_unslash( $_POST['drif_email'] ) ) : '';
		$suburb   = isset( $_POST['drif_suburb'] ) ? sanitize_text_field( wp_unslash( $_POST['drif_suburb'] ) ) : '';
		$postcode = isset( $_POST['drif_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['drif_postcode'] ) ) : '';
		$details  = isset( $_POST['drif_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['drif_details'] ) ) : '';

		// Length caps.
		$name     = substr( $name, 0, 200 );
		$company  = substr( $company, 0, 200 );
		$phone    = substr( $phone, 0, 40 );
		$suburb   = substr( $suburb, 0, 200 );
		$postcode = substr( $postcode, 0, 40 );
		$details  = substr( $details, 0, 5000 );

		// Validation.
		if ( '' === $name || '' === $details ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in the required fields.', 'demfilz-request-info' ) ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'demfilz-request-info' ) ) );
		}

		// The page/product the request came from (resolved server-side when possible).
		$page_id    = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
		$page_title = '';
		$page_url   = '';
		if ( $page_id && get_post( $page_id ) ) {
			// Decode texturized entities (300&#215;600) - this value goes into
			// plain-text contexts (post title, email) and is escaped on output.
			$page_title = html_entity_decode( (string) get_the_title( $page_id ), ENT_QUOTES, get_bloginfo( 'charset' ) );
			$page_url   = (string) get_permalink( $page_id );
		} else {
			$page_title = isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['page_title'] ) ) : '';
			$page_url   = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		}
		$page_title = substr( $page_title, 0, 300 );

		// Store the request in the dashboard.
		$post_id = wp_insert_post(
			array(
				'post_type'    => DRIF_CPT,
				'post_status'  => 'publish',
				'post_title'   => $name . ( '' !== $page_title ? ' - ' . $page_title : '' ),
				'post_content' => $details,
			),
			true
		);

		if ( ! is_wp_error( $post_id ) && $post_id ) {
			update_post_meta( $post_id, 'drif_name', $name );
			update_post_meta( $post_id, 'drif_company', $company );
			update_post_meta( $post_id, 'drif_phone', $phone );
			update_post_meta( $post_id, 'drif_email', $email );
			update_post_meta( $post_id, 'drif_suburb', $suburb );
			update_post_meta( $post_id, 'drif_postcode', $postcode );
			update_post_meta( $post_id, 'drif_page_title', $page_title );
			update_post_meta( $post_id, 'drif_page_url', $page_url );
		}

		// Email the request.
		$blogname = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );

		/* translators: 1: site name, 2: page or product title. */
		$subject = sprintf( __( '[%1$s] Information request: %2$s', 'demfilz-request-info' ), $blogname, ( '' !== $page_title ? $page_title : $name ) );

		$message = $this->email_html(
			array(
				'name'       => $name,
				'company'    => $company,
				'phone'      => $phone,
				'email'      => $email,
				'suburb'     => $suburb,
				'postcode'   => $postcode,
				'details'    => $details,
				'page_title' => $page_title,
				'page_url'   => $page_url,
				'blogname'   => $blogname,
			)
		);

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'Reply-To: ' . $name . ' <' . $email . '>',
		);

		wp_mail( drif_recipient_email(), $subject, $message, $headers );

		wp_send_json_success( array( 'message' => $success_message ) );
	}

	/**
	 * Build the HTML notification email. All values are escaped here; layout
	 * uses tables and inline styles for email-client compatibility.
	 *
	 * @param array $data Request data (name, company, phone, email, suburb,
	 *                    postcode, details, page_title, page_url, blogname).
	 * @return string
	 */
	private function email_html( $data ) {
		$accent = drif_sanitize_hex_color( (string) drif_get_option( 'button_color' ) );
		if ( '' === $accent ) {
			$accent = '#4f46e5';
		}

		$label_style = 'padding:8px 16px 8px 0;font-size:13px;color:#64748b;white-space:nowrap;vertical-align:top;width:120px;';
		$value_style = 'padding:8px 0;font-size:14px;color:#0f172a;';

		$rows = array(
			__( 'Name', 'demfilz-request-info' )     => esc_html( $data['name'] ),
			__( 'Company', 'demfilz-request-info' )  => esc_html( $data['company'] ),
			__( 'Phone', 'demfilz-request-info' )    => esc_html( $data['phone'] ),
			__( 'Email', 'demfilz-request-info' )    => '<a href="mailto:' . esc_attr( $data['email'] ) . '" style="color:' . esc_attr( $accent ) . ';">' . esc_html( $data['email'] ) . '</a>',
			__( 'Suburb', 'demfilz-request-info' )   => esc_html( $data['suburb'] ),
			__( 'Postcode', 'demfilz-request-info' ) => esc_html( $data['postcode'] ),
		);

		$rows_html = '';
		foreach ( $rows as $label => $value_html ) {
			$rows_html .= '<tr><td style="' . $label_style . '">' . esc_html( $label ) . '</td><td style="' . $value_style . '">' . $value_html . '</td></tr>';
		}

		$page_html = esc_html( $data['page_title'] );
		if ( '' !== $data['page_url'] ) {
			$page_html = '<a href="' . esc_url( $data['page_url'] ) . '" style="color:' . esc_attr( $accent ) . ';font-weight:600;text-decoration:none;">'
				. esc_html( '' !== $data['page_title'] ? $data['page_title'] : $data['page_url'] ) . '</a>'
				. '<br /><span style="font-size:12px;color:#94a3b8;">' . esc_html( $data['page_url'] ) . '</span>';
		}

		return '<!DOCTYPE html><html><head><meta charset="utf-8" /></head>'
			. '<body style="margin:0;padding:0;background:#f1f5f9;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;"><tr><td align="center">'
			. '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;font-family:-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'

			// Header bar.
			. '<tr><td style="background:' . esc_attr( $accent ) . ';padding:20px 28px;">'
			. '<h1 style="margin:0;font-size:18px;line-height:1.3;color:#ffffff;">' . esc_html__( 'New information request', 'demfilz-request-info' ) . '</h1>'
			. '<p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,0.75);">' . esc_html( $data['blogname'] ) . '</p>'
			. '</td></tr>'

			// Page / product box.
			. '<tr><td style="padding:24px 28px 4px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;"><tr><td style="padding:12px 16px;">'
			. '<span style="display:block;font-size:11px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;color:#94a3b8;margin-bottom:4px;">' . esc_html__( 'Page / Product', 'demfilz-request-info' ) . '</span>'
			. '<span style="font-size:15px;">' . $page_html . '</span>'
			. '</td></tr></table>'
			. '</td></tr>'

			// Contact fields.
			. '<tr><td style="padding:12px 28px 4px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $rows_html . '</table>'
			. '</td></tr>'

			// Details.
			. '<tr><td style="padding:8px 28px 24px;">'
			. '<span style="display:block;font-size:11px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;color:#94a3b8;margin-bottom:6px;">' . esc_html__( 'Details', 'demfilz-request-info' ) . '</span>'
			. '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;font-size:14px;line-height:1.6;color:#0f172a;">' . nl2br( esc_html( $data['details'] ) ) . '</div>'
			. '</td></tr>'

			// Footer.
			. '<tr><td style="padding:14px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;">'
			/* translators: %s: site name. */
			. '<p style="margin:0;font-size:12px;color:#94a3b8;">' . sprintf( esc_html__( 'Sent by the Demfilz Request Info plugin on %s. Reply to this email to answer the sender.', 'demfilz-request-info' ), esc_html( $data['blogname'] ) ) . '</p>'
			. '</td></tr>'

			. '</table></td></tr></table></body></html>';
	}
}
