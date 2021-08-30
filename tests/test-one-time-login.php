<?php
/**
 * Class OneTimeLoginTest
 *
 * @package One_Time_Login
 */

/**
 * Sample test case.
 */
class OneTimeLoginTest extends WP_UnitTestCase {

	/**
	 * Array of WP_User objects.
	 *
	 * @var WP_User[]
	 */
	protected static $users = array(
		'administrator' => null,
		'editor'        => null,
		'other_user'    => null,
	);

	/**
	 * Set up WP users to test with different roles / capabilities
	 *
	 * @param object $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$users = array(
			'administrator' => $factory->user->create_and_get( array( 'role' => 'administrator' ) ),
			'editor'        => $factory->user->create_and_get( array( 'role' => 'editor' ) ),
			'other_user'    => $factory->user->create_and_get( array( 'role' => 'editor' ) ),
		);
	}

	/**
	 * Test the REST API call for token generation depending on user
	 *
	 * @dataProvider rest_api_provider
	 *
	 * @param string $user
	 * @param int    $status
	 */
	public function test_rest_api_authorization( $user, $status ) {
		if ( array_key_exists( $user, self::$users ) ) {
			wp_set_current_user( self::$users[ $user ]->ID );
		}

		$request = new WP_REST_Request(
			WP_REST_Server::CREATABLE,
			'/one-time-login/v1/token'
		);
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'user' => self::$users['other_user']->user_login ) ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( $status, $response->get_status() );
	}

	/**
	 * Provider for REST API data.
	 *
	 * @return array
	 */
	public function rest_api_provider() {
		return array(
			// Admin (who can edit any user).
			array( 'administrator', 200 ),
			// User generating tokens for himself.
			array( 'other_user', 200 ),
			// Editor (can't edit other users).
			array( 'editor', 403 ),
			// Unauthenticated.
			array( 'unexisting_user', 401 ),
		);
	}

	/**
	 * Test one_time_login_generate_tokens()
	 *
	 * @dataProvider token_data_provider
	 *
	 * @param boolean $delay_delete
	 * @param int     $count
	 * @param array   $generated_count
	 */
	function test_generate_token( $delay_delete, $count, $generated_count ) {
		$this->assertSame(
			count( one_time_login_generate_tokens( self::$users['administrator'], $delay_delete, $count ) ),
			$generated_count
		);
		$this->assertSame(
			count( one_time_login_generate_tokens( self::$users['editor'], $delay_delete, $count ) ),
			$generated_count
		);
		$this->assertSame(
			count( one_time_login_generate_tokens( null, $delay_delete, $count ) ),
			0
		);
	}

	/**
	 * Provider for token data.
	 *
	 * @return array
	 */
	public function token_data_provider() {
		return array(
			array( false, 1, 1 ),
			array( true, 1, 1 ),
			array( false, 3, 3 ),
			array( true, 3, 3 ),
			array( false, 10, 10 ),
		);
	}
}
