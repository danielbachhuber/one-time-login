<?php
/**
 * Plugin Name:     One Time Login
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     Generate a one-time login URL for any user.
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
	WP_CLI::success( sprintf( 'Your one-time login URL is: %s', add_query_arg( $query_args, wp_login_url() ) ) );
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

	// Don't expose which user ids are valid
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
