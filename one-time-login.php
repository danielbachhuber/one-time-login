<?php
/**
 * Plugin Name:     One Time Login
 * Plugin URI:      https://wordpress.org/plugins/one-time-login/
 * Description:     Use WP-CLI to generate a one-time login URL for any user.
 * Author:          Daniel Bachhuber
 * Author URI:      https://danielbachhuber.com
 * Text Domain:     one-time-login
 * Domain Path:     /languages
 * Version:         0.4.0
 *
 * @package         One_Time_Login
 */

/**
 * Generate one or multiple one-time login URL(s) for any user.
 *
 * @param WP_User|null $user  ID, email address, or user login for the user.
 * @param int          $count           Generate a specified number of login tokens (default: 1).
 * @param bool         $delay_delete   Delete existing tokens after 15 minutes, instead of immediately.
 *
 * @return array
 */
function one_time_login_generate_tokens( $user, $count, $delay_delete ) {
	$tokens     = $new_tokens = array();
	$login_urls = array();

	if ( $user instanceof WP_User ) {
		if ( $delay_delete ) {
			$tokens = get_user_meta( $user->ID, 'one_time_login_token', true );
			$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
			wp_schedule_single_event( time() + ( 15 * MINUTE_IN_SECONDS ), 'one_time_login_cleanup_expired_tokens', array( $user->ID, $tokens ) );
		}

		for ( $i = 0; $i < $count; $i++ ) {
			$password     = wp_generate_password();
			$token        = sha1( $password );
			$tokens[]     = $token;
			$new_tokens[] = $token;
		}

		update_user_meta( $user->ID, 'one_time_login_token', $tokens );
		do_action( 'one_time_login_created', $user );
		foreach ( $new_tokens as $token ) {
			$query_args   = array(
				'user_id'              => $user->ID,
				'one_time_login_token' => $token,
			);
			$login_urls[] = add_query_arg( $query_args, wp_login_url() );
		}
	}

	return $login_urls;
}

/**
 * Generate one-time tokens using WP CLI.
 *
 * ## OPTIONS
 *
 * <user>
 * [--count=<count>]
 * [--delay-delete]
 *
 * ## EXAMPLES
 *
 *     # Generate two one-time login URLs.
 *     $ wp user one-time-login testuser --count=2
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_token=ebe62e3
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_token=eb41c77
 *
 * @param array $args
 * @param array $assoc_args
 */
function one_time_login_wp_cli_command( $args, $assoc_args ) {
	$fetcher      = new WP_CLI\Fetchers\User;
	$user         = $fetcher->get_check( $args[0] );
	$delay_delete = WP_CLI\Utils\get_flag_value( $assoc_args, 'delay-delete' );
	$count        = (int) ( $assoc_args['count'] ?? 1 );

	$login_urls = one_time_login_generate_tokens( $user, $count, $delay_delete );
	foreach ( $login_urls as $login_url ) {
		WP_CLI::log( $login_url );
	}
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'user one-time-login', 'one_time_login_wp_cli_command' );
}

/**
 * Generate one-time tokens using WP CLI.
 *
 * ## OPTIONS
 *
 * /count/<count>/
 * /delay-delete/<0 or 1>
 *
 * ## EXAMPLES
 *
 *     # Generate two one-time login URLs.
 *     curl --user "admin:RrcZY8bDQBpT7CYrkYk8e9k7" http://localhost:8889/wp-json/one-time-login/v1/token
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_token=ebe62e3
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_token=eb41c77
 *
 * @param WP_REST_Request $request
 *
 * @return WP_REST_Response
 */
function one_time_login_api_request( WP_REST_Request $request ) {

	$user         = get_user_by( 'login', $request['user'] );
	$delay_delete = (bool) ( $request['delay_delete'] ?? false );
	$count        = (int) ( $request['count'] ?? 1 );

	$login_urls = one_time_login_generate_tokens( $user, $count, $delay_delete );

	return new WP_REST_Response( $login_urls );
}

/**
 * Registers the API endpoint for generating one-time logins.
 */
function one_time_login_rest_api_init() {
	register_rest_route(
		'one-time-login/v1',
		'/token',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'one_time_login_api_request',
				'args'                => array(
					'user'         => array(
						'required' => true,
					),
					'count'        => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'delay_delete' => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
				'permission_callback' => function ( WP_REST_Request $request ) {
					if ( empty( $request['user'] ) ) {
						return false;
					}
					$user = get_user_by( 'login', $request['user'] );
					return current_user_can( 'edit_user', $user->ID );
				},
			),
		)
	);
}

add_action( 'rest_api_init', 'one_time_login_rest_api_init' );

/**
 * Handle cleanup process for expired one-time login tokens.
 *
 * @param int   $user_id
 * @param array $expired_tokens
 */
function one_time_login_cleanup_expired_tokens( $user_id, $expired_tokens ) {
	$tokens     = get_user_meta( $user_id, 'one_time_login_token', true );
	$tokens     = is_string( $tokens ) ? array( $tokens ) : $tokens;
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
		$error = sprintf( __( 'Invalid one-time login token, but you are logged in as \'%1$s\'. <a href="%2$s">Go to the dashboard instead</a>?', 'one-time-login' ), wp_get_current_user()->user_login, admin_url() );
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
				foreach ( $hook_events as $data ) {
					if ( ! defined( 'DOING_CRON' ) ) {
						define( 'DOING_CRON', true );
					}
					do_action_ref_array( $hook, $data['args'] );
					wp_unschedule_event( $time, $hook, $data['args'] );
				}
			}
		}
	}

	// Use a generic error message to ensure user ids can't be sniffed.
	$user = get_user_by( 'id', (int) $_GET['user_id'] );
	if ( ! $user ) {
		wp_die( $error );
	}

	$tokens   = get_user_meta( $user->ID, 'one_time_login_token', true );
	$tokens   = is_string( $tokens ) ? array( $tokens ) : $tokens;
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
