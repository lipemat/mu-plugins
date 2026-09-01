<?php

namespace Lipe\Mu;

use Lipe\Lib\Container\Container;

/**
 * @author  mat
 * @since   11/25/2017
 */
class UseTheForceTest extends \WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		$GLOBALS['use_the_force']['filters'] = [
			'test_filter_full' => [
				function() {
					return true;
				},
				1,
				1,
			],
			'test_filter'      => '__return_true',
		];
		$GLOBALS['use_the_force']['actions'] = [
			'test_action_full' => [
				'__return_true',
				1,
				0,
			],
			'test_action'      => '__return_true',
		];
		$GLOBALS['use_the_force']['container'][] = function( Container $container ) {
			$container->set_service( self::class, new class() {
				public function value(): bool {
					return true;
				}
			} );
		};
		$GLOBALS['use_the_force']['use_the_force'][] = function() {
			$GLOBALS['forced'] = true;
		};

		require \dirname( __DIR__, 3 ) . '/plugins/use-the-force.php';

		do_action( 'lipe/project/container-loaded', Container::instance() );
	}


	public function test_filters(): void {
		$this->assertTrue( apply_filters( 'test_filter_full', false ) );
		$this->assertTrue( apply_filters( 'test_filter', false ) );
	}


	public function test_actions(): void {
		$this->assertEquals( 1, has_action( 'test_action_full' ) );
		$this->assertEquals( 10, has_action( 'test_action', '__return_true' ) );
	}


	public function test_use_the_force(): void {
		$this->assertTrue( $GLOBALS['forced'] );
	}


	public function test_container(): void {
		$service = Container::instance()->get_service( __CLASS__ );

		$this->assertSame( $service, Container::instance()->get_service( __CLASS__ ), 'The container should return the same registered service instance.' );
		$this->assertTrue( \method_exists( $service, 'value' ), 'The overriden service should expose a value() method.' );
		$this->assertTrue( \call_user_func( [ $service, 'value' ] ), 'The overriden service should return true when calling value() method.' );
	}
}
