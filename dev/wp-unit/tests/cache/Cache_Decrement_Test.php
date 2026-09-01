<?php

class Cache_Decrement_Test extends Object_Cache_TestCase {

	public function test_decrement_non_existing() : void {
		$key = microtime();

		$this->object_cache->delete( $key );
		$this->assertEquals( 0, $this->object_cache->decr( $key, 5 ) );
	}


	public function test_decrement_reduces_value_by_1() : void {
		$key = microtime();

		$value = 99;

		// Verify set
		$this->assertTrue( $this->object_cache->set( $key, $value ) );

		// Verify value
		$this->assertSame( $value, $this->object_cache->get( $key ) );

		// Verify that value was properly decremented
		$this->test_cache_local_and_external_decr( $key, 98 );
	}


	public function test_decrement_reduces_value_by_x() : void {
		$key = microtime();

		$value = 99;
		$x = 5;

		$reduced_value = $value - $x;

		// Verify set
		$this->assertTrue( $this->object_cache->set( $key, $value ) );

		// Verify value
		$this->assertSame( $value, $this->object_cache->get( $key ) );

		// Verify that value was properly decremented
		$this->test_cache_local_and_external_decr( $key, $reduced_value, $x );
	}


	private function test_cache_local_and_external_decr( $key, $value, int $x = 1, $group = 'default' ) : void {
		$built_key = $this->object_cache->key( $key, $group );
		// Verify correct value and type is returned
		$this->assertSame( $value, $this->object_cache->decr( $key, $x, $group ) );
		unset( $this->object_cache->cache[ $built_key ] );
		$this->assertSame( $value - $x, $this->object_cache->decr( $key, $x, $group ) );
	}
}
