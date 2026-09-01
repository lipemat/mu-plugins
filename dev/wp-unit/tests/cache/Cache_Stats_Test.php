<?php
declare( strict_types=1 );

namespace Lipe\Mu\Cache;

/**
 * @author Mat Lipe
 * @since  November 2023
 *
 */
class Cache_Stats_Test extends \Object_Cache_TestCase {

	public function test_stats_structure(): void {
		$stats = $this->object_cache->get_stats();
		$this->assertIsArray( $stats );
		$expectedKeys = [ 'totals', 'operation_counts', 'operations', 'groups', 'slow-ops', 'slow-ops-groups' ];
		foreach ( $expectedKeys as $key ) {
			$this->assertArrayHasKey( $key, $stats );
		}
		$this->assertIsArray( $stats['totals'] );
		$this->assertArrayHasKey( 'query_time', $stats['totals'] );
		$this->assertArrayHasKey( 'size', $stats['totals'] );
		$this->assertIsArray( $stats['operation_counts'] );
		$this->assertIsArray( $stats['operations'] );
		$this->assertIsArray( $stats['groups'] );
		$this->assertIsArray( $stats['slow-ops'] );
		$this->assertIsArray( $stats['slow-ops-groups'] );
	}


	public function test_add_operation(): void {
		$key = 'testKey';
		$value = 'testValue';

		wp_cache_add( $key, $value );

		$stats = $this->object_cache->get_stats();
		$this->assertEquals( 1, $stats['operation_counts']['add'] );
		$actual = $stats['operations']['add']['testKey.default'];
		$this->assertIsFloat( $actual['time'] );
		unset( $actual['time'] );

		$this->assertEquals( [
			'count'  => 1,
			'group'  => 'default',
			'key'    => 'testKey',
			'size'   => 9,
			'result' => 'add',
		], $actual );
	}


	public function test_remove_operation(): void {
		$key = 'testKey';
		$value = 'testValue';

		wp_cache_add( $key, $value );
		wp_cache_delete( $key );

		$stats = $this->object_cache->get_stats();
		$this->assertEquals( 1, $stats['operation_counts']['delete'] );
		$actual = $stats['operations']['delete']['testKey.default'];
		$this->assertIsFloat( $actual['time'] );
		unset( $actual['time'] );

		$this->assertEquals( [
			'count'  => 1,
			'group'  => 'default',
			'key'    => 'testKey',
			'size'   => 0,
			'result' => 'delete',
		], $actual );
	}


	public function test_replace_operations(): void {
		$key = 'testKey';
		$value = 'testValue';
		$value2 = 'testValue2';

		wp_cache_add( $key, $value );
		wp_cache_replace( $key, $value2 );

		$stats = $this->object_cache->get_stats();
		$this->assertEquals( 1, $stats['operation_counts']['replace'] );
		$actual = $stats['operations']['replace']['testKey.default'];
		$this->assertIsFloat( $actual['time'] );
		unset( $actual['time'] );

		$this->assertEquals( [
			'count'  => 1,
			'group'  => 'default',
			'key'    => 'testKey',
			'size'   => 10,
			'result' => 'replace',
		], $actual );
	}


	public function test_incr_operation(): void {
		$key = 'testKey';
		$value = 10;

		wp_cache_add( $key, $value );
		wp_cache_incr( $key );

		$stats = $this->object_cache->get_stats();
		$this->assertEquals( 1, $stats['operation_counts']['incr'] );

		$this->assertEquals( 11, wp_cache_get( $key ) );
	}


	public function test_decr_operation(): void {
		$key = 'testKey';
		$value = 10;

		wp_cache_add( $key, $value );
		wp_cache_decr( $key );

		$stats = $this->object_cache->get_stats();
		$this->assertEquals( 1, $stats['operation_counts']['decr'] );

		$this->assertEquals( 9, wp_cache_get( $key ) );
	}


	public function test_get_multi_operation(): void {
		$keys = [ 'testKey1', 'testKey2' ];
		$values = [ 'testValue1', 'testValue2' ];

		wp_cache_set_multiple( array_combine( $keys, $values ) );
		$retrievedValues = wp_cache_get_multiple( $keys );

		$stats = $this->object_cache->get_stats();
		$this->assertEquals( 1, $stats['operation_counts']['get_multi'] );

		$this->assertEquals( array_combine( $keys, $values ), $retrievedValues );
	}


	public function test_delete_multi_operation(): void {
		$keys = [ 'testKey1', 'testKey2' ];
		$values = [ 'testValue1', 'testValue2' ];

		wp_cache_set_multiple( array_combine( $keys, $values ) );
		wp_cache_delete_multiple( $keys );

		$stats = $this->object_cache->get_stats();
		$this->assertEquals( 1, $stats['operation_counts']['delete_multi'] );

		foreach ( $keys as $key ) {
			$this->assertFalse( wp_cache_get( $key ) );
		}
	}


	public function test_wp_debug_false(): void {
		$this->assertTrue( WP_DEBUG );
		$cache = $this->object_cache;

		set_private_property( $this->object_cache, 'wp_debug', false );
		call_private_method( $this->object_cache, 'timer_start' );
		$this->assertEquals( 0, get_private_property( $cache, 'time_start' ) );
		$this->assertEquals( 0, call_private_method( $cache, 'timer_stop' ) );

		wp_cache_add( 'test', 'nothing' );
		$stats = $cache->get_stats();
		$this->assertEquals( 0, $stats['totals']['query_time'] );
		$this->assertEquals( 0, $stats['totals']['size'] );
		$this->assertEquals( 0, $stats['operation_counts']['add'] );
	}


	public function test_multiple_groups_same_key(): void {
		$key = 'testKey';
		$value = 'testValue';

		wp_cache_add( $key, $value, 'group1' );
		wp_cache_add( $key, $value, 'group2' );

		$stats = $this->object_cache->get_stats();
		$this->assertCount( 2, $stats['operations']['add'] );
		$this->assertEquals( 1, \reset( $stats['operations']['add'] )['count'] );
		$this->assertSame( 'group1', \reset( $stats['operations']['add'] )['group'] );
		$this->assertEquals( 1, \end( $stats['operations']['add'] )['count'] );
		$this->assertSame( 'group2', \end( $stats['operations']['add'] )['group'] );
	}
}
