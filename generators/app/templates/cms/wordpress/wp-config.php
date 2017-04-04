<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// Include local configuration
if (file_exists(dirname(__FILE__) . '/local-config.php')) {
	include(dirname(__FILE__) . '/local-config.php');
}

// Global DB config
if (!defined('DB_NAME')) {
	define('DB_NAME', $_ENV['MYSQL_DATABASE']);
}

if (!defined('DB_USER')) {
	define('DB_USER', $_ENV['MYSQL_USER']);
}

if (!defined('DB_PASSWORD')) {
	define('DB_PASSWORD', $_ENV['MYSQL_PASSWORD']);
}

if (!defined('DB_HOST')) {
	define('DB_HOST', $_ENV['MYSQL_HOST']);
}

/** Database Charset to use in creating database tables. */
if (!defined('DB_CHARSET')) {
	define('DB_CHARSET', 'utf8');
}

/** The Database Collate type. Don't change this if in doubt. */
if (!defined('DB_COLLATE')) {
	define('DB_COLLATE', '');
}

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'x+m&;VTSw96kta6jMQ(IiH^EdYZ@`#yk+w#]s&9B~t}Djx.[&<@Gomu%}C| /s;_');
define('SECURE_AUTH_KEY',  '=.~~>xG(ajs0s-&$:u)Nl.Bt76%V9|az3S$r+x,_~]we=UlR{<xl^]+1}jUP)Z/L');
define('LOGGED_IN_KEY',    '<qMePaWn}^UQs/|l_A9u3-ZZ#=&TXt#:j}Z(A2v|xkW[0>8-`ys&k<bNEi`A4rvh');
define('NONCE_KEY',        '+fw2Fqwjj+)aq1~$6}w^H?Wm[QM48GyLGxo^*3oDUd!S;z=61-sEtUZ].S_(zc=9');
define('AUTH_SALT',        'P 2VPBBH$AakZ]5A#k=lT}m0k<Bf6M)5PR$A~TYz@G0z-_{PjR@~O?G7lv}Jp+@S');
define('SECURE_AUTH_SALT', '-aw.=Q3M5;A7qv?864ybTj4eBi|S.p F5|FLQ:i?)]?fuQ8%X Z,NrZw$,>R=f+w');
define('LOGGED_IN_SALT',   't%JdEfa>wnLP&](M+Y`-?&(c^BAWQBJ|3{y3b%Fr|I|@4Rs:3@+hd7dT n0r3Q]X');
define('NONCE_SALT',       '!|Cl?r^[Z H!7w+`;}c{+K}Xf.Xdq>O*4W5KvOYG:GunkYE(RcLKb8|*^WRw,PqK');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'cj_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/**
 * Set custom paths
 *
 * These are required because wordpress is installed in a subdirectory.
 */
if (!defined('WP_SITEURL')) {
	define('WP_SITEURL', 'http://' . $_SERVER['SERVER_NAME'] . '/wordpress');
}
if (!defined('WP_HOME')) {
	define('WP_HOME',    'http://' . $_SERVER['SERVER_NAME'] . '');
}
if (!defined('WP_CONTENT_DIR')) {
	define('WP_CONTENT_DIR', dirname(__FILE__) . '/content');
}
if (!defined('WP_CONTENT_URL')) {
	define('WP_CONTENT_URL', 'http://' . $_SERVER['SERVER_NAME'] . '/content');
}

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
if (!defined('WP_DEBUG')) {
	define('WP_DEBUG', true);
}

// define('DISABLE_WP_CRON', true);
define( 'SAVEQUERIES', true );

// BEGIN iThemes Security - Do not modify or remove this line
// iThemes Security Config Details: 2
define( 'DISALLOW_FILE_EDIT', true ); // Disable File Editor - Security > Settings > WordPress Tweaks > File Editor

// if (!defined('SSL_OFF')) {
// 	define( 'FORCE_SSL_LOGIN', true ); // Force SSL for Dashboard - Security > Settings > Secure Socket Layers (SSL) > SSL for Dashboard
// 	define( 'FORCE_SSL_ADMIN', true ); // Force SSL for Dashboard - Security > Settings > Secure Socket Layers (SSL) > SSL for Dashboard
// 	// END iThemes Security - Do not modify or remove this line
//
// 	// in some setups HTTP_X_FORWARDED_PROTO might contain  // a comma-separated list e.g. http,https  // so check for https existence  if (strpos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false)
// 	$_SERVER['HTTPS'] = 'on';
// }

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
