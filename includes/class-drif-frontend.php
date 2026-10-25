<?php
/**
 * Front end: the Request Info button (products, pages, posts, shortcode),
 * asset loading, the prefill data and the popup form markup.
 *
 * @package Demfilz_Request_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs the button and the popup form on the front end.
 */
class DRIF_Frontend {

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Whether assets have been enqueued (also gates the footer markup).
	 *
	 * @var bool
	 */
	private $enqueued = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'product_button' ), 35 );
		add_filter( 'the_content', array( $this, 'append_button' ) );
		add_shortcode( 'request_info_button', array( $this, 'button_shortcode' ) );
		add_action( 'wp_footer', array( $this, 'render_modal' ) );
	}

	/**
	 * Settings accessor (cached).
	 *
	 * @return array
	 */
	private function settings() {
		if ( null === $this->settings ) {
			$this->settings = drif_get_option();
		}
		return $this->settings;
	}

	/**
	 * Whether the button auto-appears on the current singular view.
	 *
	 * @return bool
	 */
	private function should_show() {
		if ( ! is_singular() ) {
			return false;
		}
		$s    = $this->settings();
		$type = get_post_type();
		if ( 'product' === $type ) {
			return (bool) $s['show_on_products'];
		}
		if ( 'page' === $type ) {
			return (bool) $s['show_on_pages'];
		}
		if ( 'post' === $type ) {
			return (bool) $s['show_on_posts'];
		}
		return false;
	}

	/**
	 * Enqueue assets when the button will appear on this view.
	 */
	public function maybe_enqueue() {
		if ( $this->should_show() ) {
			$this->enqueue();
		}
	}

	/**
	 * Register and enqueue the form assets + localized data.
	 */
	public function enqueue() {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;

		$s = $this->settings();

		wp_enqueue_style( 'dri-form', DRIF_URL . 'assets/css/dri-form.css', array(), DRIF_VERSION );
		wp_enqueue_script( 'dri-form', DRIF_URL . 'assets/js/dri-form.js', array(), DRIF_VERSION, true );

		$accent = drif_sanitize_hex_color( $s['button_color'] );
		$text   = drif_sanitize_hex_color( $s['button_text_color'] );
		$css    = '.dri-btn,.dri-root{--dri-accent:' . ( '' !== $accent ? $accent : '#4f46e5' ) . ';--dri-accent-text:' . ( '' !== $text ? $text : '#ffffff' ) . ';}';
		wp_add_inline_style( 'dri-form', $css );

		wp_localize_script( 'dri-form', 'DRIF_FORM', $this->script_data() );
	}

	/**
	 * The data handed to the front-end script: AJAX endpoint, nonce, the
	 * logged-in user's prefill values and the current page/product.
	 *
	 * @return array
	 */
	private function script_data() {
		$user_data = array(
			'name'     => '',
			'company'  => '',
			'phone'    => '',
			'email'    => '',
			'suburb'   => '',
			'postcode' => '',
		);

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$name = trim( (string) get_user_meta( $user->ID, 'first_name', true ) . ' ' . (string) get_user_meta( $user->ID, 'last_name', true ) );
			if ( '' === $name ) {
				$name = (string) $user->display_name;
			}
			$user_data = array(
				'name'     => $name,
				'company'  => (string) get_user_meta( $user->ID, 'billing_company', true ),
				'phone'    => (string) get_user_meta( $user->ID, 'billing_phone', true ),
				'email'    => (string) $user->user_email,
				'suburb'   => (string) get_user_meta( $user->ID, 'billing_city', true ),
				'postcode' => (string) get_user_meta( $user->ID, 'billing_postcode', true ),
			);
		}

		$post_id = is_singular() ? get_queried_object_id() : 0;

		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'drif_submit' ),
			'user'    => $user_data,
			'page'    => array(
				'id'    => (int) $post_id,
				// Titles can carry texturized entities (300&#215;600); the JS
				// inserts plain text, so decode them here.
				'title' => $post_id ? html_entity_decode( (string) get_the_title( $post_id ), ENT_QUOTES, get_bloginfo( 'charset' ) ) : '',
				'url'   => $post_id ? (string) get_permalink( $post_id ) : '',
			),
			'i18n'    => array(
				'detailsIntro' => __( 'I would like more information about:', 'demfilz-request-info' ),
				'required'     => __( 'Please fill in this field.', 'demfilz-request-info' ),
				'invalidEmail' => __( 'Please enter a valid email address.', 'demfilz-request-info' ),
				'error'        => __( 'Sorry, something went wrong. Please try again.', 'demfilz-request-info' ),
				'sending'      => __( 'Sending...', 'demfilz-request-info' ),
			),
		);
	}

	/**
	 * The button markup.
	 *
	 * @param string $text Optional label override.
	 * @return string
	 */
	private function button_html( $text = '' ) {
		$s     = $this->settings();
		$label = ( '' !== $text ) ? $text : $s['button_text'];
		return '<button type="button" class="dri-btn" data-dri-open="1">' . esc_html( $label ) . '</button>';
	}

	/**
	 * Print the button on single product pages (WooCommerce hook).
	 */
	public function product_button() {
		$s = $this->settings();
		if ( empty( $s['show_on_products'] ) || ! is_singular( 'product' ) ) {
			return;
		}
		printf(
			'<div class="dri-btn-wrap"><button type="button" class="dri-btn" data-dri-open="1">%s</button></div>',
			esc_html( $s['button_text'] )
		);
	}

	/**
	 * Append the button to page/post content when enabled.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append_button( $content ) {
		static $done = array();

		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$s    = $this->settings();
		$type = get_post_type();

		// Products get the button via the WooCommerce hook instead.
		$wanted = ( 'page' === $type && ! empty( $s['show_on_pages'] ) ) || ( 'post' === $type && ! empty( $s['show_on_posts'] ) );
		if ( ! $wanted ) {
			return $content;
		}

		// Append once per post, even when a theme or builder applies the
		// content filter more than once (also guards against re-entry).
		$post_id = get_the_ID();
		if ( isset( $done[ $post_id ] ) ) {
			return $content;
		}
		$done[ $post_id ] = true;

		return $content . '<div class="dri-btn-wrap">' . $this->button_html() . '</div>';
	}

	/**
	 * The [request_info_button text="..."] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function button_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'text' => '' ), $atts, 'request_info_button' );
		$this->enqueue();
		return $this->button_html( sanitize_text_field( $atts['text'] ) );
	}

	/**
	 * The popup form markup, printed once in the footer when assets loaded.
	 */
	public function render_modal() {
		if ( ! $this->enqueued ) {
			return;
		}
		$s = $this->settings();
		?>
		<div class="dri-overlay" aria-hidden="true">
			<div class="dri-modal dri-root" role="dialog" aria-modal="true" aria-labelledby="dri-title">
				<button type="button" class="dri-close" aria-label="<?php esc_attr_e( 'Close', 'demfilz-request-info' ); ?>">&times;</button>

				<div class="dri-form-view">
					<h3 id="dri-title" class="dri-heading"><?php echo esc_html( $s['button_text'] ); ?></h3>
					<p class="dri-sub"><?php esc_html_e( 'Send us your details and we will get back to you.', 'demfilz-request-info' ); ?></p>

					<form class="dri-form" novalidate>
						<div class="dri-grid">
							<p class="dri-field">
								<label for="dri-name"><?php esc_html_e( 'Name', 'demfilz-request-info' ); ?> <span class="dri-req">*</span></label>
								<input type="text" id="dri-name" name="drif_name" autocomplete="name" required />
							</p>
							<p class="dri-field">
								<label for="dri-company"><?php esc_html_e( 'Company', 'demfilz-request-info' ); ?></label>
								<input type="text" id="dri-company" name="drif_company" autocomplete="organization" />
							</p>
							<p class="dri-field">
								<label for="dri-phone"><?php esc_html_e( 'Phone', 'demfilz-request-info' ); ?></label>
								<input type="tel" id="dri-phone" name="drif_phone" autocomplete="tel" />
							</p>
							<p class="dri-field">
								<label for="dri-email"><?php esc_html_e( 'Email', 'demfilz-request-info' ); ?> <span class="dri-req">*</span></label>
								<input type="email" id="dri-email" name="drif_email" autocomplete="email" required />
							</p>
							<p class="dri-field">
								<label for="dri-suburb"><?php esc_html_e( 'Suburb', 'demfilz-request-info' ); ?></label>
								<input type="text" id="dri-suburb" name="drif_suburb" autocomplete="address-level2" />
							</p>
							<p class="dri-field">
								<label for="dri-postcode"><?php esc_html_e( 'Postcode', 'demfilz-request-info' ); ?></label>
								<input type="text" id="dri-postcode" name="drif_postcode" autocomplete="postal-code" />
							</p>
							<p class="dri-field dri-field-wide">
								<label for="dri-details"><?php esc_html_e( 'Details', 'demfilz-request-info' ); ?> <span class="dri-req">*</span></label>
								<textarea id="dri-details" name="drif_details" rows="5" required></textarea>
							</p>
						</div>

						<p class="dri-hp" aria-hidden="true">
							<label for="dri-website"><?php esc_html_e( 'Website', 'demfilz-request-info' ); ?></label>
							<input type="text" id="dri-website" name="drif_website" tabindex="-1" autocomplete="off" />
						</p>

						<div class="dri-msg" role="alert" hidden></div>

						<p class="dri-actions">
							<button type="submit" class="dri-btn dri-submit"><?php esc_html_e( 'Send Request', 'demfilz-request-info' ); ?></button>
						</p>
					</form>
				</div>

				<div class="dri-success-view" hidden>
					<div class="dri-success-icon" aria-hidden="true">&#10003;</div>
					<h3 class="dri-heading"><?php esc_html_e( 'Request sent', 'demfilz-request-info' ); ?></h3>
					<p class="dri-success-text"><?php echo esc_html( $s['success_message'] ); ?></p>
					<p class="dri-actions">
						<button type="button" class="dri-btn dri-done"><?php esc_html_e( 'Close', 'demfilz-request-info' ); ?></button>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
}
