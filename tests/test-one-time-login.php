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
	 * @var WP_User[]
	 */
	protected static $users = array(
		'administrator' => null,
		'editor'        => null
	);

	/**
	 * Set up WP users to test with different roles / capabilities
	 *
	 * @param $factory
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$users = array(
			'administrator' => $factory->user->create_and_get( array( 'role' => 'administrator' ) ),
			'editor'        => $factory->user->create_and_get( array( 'role' => 'editor' ) )
		);
	}

	/**
	 * Test the REST API call for token generation depending on user
	 * @dataProvider rest_api_provider
	 *
	 * @param WP_User $user
	 * @param int $status
	 */
	public function test_rest_api_authorization( $user, $status ) {
		if ( $user instanceof WP_User ) {
			wp_set_current_user( $user->ID );

			var_dump( 'CURRENT_USER:' );
			var_dump( wp_get_current_user()->user_login );
			var_dump( 'CURRENT_USER_CAN:' );
			var_dump( current_user_can('administrator' ) );
		}

		$request = new WP_REST_Request(
			WP_REST_Server::CREATABLE,
			'/one-time-login/v1/token'
		);
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'user' => self::$users['editor']->user_login ) ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( $status, $response->get_status() );
	}

	/**
	 * @return array
	 */
	public function rest_api_provider() {
		return array(
			array( self::$users['administrator'], 200 ),
			array( self::$users['editor'], 401 ),
			array( null, 401 ),
		);
	}

	/**
	 * Test one_time_login_generate_tokens()
	 * @dataProvider token_data_provider
	 *
	 * @param boolean $delay_delete
	 * @param int $count
	 * @param array $generated_count
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
