<?php
/*
Plugin Name: Google Captcha (reCAPTCHA) by BestWebSoft
Plugin URI: http://bestwebsoft.com/products/wordpress/plugins/google-captcha/
Description: Protect WordPress website forms from spam entries with Google Captcha (reCaptcha).
Author: BestWebSoft
Text Domain: google-captcha
Domain Path: /languages
Version: 1.27
Author URI: http://bestwebsoft.com/
License: GPLv3 or later
*/

/*  © Copyright 2017  BestWebSoft  ( http://support.bestwebsoft.com )

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Add menu page */
if ( ! function_exists( 'gglcptch_admin_menu' ) ) {
	function gglcptch_admin_menu() {
		bws_general_menu();
		$gglcptch_settings = add_submenu_page( 'bws_panel', __( 'Google Captcha Settings', 'google-captcha' ), 'Google Captcha', 'manage_options', 'google-captcha.php', 'gglcptch_add_settings_page' );
		add_action( 'load-' . $gglcptch_settings, 'gglcptch_add_tabs' );
	}
}

if ( ! function_exists( 'gglcptch_plugins_loaded' ) ) {
	function gglcptch_plugins_loaded() {
		/* Internationalization, first(!)  */
		load_plugin_textdomain( 'google-captcha', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}

if ( ! function_exists( 'gglcptch_init' ) ) {
	function gglcptch_init() {
		global $gglcptch_plugin_info, $gglcptch_options, $gglcptch_ip_in_whitelist;;

		require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
		bws_include_init( plugin_basename( __FILE__ ) );

		if ( empty( $gglcptch_plugin_info ) ) {
			if ( ! function_exists( 'get_plugin_data' ) )
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$gglcptch_plugin_info = get_plugin_data( __FILE__ );
		}

		/* Function check if plugin is compatible with current WP version */
		bws_wp_min_version_check( plugin_basename( __FILE__ ), $gglcptch_plugin_info, '3.8' );

		$is_admin = is_admin() && ! defined( 'DOING_AJAX' );
		/* Call register settings function */
		if ( ! $is_admin || ( isset( $_GET['page'] ) && 'google-captcha.php' == $_GET['page'] ) )
			register_gglcptch_settings();

		if ( empty( $gglcptch_ip_in_whitelist ) )
			$gglcptch_ip_in_whitelist = gglcptch_whitelisted_ip();

		/* Add hooks */
		if ( ! $is_admin ) {
			/* Add hooks */
			if ( '1' == $gglcptch_options['login_form'] || '1' == $gglcptch_options['reset_pwd_form'] || '1' == $gglcptch_options['registration_form'] ) {
				add_action( 'login_enqueue_scripts', 'gglcptch_add_styles' );

				if ( '1' == $gglcptch_options['login_form'] ) {
					add_action( 'login_form', 'gglcptch_login_display' );
					if ( ! $gglcptch_ip_in_whitelist )
						add_action( 'authenticate', 'gglcptch_login_check', 21, 1 );
				}

				if ( '1' == $gglcptch_options['reset_pwd_form'] ) {
					add_action( 'lostpassword_form', 'gglcptch_login_display' );
					if ( ! $gglcptch_ip_in_whitelist )
						add_action( 'allow_password_reset', 'gglcptch_lostpassword_check' );
				}

				if ( '1' == $gglcptch_options['registration_form'] ) {
					if ( ! is_multisite() ) {
						add_action( 'register_form', 'gglcptch_login_display', 99 );
						if ( ! $gglcptch_ip_in_whitelist )
							add_action( 'registration_errors', 'gglcptch_lostpassword_check' );
					} else {
						add_action( 'signup_extra_fields', 'gglcptch_signup_display' );
						add_action( 'signup_blogform', 'gglcptch_signup_display' );
						if ( ! $gglcptch_ip_in_whitelist )
							add_filter( 'wpmu_validate_user_signup', 'gglcptch_signup_check' );
					}
				}
			}

			if ( '1' == $gglcptch_options['comments_form'] ) {
				add_action( 'comment_form_after_fields', 'gglcptch_commentform_display' );
				add_action( 'comment_form_logged_in_after', 'gglcptch_commentform_display' );
				if ( ! $gglcptch_ip_in_whitelist )
					add_action( 'pre_comment_on_post', 'gglcptch_commentform_check' );
			}

			if ( '1' == $gglcptch_options['contact_form'] ) {
				add_filter( 'cntctfrm_display_captcha', 'gglcptch_cf_display', 10, 2 );
				if ( ! $gglcptch_ip_in_whitelist )
					add_filter( 'cntctfrm_check_form', 'gglcptch_recaptcha_check' );
				/**
				 * this filters are necessary for compatibility
				 * with old Contact Form Pro by BestWebsoft versions
				 * @deprecated since 1.0.4
				 * @todo remove after 25.02.2017
				 */
				add_filter( 'cntctfrmpr_display_captcha', 'gglcptch_cf_display', 10, 2 );
				if ( ! $gglcptch_ip_in_whitelist )
					add_filter( 'cntctfrmpr_check_form', 'gglcptch_recaptcha_check' );
			}
		}
	}
}

/**
 * Activation plugin function
 */
if ( ! function_exists( 'gglcptch_plugin_activate' ) ) {
	function gglcptch_plugin_activate( $networkwide ) {
		global $wpdb;
		/* Activation function for network, check if it is a network activation - if so, run the activation function for each blog id */
		if ( is_multisite() ) {
			switch_to_blog( 1 );
			register_uninstall_hook( __FILE__, 'gglcptch_delete_options' );
			restore_current_blog();
		} else {
			register_uninstall_hook( __FILE__, 'gglcptch_delete_options' );
		}
	}
}

if ( ! function_exists( 'gglcptch_admin_init' ) ) {
	function gglcptch_admin_init() {
		global $bws_plugin_info, $gglcptch_plugin_info, $bws_shortcode_list;

		if ( empty( $bws_plugin_info ) )
			$bws_plugin_info = array( 'id' => '109', 'version' => $gglcptch_plugin_info["Version"] );

		/* add google captcha to global $bws_shortcode_list  */
		$bws_shortcode_list['gglcptch'] = array( 'name' => 'Google Captcha (reCAPTCHA)' );
	}
}

/* Add google captcha styles */
if ( ! function_exists( 'gglcptch_add_admin_script_styles' ) ) {
	function gglcptch_add_admin_script_styles() {
		if ( isset( $_REQUEST['page'] ) && 'google-captcha.php' == $_REQUEST['page'] ) {
			wp_enqueue_style( 'gglcptch_stylesheet', plugins_url( 'css/style.css', __FILE__ ) );
			wp_enqueue_script( 'gglcptch_admin_script', plugins_url( 'js/admin_script.js', __FILE__ ), array( 'jquery' ) );

			if ( isset( $_GET['action'] ) && 'custom_code' == $_GET['action'] )
				bws_plugins_include_codemirror();
		}
	}
}

/* Add google captcha admin styles for  test key  */
if ( ! function_exists( 'gglcptch_admin_footer' ) ) {
	function gglcptch_admin_footer() {
		if ( isset( $_REQUEST['page'] ) && 'google-captcha.php' == $_REQUEST['page'] ) {
			/* for gglcptch test key */
			global $gglcptch_options;
			if ( isset( $gglcptch_options['recaptcha_version'] ) && 'v2' == $gglcptch_options['recaptcha_version'] ) {
				$api_url = "https://www.google.com/recaptcha/api.js";
			} else {
				$api_url  = "https://www.google.com/recaptcha/api/js/recaptcha_ajax.js";
			}
			wp_register_script( 'gglcptch_api', $api_url, false, false, true );
			gglcptch_add_scripts();
		}
	}
}

/**
 * Remove dublicate scripts
 */
if ( ! function_exists( 'gglcptch_remove_dublicate_scripts' ) ) {
	function gglcptch_remove_dublicate_scripts() {
		global $wp_scripts;

		if ( ! is_object( $wp_scripts ) || empty( $wp_scripts ) )
			return false;

		foreach ( $wp_scripts->registered as $script_name => $args ) {
			if ( preg_match( "|google\.com/recaptcha/api\.js|", $args->src ) && 'gglcptch_api' != $script_name )
				/* remove a previously enqueued script */
				wp_dequeue_script( $script_name );
		}
	}
}

/**
 * Add google captcha styles
 */
if ( ! function_exists( 'gglcptch_add_styles' ) ) {
	function gglcptch_add_styles() {
		global $gglcptch_plugin_info, $gglcptch_options;
		wp_enqueue_style( 'gglcptch', plugins_url( 'css/gglcptch.css', __FILE__ ), false, $gglcptch_plugin_info["Version"] );

		if ( defined( 'BWS_ENQUEUE_ALL_SCRIPTS' ) && BWS_ENQUEUE_ALL_SCRIPTS ) {
			if ( ! wp_script_is( 'gglcptch_api', 'registered' ) ) {
				if ( isset( $gglcptch_options['recaptcha_version'] ) && 'v2' == $gglcptch_options['recaptcha_version'] ) {
					$api_url = "https://www.google.com/recaptcha/api.js";
				} else {
					$api_url  = "https://www.google.com/recaptcha/api/js/recaptcha_ajax.js";
				}
				wp_register_script( 'gglcptch_api', $api_url, false, false, true );
				add_action( 'wp_footer', 'gglcptch_add_scripts' );
				if (
					'1' == $gglcptch_options['login_form'] ||
					'1' == $gglcptch_options['reset_pwd_form'] ||
					'1' == $gglcptch_options['registration_form']
				)
					add_action( 'login_footer', 'gglcptch_add_scripts' );
			}
		}
	}
}

/**
 * Add google captcha js scripts
 */
if ( ! function_exists( 'gglcptch_add_scripts' ) ) {
	function gglcptch_add_scripts() {
		global $gglcptch_options;

		if ( empty( $gglcptch_options ) )
			register_gglcptch_settings();

		if ( isset( $gglcptch_options['recaptcha_version'] ) && 'v2' == $gglcptch_options['recaptcha_version'] )
			gglcptch_remove_dublicate_scripts();

		wp_enqueue_script( 'gglcptch_script', plugins_url( 'js/script.js', __FILE__ ), array( 'jquery', 'gglcptch_api' ), false, true );

		$version = $gglcptch_options['recaptcha_version'] == 'v2' ? '_v2' : '';

		wp_localize_script( 'gglcptch_script', 'gglcptch', array(
			'options' => array(
				'version' => $gglcptch_options['recaptcha_version'],
				'sitekey' => $gglcptch_options['public_key'],
				'theme'   => $gglcptch_options[ 'theme' . $version ],
				'error'   => "<strong>" . __( 'Warning', 'google-captcha' ) . ":</strong>&nbsp;" . __( 'It has been found more than one reCAPTCHA in current form. In this case reCAPTCHA will not work properly. Please remove all unnecessary reCAPTCHA blocks.', 'google-captcha' )
			),
			'vars' => array(
				'ajaxurl'   	=> admin_url( 'admin-ajax.php' ),
				'error_msg' 	=> __( 'Error: You have entered an incorrect reCAPTCHA value', 'google-captcha' ),
				'nonce'     	=> wp_create_nonce( 'gglcptch_recaptcha_nonce' ),
				'visibility'	=> ( 'login_footer' == current_filter() ) ? true : false
			)
		) );
	}
}

if ( ! function_exists( 'gglcptch_pagination_callback' ) ) {
	function gglcptch_pagination_callback( $content ) {
		$content .= "if ( typeof gglcptch !== 'undefined' ) { gglcptch.prepare(); }";
		return $content;
	}
}

/**
 * Add the "async" attribute to our registered script.
 */
if ( ! function_exists( 'gglcptch_add_async_attribute' ) ) {
	function gglcptch_add_async_attribute( $tag, $handle ) {
		if ( 'gglcptch_api' == $handle )
			$tag = str_replace( ' src', ' data-cfasync="false" async="async" defer="defer" src', $tag );
		return $tag;
	}
}

if ( ! function_exists( 'gglcptch_create_table' ) ) {
	function gglcptch_create_table() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$whitelist_exist = $wpdb->query( "SHOW TABLES LIKE '{$wpdb->prefix}gglcptch_whitelist';" );
		if ( ! $whitelist_exist ) {
			$sql = "CREATE TABLE `{$wpdb->prefix}gglcptch_whitelist` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`ip` CHAR(31) NOT NULL,
				`ip_from_int` BIGINT,
				`ip_to_int` BIGINT,
				`add_time` DATETIME,
				PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
			dbDelta( $sql );

			/* add unique key */
			if ( 0 == $wpdb->query( "SHOW KEYS FROM `{$wpdb->prefix}gglcptch_whitelist` WHERE Key_name='ip'" ) )
				$wpdb->query( "ALTER TABLE `{$wpdb->prefix}gglcptch_whitelist` ADD UNIQUE(`ip`);" );
		}
	}
}

/* Google catpcha settings */
if ( ! function_exists( 'register_gglcptch_settings' ) ) {
	function register_gglcptch_settings() {
		global $gglcptch_options, $bws_plugin_info, $gglcptch_plugin_info;

		$plugin_db_version = '0.1';

		/* Install the option defaults */
		if ( ! get_option( 'gglcptch_options' ) )
			add_option( 'gglcptch_options', gglcptch_get_default_options() );
		/* Get options from the database */
		$gglcptch_options = get_option( 'gglcptch_options' );

		/* Array merge incase this version has added new options */
		if ( ! isset( $gglcptch_options['plugin_option_version'] ) || $gglcptch_options['plugin_option_version'] != $gglcptch_plugin_info["Version"] ) {
			$gglcptch_options = array_merge( gglcptch_get_default_options(), $gglcptch_options );
			$gglcptch_options['plugin_option_version'] = $gglcptch_plugin_info["Version"];
			/* show pro features */
			$gglcptch_options['hide_premium_options'] = array();

			if ( is_multisite() ) {
				switch_to_blog( 1 );
				register_uninstall_hook( __FILE__, 'gglcptch_delete_options' );
				restore_current_blog();
			} else {
				register_uninstall_hook( __FILE__, 'gglcptch_delete_options' );
			}
			update_option( 'gglcptch_options', $gglcptch_options );
		}
		/* Update tables when update plugin and tables changes*/
		if (
			! isset( $gglcptch_options['plugin_db_version'] ) ||
			( isset( $gglcptch_options['plugin_db_version'] ) && $gglcptch_options['plugin_db_version'] != $plugin_db_version )
		) {
			if ( ! isset( $gglcptch_options['plugin_db_version'] ) ) {
				gglcptch_create_table();
			}
			$gglcptch_options['plugin_db_version'] = $plugin_db_version;
			update_option( 'gglcptch_options', $gglcptch_options );
		}
	}
}

if ( ! function_exists( 'gglcptch_get_default_options' ) ) {
	function gglcptch_get_default_options() {
		global $gglcptch_plugin_info;

		$default_options = array(
			'whitelist_message'			=> __( 'You are in the whitelist', 'google-captcha' ),
			'public_key'				=> '',
			'private_key'				=> '',
			'login_form'				=> '1',
			'registration_form'			=> '1',
			'reset_pwd_form'			=> '1',
			'comments_form'				=> '1',
			'contact_form'				=> '0',
			'theme'						=> 'red',
			'theme_v2'					=> 'light',
			'recaptcha_version'			=> 'v2',
			'plugin_option_version'		=> $gglcptch_plugin_info["Version"],
			'first_install'				=>	strtotime( "now" ),
			'display_settings_notice'	=> 1,
			'suggest_feature_banner'	=> 1,
		);

		if ( function_exists( 'get_editable_roles' ) ) {
			foreach ( get_editable_roles() as $role => $fields ) {
				$default_options[ $role ] = '0';
			}
		}
		return $default_options;
	}
}

if ( ! function_exists( 'gglcptch_plugin_status' ) ) {
	function gglcptch_plugin_status( $plugins, $all_plugins, $is_network ) {
		$result = array(
			'status'      => '',
			'plugin'      => '',
			'plugin_info' => array(),
		);
		foreach ( (array)$plugins as $plugin ) {
			if ( array_key_exists( $plugin, $all_plugins ) ) {
				if (
					( $is_network && is_plugin_active_for_network( $plugin ) ) ||
					( ! $is_network && is_plugin_active( $plugin ) )
				) {
					$result['status']      = 'actived';
					$result['plugin']      = $plugin;
					$result['plugin_info'] = $all_plugins[$plugin];
					break;
				} else {
					$result['status']      = 'deactivated';
					$result['plugin']      = $plugin;
					$result['plugin_info'] = $all_plugins[$plugin];
				}

			}
		}
		if ( empty( $result['status'] ) )
			$result['status'] = 'not_installed';
		return $result;
	}
}

if ( ! function_exists( 'gglcptch_whitelisted_ip' ) ) {
	function gglcptch_whitelisted_ip() {
		global $wpdb, $gglcptch_options;
		$checked = false;
		if ( empty( $gglcptch_options ) )
			$gglcptch_options = get_option( 'gglcptch_options' );
		$whitelist_exist = $wpdb->query( "SHOW TABLES LIKE '{$wpdb->prefix}gglcptch_whitelist'" );
		if ( 1 === $whitelist_exist ) {
			$ip = '';
			if ( isset( $_SERVER ) ) {
				$server_vars = array( 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
				foreach( $server_vars as $var ) {
					if ( isset( $_SERVER[ $var ] ) && ! empty( $_SERVER[ $var ] ) ) {
						if ( filter_var( $_SERVER[ $var ], FILTER_VALIDATE_IP ) ) {
							$ip = $_SERVER[ $var ];
							break;
						} else { /* if proxy */
							$ip_array = explode( ',', $_SERVER[ $var ] );
							if ( is_array( $ip_array ) && ! empty( $ip_array ) && filter_var( $ip_array[0], FILTER_VALIDATE_IP ) ) {
								$ip = $ip_array[0];
								break;
							}
						}
					}
				}
			}

			if ( ! empty( $ip ) ) {
				$ip_int = sprintf( '%u', ip2long( $ip ) );
				$result = $wpdb->get_var(
					"SELECT `id`
					FROM `{$wpdb->prefix}gglcptch_whitelist`
					WHERE ( `ip_from_int` <= {$ip_int} AND `ip_to_int` >= {$ip_int} ) OR `ip` LIKE '{$ip}' LIMIT 1;"
				);
				$checked = is_null( $result ) || ! $result ? false : true;
			} else {
				$checked = false;
			}
		}
		return $checked;
	}
}

/* Display settings page */
if ( ! function_exists( 'gglcptch_add_settings_page' ) ) {
	function gglcptch_add_settings_page() {
		global $gglcptch_options, $gglcptch_plugin_info, $wp_version;
		require_once( dirname( __FILE__ ) . '/includes/pro_banners.php' );

		$plugin_basename = plugin_basename( __FILE__ );
		$message = $error = '';

		$all_plugins      = get_plugins();
		$is_network       = is_multisite() && is_network_admin();
		$is_main_site     = is_main_site( get_current_blog_id() );
		$admin_url        = $is_network ? network_admin_url( '/' ) : admin_url( '/' );
		$bws_contact_form = gglcptch_plugin_status( array( 'contact-form-plugin/contact_form.php', 'contact-form-pro/contact_form_pro.php' ), $all_plugins, $is_network );

		if ( isset( $_POST['bws_hide_premium_options'] ) && check_admin_referer( $plugin_basename, 'gglcptch_nonce_name' ) ) {
			$result        = bws_hide_premium_options( $gglcptch_options );
			$gglcptch_options = $result['options'];
			update_option( 'gglcptch_options', $gglcptch_options );
		}
		if ( ! isset( $_GET['action'] ) ) {

			$all_plugins = get_plugins();

			/* Private and public keys */
			$gglcptch_keys = array(
				'public' => array(
					'display_name'	=>	__( 'Site key', 'google-captcha' ),
					'form_name'		=>	'gglcptch_public_key',
					'error_msg'		=>	'',
				),
				'private' => array(
					'display_name'	=>	__( 'Secret Key', 'google-captcha' ),
					'form_name'		=>	'gglcptch_private_key',
					'error_msg'		=>	'',
				),
			);

			/* Checked forms */
			$gglcptch_forms = array(
				array( 'login_form', __( 'Login form', 'google-captcha' ) ),
				array( 'registration_form', __( 'Registration form', 'google-captcha' ) ),
				array( 'reset_pwd_form', __( 'Reset password form', 'google-captcha' ) ),
				array( 'comments_form', __( 'Comments form', 'google-captcha' ) ),
			);

			/* Google captcha themes */
			$gglcptch_themes = array(
				array( 'red', 'Red' ),
				array( 'white', 'White' ),
				array( 'blackglass', 'Blackglass' ),
				array( 'clean', 'Clean' ),
			);

			/* Save data for settings page */
			if ( isset( $_POST['gglcptch_form_submit'] ) && check_admin_referer( $plugin_basename, 'gglcptch_nonce_name' ) ) {
				if ( isset( $_POST['bws_hide_premium_options'] ) ) {
					$hide_result = bws_hide_premium_options( $gglcptch_options );
					$gglcptch_options = $hide_result['options'];
				}

				if ( ! $_POST['gglcptch_public_key'] || '' == $_POST['gglcptch_public_key'] ) {
					$gglcptch_keys['public']['error_msg'] = __( 'Enter site key', 'google-captcha' );
					$error = __( "WARNING: The captcha will not display while you don't fill key fields.", 'google-captcha' );
				} else
					$gglcptch_keys['public']['error_msg'] = '';

				if ( ! $_POST['gglcptch_private_key'] || '' == $_POST['gglcptch_private_key'] ) {
					$gglcptch_keys['private']['error_msg'] = __( 'Enter secret key', 'google-captcha' );
					$error = __( "WARNING: The captcha will not display while you don't fill key fields.", 'google-captcha' );
				} else
					$gglcptch_keys['private']['error_msg'] = '';

				if ( $_POST['gglcptch_public_key'] != $gglcptch_options['public_key'] || $_POST['gglcptch_private_key'] != $gglcptch_options['private_key'] )
					$gglcptch_options['keys_verified'] = false;

				$gglcptch_options['whitelist_message']	=	stripslashes( esc_html( $_POST['gglcptch_whitelist_message'] ) );
				$gglcptch_options['public_key']			=	trim( stripslashes( esc_html( $_POST['gglcptch_public_key'] ) ) );
				$gglcptch_options['private_key']		=	trim( stripslashes( esc_html( $_POST['gglcptch_private_key'] ) ) );
				$gglcptch_options['login_form']			=	isset( $_POST['gglcptch_login_form'] ) ? 1 : 0;
				$gglcptch_options['registration_form']	=	isset( $_POST['gglcptch_registration_form'] ) ? 1 : 0;
				$gglcptch_options['reset_pwd_form']		=	isset( $_POST['gglcptch_reset_pwd_form'] ) ? 1 : 0;
				$gglcptch_options['comments_form']		=	isset( $_POST['gglcptch_comments_form'] ) ? 1 : 0;
				$gglcptch_options['contact_form']		=	isset( $_POST['gglcptch_contact_form'] ) ? 1 : 0;
				$gglcptch_options['recaptcha_version']	=	'v1' == $_POST['gglcptch_recaptcha_version'] ? 'v1' : 'v2';
				$gglcptch_options['theme']				=	stripslashes( esc_html( $_POST['gglcptch_theme'] ) );
				$gglcptch_options['theme_v2']			=	stripslashes( esc_html( $_POST['gglcptch_theme_v2'] ) );

				if ( function_exists( 'get_editable_roles' ) ) {
					foreach ( get_editable_roles() as $role => $fields ) {
						$gglcptch_options[ $role ] = isset( $_POST[ 'gglcptch_' . $role ] ) ? 1 : 0;
					}
				}

				update_option( 'gglcptch_options', $gglcptch_options );
				$message = __( 'Settings saved', 'google-captcha' );
			}

			if ( isset( $_REQUEST['bws_restore_confirm'] ) && check_admin_referer( $plugin_basename, 'bws_settings_nonce_name' ) ) {
				$gglcptch_options = gglcptch_get_default_options();
				update_option( 'gglcptch_options', $gglcptch_options );
				$message = __( 'All plugin settings were restored', 'google-captcha' );
			}
		}

		$bws_hide_premium_options_check = bws_hide_premium_options_check( $gglcptch_options );

		/* GO PRO */
		if ( isset( $_GET['action'] ) && 'go_pro' == $_GET['action'] ) {
			$go_pro_result = bws_go_pro_tab_check( $plugin_basename, 'gglcptch_options' );
			if ( ! empty( $go_pro_result['error'] ) )
				$error = $go_pro_result['error'];
			elseif ( ! empty( $go_pro_result['message'] ) )
				$message = $go_pro_result['message'];
		} ?>
		<div class="wrap gglcptch_settings_page">
			<h1 style="line-height: normal;"><?php _e( 'Google Captcha Settings', 'google-captcha' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab<?php if ( ! isset( $_GET['action'] ) ) echo ' nav-tab-active'; ?>" href="admin.php?page=google-captcha.php"><?php _e( 'Settings', 'google-captcha' ); ?></a>
				<a class="nav-tab<?php if ( isset( $_GET['action'] ) && 'whitelist' == $_GET['action'] ) echo ' nav-tab-active'; ?>" href="admin.php?page=google-captcha.php&amp;action=whitelist"><?php _e( 'Whitelist', 'google-captcha' ); ?></a>
				<a class="nav-tab <?php if ( isset( $_GET['action'] ) && 'custom_code' == $_GET['action'] ) echo ' nav-tab-active'; ?>" href="admin.php?page=google-captcha.php&amp;action=custom_code"><?php _e( 'Custom code', 'google-captcha' ); ?></a>
				<a class="nav-tab<?php if ( isset( $_GET['action'] ) && 'go_pro' == $_GET['action'] ) echo ' nav-tab-active'; ?> bws_go_pro_tab" href="admin.php?page=google-captcha.php&amp;action=go_pro"><?php _e( 'Go PRO', 'google-captcha' ); ?></a>
			</h2>
			<?php if ( ! isset( $_GET['action'] ) && ! isset( $_REQUEST['bws_restore_default'] ) ) {
				if ( $gglcptch_options['recaptcha_version'] == 'v1' ) {
					printf( '<div id="gglcptch_v1_notice" class="updated inline"><p><strong>%s</strong></p></div>',
						__( "Only one reCAPTCHA can be displayed on the page, it's related to reCAPTCHA version 1 features.", 'google-captcha' )
					);
				}
			}
			bws_show_settings_notice(); ?>
			<div class="updated fade inline" <?php if ( "" == $message ) echo 'style="display:none"'; ?>><p><strong><?php echo $message; ?></strong></p></div>
			<div class="error inline" <?php if ( "" == $error ) echo 'style="display:none"'; ?>><p><strong><?php echo $error; ?></strong></p></div>
			<?php if ( ! empty( $hide_result['message'] ) ) { ?>
				<div class="updated fade inline"><p><strong><?php echo $hide_result['message']; ?></strong></p></div>
			<?php }
			if ( ! isset( $_GET['action'] ) ) {
				if ( isset( $_REQUEST['bws_restore_default'] ) && check_admin_referer( $plugin_basename, 'bws_settings_nonce_name' ) ) {
					bws_form_restore_default_confirm( $plugin_basename );
				} else { ?>
					<div style="margin: 20px 0;">
						<?php printf( __( "If you would like to add a Google Captcha (reCAPTCHA) to your page or post, please use %s button", 'google-captcha' ),
							'<span class="bws_code"><img style="vertical-align: sub;" src="' . plugins_url( 'bws_menu/images/shortcode-icon.png', __FILE__ ) . '" alt=""/></span>'
						); ?>
						<div class="bws_help_box bws_help_box_right dashicons dashicons-editor-help" style="vertical-align: middle;">
							<div class="bws_hidden_help_text" style="min-width: 260px;">
								<?php printf(
									__( "You can add the Google Captcha (reCAPTCHA) to your page or post by clicking on %s button in the content edit block using the Visual mode. If the button isn't displayed or you would like to add the Google Captcha (reCAPTCHA) to your own form , please use the shortcode %s", 'google-captcha' ),
									'<code><img style="vertical-align: sub;" src="' . plugins_url( 'bws_menu/images/shortcode-icon.png', __FILE__ ) . '" alt="" /></code>',
									sprintf( '<span class="bws_code">[bws_google_captcha]</span><br/>' )
								); ?>
							</div>
						</div>
					</div>
					<form id="gglcptch_admin_settings_page" class="bws_form" method="post" action="admin.php?page=google-captcha.php">
						<h3><?php _e( 'Authentication', 'google-captcha' ); ?></h3>
						<p><?php printf( __( 'Before you are able to do something, you must to register %shere%s', 'google-captcha' ), '<a target="_blank" href="https://www.google.com/recaptcha/admin#list">','</a>.' ); ?></p>
						<p><?php _e( 'Enter site key and secret key, that you get after registration', 'google-captcha' ); ?></p>
						<table id="gglcptch-keys" class="form-table">
							<?php foreach ( $gglcptch_keys as $key => $fields ) { ?>
								<tr valign="top">
									<th scope="row"><?php echo $fields['display_name']; ?></th>
									<td>
										<input type="text" name="<?php echo $fields['form_name']; ?>" value="<?php echo $gglcptch_options[ $key . '_key' ] ?>" maxlength="200" />
										<label class="gglcptch_error_msg error"><?php echo $fields['error_msg']; ?></label>
										<span class="dashicons dashicons-yes gglcptch_verified <?php if ( ! isset( $gglcptch_options['keys_verified'] ) || true !== $gglcptch_options['keys_verified'] ) echo 'hidden'; ?>"></span>
									</td>
								</tr>
							<?php } ?>
						</table>
						<?php if ( ! empty( $gglcptch_options['public_key'] ) && ! empty( $gglcptch_options['private_key'] ) ) { ?>
							<p id="gglcptch-test-keys" class="submit hide-if-no-js">
								<a class="button button-secondary" href="<?php echo add_query_arg( array( '_wpnonce' => wp_create_nonce( 'gglcptch-test-keys' ) , 'action' => 'gglcptch-test-keys' ), admin_url( 'admin-ajax.php' ) ); ?>"><?php _e( 'Test Keys' , 'google-captcha' ); ?></a>
							</p>
						<?php } ?>
						<h3><?php _e( 'Options', 'google-captcha' ); ?></h3>
						<table class="form-table">
							<tr valign="top">
								<th scope="row"><?php _e( 'Enable reCAPTCHA for', 'google-captcha' ); ?></th>
								<td>
									<fieldset>
										<p>
											<i><?php _e( 'WordPress default', 'google-captcha' ); ?></i>
										</p>
										<?php foreach ( $gglcptch_forms as $form ) {
											$gglcptch_form_type = $form[0];
											$gglcptch_form_name = $form[1];
											$gglcptch_form_attr = ( '1' == $gglcptch_options[ $gglcptch_form_type ] ) ? 'checked="checked"' : '';
											$gglcptch_form_notice = '';

											if ( ( $gglcptch_form_type == 'registration_form' || $gglcptch_form_type == 'reset_pwd_form' ) && ! $is_main_site ) {
												$gglcptch_form_notice .= sprintf( '<span class="bws_info">%s</span>', __( 'This option is available only for network or for main blog', 'google-captcha' ) );
												$gglcptch_form_attr = 'disabled="disabled" readonly="readonly"';
											} ?>
											<label><input type="checkbox" name="<?php echo 'gglcptch_' . $gglcptch_form_type; ?>" value="<?php echo $gglcptch_form_type; ?>" <?php echo $gglcptch_form_attr; ?> /> <?php echo $gglcptch_form_name; ?></label>
											<div class="bws_help_box dashicons dashicons-editor-help" style="vertical-align: middle;"><div class="bws_hidden_help_text"><img src="<?php echo plugins_url( 'google-captcha/images') . '/' . $gglcptch_form_type; ?>.jpg" title="<?php echo $gglcptch_form_name; ?>" alt="<?php echo $gglcptch_form_name; ?>"></div></div> <?php echo $gglcptch_form_notice; ?><br />
										<?php } ?>
										<br />
										<p>
											<i><?php _e( 'Plugins', 'google-captcha' ); ?></i>
										</p>
										<?php /* Check Contact Form by BestWebSoft */
										$gglcptch_plugin = $bws_contact_form;
										$gglcptch_plugin_name = 'Contact Form by BestWebSoft';
										$gglcptch_attrs = $gglcptch_plugin_notice = '';
										if ( 'deactivated' == $gglcptch_plugin['status'] ) {
											$gglcptch_attrs = 'disabled="disabled"';
											$gglcptch_plugin_notice = sprintf( __( 'You should %s to use this functionality', 'google-captcha' ),
												sprintf( '<a href="%splugins.php">%s%s %s</a>', $admin_url, __( 'activate', 'google-captcha' ), ( is_network_admin() ? ' ' . __( 'for network', 'google-captcha' ) : '' ), $gglcptch_plugin_name )
											);
										} elseif ( 'not_installed' == $gglcptch_plugin['status'] ) {
											$gglcptch_attrs = 'disabled="disabled"';
											$gglcptch_plugin_notice = sprintf( __( 'You should %s to use this functionality', 'google-captcha' ),
												sprintf( '<a href="http://bestwebsoft.com/products/wordpress/plugins/contact-form/?k=0a750deb99a8e5296a5432f4c9cb9b55&pn=75&v=%s&wp_v=%s">%s %s</a>', $gglcptch_plugin_info["Version"], $wp_version, __( 'download', 'google-captcha' ), $gglcptch_plugin_name )
											);
										}
										if ( $gglcptch_attrs == '' && ( is_plugin_active( 'contact-form-multi-pro/contact-form-multi-pro.php' ) || is_plugin_active( 'contact-form-multi/contact-form-multi.php' ) ) )
											$gglcptch_plugin_notice = ' (' . __( 'Check off for adding captcha to forms on their settings pages', 'google-captcha' ) . ')';

										if ( '1' == $gglcptch_options['contact_form'] && $gglcptch_attrs == '' ) {
											$gglcptch_attrs .= ' checked="checked"';
										} ?>
										<label><input type="checkbox" <?php echo $gglcptch_attrs; ?> name="gglcptch_contact_form" value="contact_form" /> <?php echo $gglcptch_plugin_name; ?></label>
										<div class="bws_help_box dashicons dashicons-editor-help" style="vertical-align: middle;"><div class="bws_hidden_help_text"><img src="<?php echo plugins_url( 'google-captcha/images'); ?>/contact_form.jpg" title="<?php echo $gglcptch_plugin_name; ?>" alt="<?php echo $gglcptch_plugin_name; ?>"></div></div>
										<span class="bws_info"><?php echo $gglcptch_plugin_notice; ?></span><br />
										<?php gglcptch_pro_block( 'gglcptch_supported_plugins_banner' ); ?>
										<span class="bws_info"><?php printf( __( 'If you would like to add Google Captcha (reCAPTCHA) to a custom form see %s', 'google-captcha' ), sprintf( '<a href="http://bestwebsoft.com/products/wordpress/plugins/google-captcha/faq/" target="_blank">%s</a>', __( 'FAQ', 'google-captcha' ) ) ); ?></span>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'Hide reCAPTCHA in Comments form for', 'google-captcha' ); ?></th>
								<td>
									<fieldset>
										<?php if ( function_exists( 'get_editable_roles' ) ) {
											foreach ( get_editable_roles() as $role => $fields) : ?>
												<label><input type="checkbox" name="<?php echo 'gglcptch_' . $role; ?>" value=<?php echo $role; if ( isset( $gglcptch_options[ $role ] ) && '1' == $gglcptch_options[ $role ] ) echo ' checked'; ?>> <?php echo $fields['name']; ?></label><br/>
											<?php endforeach;
										} ?>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'Whitelist notification', 'google-captcha' ); ?></th>
								<td>
									<label>
										<input type="text" id="gglcptch_whitelist_message" name="gglcptch_whitelist_message" value="<?php echo $gglcptch_options['whitelist_message']; ?>">
										<div class="bws_help_box dashicons dashicons-editor-help" style="vertical-align: middle;">
											<div class="bws_hidden_help_text" style="min-width: 260px;">
												<?php _e( 'This message will be displayed instead of the reCAPTCHA If the user IP is added to the whitelist', 'google-captcha' ); ?>
											</div>
										</div><div class="clear"></div>
									</label>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'reCAPTCHA version', 'google-captcha' ); ?></th>
								<td>
									<fieldset>
										<label><input type="radio" name="gglcptch_recaptcha_version" value="v1"<?php if ( 'v1' == $gglcptch_options['recaptcha_version'] ) echo ' checked="checked"'; ?>> <?php _e( 'version', 'google-captcha' ); ?> 1</label>
										<div class="bws_help_box dashicons dashicons-editor-help" style="vertical-align: middle;"><div class="bws_hidden_help_text"><img src="<?php echo plugins_url( 'google-captcha/images'); ?>/recaptcha_v1.png" title="reCAPTCHA <?php _e( 'version', 'google-captcha' ); ?> 1" alt="reCAPTCHA <?php _e( 'version', 'google-captcha' ); ?> 1"></div></div><br/>
										<label><input type="radio" name="gglcptch_recaptcha_version" value="v2"<?php if ( 'v2' == $gglcptch_options['recaptcha_version'] ) echo ' checked="checked"'; ?>> <?php _e( 'version', 'google-captcha' ); ?> 2</label>
										<div class="bws_help_box dashicons dashicons-editor-help" style="vertical-align: middle;"><div class="bws_hidden_help_text"><img src="<?php echo plugins_url( 'google-captcha/images'); ?>/recaptcha_v2.png" title="reCAPTCHA <?php _e( 'version', 'google-captcha' ); ?> 2" alt="reCAPTCHA <?php _e( 'version', 'google-captcha' ); ?> 2"></div></div>
									</fieldset>
								</td>
							</tr>
							<tr class="gglcptch_theme_v1" valign="top">
								<th scope="row">
									<?php _e( 'reCAPTCHA theme', 'google-captcha' ); ?>
									<br/><span class="bws_info">(<?php _e( 'for version', 'google-captcha' ); ?> 1)</span>
								</th>
								<td>
									<select name="gglcptch_theme">
										<?php foreach ( $gglcptch_themes as $theme ) : ?>
											<option value=<?php echo $theme[0]; if ( $theme[0] == $gglcptch_options['theme'] ) echo ' selected'; ?>> <?php echo $theme[1]; ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr class="gglcptch_theme_v2" valign="top">
								<th scope="row">
									<?php _e( 'reCAPTCHA theme', 'google-captcha' ); ?>
									<br/><span class="bws_info">(<?php _e( 'for version', 'google-captcha' ); ?> 2)</span>
								</th>
								<td>
									<select name="gglcptch_theme_v2">
										<option value="light" <?php if ( 'light' == $gglcptch_options['theme_v2'] ) echo ' selected'; ?>>light</option>
										<option value="dark" <?php if ( 'dark' == $gglcptch_options['theme_v2'] ) echo ' selected'; ?>>dark</option>
									</select>
								</td>
							</tr>
						</table>
						<?php gglcptch_pro_block( 'gglcptch_additional_settings_banner' ); ?>
						<p class="submit">
							<input id="bws-submit-button" type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'google-captcha' ); ?>" name="gglcptch_save_changes" />
							<input type="hidden" name="gglcptch_form_submit" value="submit" />
							<?php wp_nonce_field( $plugin_basename, 'gglcptch_nonce_name' ); ?>
						</p>
					</form>
					<?php bws_form_restore_default_settings( $plugin_basename );
				}
			} elseif ( 'whitelist' == $_GET['action'] ) {
				require_once( dirname( __FILE__ ) . '/includes/whitelist.php' );
				$page = new Gglcptch_Whitelist( $plugin_basename );
				if ( is_object( $page ) )
					$page->display_content();
			} elseif ( 'custom_code' == $_GET['action'] ) {
				bws_custom_code_tab();
			} elseif ( 'go_pro' == $_GET['action'] ) {
				bws_go_pro_tab_show( $bws_hide_premium_options_check, $gglcptch_plugin_info, $plugin_basename, 'google-captcha.php', 'google-captcha-pro.php', 'google-captcha-pro/google-captcha-pro.php', 'google-captcha', 'b850d949ccc1239cab0da315c3c822ab', '109', isset( $go_pro_result['pro_plugin_is_activated'] ) );
			}
			bws_plugin_reviews_block( $gglcptch_plugin_info['Name'], 'google-captcha' ); ?>
		</div>
	<?php }
}

/* Checking current user role */
if ( ! function_exists( 'gglcptch_check_role' ) ) {
	function gglcptch_check_role() {
		global $current_user, $gglcptch_options;

		if ( ! is_user_logged_in() )
			return false;

		if ( ! empty( $current_user->roles[0] ) ) {
			$role = $current_user->roles[0];
			if ( empty( $gglcptch_options ) )
				register_gglcptch_settings();
			return isset( $gglcptch_options[ $role ] ) && '1' == $gglcptch_options[ $role ] ? true : false;
		} else
			return false;
	}
}

/* Display google captcha via shortcode */
if ( ! function_exists( 'gglcptch_display' ) ) {
	function gglcptch_display( $content = false ) {
		global $gglcptch_options, $gglcptch_count, $gglcptch_ip_in_whitelist;

		if ( empty( $gglcptch_options ) )
			register_gglcptch_settings();

		if ( empty( $gglcptch_ip_in_whitelist ) )
			$gglcptch_ip_in_whitelist = gglcptch_whitelisted_ip();

		if ( ! $gglcptch_ip_in_whitelist ) {

			if ( ! $gglcptch_count )
				$gglcptch_count = 1;

			$publickey  = $gglcptch_options['public_key'];
			$privatekey = $gglcptch_options['private_key'];

			$content .= '<div class="gglcptch gglcptch_' . $gglcptch_options['recaptcha_version'] . '">';
			if ( ! $privatekey || ! $publickey ) {
				if ( current_user_can( 'manage_options' ) ) {
					$content .= sprintf(
						'<strong>%s <a target="_blank" href="https://www.google.com/recaptcha/admin#list">%s</a> %s <a target="_blank" href="%s">%s</a>.</strong>',
						__( 'To use Google Captcha you must get the keys from', 'google-captcha' ),
						__( 'here', 'google-captcha' ),
						__( 'and enter them on the', 'google-captcha' ),
						admin_url( '/admin.php?page=google-captcha.php' ),
						__( 'plugin setting page', 'google-captcha' )
					);
				}
				$content .= '</div>';
				$gglcptch_count++;
				return $content;
			}

			/* generating random id value in case of getting content with pagination plugin for not getting duplicate id values */
			$id = mt_rand();
			if ( isset( $gglcptch_options['recaptcha_version'] ) && 'v2' == $gglcptch_options['recaptcha_version'] ) {
				$content .= '<div id="gglcptch_recaptcha_' . $id . '" class="gglcptch_recaptcha"></div>
				<noscript>
					<div style="width: 302px;">
						<div style="width: 302px; height: 422px; position: relative;">
							<div style="width: 302px; height: 422px; position: absolute;">
								<iframe src="https://www.google.com/recaptcha/api/fallback?k=' . $publickey . '" frameborder="0" scrolling="no" style="width: 302px; height:422px; border-style: none;"></iframe>
							</div>
						</div>
						<div style="border-style: none; bottom: 12px; left: 25px; margin: 0px; padding: 0px; right: 25px; background: #f9f9f9; border: 1px solid #c1c1c1; border-radius: 3px; height: 60px; width: 300px;">
							<textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px !important; height: 40px !important; border: 1px solid #c1c1c1 !important; margin: 10px 25px !important; padding: 0px !important; resize: none !important;"></textarea>
						</div>
					</div>
				</noscript>';
				$api_url = "https://www.google.com/recaptcha/api.js";
			} else {
				require_once( 'lib/recaptchalib.php' );
				$content .= '<div id="gglcptch_recaptcha_' . $id . '" class="gglcptch_recaptcha"></div>';
				$content .= gglcptch_recaptcha_get_html( $publickey, null, is_ssl() );
				$api_url  = "https://www.google.com/recaptcha/api/js/recaptcha_ajax.js";
			}
			$content .= '</div>';
			$gglcptch_count++;

			/* register reCAPTCHA script */
			if ( ! wp_script_is( 'gglcptch_api', 'registered' ) ) {
				wp_register_script( 'gglcptch_api', $api_url, false, false, true );
				add_action( 'wp_footer', 'gglcptch_add_scripts' );
				if (
					'1' == $gglcptch_options['login_form'] ||
					'1' == $gglcptch_options['reset_pwd_form'] ||
					'1' == $gglcptch_options['registration_form']
				)
					add_action( 'login_footer', 'gglcptch_add_scripts' );
			}
		} else {
			if ( ! empty( $gglcptch_options['whitelist_message'] ) )
				$content .= '<label class="gglcptch_whitelist_message">' . $gglcptch_options['whitelist_message'] . '</label>';
		}

		return $content;
	}
}

if ( ! function_exists( 'gglcptch_get_response' ) ) {
	function gglcptch_get_response( $privatekey, $remote_ip ) {
		$args = array(
			'body' => array(
				'secret'   => $privatekey,
				'response' => stripslashes( esc_html( $_POST["g-recaptcha-response"] ) ),
				'remoteip' => $remote_ip,
			),
			'sslverify' => false
		);
		$resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', $args );
		return json_decode( wp_remote_retrieve_body( $resp ), true );
	}
}

/* Check google captcha */
if ( ! function_exists( 'gglcptch_check' ) ) {
	function gglcptch_check( $debug = false ) {
		global $gglcptch_options;

		if ( empty( $gglcptch_options ) )
			register_gglcptch_settings();

		$publickey	= $gglcptch_options['public_key'];
		$privatekey	= $gglcptch_options['private_key'];

		if ( ! $privatekey || ! $publickey ) {
			return array(
				'response' => false,
				'reason'   => 'ERROR_NO_KEYS'
			);
		}

		$gglcptch_remote_addr = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP );

		if ( isset( $gglcptch_options['recaptcha_version'] ) && 'v2' == $gglcptch_options['recaptcha_version'] ) {

			if ( ! isset( $_POST["g-recaptcha-response"] ) ) {
				return array(
					'response' => false,
					'reason'   => 'RECAPTCHA_NO_RESPONSE'
				);
			} elseif ( empty( $_POST["g-recaptcha-response"] ) ) {
				return array(
					'response' => false,
					'reason'   => 'RECAPTCHA_EMPTY_RESPONSE'
				);
			}

			$response = gglcptch_get_response( $privatekey, $gglcptch_remote_addr );

			if ( isset( $response['success'] ) && !! $response['success'] ) {
				return array(
					'response' => true,
					'reason' => ''
				);
			} else {
				return array(
					'response' => false,
					'reason' => $debug ? $response['error-codes'] : 'VERIFICATION_FAILED'
				);
			}
		} else {
			$gglcptch_recaptcha_challenge_field = $gglcptch_recaptcha_response_field = '';

			if ( ! isset( $_POST['recaptcha_challenge_field'] ) && ! isset( $_POST['recaptcha_response_field'] ) ) {
				return array(
					'response' => false,
					'reason'   => 'RECAPTCHA_NO_RESPONSE'
				);
			} elseif ( ! empty( $_POST['recaptcha_challenge_field'] ) && empty( $_POST['recaptcha_response_field'] ) ) {
				return array(
					'response' => false,
					'reason'   => 'RECAPTCHA_EMPTY_RESPONSE'
				);
			} else {
				$gglcptch_recaptcha_challenge_field = stripslashes( esc_html( $_POST['recaptcha_challenge_field'] ) );
				$gglcptch_recaptcha_response_field  = stripslashes( esc_html( $_POST['recaptcha_response_field'] ) );
			}

			require_once( 'lib/recaptchalib.php' );
			$response = gglcptch_recaptcha_check_answer( $privatekey, $gglcptch_remote_addr, $gglcptch_recaptcha_challenge_field, $gglcptch_recaptcha_response_field );

			if ( ! $response->is_valid ) {
				return array(
					'response' => false,
					'reason'   => $debug ? $response->error : 'VERIFICATION_FAILED'
				);
			} else {
				return array(
					'response' => true,
					'reason'   => ''
				);
			}
		}
	}
}

/* Add google captcha to the login form */
if ( ! function_exists( 'gglcptch_login_display' ) ) {
	function gglcptch_login_display() {
		global $gglcptch_options;
		if ( isset( $gglcptch_options['recaptcha_version'] ) && 'v2' == $gglcptch_options['recaptcha_version'] ) {
			$from_width = 302;
		} else {
			$from_width = 320;
			if ( 'clean' == $gglcptch_options['theme'] )
				$from_width = 450;
		} ?>
		<style type="text/css" media="screen">
			.login-action-login #loginform,
			.login-action-lostpassword #lostpasswordform,
			.login-action-register #registerform {
				width: <?php echo $from_width; ?>px !important;
			}
			#login_error,
			.message {
				width: <?php echo $from_width + 20; ?>px !important;
			}
			.login-action-login #loginform .gglcptch,
			.login-action-lostpassword #lostpasswordform .gglcptch,
			.login-action-register #registerform .gglcptch {
				margin-bottom: 10px;
			}
		</style>
		<?php echo gglcptch_display();
		return true;
	}
}

/* Check google captcha in login form */
if ( ! function_exists( 'gglcptch_login_check' ) ) {
	function gglcptch_login_check( $user ) {

		$result = gglcptch_check();

		if ( ! $result['response'] ) {
			if ( $result['reason'] == 'ERROR_NO_KEYS' ) {
				return $user;
			}

			$error_message = sprintf( '<strong>%s</strong>: %s', __( 'Error', 'google-captcha' ), __( 'You have entered an incorrect reCAPTCHA value', 'google-captcha' ) );

			if ( $result['reason'] == 'VERIFICATION_FAILED' ) {
				wp_clear_auth_cookie();
				return new WP_Error( 'gglcptch_error', $error_message );
			}

			if ( isset( $_REQUEST['log'] ) && isset( $_REQUEST['pwd'] ) ) {
				return new WP_Error( 'gglcptch_error', $error_message );
			} else {
				return $user;
			}
		} else {
			return $user;
		}
	}
}

/* Check google captcha in BWS Contact Form */
if ( ! function_exists( 'gglcptch_recaptcha_check' ) ) {
	function gglcptch_recaptcha_check( $allow = true ) {
		/**
		 * this condition is necessary for compatibility
		 * with Contact Form ( Free and Pro ) by BestWebsoft plugins versions
		 * that use $_POST as parameter for hook ( old versions )
		 * apply_filters( 'cntctfrmpr_check_form', $_POST );
		 * @deprecated since 1.22
		 * @todo remove after 25.02.2017
		 */
		if ( is_array( $allow ) ) {
			$allow = false;
			$old_cf_version = true;
		} else /* end @todo */ if ( ! $allow || is_string( $allow ) || is_wp_error( $allow ) ) {
			return $allow;
		}

		$result = gglcptch_check();

		if ( $result['response'] || $result['reason'] == 'ERROR_NO_KEYS' )
			return true;

		/**
		 * @deprecated since 1.22
		 * @todo remove after 25.02.2017
		 */
		if ( isset( $old_cf_version ) ) {
			return false;
		} else /* end @todo */ {
			$error_message = '<strong>' . __( 'Error', 'google-captcha' ) . '</strong>:&nbsp;' . __( 'You have entered an incorrect reCAPTCHA value', 'google-captcha' );
			/**
	         * Function 'cntctfrm_handle_captcha_filters' was added in Contact Form 4.0.2 (Free and Pro)
	         * remove this condition. WP_Error is correct object for return.
	         * @deprecated since 1.26
		 	 * @todo remove after 01.08.2017
	         */
			if ( function_exists( 'cntctfrm_handle_captcha_filters' ) ) {
				$allow = new WP_Error();
				$allow->add( 'gglcptch_error', $error_message );
			} else {
				$allow = $error_message;
			}
		}
		return $allow;
	}
}

/* Check google captcha in lostpassword form */
if ( ! function_exists( 'gglcptch_lostpassword_check' ) ) {
	function gglcptch_lostpassword_check( $allow ) {

		$result = gglcptch_check();

		if ( $result['response'] || $result['reason'] == 'ERROR_NO_KEYS' )
			return $allow;

		if ( ! is_wp_error( $allow ) )
			$allow = new WP_Error();

		$allow->add( 'gglcptch_error', '<strong>' . __( 'ERROR', 'google-captcha' ) . '</strong>:&nbsp;' . __( 'You have entered an incorrect reCAPTCHA value', 'google-captcha' ) . '.' );
		return $allow;
	}
}

/* Add google captcha to the multisite login form */
if ( ! function_exists( 'gglcptch_signup_display' ) ) {
	function gglcptch_signup_display( $errors ) {
		if ( $error_message = $errors->get_error_message( 'gglcptch_error' ) ) {
			printf( '<p class="error gglcptch_error">%s</p>', $error_message );
		}
		echo gglcptch_display();
	}
}

/* Check google captcha in multisite login form */
if ( ! function_exists( 'gglcptch_signup_check' ) ) {
	function gglcptch_signup_check( $result ) {
		global $current_user;

		if ( is_admin() && ! defined( 'DOING_AJAX' ) && ! empty( $current_user->data->ID ) )
			return $result;

		$check_result = gglcptch_check();

		if ( $check_result['response'] || $check_result['reason'] == 'ERROR_NO_KEYS' )
			return $result;

		$error = $result['errors'];
		$error->add( 'gglcptch_error', '<strong>' . __( 'ERROR', 'google-captcha' ) . '</strong>:&nbsp;' . __( 'You have entered an incorrect reCAPTCHA value', 'google-captcha' ) . '.' );
		return $result;
	}
}

/* Add google captcha to the comment form */
if ( ! function_exists( 'gglcptch_commentform_display' ) ) {
	function gglcptch_commentform_display() {
		if ( gglcptch_check_role() )
			return;
		echo gglcptch_display();
		return true;
	}
}

/* Check JS enabled for comment form  */
if ( ! function_exists( 'gglcptch_commentform_check' ) ) {
	function gglcptch_commentform_check() {
		if ( gglcptch_check_role() )
			return;

		$result = gglcptch_check();

		if ( $result['response'] || $result['reason'] == 'ERROR_NO_KEYS' )
			return;

		wp_die( '<strong>' . __( 'ERROR', 'google-captcha' ) . '</strong>:&nbsp;' . __( 'You have entered an incorrect reCAPTCHA value. Click the BACK button on your browser, and try again.', 'google-captcha' ) );
	}
}

/* display google captcha in Contact form */
if ( ! function_exists( 'gglcptch_cf_display' ) ) {
	function gglcptch_cf_display( $content, $form_slug = "" ) {
		/**
		 * this are necessary for compatibility
		 * with old Contact Form Free and Pro by BestWebsoft versions.
		 * correct return - $content = $content . gglcptch_display();
    	 * @since 1.26
		 * @todo remove after 1.03.2017
		 */
		if ( is_string( $content ) )
			$content = $content . gglcptch_display();
		elseif ( is_array( $content ) )
			$content = gglcptch_display();
		else
			$content = $form_slug . gglcptch_display();

		return $content;
	}
}

/* Check Google Captcha in shortcode and contact form */
if ( ! function_exists( 'gglcptch_captcha_check' ) ) {
	function gglcptch_captcha_check() {
		$result = gglcptch_check();
		echo $result['response'] ? "success" : "error";
		die();
	}
}

if ( ! function_exists( 'gglcptch_test_keys' ) ) {
	function gglcptch_test_keys() {
		if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'] , $_REQUEST['action'] ) ) {
			header( 'Content-Type: text/html' ); ?>
			<p><?php _e( 'Please, complete the captcha and submit "Test verification"', 'google-captcha' ); ?></p>
			<?php echo gglcptch_display(); ?>
			<p>
				<input type="hidden" name="gglcptch_test_keys_verification-nonce" value="<?php echo wp_create_nonce( 'gglcptch_test_keys_verification' ); ?>" />
				<button id="gglcptch_test_keys_verification" name="action" class="button-primary" value="gglcptch_test_keys_verification"><?php _e( 'Test verification', 'google-captcha' ); ?></button>
			</p>
		<?php }
		die();
	}
}

if ( ! function_exists( 'gglcptch_test_keys_verification' ) ) {
	function gglcptch_test_keys_verification() {
		if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'] , $_REQUEST['action'] ) ) {
			$result = gglcptch_check( true );

			if ( ! $result['response'] ) {
				$errors = array(
					/* custom error */
					'RECAPTCHA_EMPTY_RESPONSE'	=> __( 'The user response was missing', 'google-captcha' ),
					/* v2 error */
					'missing-input-secret' 		=> __( 'The Secret Key is missing', 'google-captcha' ),
					'invalid-input-secret' 		=> sprintf(
						'<strong>%s</strong>. <a target="_blank" href="https://www.google.com/recaptcha/admin#list">%s</a> %s.',
						__( 'The Secret Key is invalid', 'google-captcha' ),
						__( 'Check your domain configuration', 'google-captcha' ),
						__( 'and enter it again', 'google-captcha' )
					),
					'missing-input-response' 	=> __( 'The user response was missing', 'google-captcha' ),
					'invalid-input-response' 	=> __( 'The user response is invalid', 'google-captcha' ),
					/* v1 error */
					'invalid-site-private-key'	=> sprintf(
						'<strong>%s</strong>. <a target="_blank" href="https://www.google.com/recaptcha/admin#list">%s</a> %s.',
						__( 'The Secret Key is invalid', 'google-captcha' ),
						__( 'Check your domain configuration', 'google-captcha' ),
						__( 'and enter it again', 'google-captcha' )
					),
					'incorrect-captcha-sol' 	=> __( 'The user response is invalid', 'google-captcha' ),
				);

				if ( isset( $result['reason'] ) ) {
					foreach ( (array)$result['reason'] as $error ) { ?>
						<div class="error gglcptch-test-results"><p><?php echo $error; ?></p></div>
					<?php }
				}
			} else { ?>
				<div class="updated gglcptch-test-results"><p><?php _e( 'The verification is successfully completed','google-captcha' ); ?></p></div>
				<?php $gglcptch_options = get_option( 'gglcptch_options' );
				$gglcptch_options['keys_verified'] = true;
				update_option( 'gglcptch_options', $gglcptch_options );
			}
		}
		die();
	}
}

if ( ! function_exists( 'gglcptch_action_links' ) ) {
	function gglcptch_action_links( $links, $file ) {
		if ( ! is_network_admin() ) {
			static $this_plugin;
			if ( ! $this_plugin )
				$this_plugin = plugin_basename(__FILE__);

			if ( $file == $this_plugin ) {
				$settings_link = '<a href="admin.php?page=google-captcha.php">' . __( 'Settings', 'google-captcha' ) . '</a>';
				array_unshift( $links, $settings_link );
			}
		}
		return $links;
	}
}

if ( ! function_exists( 'gglcptch_shortcode_button_content' ) ) {
	function gglcptch_shortcode_button_content( $content ) { ?>
		<div id="gglcptch" style="display:none;">
			<input class="bws_default_shortcode" type="hidden" name="default" value="[bws_google_captcha]" />
		</div>
	<?php }
}

if ( ! function_exists( 'gglcptch_links' ) ) {
	function gglcptch_links( $links, $file ) {
		$base = plugin_basename( __FILE__ );
		if ( $file == $base ) {
			if ( ! is_network_admin() )
				$links[]	=	'<a href="admin.php?page=google-captcha.php">' . __( 'Settings', 'google-captcha' ) . '</a>';
			$links[]	=	'<a href="http://wordpress.org/plugins/google-captcha/faq/" target="_blank">' . __( 'FAQ', 'google-captcha' ) . '</a>';
			$links[]	=	'<a href="http://support.bestwebsoft.com">' . __( 'Support', 'google-captcha' ) . '</a>';
		}
		return $links;
	}
}

if ( ! function_exists ( 'gglcptch_plugin_banner' ) ) {
	function gglcptch_plugin_banner() {
		global $hook_suffix, $gglcptch_plugin_info, $gglcptch_options;
		if ( 'plugins.php' == $hook_suffix ) {
			if ( empty( $gglcptch_options ) )
				register_gglcptch_settings();

			if ( empty( $gglcptch_options['public_key'] ) || empty( $gglcptch_options['private_key'] ) ) { ?>
				<div class="error">
					<p>
						<?php printf(
							'<strong>%s <a target="_blank" href="https://www.google.com/recaptcha/admin#list">%s</a> %s <a target="_blank" href="%s">%s</a>.</strong>',
							__( 'To use Google Captcha you must get the keys from', 'google-captcha' ),
							__ ( 'here', 'google-captcha' ),
							__ ( 'and enter them on the', 'google-captcha' ),
							admin_url( '/admin.php?page=google-captcha.php' ),
							__( 'plugin setting page', 'google-captcha' )
						); ?>
					</p>
				</div>
			<?php }
			if ( isset( $gglcptch_options['first_install'] ) && strtotime( '-1 week' ) > $gglcptch_options['first_install'] )
				bws_plugin_banner( $gglcptch_plugin_info, 'gglcptch', 'google-captcha', '676d9558f9786ab41d7de35335cf5c4d', '109', '//ps.w.org/google-captcha/assets/icon-128x128.png' );

			bws_plugin_banner_to_settings( $gglcptch_plugin_info, 'gglcptch_options', 'google-captcha', 'admin.php?page=google-captcha.php' );
		}

		if ( isset( $_GET['page'] ) && 'google-captcha.php' == $_GET['page'] ) {
			bws_plugin_suggest_feature_banner( $gglcptch_plugin_info, 'gglcptch_options', 'google-captcha' );
		}
	}
}

/* add help tab  */
if ( ! function_exists( 'gglcptch_add_tabs' ) ) {
	function gglcptch_add_tabs() {
		$screen = get_current_screen();
		$args = array(
			'id' 			=> 'gglcptch',
			'section' 		=> '200538719'
		);
		bws_help_tab( $screen, $args );
	}
}

if ( ! function_exists( 'gglcptch_delete_options' ) ) {
	function gglcptch_delete_options() {
		if ( ! function_exists( 'get_plugins' ) )
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$all_plugins = get_plugins();

		if ( ! array_key_exists( 'google-captcha-pro/google-captcha-pro.php', $all_plugins ) ) {
			global $wpdb;
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				$old_blog = $wpdb->blogid;
				/* Get all blog ids */
				$blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}gglcptch_whitelist`;" );
					delete_option( 'gglcptch_options' );
				}
				switch_to_blog( $old_blog );
			} else {
				$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}gglcptch_whitelist`;" );
				delete_option( 'gglcptch_options' );
			}
		}

		require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
		bws_include_init( plugin_basename( __FILE__ ) );
		bws_delete_plugin( plugin_basename( __FILE__ ) );
	}
}

register_activation_hook( __FILE__, 'gglcptch_plugin_activate' );

add_action( 'admin_menu', 'gglcptch_admin_menu' );

add_action( 'init', 'gglcptch_init' );
add_action( 'admin_init', 'gglcptch_admin_init' );

add_action( 'plugins_loaded', 'gglcptch_plugins_loaded' );

add_action( 'admin_enqueue_scripts', 'gglcptch_add_admin_script_styles' );
add_action( 'wp_enqueue_scripts', 'gglcptch_add_styles' );
add_filter( 'script_loader_tag', 'gglcptch_add_async_attribute', 10, 2 );
add_action( 'admin_footer', 'gglcptch_admin_footer' );
add_filter( 'pgntn_callback', 'gglcptch_pagination_callback' );

/* custom filter for bws button in tinyMCE */
add_filter( 'bws_shortcode_button_content', 'gglcptch_shortcode_button_content' );
add_shortcode( 'bws_google_captcha', 'gglcptch_display' );
add_filter( 'widget_text', 'do_shortcode' );

add_filter( 'plugin_action_links', 'gglcptch_action_links', 10, 2 );
add_filter( 'plugin_row_meta', 'gglcptch_links', 10, 2 );

add_action( 'admin_notices', 'gglcptch_plugin_banner' );

add_action( 'wp_ajax_gglcptch_captcha_check', 'gglcptch_captcha_check' );
add_action( 'wp_ajax_nopriv_gglcptch_captcha_check', 'gglcptch_captcha_check' );
add_action( 'wp_ajax_gglcptch-test-keys', 'gglcptch_test_keys' );
add_action( 'wp_ajax_gglcptch_test_keys_verification', 'gglcptch_test_keys_verification' );