<?php
/**
 * Plugin Name:     One Time Login
 * Plugin URI:      https://wordpress.org/plugins/one-time-login/
 * Description:     Use WP-CLI to generate a one-time login URL for any user.
 * Author:          Daniel Bachhuber, Pantheon
 * Author URI:      http://runcommand.io
 * Text Domain:     one-time-login
 * Domain Path:     /languages
 * Version:         0.1.2
 *
 * @package         One_Time_Login
 */

/**
 * Generate a one-time login URL for any user.
 *
 * ## OPTIONS
 *
 * <user>
 * : ID, email address, or user login for the user.
 *
 * [--count=<count>]
 * : Generate a specified number of login tokens.
 * ---
 * default: 1
 * ---
 *
 * ## EXAMPLES
 *
 *     # Generate two one-time login URLs.
 *     $ wp user one-time-login testuser --count=2
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_token=ebe62e3
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_token=eb41c77
 */
function one_time_login_wp_cli_command( $args, $assoc_args ) {

	$fetcher = new WP_CLI\Fetchers\User;
	$user = $fetcher->get_check( $args[0] );

	$count = (int) $assoc_args['count'];
	$tokens = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$password = wp_generate_password();
		$tokens[] = sha1( $password );
	}

	update_user_meta( $user->ID, 'one_time_login_token', $tokens );
	do_action( 'one_time_login_created', $user );
	foreach ( $tokens as $token ) {
		$query_args = array(
			'user_id'              => $user->ID,
			'one_time_login_token' => $token,
		);
		$login_url = add_query_arg( $query_args, wp_login_url() );
		WP_CLI::log( $login_url );
	}
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'user one-time-login', 'one_time_login_wp_cli_command' );
}

/**
 * Log a request in as a user if the token is valid.
 */
function one_time_login_handle_token() {
	global $pagenow;

	if ( 'wp-login.php' !== $pagenow || empty( $_GET['user_id'] ) || empty( $_GET['one_time_login_token'] ) ) {
		return;
	}

	$error = sprintf( __( 'Invalid one-time login token. <a href="%s">Try logging in</a>?', 'one-time-login' ), wp_login_url() );

	// Use a generic error message to ensure user ids can't be sniffed
	$user = get_user_by( 'id', (int) $_GET['user_id'] );
	if ( ! $user ) {
		wp_die( $error );
	}

	$tokens = get_user_meta( $user->ID, 'one_time_login_token', true );
	$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
	$is_valid = false;
	foreach ( $tokens as $i => $token ) {
		if ( hash_equals( $token, $_GET['one_time_login_token'] ) ) {
			$is_valid = true;
			unset( $tokens[ $i ] );
			break;
		}
	}

	if ( ! $is_valid ) {
		wp_die( $error );
	}

	do_action( 'one_time_login_logged_in', $user );
	update_user_meta( $user->ID, 'one_time_login_token', $tokens );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );
	wp_safe_redirect( admin_url() );
	exit;
}
add_action( 'init', 'one_time_login_handle_token' );
