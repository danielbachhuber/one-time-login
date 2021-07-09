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
	 * @var array
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
