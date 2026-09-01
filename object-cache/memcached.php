<?php
declare( strict_types=1 );

/*
Plugin Name: Memcached
Description: MemcacheD backend for the WP Object Cache.
Version: 9.2.0
Author: Mat Lipe

As of version 6, this requires MemcacheD to be available and
does not support Memcache.
*/

use Lipe\Mu\Cache\ObjectCache;
use Lipe\Mu\Cache\Object_Cache_Base;

// Stop direct access
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! \defined( 'WP_OBJECT_CACHE_COMPRESS' ) ) {
	\define( 'WP_OBJECT_CACHE_COMPRESS', false );
}

final class WP_Object_Cache extends Object_Cache_Base implements ObjectCache {
	public const string DEFAULT_GROUP = 'default';

	/**
	 * Memcache servers organized by cache groups.
	 *
	 * Different buckets may be used for different groups.
	 *
	 * @var Memcached[]
	 */
	protected array $mc = [];


	/**
	 * Connect the memcache server.
	 *
	 * Optionally specify separate servers for particular cache groups
	 * as `$memcached_servers` array keys.
	 *
	 * @example `$memcached_servers = [
	 *              'default' => '127.0.0.1:11211',
	 *              'custom-cache-group' => '41.12.10.99:11211',
	 *          ];`
	 *
	 * Hostinger
	 * @example `$memcached_servers = [
	 *          '[::1]:11211'
	 *          [ '::1' => '11211']
	 * ]
	 *
	 */
	public function __construct() {
		global $memcached_servers;
		$buckets = $memcached_servers ?? [ '127.0.0.1:11211' ];

		\reset( $buckets );
		// A single server for all groups.
		if ( \is_int( \key( $buckets ) ) ) {
			$buckets = [ self::DEFAULT_GROUP => $buckets ];
		}

		foreach ( $buckets as $bucket => $servers ) {
			$this->mc[ $bucket ] = new Memcached();
			$this->mc[ $bucket ]->setOption( Memcached::OPT_COMPRESSION, WP_OBJECT_CACHE_COMPRESS );
			$instances = [];
			foreach ( (array) $servers as $server ) {
				[ $node, $port ] = $this->parse_memcache_server( $server );
				$instances[] = [ $node, $port, 1 ];
			}
			$this->mc[ $bucket ]->addServers( $instances );
		}

		parent::__construct();
	}


	/**
	 * __clone not allowed
	 */
	private function __clone() {
	}


	/**
	 * Use for stat reports.
	 *
	 * @return string
	 */
	protected function get_cache_type(): string {
		return 'Memcached';
	}


	/**
	 * Memcached treats expiration times over 30 days as Unix Time. Because of this, if
	 * a user tries to set cache with an expiration to over 30 days, we need to convert it.
	 *
	 * @param int $seconds - Time to expire in seconds.
	 *
	 * @return int
	 */
	protected function convert_expire_time( int $seconds ): int {
		$expire = ( 0 === $seconds ) ? $this->default_expiration : $seconds;
		if ( $expire > 30 * DAY_IN_SECONDS ) {
			$expire = time() + $expire;
		}
		return $expire;
	}


	/**
	 * Adds data to the cache, if the cache id does not already exist.
	 *
	 * @param int|string $id     - What the contents in the cache are called.
	 * @param mixed      $data   The data to add to the cache store.
	 * @param string     $group  The group to add the cache to.
	 * @param int        $expire When the cache data should be expired.
	 *
	 * @return bool False if cache id and group already exist, true on success
	 */
	public function add( int|string $id, mixed $data, string $group = self::DEFAULT_GROUP, int $expire = 0 ): bool {
		if ( wp_suspend_cache_addition() ) {
			return false;
		}
		$key = $this->key( $id, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		if ( in_array( $group, $this->no_cache_groups, true ) ) {
			$this->cache[ $key ] = $data;

			return true;
		}

		if ( isset( $this->cache[ $key ] ) && false !== $this->cache[ $key ] ) {
			return false;
		}

		$mc = $this->get_mc( $group );
		$this->timer_start();
		$result = $mc->add( $key, $data, $this->convert_expire_time( $expire ) );
		$elapsed = $this->timer_stop();

		if ( false !== $result ) {
			$this->update_stats( 'add', $group, $id, $elapsed, $data );
			$this->cache[ $key ] = $data;
		}

		return $result;
	}


	/**
	 * If a value does not exist in the cache we can't increment
	 * it, so we add it first. Memcache will do nothing when add is
	 * called on an existing key.
	 *
	 * @param int|string $id     - What the contents in the cache are called.
	 * @param int        $offset - Count to increment.
	 * @param string     $group  - Where the cache contents are grouped.
	 *
	 * @return int|false
	 */
	public function incr( int|string $id, int $offset = 1, string $group = self::DEFAULT_GROUP ): int|false {
		$key = $this->key( $id, $group );
		$mc = $this->get_mc( $group );
		$this->timer_start();
		$mc->add( $key, 0 );
		$this->cache[ $key ] = (int) $mc->increment( $key, $offset );
		$this->update_stats( 'incr', $group, $id, $this->timer_stop() );

		return $this->cache[ $key ];
	}


	/**
	 * The returned value will never be lower than 0, if a value does
	 * not exist in the cache, it will always return 0.
	 *
	 * @param int|string $id    - What the contents in the cache are called.
	 * @param int        $offset
	 * @param string     $group - Where the cache contents are grouped.
	 *
	 * @return int|false
	 */
	public function decr( int|string $id, int $offset = 1, string $group = self::DEFAULT_GROUP ): int|false {
		$key = $this->key( $id, $group );
		$mc = $this->get_mc( $group );
		$this->timer_start();
		$this->cache[ $key ] = $mc->decrement( $key, $offset );
		$this->update_stats( 'decr', $group, $id, $this->timer_stop() );

		return $this->cache[ $key ];
	}


	/**
	 * Remove the contents of the cache key in the group
	 *
	 * If the cache key does not exist in the group, then nothing will happen.
	 *
	 * @param int|string $id    - What the contents in the cache are called.
	 * @param string     $group - Where the cache contents are grouped.
	 *
	 * @return bool False if the contents weren't deleted and true on success.
	 */
	public function delete( int|string $id, string $group = self::DEFAULT_GROUP ): bool {
		$key = $this->key( $id, $group );
		unset( $this->cache[ $key ] );
		if ( in_array( $group, $this->no_cache_groups, true ) ) {
			return true;
		}

		$mc = $this->get_mc( $group );

		$this->timer_start();
		$result = $mc->delete( $key );
		$this->update_stats( 'delete', $group, $id, $this->timer_stop() );

		return $result;
	}


	/**
	 * Flush all known Memcache buckets
	 *
	 * @return bool
	 */
	public function flush(): bool {
		$this->cache = [];
		$this->reset_stats();
		foreach ( $this->mc as $mc ) {
			if ( ! $mc->flush() ) {
				return false;
			}
		}
		return true;
	}


	/**
	 * Close all connections.
	 */
	public function close(): bool {
		foreach ( $this->mc as $mc ) {
			if ( ! $mc->quit() ) {
				return false;
			}
		}
		return true;
	}


	/**
	 * Retrieves the cache contents, if it exists
	 *
	 * The contents will be first attempted to be retrieved by searching by the
	 * id in the cache id. If the cache is hit (found) then the contents
	 * are returned.
	 *
	 * On failure, the number of cache misses will be incremented.
	 *
	 * @param-out bool   $found
	 *
	 * @param int|string $id    - What the contents in the cache are called.
	 * @param string     $group - Where the cache contents are grouped.
	 * @param bool       $force - Force pulling from memcache instead of this class' `cache` property.
	 * @param null|bool  $found - Set to true/false if we have a cached value
	 *
	 * @return bool|mixed False on failure to retrieve contents, or the cache contents on found
	 */
	public function get( int|string $id, string $group = self::DEFAULT_GROUP, bool $force = false, ?bool &$found = null ): mixed {
		$key = $this->key( $id, $group );
		$mc = $this->get_mc( $group );
		$value = false;
		$found = false;

		if ( isset( $this->cache[ $key ] ) && ( ! $force || \in_array( $group, $this->no_cache_groups, true ) ) ) {
			if ( \is_object( $this->cache[ $key ] ) ) {
				$value = clone $this->cache[ $key ];
			} else {
				$value = $this->cache[ $key ];
			}
			$found = true;
			$this->update_stats( 'get', $group, $id );
		} elseif ( \in_array( $group, $this->no_cache_groups, true ) ) {
			$this->cache[ $key ] = false;
		} else {
			$this->timer_start();
			$value = $mc->get( $key );
			$elapsed = $this->timer_stop();
			if ( - 1 === $value ) {
				$value = false;
			}
			if ( false === $value ) {
				$found = $mc->getResultCode() !== Memcached::RES_NOTFOUND;
			} else {
				$found = true;
			}
			$this->cache[ $key ] = $value;
			$this->update_stats( 'external', $group, $id, $elapsed, $value );
		}
		if ( ! $found ) {
			$this->update_stats( 'miss', $group, $id );
		}

		return $value;
	}


	/**
	 * Deletes multiple values from the cache in one call.
	 *
	 * @param array<int|string> $keys  Array of keys under which the cache contents are stored.
	 * @param string            $group Where the cache contents are grouped.
	 *
	 * @return array<int|string, bool>
	 */
	public function delete_multiple( array $keys, string $group = self::DEFAULT_GROUP ): array {
		$keys = \array_unique( $keys );
		$return = [];
		$keys_to_delete = [];
		$mc = $this->get_mc( $group );
		foreach ( $keys as $id ) {
			$cache_key = $this->key( $id, $group );
			$return[ $cache_key ] = false;
			unset( $this->cache[ $cache_key ] );
			if ( \in_array( $group, $this->no_cache_groups, true ) ) {
				continue;
			}
			$keys_to_delete[ $cache_key ] = $id;
		}

		$elapsed = 0;
		if ( \count( $keys_to_delete ) > 0 ) {
			$this->timer_start();
			$results = $mc->deleteMulti( \array_keys( $keys_to_delete ) );
			$elapsed = $this->timer_stop();
			$return = \array_merge( $return, $results );
			\array_walk( $results, function( $value, $key ) use ( $group, $keys_to_delete ) {
				if ( isset( $keys_to_delete[ $key ] ) ) {
					$this->update_stats( 'delete', $group, $keys_to_delete[ $key ] );
				}
			} );
		}

		$this->update_stats( 'delete_multi', $group, \implode( ', ', $keys ), $elapsed );

		return \array_combine( $keys, \array_values( $return ) );
	}


	/**
	 * Retrieve multiple values at once.
	 * Faster than retrieving individual values.
	 *
	 * @param array<int|string> $keys  - Array of keys to retrieve.
	 * @param string            $group - Where the cache contents are grouped
	 *
	 * @param bool              $force - Force pulling from the external cache instead of the cache property.
	 *
	 * @return array<int|string, false|mixed> Value for each key if found, false if not.
	 */
	public function get_multiple( array $keys, string $group = self::DEFAULT_GROUP, bool $force = false ): array {
		$keys = \array_unique( $keys );
		$return = [];
		$keys_to_get = [];
		$mc = $this->get_mc( $group );
		foreach ( $keys as $id ) {
			$key = $this->key( $id, $group );
			if ( ! $force && isset( $this->cache[ $key ] ) ) {
				if ( is_object( $this->cache[ $key ] ) ) {
					$return[ $key ] = clone $this->cache[ $key ];
				} else {
					$return[ $key ] = $this->cache[ $key ];
				}
				$this->update_stats( 'get', $group, $id );
				continue;
			}
			$return[ $key ] = false;
			if ( \in_array( $group, $this->no_cache_groups, true ) ) {
				continue;
			}
			$keys_to_get[ $key ] = $id;
		}

		if ( \count( $keys_to_get ) > 0 ) {
			$this->timer_start();
			$results = $mc->getMulti( \array_keys( $keys_to_get ), Memcached::GET_PRESERVE_ORDER );
			$elapsed = $this->timer_stop();
			if ( false !== $results ) {
				foreach ( $results as $key => $value ) {
					if ( null === $value ) {
						$this->update_stats( 'miss', $group, $keys_to_get[ $key ] );
						$results[ $key ] = false;
					} else {
						$this->update_stats( 'external', $group, $keys_to_get[ $key ] );
					}
				}
				$return = \array_merge( $return, $results );
				$this->update_stats( 'get_multi', $group, \implode( ', ', $keys ), $elapsed, $results );
			}
		} else {
			$this->update_stats( 'get_multi', $group, \implode( ', ', $keys ) );
		}

		$this->cache = \array_merge( $this->cache, $return );

		return \array_combine( $keys, \array_values( $return ) );
	}


	/**
	 * Set multiple values at once.
	 * Faster than setting individual values.
	 *
	 * @param array<int|string, mixed> $data   - Array of keys to set.
	 * @param string                   $group  - Where the cache contents are grouped.
	 * @param int                      $expire - When to expire the cache contents in seconds.
	 *
	 * @return array<bool> - Array of keys and their status.
	 */
	public function set_multiple( array $data, string $group = self::DEFAULT_GROUP, int $expire = 0 ): array {
		$sets = [];
		$mc = $this->get_mc( $group );

		foreach ( $data as $id => $item ) {
			$key = $this->key( $id, $group );
			if ( \is_object( $item ) ) {
				$item = clone $item;
			}
			$this->cache[ $key ] = $item;
			if ( \in_array( $group, $this->no_cache_groups, true ) ) {
				continue;
			}
			$sets[ $key ] = $item;
			$this->update_stats( 'set', $group, $id );
		}

		$result = false;
		if ( \count( $sets ) > 0 ) {
			$this->timer_start();
			$result = $mc->setMulti( $sets, $this->convert_expire_time( $expire ) );
			$this->update_stats( 'set_multi', $group, \implode( ', ', \array_keys( $data ) ), $this->timer_stop(), $data );
		}
		return \array_fill_keys( \array_keys( $data ), $result );
	}


	/**
	 * Replace the contents in the cache, if contents already exist.
	 *
	 * @param int|string $id     - What to call the contents in the cache.
	 * @param mixed      $data   - The contents to store in the cache.
	 * @param string     $group  - Where to group the cache contents.
	 * @param int        $expire - When to expire the cache contents in seconds.
	 *
	 * @return bool False if not exists, true if contents were replaced.
	 */
	public function replace( int|string $id, mixed $data, string $group = self::DEFAULT_GROUP, int $expire = 0 ): bool {
		$key = $this->key( $id, $group );
		$mc = $this->get_mc( $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$this->timer_start();
		$result = $mc->replace( $key, $data, $this->convert_expire_time( $expire ) );
		$this->update_stats( 'replace', $group, $id, $this->timer_stop(), $data );
		if ( false !== $result ) {
			$this->cache[ $key ] = $data;
		}

		return $result;
	}


	/**
	 * Sets the data contents into the cache
	 *
	 * @param int|string $id     What to call the contents in the cache
	 * @param mixed      $data   The contents to store in the cache
	 * @param string     $group  Where to group the cache contents
	 * @param int        $expire When to expire the cache contents in seconds.
	 *
	 * @return bool True if cache set successfully else false
	 */
	public function set( int|string $id, mixed $data, string $group = 'default', int $expire = 0 ): bool {
		$key = $this->key( $id, $group );
		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$this->cache[ $key ] = $data;

		if ( in_array( $group, $this->no_cache_groups, true ) ) {
			return true;
		}

		$mc = $this->get_mc( $group );
		$this->timer_start();
		$result = $mc->set( $key, $data, $this->convert_expire_time( $expire ) );
		$elapsed = $this->timer_stop();

		if ( false !== $result ) {
			$this->update_stats( 'set', $group, $id, $elapsed, $data );
		}

		return $result;
	}


	/**
	 * Parse a "host:port" memcache server string into its host and port.
	 *
	 * IPv6 addresses must be wrapped in square brackets, e.g. '[::1]:11211'.
	 *
	 * @param string|array{0: string, 1: string|int} $server
	 *
	 * @return array{0: string, 1: int}
	 */
	private function parse_memcache_server( string|array $server ): array {
		if ( \is_array( $server ) ) {
			return [ (string) \array_key_first( $server ), (int) \array_first( $server ) ];
		}

		if ( ! \str_starts_with( $server, '[' ) ) {
			[ $host, $port ] = \explode( ':', $server );
			return [ $host, (int) $port ];
		}

		$closing_bracket = (int) \strpos( $server, ']' );
		$host = \substr( $server, 1, $closing_bracket - 1 );
		$port_separator = (int) \strpos( $server, ':', $closing_bracket );
		$port = \substr( $server, $port_separator + 1 );

		return [ $host, (int) $port ];
	}


	/**
	 * Get the instance of Memcached based on a group.
	 *
	 * Different buckets may be used for different cache groups.
	 *
	 * @param string $group - Cache group.
	 *
	 * @return Memcached
	 */
	private function get_mc( string $group = self::DEFAULT_GROUP ): Memcached {
		return $this->mc[ $group ] ?? $this->mc[ self::DEFAULT_GROUP ];
	}
}
