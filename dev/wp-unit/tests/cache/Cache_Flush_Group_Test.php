<?php

use PHPUnit\Framework\Attributes\RequiresMethod;

/**
 * @notice  Not yet supported on the WP Engine object cache version.
 *
 * @version 1.0.1
 */
final class Cache_Flush_Group_Test extends Object_Cache_TestCase {
	private const GROUP_1 = 'wp-unit-1';
	private const GROUP_2 = 'wp-unit-2';

	private const KEY   = 'wp-unit-key';
	private const VALUE = 'wp-unit-value';


	#[RequiresMethod( \WP_Object_Cache::class, 'flush_group' )]
	public function test_flush_group(): void {
		$this->assertNotCacheExternal( self::KEY, self::GROUP_1 );
		$this->object_cache->set( self::KEY, self::VALUE, self::GROUP_1 );
		$this->assertCacheExternal( self::KEY, self::GROUP_1 );
		$this->assertCachePropertyAndExternal( self::KEY, self::VALUE, self::GROUP_1 );

		$this->assertNotCacheExternal( self::KEY, self::GROUP_2 );
		$this->object_cache->set( self::KEY, self::VALUE, self::GROUP_2 );
		$this->assertCachePropertyAndExternal( self::KEY, self::VALUE, self::GROUP_1 );

		$this->assertTrue( $this->object_cache->flush_group( self::GROUP_1 ) );
		$this->assertNotCacheExternal( self::KEY, self::GROUP_1 );
		$this->assertCachePropertyAndExternal( self::KEY, false, self::GROUP_1 );
		$this->assertCachePropertyAndExternal( self::KEY, self::VALUE, self::GROUP_2 );
	}
}
