<?php

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Expire_Passwords_Settings {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'admin_menu',        array( $this, 'submenu_page' ) );
		add_action( 'admin_init',        array( $this, 'init' ) );
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );

	}

	/**
	 * Add custom submenu page under the Users menu.
	 *
	 * @action admin_menu
	 */
	public function submenu_page() {

		add_submenu_page(
			'users.php',
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			'manage_options',
			'expire_passwords',
			array( $this, 'render_submenu_page' )
		);

	}

	/**
	 * Content for the custom submenu page under the Users menu.
	 *
	 * @see $this->submenu_page()
	 */
	public function render_submenu_page() {

		?>
		<div class="wrap">

			<h2><?php esc_html_e( 'Expire Passwords', 'expire-passwords' ) ?></h2>

			<form method="post" action="options.php">
				<?php

				settings_fields( 'expass_settings_page' );

				do_settings_sections( 'expass_settings_page' );

				submit_button();

				?>
			</form>

		</div>
		<?php

	}

	/**
	 * Register custom setting sections and fields.
	 *
	 * @action admin_init
	 */
	public function init() {

		register_setting(
			'expass_settings_page',
			'expass_settings'
		);

		add_settings_section(
			'expass_settings_page_section',
			null,
			array( $this, 'render_section' ),
			'expass_settings_page'
		);

		add_settings_field(
			'expass_settings_field_limit',
			esc_html__( 'Require password reset every', 'expire-passwords' ),
			array( $this, 'render_field_limit' ),
			'expass_settings_page',
			'expass_settings_page_section'
		);

		add_settings_field(
			'expass_settings_field_roles',
			esc_html__( 'For users in these roles', 'expire-passwords' ),
			array( $this, 'render_field_roles' ),
			'expass_settings_page',
			'expass_settings_page_section'
		);

	}

	/**
	 * Content for the custom settings section.
	 *
	 * @see $this->init()
	 */
	public function render_section() {

		printf(
			'<p>%s</p>',
			esc_html__( 'Require certain users to change their passwords on a regular basis.', 'expire-passwords' )
		);

	}

	/**
	 * Content for the limit setting field.
	 *
	 * @see $this->init()
	 */
	public function render_field_limit() {

		$options = (array) get_option( 'expass_settings', array() );
		$value   = isset( $options['limit'] ) ? $options['limit'] : null;

		printf(
			'<input type="number" min="1" max="365" maxlength="3" name="expass_settings[limit]" placeholder="%s" value="%s"> %s',
			esc_attr( Expire_Passwords::$default_limit ),
			esc_attr( $value ),
			esc_html__( 'days', 'expire-passwords' )
		);

	}

	/**
	 * Content for the roles setting field.
	 *
	 * @see $this->init()
	 */
	public function render_field_roles() {

		$options = (array) get_option( 'expass_settings', array() );
		$roles   = get_editable_roles();

		foreach ( $roles as $role => $role_data ) {

			$name  = sanitize_key( $role );
			$value = ( ! $options ) ? ( 'administrator' === $role ? 0 : 1 ) : ( empty( $options['roles'][ $name ] ) ? 0 : 1 );

			printf(
				'<p><input type="checkbox" name="expass_settings[roles][%1$s]" id="expass_settings[roles][%1$s]" %2$s value="1"><label for="expass_settings[roles][%1$s]">%3$s</label></p>',
				esc_attr( $name ),
				checked( $value, 1, false ),
				esc_html( $role_data['name'] )
			);

		}

	}

	/**
	 * Plugin review call-to-action text for the admin footer.
	 *
	 * @filter admin_footer_text
	 *
	 * @param  string $text
	 *
	 * @return string
	 */
	public function admin_footer_text( $text ) {

		$screen = get_current_screen();

		if ( ! isset( $screen->id ) || 'users_page_expire_passwords' !== $screen->id ) {

			return $text;

		}

		return sprintf(
			__( 'Do you like the %1$s plugin? Please consider %2$s on %3$s', 'expire-passwords' ),
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( 'https://wordpress.org/support/view/plugin-reviews/expire-passwords#postform' ),
				__( 'leaving a &#9733;&#9733;&#9733;&#9733;&#9733; review', 'expire-passwords' )
			),
			sprintf(
				'<a href="%s" target="_blank">WordPress.org</a>',
				esc_url( 'https://wordpress.org/plugins/expire-passwords/' )
			)
		);

	}

}
