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
	 * Redirect location.
	 *
	 * @var string|null
	 */
	public $redirect_location = null;

	/**
	 * Redirect status.
	 *
	 * @var int|null
	 */
	public $redirect_status = null;

	/**
	 * Set up the tests.
	 */
	public function setUp(): void {
		parent::setUp();
		add_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ), 10, 2 );
		$this->redirect_location = null;
		$this->redirect_status   = null;
	}

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

		if ( is_multisite() && 'administrator' === $user ) {
			update_site_option( 'site_admins', array( self::$users[ $user ]->user_login ) );
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
	 * Test that one_time_login_handle_token() works as expected when user is invalid.
	 */
	public function test_one_time_login_handle_token_invalid_user() {
		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'Invalid one-time login token. <a href="http://example.org/wp-login.php">Try signing in instead</a>?' );
		one_time_login_generate_tokens( self::$users['administrator'], 1, false );
		$tokens                       = get_user_meta( self::$users['administrator']->ID, 'one_time_login_token', true );
		$token                        = array_shift( $tokens );
		$GLOBALS['pagenow']           = 'wp-login.php';
		$_GET['one_time_login_token'] = $token;
		$_GET['user_id']              = 999999;
		one_time_login_handle_token();
	}

	/**
	 * Test that one_time_login_handle_token() works as expected when user is token.
	 */
	public function test_one_time_login_handle_token_invalid_token() {
		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'Invalid one-time login token. <a href="http://example.org/wp-login.php">Try signing in instead</a>?' );
		one_time_login_generate_tokens( self::$users['administrator'], 1, false );
		$GLOBALS['pagenow']           = 'wp-login.php';
		$_GET['one_time_login_token'] = 'abc123';
		$_GET['user_id']              = self::$users['administrator']->ID;
		one_time_login_handle_token();
	}

	/**
	 * Test that one_time_login_handle_token() works as expected when conditions are met.
	 */
	public function test_one_time_login_handle_token_success() {
		one_time_login_generate_tokens( self::$users['administrator'], 1, false );
		$tokens                       = get_user_meta( self::$users['administrator']->ID, 'one_time_login_token', true );
		$token                        = reset( $tokens );
		$GLOBALS['pagenow']           = 'wp-login.php';
		$_GET['one_time_login_token'] = $token['token'];
		$_GET['user_id']              = self::$users['administrator']->ID;
		one_time_login_handle_token();
		$this->assertRedirect( admin_url(), 302 );
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
	public function test_generate_token( $delay_delete, $count, $generated_count ) {
		$this->assertSame(
			count( one_time_login_generate_tokens( self::$users['administrator'], $count, $delay_delete ) ),
			$generated_count
		);
		$this->assertSame(
			count( one_time_login_generate_tokens( self::$users['editor'], $count, $delay_delete ) ),
			$generated_count
		);
		$this->assertSame(
			count( one_time_login_generate_tokens( null, $count, $delay_delete ) ),
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

	/**
	 * Filter wp_redirect() to capture the redirect location.
	 *
	 * @param string $location Redirect location.
	 * @param int    $status   HTTP status code.
	 * @return string
	 */
	public function filter_wp_redirect( $location, $status = null ) {
		$this->redirect_location = $location;
		$this->redirect_status   = $status;
		return null;
	}

	/**
	 * Assert an expected redirect.
	 *
	 * @param string $location Expected redirect location.
	 * @param int    $status   Expected redirect status.
	 */
	public function assertRedirect( $location, $status ) {
		$this->assertSame( $location, $this->redirect_location );
		$this->assertSame( $status, $this->redirect_status );
	}

	/**
	 * Tear down the tests.
	 */
	public function tearDown(): void {
		parent::tearDown();
		remove_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ) );
	}
}
