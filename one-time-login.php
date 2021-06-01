<?php
/**
 * Plugin Name:     One Time Login
 * Plugin URI:      https://wordpress.org/plugins/one-time-login/
 * Description:     Use WP-CLI to generate a one-time login URL for any user.
 * Author:          Daniel Bachhuber
 * Author URI:      https://danielbachhuber.com
 * Text Domain:     one-time-login
 * Domain Path:     /languages
 * Version:         0.3.1
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
 * [--delay-delete]
 * : Delete existing tokens after 15 minutes, instead of immediately.
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
	$delay_delete = WP_CLI\Utils\get_flag_value( $assoc_args, 'delay-delete' );
	$count = (int) $assoc_args['count'];
	$tokens = $new_tokens = array();

	if ( $delay_delete ) {
		$tokens = get_user_meta( $user->ID, 'one_time_login_token', true );
		$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
		wp_schedule_single_event( time() + ( 15 * MINUTE_IN_SECONDS ), 'one_time_login_cleanup_expired_tokens', array( $user->ID, $tokens ) );
	}

	for ( $i = 0; $i < $count; $i++ ) {
		$password = wp_generate_password();
		$token = sha1( $password );
		$tokens[] = $token;
		$new_tokens[] = $token;
	}

	update_user_meta( $user->ID, 'one_time_login_token', $tokens );
	do_action( 'one_time_login_created', $user );
	foreach ( $new_tokens as $token ) {
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
 * Handle cleanup process for expired one-time login tokens.
 */
function one_time_login_cleanup_expired_tokens( $user_id, $expired_tokens ) {
	$tokens = get_user_meta( $user_id, 'one_time_login_token', true );
	$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
	$new_tokens = array();
	foreach ( $tokens as $token ) {
		if ( ! in_array( $token, $expired_tokens, true ) ) {
			$new_tokens[] = $token;
		}
	}
	update_user_meta( $user_id, 'one_time_login_token', $new_tokens );
}
add_action( 'one_time_login_cleanup_expired_tokens', 'one_time_login_cleanup_expired_tokens', 10, 2 );

/**
 * Log a request in as a user if the token is valid.
 */
function one_time_login_handle_token() {
	global $pagenow;

	if ( 'wp-login.php' !== $pagenow || empty( $_GET['user_id'] ) || empty( $_GET['one_time_login_token'] ) ) {
		return;
	}

	if ( is_user_logged_in() ) {
		$error = sprintf( __( 'Invalid one-time login token, but you are logged in as \'%s\'. <a href="%s">Go to the dashboard instead</a>?', 'one-time-login' ), wp_get_current_user()->user_login, admin_url() );
	} else {
		$error = sprintf( __( 'Invalid one-time login token. <a href="%s">Try signing in instead</a>?', 'one-time-login' ), wp_login_url() );
	}

	// Ensure any expired crons are run
	// It would be nice if WP-Cron had an API for this, but alas.
	$crons = _get_cron_array();
	if ( ! empty( $crons ) ) {
		foreach ( $crons as $time => $hooks ) {
			if ( time() < $time ) {
				continue;
			}
			foreach ( $hooks as $hook => $hook_events ) {
				if ( 'one_time_login_cleanup_expired_tokens' !== $hook ) {
					continue;
				}
				foreach ( $hook_events as $sig => $data ) {
					if ( ! defined( 'DOING_CRON' ) ) {
						define( 'DOING_CRON', true );
					}
					do_action_ref_array( $hook, $data['args'] );
					wp_unschedule_event( $time, $hook, $data['args'] );
				}
			}
		}
	}

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
	do_action( 'one_time_login_after_auth_cookie_set', $user );
	wp_safe_redirect( admin_url() );
	exit;
}
add_action( 'init', 'one_time_login_handle_token' );
