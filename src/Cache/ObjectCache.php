<?php
declare( strict_types=1 );

namespace Lipe\Mu\Cache;

/**
 * Interface for a persistent cache of any type.
 *
 * @author  Mat Lipe
 *
 * @version 2.0.0
 */
interface ObjectCache {
	public function key( int|string $key, string $group = 'default' ): string;


	public function get( int|string $id, string $group = 'default', bool $force = false, ?bool &$found = null ): mixed;


	/**
	 * @param array<int|string> $keys
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_multiple( array $keys, string $group = '', bool $force = false ): array;


	public function set( int|string $id, mixed $data, string $group = 'default', int $expire = 0 ): bool;


	/**
	 * @param array<int|string, mixed> $data
	 *
	 * @return array<int|string, bool>
	 */
	public function set_multiple( array $data, string $group = 'default', int $expire = 0 ): array;


	public function add( int|string $id, mixed $data, string $group = 'default', int $expire = 0 ): bool;


	public function replace( int|string $id, mixed $data, string $group = 'default', int $expire = 0 ): bool;


	public function delete( int|string $id, string $group = 'default' ): bool;


	/**
	 * @param array<int|string> $keys
	 *
	 * @return array<int|string, bool>
	 */
	public function delete_multiple( array $keys, string $group = 'default' ): array;


	public function incr( int|string $id, int $offset = 1, string $group = 'default' ): int|false;


	public function decr( int|string $id, int $offset = 1, string $group = 'default' ): int|false;


	public function flush_group( string $group = 'default' ): bool;


	public function flush(): bool;


	public function close(): bool;


	public function switch_to_blog( int $blog_id ): void;


	/**
	 * @param string|string[] $groups
	 */
	public function add_global_groups( string|array $groups ): void;


	/**
	 * @param string|string[] $groups
	 */
	public function add_non_persistent_groups( string|array $groups ): void;
}
