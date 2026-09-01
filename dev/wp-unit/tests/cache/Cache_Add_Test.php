<?php

class Cache_Add_Test extends Object_Cache_TestCase {
	/**
	 * Verify "add" method with string as value
	 */
	public function test_add_string() : void {
		$key = microtime();
		$value = 'brodeur';

		// Add string to Opcache
		$this->assertTrue( $this->object_cache->add( $key, $value ) );

		// Verify correct value is returned
		$this->assertCachePropertyAndExternal( $key, $value );
	}


	/**
	 * Verify "add" method with an int as value.
	 */
	public function test_add_int() : void {
		$key = microtime();
		$value = 42;

		// Add int to Opcache
		$this->assertTrue( $this->object_cache->add( $key, $value ) );

		// Verify correct value is returned
		$this->assertCachePropertyAndExternal( $key, $value );
	}


	/**
	 * Verify "add" method with an array as value.
	 */
	public function test_add_array() : void {
		$key = microtime();
		$value = [ 5, 'quick' ];

		// Add an array to Opcache
		$this->assertTrue( $this->object_cache->add( $key, $value ) );

		// Verify correct value is returned
		$this->assertCachePropertyAndExternal( $key, $value );
	}


	/**
	 * Verify "add" method values when adding second object with existing key.
	 */
	public function test_add_fails_if_key_exists() : void {
		$key = microtime();
		$value1 = 'parise';
		$value2 = 'king';

		// Verify that one value is added to cache
		$this->assertTrue( $this->object_cache->add( $key, $value1 ) );

		// Make sure second value with same key fails
		$this->assertFalse( $this->object_cache->add( $key, $value2 ) );

		// Verify correct value is returned
		$this->assertCachePropertyAndExternal( $key, $value1 );
	}


	public function test_add_with_expiration_of_30_days() : void {
		$key = 'usa';
		$value = 'merica';
		$group = 'july';
		// 30 days
		$expiration = 60 * 60 * 24 * 30;

		$this->assertTrue( $this->object_cache->add( $key, $value, $group, $expiration ) );

		// Verify correct value is returned
		$this->assertCachePropertyAndExternal( $key, $value, $group );
	}
}
