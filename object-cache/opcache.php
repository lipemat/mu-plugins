<?php
declare( strict_types=1 );
/*
Plugin Name: WordPress Opcache Cache Plugin
Description: Opcache Object Cache plugin for WordPress.
Version: 7.0.2
Author: Mat Lipe
*/

/**
 * @notice   You must set opcache to validate timestamps otherwise
 *         you'll have to manually clear the cache any time you update a
 *         setting or post or whatever.
 *
 * @example  `opcache.validate_timestamps=1`
 * @example  `opcache.revalidate_freq=0`- No delay
 * @example  `opcache.revalidate_freq=60` - Up to a 60-second delay before seeing changes
 *
 */

use Lipe\Mu\Cache\ObjectCache;
use Lipe\Mu\Cache\Object_Cache_Base;

// Stop direct access
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! \defined( 'WP_CACHE_DIR' ) ) {
	\define( 'WP_CACHE_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache' );
}

/**
 * WordPress opcache Object Cache driver.
 *
 * @link https://github.com/elcobvg/wordpress-opcache/blob/master/object-cache.php
 *
 * The WordPress Object Cache is used to save on trips to the database.
 * The OPCache Cache stores all the cache data to PHP OPCache and makes
 * the cache contents available by using a key, used to name and
 * later retrieve the cache contents.
 *
 */
final class WP_Object_Cache extends Object_Cache_Base implements ObjectCache {
	public const DEFAULT_GROUP = 'default';

	/**
	 * @var bool Stores if opcache is available.
	 */
	private bool $is_opcache_enabled;


	/**
	 * Cache entries are executable PHP, so the file names are distinct
	 * enough to flush without touching the directory's own files.
	 */
	private const string CACHE_SUFFIX = '.cache.php';

	private const string TEMP_SUFFIX = '.tmp.php';


	/**
	 * Create the cache directory.
	 */
	public function __construct() {
		$this->is_opcache_enabled = $this->is_opcache_enabled();

		if ( $this->is_opcache_enabled && ! is_dir( WP_CACHE_DIR ) && ! \mkdir( WP_CACHE_DIR, 0755, true ) && ! is_dir( WP_CACHE_DIR ) ) {
			throw new \RuntimeException( esc_html( \sprintf( 'Directory "%s" was not created', WP_CACHE_DIR ) ) );
		}
		if ( $this->is_opcache_enabled ) {
			$this->protect_cache_dir();
		}

		parent::__construct();
	}


	/**
	 * Keep the cache directory from being served over HTTP.
	 *
	 * Entries hold whatever the site caches, which includes user objects and
	 * transients holding credentials, and `get` includes them as PHP. The
	 * directory sits inside the document root by default, so it needs both a
	 * deny rule and an index.
	 *
	 * @notice Only Apache honors the `.htaccess`. Point `WP_CACHE_DIR` outside
	 *        the document root on any other server.
	 *
	 * @return void
	 */
	private function protect_cache_dir(): void {
		$htaccess = WP_CACHE_DIR . DIRECTORY_SEPARATOR . '.htaccess';
		if ( ! \file_exists( $htaccess ) ) {
			\file_put_contents( $htaccess, "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n", LOCK_EX );
		}

		$index = WP_CACHE_DIR . DIRECTORY_SEPARATOR . 'index.php';
		if ( ! \file_exists( $index ) ) {
			\file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX );
		}
	}


	/**
	 * __clone not allowed
	 */
	private function __clone() {
	}


	protected function get_cache_type(): string {
		return 'Opcache';
	}


	/**
	 * Get the expiration time based on the given seconds.
	 *
	 * Fallback to default expiration of `0` is provided.
	 *
	 * @param int $seconds
	 *
	 * @return int
	 */
	protected function convert_expire_time( int $seconds ): int {
		$seconds = ( 0 === $seconds ) ? $this->default_expiration : $seconds;
		return time() + $seconds;
	}


	public function is_opcache_enabled(): bool {
		return \extension_loaded( 'Zend OPCache' ) && false !== ini_get( 'opcache.enable' );
	}


	/**
	 * Adds data to the cache, if the cache id does not already exist.
	 *
	 * @param int|string $id     - What the contents in the cache are called.
	 * @param mixed      $data   -  The data to add to the cache store
	 * @param string     $group  - The group to add the cache to
	 * @param int        $expire - When to expire the cache contents in seconds.
	 *
	 * @return bool False if cache id and group already exist, true on success.
	 */
	public function add( int|string $id, mixed $data, string $group = self::DEFAULT_GROUP, int $expire = 0 ): bool {
		$key = $this->key( $id, $group );

		if ( in_array( $group, $this->no_cache_groups, true ) ) {
			$this->cache[ $key ] = $data;

			return true;
		}

		if ( wp_suspend_cache_addition() || $this->exists( $key ) ) {
			return false;
		}

		if ( $this->set( $id, $data, $group, $expire ) ) {
			$this->update_stats( 'add', $group, $id, 0, $data );
			$this->cache[ $key ] = $data;
		}

		return true;
	}


	/**
	 * Decrement numeric cache item's value.
	 *
	 * The returned value will never be lower than 0, if a value does
	 * not exist in the cache, it will always return 0.
	 *
	 * @param int|string $id     - The cache key to decrement
	 * @param int        $offset - The amount by which to decrement the item's value. Default is 1.
	 * @param string     $group  - Where the cache contents are grouped.
	 *
	 * @return false|int False on failure, the item's new value on success.
	 */
	public function decr( int|string $id, int $offset = 1, string $group = self::DEFAULT_GROUP ): false|int {
		$this->update_stats( 'decr', $group, $id );
		return $this->incr( $id, $offset * - 1, $group );
	}


	/**
	 * Remove the contents of the cache key in the group
	 *
	 * If the cache key does not exist in the group, then nothing will happen.
	 *
	 * @param int|string $id    -  What the contents in the cache are called.
	 * @param string     $group - Where the cache contents are grouped.
	 *
	 * @return bool False if the contents weren't deleted and true on success
	 */
	public function delete( int|string $id, string $group = self::DEFAULT_GROUP ): bool {
		$key = $this->key( $id, $group );
		$path = $this->file_path( $key );
		unset( $this->cache[ $key ] );
		if ( \in_array( $group, $this->no_cache_groups, true ) ) {
			return true;
		}

		if ( $this->is_opcache_enabled ) {
			\opcache_invalidate( $path, true );
		}
		$file = $this->file_path( $key );
		if ( \file_exists( $file ) ) {
			$this->timer_start();
			$result = \unlink( $path );
			$this->update_stats( 'delete', $group, $id, $this->timer_stop() );
			return $result;
		}

		return true;
	}


	/**
	 * Clears the object cache of all data
	 *
	 * @return bool Always returns true
	 */
	public function flush(): bool {
		$this->cache = [];
		$this->reset_stats();

		$files = \array_filter( $this->cached_files(), \is_file( ... ) );
		if ( $this->is_opcache_enabled ) {
			\array_map( \opcache_invalidate( ... ), $files );
		}

		\array_map( \unlink( ... ), $files );
		return true;
	}


	/**
	 * Do nothing in this case.
	 */
	public function close(): bool {
		return true;
	}


	/**
	 * Retrieves the cache contents, if it exists
	 *
	 * The contents will be first attempted to be retrieved by searching by the
	 * id in the cache id. If the cache is hit (found) then the contents
	 * are returned.
	 *
	 * @param-out bool   $found
	 *
	 * @param int|string $id    - What the contents in the cache are called.
	 * @param string     $group Where the cache contents are grouped
	 * @param bool       $force Force pulling from file cache instead of this classes' `cache` property.
	 * @param null|bool  $found Set to true/false if we have a cached value.
	 *
	 * @return bool|mixed False on failure to retrieve contents, or the cache contents on found.
	 */
	public function get( int|string $id, string $group = self::DEFAULT_GROUP, bool $force = false, ?bool &$found = null ): mixed {
		$data = ''; // overridden by cached data.
		$exp = 10; // overridden by cached data.
		$key = $this->key( $id, $group );
		$found = false;

		if ( ! $force && isset( $this->cache[ $key ] ) ) {
			$this->update_stats( 'get', $group, $id );
			$found = true;

			if ( is_object( $this->cache[ $key ] ) ) {
				return clone $this->cache[ $key ];
			}

			return $this->cache[ $key ];
		}

		if ( in_array( $group, $this->no_cache_groups, true ) ) {
			$this->cache[ $key ] = false;
			return false;
		}

		$file = $this->file_path( $key );

		if ( ! file_exists( $file ) ) {
			$this->cache[ $key ] = false;
			$this->update_stats( 'miss', $group, $id );
			return false;
		}

		$this->timer_start();
		include $file;
		$this->update_stats( 'external', $group, $id, $this->timer_stop(), $data );

		if ( $exp < time() ) {
			$data = null;
			$this->delete( $key, $group );
		}

		if ( null !== $data ) {
			$this->cache[ $key ] = $data;
			$found = true;
		} else {
			$this->cache[ $key ] = false;
			return false;
		}

		return $this->cache[ $key ];
	}


	/**
	 * Deletes multiple values from the cache in one call.
	 *
	 * @param array<int|string> $keys  Array of keys under which the cache contents are stored.
	 * @param string            $group Where the cache contents are grouped.
	 *
	 * @return array<int|string, bool>
	 */
	public function delete_multiple( array $keys, string $group = 'default' ): array {
		$keys = \array_unique( $keys );
		if ( \in_array( $group, $this->no_cache_groups, true ) ) {
			return \array_fill_keys( $keys, false );
		}
		$return = [];
		foreach ( $keys as $key ) {
			if ( in_array( $group, $this->no_cache_groups, true ) ) {
				$return[ $key ] = false;
				continue;
			}
			$return[ $key ] = $this->delete( $key, $group );
		}

		$this->update_stats( 'delete_multi', $group, implode( ', ', $keys ) );

		return $return;
	}


	/**
	 * Retrieve multiple values at once.
	 *
	 * @param array<int|string> $keys  - Array of keys to retrieve.
	 * @param string            $group - Where the cache contents are grouped
	 *
	 * @param bool              $force - Force pulling from the external cache instead of the cache property.
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_multiple( array $keys, string $group = self::DEFAULT_GROUP, bool $force = false ): array {
		$return = [];
		foreach ( $keys as $key ) {
			if ( in_array( $group, $this->no_cache_groups, true ) ) {
				$return[ $key ] = false;
				continue;
			}
			$return[ $key ] = $this->get( $key, $group, $force );
		}

		$this->update_stats( 'get_multi', $group, implode( ', ', $keys ) );

		return $return;
	}


	/**
	 * Set multiple values at once.
	 * Faster than setting individual values.
	 *
	 * @param array<int|string, mixed> $data   - Array of keys and values to set.
	 * @param string                   $group  - Where the cache contents are grouped.
	 * @param int                      $expire - When to expire the cache contents in seconds.
	 *
	 * @return array<int|string, bool> - Array of keys and their result.
	 */
	public function set_multiple( array $data, string $group = self::DEFAULT_GROUP, int $expire = 0 ): array {
		$results = [];
		foreach ( $data as $id => $item ) {
			$results[ $id ] = $this->set( $id, $item, $group, $expire );
		}

		$this->update_stats( 'set_multi', $group, implode( ', ', \array_keys( $data ) ) );

		return $results;
	}


	/**
	 * Increment numeric cache item's value
	 *
	 * @param int|string $id     - What the contents in the cache are called.
	 * @param int        $offset The amount by which to increment the item's value. Default is 1.
	 * @param string     $group  The group the key is in.
	 *
	 * @return false|int False on failure, the item's new value on success.
	 */
	public function incr( int|string $id, int $offset = 1, string $group = self::DEFAULT_GROUP ): false|int {
		$key = $this->key( $id, $group );
		$this->cache[ $key ] = ( (int) $this->get( $id, $group ) + $offset );
		if ( $this->cache[ $key ] < 0 ) {
			$this->cache[ $key ] = 0;
		}
		$this->update_stats( 'incr', $group, $id );
		return $this->set( $id, $this->cache[ $key ], $group ) ? $this->cache[ $key ] : false;
	}


	/**
	 * Replace the contents in the cache, if contents already exist.
	 *
	 * @param int|string $id     - What the contents in the cache are called.
	 * @param mixed      $data   - The contents to store in the cache.
	 * @param string     $group  - Where to group the cache contents.
	 * @param int        $expire - When to expire the cache contents.
	 *
	 * @return bool False if not exists, true if contents were replaced
	 */
	public function replace( int|string $id, mixed $data, string $group = self::DEFAULT_GROUP, int $expire = 0 ): bool {
		if ( ! $this->exists( $this->key( $id, $group ) ) ) {
			return false;
		}

		$this->update_stats( 'replace', $group, $id, 0, $data );
		return $this->set( $id, $data, $group, $expire );
	}


	/**
	 * Sets the data contents into the cache
	 *
	 * @param int|string $id     - What the contents in the cache are called.
	 * @param mixed      $data   - The contents to store in the cache.
	 * @param string     $group  - Where to group the cache contents.
	 * @param int        $expire - When to expire the cache contents.
	 *
	 * @return bool True if cache set successfully else false
	 */
	public function set( int|string $id, mixed $data, string $group = self::DEFAULT_GROUP, int $expire = 0 ): bool {
		$key = $this->key( $id, $group );

		$this->cache[ $key ] = $data;
		$expire = \max( $expire, 0 );

		if ( \in_array( $group, $this->no_cache_groups, true ) ) {
			return true;
		}

		// Keep the runtime value, but never write an object this cache cannot rebuild.
		if ( ! $this->is_persistable( $data ) ) {
			return true;
		}

		$data = \var_export( $data, true );

		$this->timer_start();
		$result = $this->write_file( $key, $this->convert_expire_time( $expire ), $this->handle__set_state( $data ) );
		$this->update_stats( 'set', $group, $id, $this->timer_stop(), $data );
		return $result;
	}


	/**
	 * May this value be written to a cache file?
	 *
	 * `var_export` renders an unknown object as `\Some\Class::__set_state(…)`,
	 * which `get` executes when the entry is read back. Any class outside the
	 * map is therefore both a broken read and a callable this cache never
	 * intended to invoke, so such values stay in the runtime cache only.
	 *
	 * @param mixed $data - Value headed for the cache file.
	 *
	 * @return bool
	 */
	private function is_persistable( mixed $data ): bool {
		if ( \is_object( $data ) ) {
			if ( ! \array_key_exists( $data::class, $this->get_set_state_map() ) ) {
				return false;
			}
			$data = \get_object_vars( $data );
		}

		if ( \is_array( $data ) ) {
			foreach ( $data as $value ) {
				if ( ! $this->is_persistable( $value ) ) {
					return false;
				}
			}
		}

		return true;
	}


	/**
	 * Classes which may be written to a cache file, mapped to the search and
	 * replacement used to turn `var_export` output back into a constructor.
	 *
	 * Adding an entry both allows the class to be persisted and describes how
	 * to rebuild it, so the two can never drift apart.
	 *
	 * `var_export` renders `stdClass` as an `(object)` cast already, so it
	 * needs no replacement and maps to `null`.
	 *
	 * @return array<class-string, ?array{0: string, 1: string}>
	 */
	private function get_set_state_map(): array {
		/**
		 * Filter the classes this cache is able to store on disk.
		 *
		 * @param array<class-string, ?array{0: string, 1: string}> $map
		 */
		return apply_filters( 'lipe/mu/object-cache/set-state-map', [
			'stdClass' => null,
			'WP_Post'  => [ '\WP_Post::__set_state(', 'new \WP_Post((object)' ],
			'WP_Site'  => [ '\WP_Site::__set_state(', 'new \WP_Site((object)' ],
			'WP_User'  => [ '\WP_User::__set_state(', 'new \WP_User((object)' ],
		] );
	}


	/**
	 * Checks if the cached OPCache key exists
	 *
	 * @param string $key - What the contents in the cache are called.
	 *
	 * @return bool True if cache key exists else false
	 */
	private function exists( string $key ): bool {
		return ( $this->is_opcache_enabled && opcache_is_script_cached( $this->file_path( $key ) ) ) || file_exists( $this->file_path( $key ) );
	}


	/**
	 * Every file this cache owns, so a flush leaves the directory's
	 * `.htaccess` and `index.php` in place.
	 *
	 * @return array<int, string>
	 */
	private function cached_files(): array {
		$files = [];
		foreach ( [ self::CACHE_SUFFIX, self::TEMP_SUFFIX ] as $suffix ) {
			$found = \glob( WP_CACHE_DIR . '/*' . $suffix );
			if ( \is_array( $found ) ) {
				$files = [ ...$files, ...$found ];
			}
		}

		return $files;
	}


	/**
	 * Get fully qualified file path.
	 *
	 * The `.php` extension keeps a direct HTTP request from returning the
	 * cached value as text where the directory is reachable. Executing the
	 * file emits nothing because it only assigns variables.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function file_path( string $key ): string {
		return WP_CACHE_DIR . '/' . preg_replace( '/[^A-Za-z0-9_-]/', '--', $key ) . self::CACHE_SUFFIX;
	}


	/**
	 * Write the cache file to disk.
	 *
	 * @param string $key
	 * @param int    $exp
	 * @param string $data
	 *
	 * @return  bool
	 */
	private function write_file( string $key, int $exp, string $data ): bool {
		// Write to temp file first to ensure an autonomy. Use crc32 for speed.
		$tmp = WP_CACHE_DIR . '/' . \crc32( $key ) . '-' . \uniqid( '', true ) . self::TEMP_SUFFIX;
		\file_put_contents( $tmp, '<?php $exp = ' . $exp . '; $data = ' . $data . ';', LOCK_EX );

		return \rename( $tmp, $this->file_path( $key ) );
	}


	/**
	 * __set_state is not available on most classes, so we have to
	 * replace the cases set by var_export().
	 *
	 * @notice `var_export` fully qualifies the class, so the leading
	 *        backslash is part of what gets replaced.
	 *
	 * @link http://us1.php.net/manual/en/language.oop5.magic.php#object.set-state
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	private function handle__set_state( string $data ): string {
		$cases = [];
		$replacements = [];
		foreach ( $this->get_set_state_map() as $pair ) {
			if ( \is_array( $pair ) ) {
				$cases[] = $pair[0];
				$replacements[] = $pair[1];
			}
		}

		return \str_replace( $cases, $replacements, $data );
	}
}
