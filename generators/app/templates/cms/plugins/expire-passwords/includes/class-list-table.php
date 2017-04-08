<?php

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Expire_Passwords_List_Table {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'admin_head',                 array( $this, 'admin_css' ) );
		add_filter( 'manage_users_columns',       array( $this, 'users_column' ) );
		add_action( 'manage_users_custom_column', array( $this, 'render_users_column' ), 10, 3 );

	}

	/**
	 * Print custom CSS styles for the users.php screen.
	 *
	 * @action admin_head
	 */
	public function admin_css() {

		$screen = get_current_screen();

		if ( ! isset( $screen->id ) || 'users' !== $screen->id ) {

			return;

		}

		?>
		<style type="text/css">
		.fixed .column-expass {
			width: 150px;
		}
		@media screen and (max-width: 782px) {
			.fixed .column-expass {
				display: none;
			}
		}
		.expass-is-expired {
			color: #a00;
		}
		</style>
		<?php

	}

	/**
	 * Add a custom column to the Users list table.
	 *
	 * @filter manage_users_columns
	 *
	 * @param  array $columns
	 *
	 * @return array
	 */
	public function users_column( $columns ) {

		$columns['expass'] = esc_html__( 'Password Reset', 'expire-passwords' );

		return $columns;

	}

	/**
	 * Add content to the custom column in the Users list table.
	 *
	 * @action manage_users_custom_column
	 *
	 * @param  string $value
	 * @param  string $column_name
	 * @param  int    $user_id
	 *
	 * @return string
	 */
	public function render_users_column( $value, $column_name, $user_id ) {

		if ( 'expass' !== $column_name ) {

			return $value;

		}

		if (
			! Expire_Passwords::has_expirable_role( $user_id )
			||
			false === ( $reset = Expire_Passwords::get_user_meta( $user_id ) )
		) {

			return '&mdash;';

		}

		$time_diff = sprintf( __( '%1$s ago', 'expire-passwords' ), human_time_diff( $reset, time() ) );
		$class     = Expire_Passwords::is_expired( $user_id ) ? 'expass-is-expired' : 'expass-not-expired';

		return sprintf(
			'<span class="%s">%s</span>',
			esc_attr( $class ),
			esc_html( $time_diff )
		);

	}

}
