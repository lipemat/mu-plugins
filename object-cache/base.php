<?php
declare( strict_types=1 );

/**
 * Object Cache switch.
 *
 * Allow us to use either Memcached or Opcache based on what we have available.
 *
 * @notice  If storing lots of big objects, the Opcache is actually faster.
 *         Otherwise, Memcached is preferred.
 *
 * @see     OBJECT_CACHE_HANDLER
 *
 * Copied to `wp-content/object-cache.php` as a stub which requires this file.
 *
 * @see     stubs/object-cache.php
 *
 * @version 8.0.0
 */

// Stop direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Salt every cache key so sites sharing a Memcached instance cannot read
 * or overwrite each other's entries.
 *
 * @notice The `DB_NAME` fallback is guessable. Define `WP_CACHE_KEY_SALT` as a
 *        secret random string in `wp-config.php` on any shared cache server.
 */
if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
	define( 'WP_CACHE_KEY_SALT', DB_NAME );
}

require_once __DIR__ . '/../src/Cache/ObjectCache.php';
require_once __DIR__ . '/../src/Cache/Object_Cache_Base.php';

/**
 * Adds data to the cache, if the cache key does not already exist.
 *
 * @param int|string $key    The cache key to use for retrieval later
 * @param mixed      $data   The data to add to the cache store
 * @param string     $group  The group to add the cache to
 * @param int        $expire When the cache data should be expired
 *
 * @return bool False if cache key and group already exist, true on success.
 */
function wp_cache_add( int|string $key, mixed $data, string $group = WP_Object_Cache::DEFAULT_GROUP, int $expire = 0 ): bool {
	return WP_Object_Cache::instance()->add( $key, $data, $group, $expire );
}

/**
 * Closes the cache.
 *
 * This function has ceased to do anything since WordPress 2.5. The
 * functionality was removed along with the rest of the persistent cache. This
 * does not mean that plugins can't implement this function when they need to
 * make sure the cache is cleaned up after WordPress no longer needs it.
 *
 * @return bool Always returns True
 */
function wp_cache_close(): bool {
	return WP_Object_Cache::instance()->close();
}

/**
 * Decrement numeric cache item's value
 *
 * @param int|string $key    The cache key to increment
 * @param int        $offset The amount by which to decrement the item's value. Default is 1.
 * @param string     $group  The group the key is in.
 *
 * @return false|int False on failure, the item's new value on success.
 */
function wp_cache_decr( int|string $key, int $offset = 1, string $group = WP_Object_Cache::DEFAULT_GROUP ): bool|int {
	return WP_Object_Cache::instance()->decr( $key, $offset, $group );
}

/**
 * Removes the cache contents matching key and group.
 *
 * @param int|string $key   What the contents in the cache are called
 * @param string     $group Where the cache contents are grouped
 *
 * @return bool True on successful removal, false on failure
 */
function wp_cache_delete( int|string $key, string $group = WP_Object_Cache::DEFAULT_GROUP ): bool {
	return WP_Object_Cache::instance()->delete( $key, $group );
}

/**
 * Removes all cache items in a group.
 *
 * @param string $group Where the cache contents are grouped.
 *
 * @return bool True if group was flushed, false otherwise.
 */
function wp_cache_flush_group( string $group = WP_Object_Cache::DEFAULT_GROUP ): bool {
	return WP_Object_Cache::instance()->flush_group( $group );
}

/**
 * Removes all cache items from the in-memory runtime cache.
 *
 * Does not flush the persistent cache.
 *
 * @see   wp_cache_flush_runtime()
 *
 * @return bool True on success, false on failure.
 */
function wp_cache_flush_runtime(): bool {
	WP_Object_Cache::instance()->cache = [];
	WP_Object_Cache::instance()->reset_stats();
	return true;
}

/**
 * Removes all cache items.
 *
 * @return bool False on failure, true on success
 */
function wp_cache_flush(): bool {
	return \WP_Object_Cache::instance()->flush();
}

/**
 * Retrieve the cache contents from the cache by key and group.
 *
 * @param int|string $key   What the contents in the cache are called
 * @param string     $group Where the cache contents are grouped
 * @param bool       $force Force pulling from the external cache instead of object classes' `cache` property.
 * @param null|bool  $found Set to true/false if we have a cached value.
 *
 * @return bool|mixed False on failure to retrieve contents, or the cache contents on success.
 */
function wp_cache_get( int|string $key, string $group = WP_Object_Cache::DEFAULT_GROUP, bool $force = false, ?bool &$found = null ) {
	return WP_Object_Cache::instance()->get( $key, $group, $force, $found );
}

/**
 * Deletes multiple values from the cache in one call.
 *
 * @param array<int|string> $keys  Array of keys under which the cache contents are stored.
 * @param string            $group Where the cache contents are grouped.
 *
 * @return array<int|string, bool> Array of return values, grouped by the key.
 *                Each value is either true on success, or false if the contents were not deleted.
 */
function wp_cache_delete_multiple( array $keys, string $group = WP_Object_Cache::DEFAULT_GROUP ): array {
	return WP_Object_Cache::instance()->delete_multiple( $keys, $group );
}

/**
 * Retrieve multiple values from the cache in one call.
 *
 * @param array<int|string> $keys  Array of keys under which the cache contents are stored.
 * @param string            $group Where the cache contents are grouped.
 * @param bool              $force Force pulling from the external cache instead of the cache property.
 *
 * @return array<int|string, mixed> Array of values organized into keys.
 */
function wp_cache_get_multiple( array $keys, string $group = WP_Object_Cache::DEFAULT_GROUP, bool $force = false ): array {
	return WP_Object_Cache::instance()->get_multiple( $keys, $group, $force );
}

/**
 * Set multiple values to the cache in one call.
 *
 * @param array<int|string> $keys   Array of keys under which the cache contents are stored.
 * @param string            $group  Where the cache contents are grouped.
 * @param int               $expire When to expire the cache contents in seconds.
 *
 * @return array<int|string, bool> - Array of keys and their result.
 */
function wp_cache_set_multiple( array $keys, string $group = WP_Object_Cache::DEFAULT_GROUP, int $expire = 0 ): array {
	return WP_Object_Cache::instance()->set_multiple( $keys, $group, $expire );
}

/**
 * Increment numeric cache item's value
 *
 * @param int|string $key    The cache key to increment
 * @param int        $offset The amount by which to increment the item's value. Default is 1.
 * @param string     $group  The group the key is in.
 *
 * @return int
 */
function wp_cache_incr( int|string $key, int $offset = 1, string $group = WP_Object_Cache::DEFAULT_GROUP ): false|int {
	return WP_Object_Cache::instance()->incr( $key, $offset, $group );
}

/**
 * Sets up Object Cache Global and assigns it.
 *
 * @global WP_Object_Cache $wp_object_cache WordPress Object Cache
 */
function wp_cache_init(): void {
	$GLOBALS['wp_object_cache'] = WP_Object_Cache::instance();
}

/**
 * Replaces the contents of the cache with new data.
 *
 * @param int|string $key    What to call the contents in the cache
 * @param mixed      $data   The contents to store in the cache
 * @param string     $group  Where to group the cache contents
 * @param int        $expire When to expire the cache contents
 *
 * @return bool False if not exists, true if contents were replaced
 */
function wp_cache_replace( int|string $key, mixed $data, string $group = WP_Object_Cache::DEFAULT_GROUP, int $expire = 0 ): bool {
	return WP_Object_Cache::instance()->replace( $key, $data, $group, $expire );
}

/**
 * Saves the data to the cache.
 *
 * @param int|string $key    What to call the contents in the cache
 * @param mixed      $data   The contents to store in the cache
 * @param string     $group  Where to group the cache contents
 * @param int        $expire When to expire the cache contents in seconds
 *
 * @return bool False on failure, true on success
 */
function wp_cache_set( $key, $data, string $group = WP_Object_Cache::DEFAULT_GROUP, int $expire = 0 ): bool {
	return WP_Object_Cache::instance()->set( $key, $data, $group, $expire );
}

/**
 * Switch the internal blog id.
 *
 * Changes the blog id used to create keys in blog specific groups.
 *
 * @param int|string $blog_id Blog ID
 */
function wp_cache_switch_to_blog( int|string $blog_id ): void {
	WP_Object_Cache::instance()->switch_to_blog( $blog_id );
}

/**
 * Adds a group or set of groups to the list of global groups.
 *
 * @param string|string[] $groups A group, or an array of groups to add
 */
function wp_cache_add_global_groups( string|array $groups ): void {
	WP_Object_Cache::instance()->add_global_groups( $groups );
}

/**
 * Add a group or set of groups to list of non-persistent groups.
 *
 * @param string|string[] $groups A group, or an array of groups to add
 */
function wp_cache_add_non_persistent_groups( string|array $groups ): void {
	WP_Object_Cache::instance()->add_non_persistent_groups( $groups );
}

/**
 * Remove a group or set of groups to list of non-persistent groups.
 *
 * @param string|string[] $groups A group, or an array of groups to add
 */
function wp_cache_remove_non_persistent_groups( string|array $groups ): void {
	WP_Object_Cache::instance()->remove_non_persistent_groups( $groups );
}

/**
 * Determines whether the object cache implementation supports a particular feature.
 *
 * @param string $feature Name of the feature to check for.
 *                        Possible values include:
 *                        'add_multiple',
 *                        'set_multiple',
 *                        'get_multiple',
 *                        'delete_multiple',
 *                        'flush_runtime',
 *                        'flush_group'
 *
 * @return bool True if the feature is supported, false otherwise.
 */
function wp_cache_supports( string $feature ): bool {
	return \in_array( $feature, [
		'add_multiple',
		'set_multiple',
		'get_multiple',
		'delete_multiple',
		'flush_runtime',
		'flush_group',
	], true );
}


// For specifying a handler.
if ( \defined( 'OBJECT_CACHE_HANDLER' ) ) {
	require OBJECT_CACHE_HANDLER;
} elseif ( \class_exists( 'Memcached' ) ) {
	require __DIR__ . '/memcached.php';
} else {
	require __DIR__ . '/opcache.php';
}
