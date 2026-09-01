<?php
declare( strict_types=1 );

use Lipe\WP_Unit\Utils\PrivateAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresMethod;

/**
 * The opcache handler writes executable PHP into a directory which sits
 * inside the document root by default, so these cover what keeps that
 * from being reachable or from executing something unexpected.
 *
 * `is_opcache_enabled` only exists on the opcache handler, which is how
 * the whole class is skipped when Memcached is in use.
 */
#[CoversClass( WP_Object_Cache::class )]
#[RequiresMethod( WP_Object_Cache::class, 'is_opcache_enabled' )]
final class Cache_Security_Test extends Object_Cache_TestCase {
	public function test_construct_writes_htaccess_deny_rule(): void {
		$htaccess = WP_CACHE_DIR . DIRECTORY_SEPARATOR . '.htaccess';
		\unlink( $htaccess );
		$this->assertFileDoesNotExist( $htaccess, 'The arrange must start without the deny rule.' );

		new WP_Object_Cache();

		$this->assertStringContainsString( 'Require all denied', (string) \file_get_contents( $htaccess ), 'The cache directory must deny direct requests.' );
	}


	public function test_construct_writes_index_file(): void {
		$index = WP_CACHE_DIR . DIRECTORY_SEPARATOR . 'index.php';
		\unlink( $index );
		$this->assertFileDoesNotExist( $index, 'The arrange must start without the index file.' );

		new WP_Object_Cache();

		$this->assertFileExists( $index, 'The cache directory must block folder indexing.' );
	}


	public function test_cache_file_uses_php_extension(): void {
		$this->object_cache->set( 'goalie', 'brodeur' );

		$path = $this->file_path( 'goalie' );

		$this->assertStringEndsWith( '.cache.php', $path, 'A direct request must hit PHP rather than return the value as text.' );
		$this->assertFileExists( $path, 'The value should have been written to that path.' );
	}


	public function test_flush_keeps_directory_protection(): void {
		$this->object_cache->set( 'goalie', 'brodeur' );
		$htaccess = WP_CACHE_DIR . DIRECTORY_SEPARATOR . '.htaccess';
		$this->assertFileExists( $htaccess, 'The arrange must start with the deny rule in place.' );

		$this->object_cache->flush();

		$this->assertFileExists( $htaccess, 'Flushing entries must leave the deny rule behind.' );
	}


	public function test_flush_removes_cache_files(): void {
		$this->object_cache->set( 'goalie', 'brodeur' );
		$this->assertFileExists( $this->file_path( 'goalie' ), 'The arrange must start with a cache file.' );

		$this->object_cache->flush();

		$this->assertFileDoesNotExist( $this->file_path( 'goalie' ), 'Flushing must remove the entry from disk.' );
	}


	public function test_set_persists_mapped_class(): void {
		$post = self::factory()->post->create_and_get();

		$this->object_cache->set( 'goalie', $post );

		$this->assertFileExists( $this->file_path( 'goalie' ), 'WP_Post is in the set state map, so it belongs on disk.' );
	}


	public function test_set_skips_unmapped_class(): void {
		$term = self::factory()->term->create_and_get( [ 'taxonomy' => 'category' ] );

		$this->object_cache->set( 'goalie', $term );

		$this->assertFileDoesNotExist( $this->file_path( 'goalie' ), 'WP_Term has no __set_state, so reading it back would execute an unintended call.' );
	}


	public function test_set_skips_unmapped_class_nested_in_array(): void {
		$term = self::factory()->term->create_and_get( [ 'taxonomy' => 'category' ] );

		$this->object_cache->set( 'goalie', [ 'terms' => [ $term ] ] );

		$this->assertFileDoesNotExist( $this->file_path( 'goalie' ), 'An unmapped class buried in an array is the same risk as a bare one.' );
	}


	public function test_set_skips_unmapped_class_but_keeps_runtime_value(): void {
		$term = self::factory()->term->create_and_get( [ 'taxonomy' => 'category' ] );

		$this->object_cache->set( 'goalie', $term );

		$this->assertSame( $term->term_id, $this->object_cache->get( 'goalie' )->term_id, 'Refusing to write must not lose the value for this request.' );
	}


	public function test_get_rebuilds_mapped_class_from_file(): void {
		$post = self::factory()->post->create_and_get();
		$this->object_cache->set( 'goalie', $post );
		$this->object_cache->cache = [];

		$found = $this->object_cache->get( 'goalie' );

		$this->assertSame( $post->ID, $found->ID, 'var_export fully qualifies the class, so the rewrite has to account for the leading backslash.' );
	}


	/**
	 * Resolve where an entry lands on disk, matching the handler's own naming.
	 */
	private function file_path( string $key, string $group = 'default' ): string {
		return (string) PrivateAccess::in()->call_private_method( $this->object_cache, 'file_path', [ $this->get_cache_key( $key, $group ) ] );
	}
}
