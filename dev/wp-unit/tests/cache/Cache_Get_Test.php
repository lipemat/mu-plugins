<?php

class Cache_Get_Test extends Object_Cache_TestCase {
	public function test_get_value() : void {
		$key = microtime();
		$value = 'brodeur';

		// Add string to Opcache
		$this->assertTrue( $this->object_cache->add( $key, $value ) );

		// Verify correct value is returned
		$this->assertCachePropertyAndExternal( $key, $value );
	}


	public function test_get_value_with_group() : void {
		$key = microtime();
		$value = 'brodeur';

		$group = 'devils';

		// Add string to Opcache
		$this->assertTrue( $this->object_cache->add( $key, $value, $group ) );

		// Verify correct value is returned
		$this->assertCachePropertyAndExternal( $key, $value, $group );
	}


	public function test_get_value_with_found_indicator_when_value_is_not_found() : void {
		$key = microtime();
		$value = 'neil';
		$group = 'senators';
		$found = false;

		// Add string to Opcache
		$this->assertTrue( $this->object_cache->add( $key, $value, $group ) );

		// Verify that the value is deleted
		$this->assertTrue( $this->object_cache->delete( $key, $group ) );

		// Verify that false is returned
		$this->assertFalse( $this->object_cache->get( $key, $group, false, $found ) );

		$this->assertFalse( $found );
	}


	public function test_get_empty_value_types() : void {
		$this->object_cache->set( 'empty-string', '0', 'unit-tests' );
		$this->object_cache->set( 'empty-number', 0, 'unit-tests' );

		$this->assertCachePropertyAndExternal( 'empty-string', '0', 'unit-tests' );
		$this->assertCachePropertyAndExternal( 'empty-number', 0, 'unit-tests' );
	}
}
