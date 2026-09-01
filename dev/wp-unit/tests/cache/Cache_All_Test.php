<?php

use Lipe\WP_Unit\Utils\PrivateAccess;
use PHPUnit\Framework\Attributes\RequiresMethod;

/**
 * @author  Mat Lipe
 * @since   October 2018
 *
 * @version 1.1.0
 */
class Cache_All_Test extends Object_Cache_TestCase {

	public function test_flush(): void {
		$key = microtime();
		$value = 'brodeur';

		// Add to Opcache
		$this->assertTrue( $this->object_cache->add( $key, $value ) );

		// Verify correct value and type is returned
		$this->assertSame( $value, $this->object_cache->get( $key ) );

		// Flush cache
		$this->assertTrue( $this->object_cache->flush() );

		// Make sure value is no longer available
		$this->assertFalse( $this->object_cache->get( $key ) );
	}


	#[RequiresMethod( \WP_Object_Cache::class, 'instance' )]
	public function test_wp_cache_flush_runtime(): void {
		wp_cache_set( 'fake', true );
		$this->assertNotEmpty( WP_Object_Cache::instance()->cache );
		wp_cache_flush_runtime();
		$this->assertEmpty( WP_Object_Cache::instance()->cache );
		$this->assertTrue( wp_cache_get( 'fake' ) );
	}


	#[RequiresMethod( \WP_Object_Cache::class, 'instance' )]
	public function test_wp_cache_supports(): void {
		foreach (
			[
				'add_multiple',
				'set_multiple',
				'get_multiple',
				'delete_multiple',
				'flush_runtime',
				'flush_group',
			] as $feature
		) {
			$this->assertTrue( wp_cache_supports( $feature ) );
		}

		$this->assertFalse( wp_cache_supports( 'making coffee' ) );
	}


	public function test_convert_expire_time(): void {
		$key = 'usa';
		$value = 'merica';
		$group = 'july';

		// 30 days
		$thirty = 60 * 60 * 24 * 30;
		// 30 days and 1 second;
		$over_30 = 60 * 60 * 24 * 30 + 1;
		if ( ! class_exists( 'Memcached' ) || defined( 'OBJECT_CACHE_HANDLER' ) ) {
			// opcache result
			$this->assertEquals( time() + $this->object_cache->default_expiration, call_private_method( $this->object_cache, 'convert_expire_time', [ 0 ] ) );
			$this->assertEquals( time() + $this->object_cache->default_expiration, call_private_method( $this->object_cache, 'convert_expire_time', [ 0 ] ) );
			$this->assertEquals( time() + $thirty, call_private_method( $this->object_cache, 'convert_expire_time', [ $thirty ] ) );
		} else {
			$this->assertEquals( $this->object_cache->default_expiration, call_private_method( $this->object_cache, 'convert_expire_time', [ 0 ] ) );
			$this->assertEquals( $thirty, call_private_method( $this->object_cache, 'convert_expire_time', [ $thirty ] ) );
		}
		$this->assertEquals( time() + $over_30, call_private_method( $this->object_cache, 'convert_expire_time', [ $over_30 ] ) );

		$this->assertTrue( $this->object_cache->add( $key, $value, $group, $over_30 ) );
		$this->assertCachePropertyAndExternal( $key, $value, $group );

		$this->assertTrue( $this->object_cache->set( $key . '-set', $value, $group, $over_30 ) );
		$this->assertCachePropertyAndExternal( $key . '-set', $value, $group );

		// We skip on sites, which don't yet support this like WP Engine.
		if ( method_exists( $this->object_cache, 'set_multiple' ) ) {
			$this->assertEquals( [
				$key . '-set-multi' => true,
			], $this->object_cache->set_multiple( [ $key . '-set-multi' => $value ], $group, $over_30 ) );
			$this->assertCachePropertyAndExternal( $key . '-set-multi', $value, $group );
		}
	}


	#[RequiresMethod( \WP_Object_Cache::class, 'is_opcache_enabled' )]
	public function test_flush_deletes_cache_files_from_disk(): void {
		$key = 'flush-disk-' . microtime( true );
		$group = 'flush-disk';

		$this->object_cache->set( $key, 'brodeur', $group );

		$file = PrivateAccess::in()->call_private_method( $this->object_cache, 'file_path', [ $this->object_cache->key( $key, $group ) ] );
		$this->assertFileExists( $file, 'Cache file should be written to disk.' );

		$this->assertTrue( $this->object_cache->flush(), 'Flush should report success.' );

		$this->assertFileDoesNotExist( $file, 'Flush must delete the cache file from disk.' );
	}
}
