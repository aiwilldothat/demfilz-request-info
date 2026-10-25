<?php
/**
 * Admin: the settings screen (under the Request Info menu).
 *
 * @package Demfilz_Request_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the plugin settings page.
 */
class DRIF_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the Settings submenu under the Request Info menu.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . DRIF_CPT,
			__( 'Request Info Settings', 'demfilz-request-info' ),
			__( 'Settings', 'demfilz-request-info' ),
			'manage_options',
			'drif-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the single settings option.
	 */
	public function register_settings() {
		register_setting(
			'drif_settings_group',
			DRIF_OPTION_KEY,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	/**
	 * Sanitize the settings on save.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = drif_default_settings();
		$input    = is_array( $input ) ? $input : array();
		$clean    = array();

		$clean['show_on_products'] = empty( $input['show_on_products'] ) ? 0 : 1;
		$clean['show_on_pages']    = empty( $input['show_on_pages'] ) ? 0 : 1;
		$clean['show_on_posts']    = empty( $input['show_on_posts'] ) ? 0 : 1;

		$button_text          = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '';
		$clean['button_text'] = ( '' !== $button_text ) ? $button_text : $defaults['button_text'];

		$button_color          = isset( $input['button_color'] ) ? drif_sanitize_hex_color( $input['button_color'] ) : '';
		$clean['button_color'] = ( '' !== $button_color ) ? $button_color : $defaults['button_color'];

		$button_text_color          = isset( $input['button_text_color'] ) ? drif_sanitize_hex_color( $input['button_text_color'] ) : '';
		$clean['button_text_color'] = ( '' !== $button_text_color ) ? $button_text_color : $defaults['button_text_color'];

		$recipient          = isset( $input['recipient'] ) ? sanitize_email( $input['recipient'] ) : '';
		$clean['recipient'] = is_email( $recipient ) ? $recipient : '';

		$success_message          = isset( $input['success_message'] ) ? sanitize_textarea_field( $input['success_message'] ) : '';
		$clean['success_message'] = ( '' !== $success_message ) ? $success_message : $defaults['success_message'];

		return $clean;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = drif_get_option();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Request Info Settings', 'demfilz-request-info' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'drif_settings_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Where the button appears', 'demfilz-request-info' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show automatically on', 'demfilz-request-info' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( DRIF_OPTION_KEY ); ?>[show_on_products]" value="1" <?php checked( $s['show_on_products'], 1 ); ?> />
									<?php esc_html_e( 'WooCommerce products (after the add-to-cart area)', 'demfilz-request-info' ); ?>
								</label><br />
								<label>
									<input type="checkbox" name="<?php echo esc_attr( DRIF_OPTION_KEY ); ?>[show_on_pages]" value="1" <?php checked( $s['show_on_pages'], 1 ); ?> />
									<?php esc_html_e( 'Pages (after the content)', 'demfilz-request-info' ); ?>
								</label><br />
								<label>
									<input type="checkbox" name="<?php echo esc_attr( DRIF_OPTION_KEY ); ?>[show_on_posts]" value="1" <?php checked( $s['show_on_posts'], 1 ); ?> />
									<?php esc_html_e( 'Posts (after the content)', 'demfilz-request-info' ); ?>
								</label>
							</fieldset>
							<p class="description">
								<?php esc_html_e( 'You can also place the button anywhere with the shortcode:', 'demfilz-request-info' ); ?>
								<code>[request_info_button text="Request Info"]</code>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="drif-button-text"><?php esc_html_e( 'Button text', 'demfilz-request-info' ); ?></label></th>
						<td>
							<input type="text" id="drif-button-text" class="regular-text" name="<?php echo esc_attr( DRIF_OPTION_KEY ); ?>[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Button colours', 'demfilz-request-info' ); ?></th>
						<td>
							<label>
								<input type="color" name="<?php echo esc_attr( DRIF_OPTION_KEY ); ?>[button_color]" value="<?php echo esc_attr( $s['button_color'] ); ?>" />
								<?php esc_html_e( 'Background', 'demfilz-request-info' ); ?>
							</label>
							&nbsp;&nbsp;
							<label>
								<input type="color" name="<?php echo esc_attr( DRIF_OPTION_KEY ); ?>[button_text_color]" value="<?php echo esc_attr( $s['button_text_color'] ); ?>" />
								<?php esc_html_e( 'Text', 'demfilz-request-info' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Email & messages', 'demfilz-request-info' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="drif-recipient"><?php esc_html_e( 'Send requests to', 'demfilz-request-info' ); ?></label></th>
						<td>
							<input type="email" id="drif-recipient" class="regular-text" name="<?php echo esc_attr( DRIF_OPTION_KEY ); ?>[recipient]" value="<?php echo esc_attr( $s['recipient'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to use the site admin email. Requests are also stored under Request Info > All Requests.', 'demfilz-request-info' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="drif-success"><?php esc_html_e( 'Success message', 'demfilz-request-info' ); ?></label></th>
						<td>
							<textarea id="drif-success" class="large-text" rows="2" name="<?php echo esc_attr( DRIF_OPTION_KEY ); ?>[success_message]"><?php echo esc_textarea( $s['success_message'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown inside the popup after a successful submission.', 'demfilz-request-info' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
