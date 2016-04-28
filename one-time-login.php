<?php
/**
 * Plugin Name:     One Time Login
 * Plugin URI:      https://wordpress.org/plugins/one-time-login/
 * Description:     Use WP-CLI to generate a one-time login URL for any user.
 * Author:          Daniel Bachhuber, Pantheon
 * Author URI:      https://handbuilt.co
 * Text Domain:     one-time-login
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         One_Time_Login
 */

/**
 * Generate a one-time login URL for any user.
 *
 * <user>
 * : ID, email address, or user login for the user.
 *
 * [--porcelain]
 * : Only output the one-time login URL, if you want to pipe it to another command.
 */
function one_time_login_wp_cli_command( $args ) {

	$fetcher = new WP_CLI\Fetchers\User;
	$user = $fetcher->get_check( $args[0] );
	$password = wp_generate_password();
	$token = sha1( $password );
	update_user_meta( $user->ID, 'one_time_login_token', $token );
	$query_args = array(
		'user_id'              => $user->ID,
		'one_time_login_token' => $token,
	);
	$login_url = add_query_arg( $query_args, wp_login_url() );
	if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
		WP_CLI::log( $login_url );
	} else {
		WP_CLI::success( sprintf( 'Your one-time login URL is: %s', $login_url ) );
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

	$error = __( 'Invalid one-time login token', 'one-time-login' );

	// Use a generic error message to ensure user ids can't be sniffed
	$user = get_user_by( 'id', (int) $_GET['user_id'] );
	if ( ! $user ) {
		wp_die( $error );
	}

	$token = get_user_meta( $user->ID, 'one_time_login_token', true );
	if ( ! hash_equals( $token, $_GET['one_time_login_token'] ) ) {
		wp_die( $error );
	}

	delete_user_meta( $user->ID, 'one_time_login_token' );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );
	wp_safe_redirect( admin_url() );
	exit;
}
add_action( 'init', 'one_time_login_handle_token' );
