<?php
/** @noinspection PhpMultipleClassDeclarationsInspection, PhpIllegalPsrClassPathInspection */
declare(strict_types=1);

use PHPUnit\Framework\Attributes\RequiresMethod;

/**
 * @author    Mat Lipe
 * @since     August 2020
 *
 * @notice    Not yet available on WP Engine object cache version.
 *
 * @version   1.2.0
 */
class Cache_MultiTest extends \Object_Cache_TestCase {

	#[RequiresMethod( \WP_Object_Cache::class, 'instance' )]
	public function test_set_multi(): void {
		$count = WP_Object_Cache::instance()->stats['set_multi'];
		wp_cache_set_multiple( [
			'one'    => 'is',
			'the'    => 'loneliest',
			'number' => 'always',
		] );
		$this->assertSame( 'is', wp_cache_get( 'one' ) );
		$this->assertSame( 'loneliest', wp_cache_get( 'the' ) );
		$this->assertSame( 'always', wp_cache_get( 'number' ) );
		$this->assertSame( $count + 1, WP_Object_Cache::instance()->stats['set_multi'] );

		$this->assertSame( [
			'one'    => true,
			'the'    => true,
			'number' => true,
		], WP_Object_Cache::instance()->set_multiple( [
			'one'    => 'is',
			'the'    => 'loneliest',
			'number' => [ 'always' ],
		], 'test-group' ) );

		WP_Object_Cache::instance()->cache = [];
		$this->assertSame( 'is', wp_cache_get( 'one', 'test-group' ) );
		$this->assertSame( 'loneliest', wp_cache_get( 'the', 'test-group' ) );
		$this->assertSame( [ 'always' ], wp_cache_get( 'number', 'test-group' ) );
		$this->assertSame( $count + 2, WP_Object_Cache::instance()->stats['set_multi'] );
	}


	public function test_get_multi(): void {
		wp_cache_set( 'tgm', 3 );
		wp_cache_set( 'mgt', 'four' );
		wp_cache_set( 'aqua', 'man' );

		$this->assertSame( [
			'tgm'  => 3,
			'mgt'  => 'four',
			'aqua' => 'man',
		], wp_cache_get_multiple( [ 'tgm', 'mgt', 'aqua' ] ) );

		WP_Object_Cache::instance()->cache = [];

		$this->assertSame( [
			'tgm'  => 3,
			'mgt'  => 'four',
			'aqua' => 'man',
		], WP_Object_Cache::instance()->get_multiple( [ 'tgm', 'mgt', 'aqua' ] ) );

		$count = WP_Object_Cache::instance()->stats['external'];
		$this->assertSame( [
			'tgm'  => 3,
			'mgt'  => 'four',
			'aqua' => 'man',
		], WP_Object_Cache::instance()->get_multiple( [ 'tgm', 'mgt', 'aqua' ] ) );
		foreach ( array_keys( WP_Object_Cache::instance()->cache ) as $key ) {
			self::assertIsNotNumeric( $key );
		}
		$this->assertSame( $count, WP_Object_Cache::instance()->stats['external'] );
	}


	public function test_delete_multiple(): void {
		WP_Object_Cache::instance()->add_non_persistent_groups( 'fake' );
		WP_Object_Cache::instance()->set( 'no-persist', true, 'here' );
		$this->assertSame( [ 'no-persist' => false ], WP_Object_Cache::instance()->delete_multiple( [ 'no-persist' ], 'fake' ) );

		$count = WP_Object_Cache::instance()->stats['delete_multi'];
		$local_count = WP_Object_Cache::instance()->stats['delete'];
		$values = [
			'key-1' => 'sasquatch',
			'key-2' => 'yeti',
			'key-3' => 'safe',
		];
		foreach ( $values as $key => $value ) {
			$this->assertTrue( WP_Object_Cache::instance()->set( $key, $value ) );
			$this->assertCachePropertyAndExternal( $key, $value );
		}

		$keys = \array_keys( $values );
		$this->assertSame( [
			$keys[0] => true,
			$keys[1] => true,
		], WP_Object_Cache::instance()->delete_multiple( [ $keys[0], $keys[1] ] ) );

		$this->assertFalse( WP_Object_Cache::instance()->get( $keys[0] ) );
		$this->assertFalse( WP_Object_Cache::instance()->get( $keys[1] ) );
		$this->assertCachePropertyAndExternal( $keys[2], $values[ $keys[2] ] );

		$this->assertSame( $count + 1, WP_Object_Cache::instance()->stats['delete_multi'] );
		$this->assertSame( $local_count + 2, WP_Object_Cache::instance()->stats['delete'] );
	}


	public function test_get_partial_external(): void {
		$values = [
			'one'   => true,
			'two'   => 2,
			'three' => 3,
			'four'  => 4,
		];
		WP_Object_Cache::instance()->set_multiple( $values );
		WP_Object_Cache::instance()->cache = [];
		WP_Object_Cache::instance()->get( 'two' );
		WP_Object_Cache::instance()->get( 'four' );
		$this->assertSame( $values, WP_Object_Cache::instance()->get_multiple( [ 'one', 'two', 'three', 'four' ] ) );

		$values = [
			3 => true,
			2 => 2,
			1 => 3,
			4 => 4,
		];
		WP_Object_Cache::instance()->set_multiple( $values );
		WP_Object_Cache::instance()->cache = [];
		WP_Object_Cache::instance()->get( 2 );
		WP_Object_Cache::instance()->get( 4 );
		// Notice the extra `2`.
		$this->assertSame( $values, WP_Object_Cache::instance()->get_multiple( [ 3, 2, 2, 1, 4 ] ) );
	}


	public function test_missing_keys(): void {
		$values = [
			'one'  => true,
			'two'  => 2,
			'four' => 4,
		];
		WP_Object_Cache::instance()->set_multiple( $values );

		$this->assertSame( [
			'one'   => true,
			'two'   => 2,
			'three' => false,
			'four'  => 4,
		], WP_Object_Cache::instance()->get_multiple( [ 'one', 'two', 'three', 'four' ] ) );
	}


	public function test_runtime_cache_after_get_multiple(): void {
		$values = [
			'one'  => true,
			'two'  => 2,
			'four' => 4,
		];
		WP_Object_Cache::instance()->set_multiple( $values );
		wp_cache_flush_runtime();
		$prior_count = WP_Object_Cache::instance()->stats['external'];
		WP_Object_Cache::instance()->get_multiple( [ 'one', 'two', 'three', 'four' ] );
		$this->assertSame( $prior_count + 3, WP_Object_Cache::instance()->stats['external'] );

		$counts = WP_Object_Cache::instance()->stats;
		WP_Object_Cache::instance()->get_multiple( [ 'one', 'two', 'three', 'four' ] );
		$this->assertSame( $counts['external'], WP_Object_Cache::instance()->stats['external'] );
		$this->assertSame( $counts['get'] + 4, WP_Object_Cache::instance()->stats['get'] );
		$this->assertSame( $counts['miss'], WP_Object_Cache::instance()->stats['miss'] );
	}
}
