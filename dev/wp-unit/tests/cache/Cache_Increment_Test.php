<?php

class Cache_Increment_Test extends Object_Cache_TestCase {

	public function test_increment_increases_value_by_1() : void {
		$key = microtime();

		$value = 99;

		// Verify set
		$this->assertTrue( $this->object_cache->set( $key, $value ) );

		// Verify value
		$this->assertSame( $value, $this->object_cache->get( $key ) );

		// Verify that value was properly incremented
		$this->test_cache_local_and_external_incr( $key, 100 );
	}


	public function test_increment_increases_value_by_x() : void {
		$key = microtime();

		$value = 99;
		$x = 5;

		$reduced_value = $value + $x;

		// Verify set
		$this->assertTrue( $this->object_cache->set( $key, $value ) );

		// Verify value
		$this->assertSame( $value, $this->object_cache->get( $key ) );

		// Verify that value was properly incremented
		$this->test_cache_local_and_external_incr( $key, $reduced_value, $x );
	}


	private function test_cache_local_and_external_incr( $key, $value, int $x = 1, $group = 'default' ) : void {
		$built_key = $this->object_cache->key( $key, $group );
		// Verify correct value and type is returned
		$this->assertSame( $value, $this->object_cache->incr( $key, $x, $group ) );
		unset( $this->object_cache->cache[ $built_key ] );
		$this->assertSame( $value + $x, $this->object_cache->incr( $key, $x, $group ) );
	}
}
