<?php
declare( strict_types=1 );

namespace Lipe\Mu\Cache;

/**
 * Shared implementation for every object cache handler.
 *
 * Loaded manually by `object-cache/base.php` because the drop-in runs
 * before the Composer autoloader is registered.
 *
 * @version 2.0.0
 */
/**
 * @phpstan-type STAT 'add'|'delete'|'delete_multi'|'external'|'get'|'get_multi'|'miss'|'set'|'set_multi'|'incr'|'decr'|'replace'|'slow-ops'
 */
abstract class Object_Cache_Base {
	public const DEFAULT_GROUP = 'default';

	protected const string FLUSH_KEY = 'last_flushed';

	/**
	 * Default expiration when `0` is passed as items not
	 * needed for 7 or more days may free up memory for
	 * more frequent items.
	 */
	public int $default_expiration = DAY_IN_SECONDS * 7;

	/**
	 * @var int Keeps count of how many times the
	 *    cache was successfully received from the cache.
	 */
	public int $cache_hits = 0;

	/**
	 * @var int Keeps count of how many times the
	 *    cache was not successfully received from the cache.
	 */
	public int $cache_misses = 0;

	public string $global_prefix;

	/**
	 * Holds the cached data.
	 *
	 * @var array<string, mixed>
	 */
	public array $cache = [];

	/**
	 * Count of how many times each operation was called.
	 *
	 * @phpstan-var array<STAT, int>
	 */
	public array $stats = [];

	/**
	 * Information on every called operation.
	 *
	 * @phpstan-var array<string, array<array{0: STAT, 1: string, 2: float, 3: float, 4: string, 5?: string}>>
	 *
	 * @var array
	 */
	public array $stat_operations = [];

	/**
	 * @var string[] Holds a list of cache groups that are
	 *    shared across all sites in a multi-site installation
	 */
	protected array $global_groups = [];

	/**
	 * @var string[] Groups, which do not have a persistent cache.
	 */
	protected array $no_cache_groups = [];

	/**
	 * This only differs if running a multi-site installations.
	 *
	 * @var string|int The sites current blog ID.
	 */
	protected string|int $blog_prefix;

	/**
	 * Total size of all data added and retrieved from the cache.
	 *
	 * @var float
	 */
	protected float $size_total = 0;

	/**
	 * Total time spent on all cache operations.
	 *
	 * @var float
	 */
	protected float $time_total = 0;

	/**
	 * Holds the microtime when the timer was started.
	 *
	 * @var float
	 */
	protected float $time_start = 0;

	/**
	 * Whether WP_DEBUG is true.
	 *
	 * Used as a property to allow for unit testing.
	 *
	 * @var bool
	 */
	protected bool $wp_debug;


	/**
	 * Reset the class properties.
	 */
	public function __construct() {
		global $blog_id, $table_prefix;
		$this->global_prefix =
			( is_multisite() || ( defined( 'CUSTOM_USER_TABLE' ) && defined( 'CUSTOM_USER_META_TABLE' ) ) ) ? 'global' : $table_prefix;
		$this->blog_prefix = is_multisite() ? $blog_id : $table_prefix;
		$this->reset_stats();
		$this->wp_debug = \defined( 'WP_DEBUG' ) && WP_DEBUG;
	}


	abstract protected function get_cache_type(): string;


	abstract protected function convert_expire_time( int $seconds ): int;


	/**
	 * Sets the list of global groups.
	 *
	 * @param string|string[] $groups List of groups that are global.
	 */
	final public function add_global_groups( string|array $groups ): void {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->global_groups = array_merge( $this->global_groups, $groups );
		$this->global_groups = array_unique( $this->global_groups );
	}


	/**
	 * Adds a group or set of groups to list of non-persistent groups.
	 *
	 * @param string|string[] $groups A group, or an array of groups to add
	 */
	final public function add_non_persistent_groups( string|array $groups ): void {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->no_cache_groups = array_merge( $this->no_cache_groups, $groups );
		$this->no_cache_groups = array_unique( $this->no_cache_groups );
	}


	/**
	 * Remove a group or set of groups to list of non-persistent groups.
	 *
	 * @param string|string[] $groups A group, or an array of groups to add
	 */
	final public function remove_non_persistent_groups( string|array $groups ): void {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->no_cache_groups = array_diff( $this->no_cache_groups, $groups );
	}


	/**
	 * Gets a value specifically from the internal, run-time cache, not external.
	 *
	 * @param int|string|float $key   - What the contents in the cache are called.
	 * @param string           $group - Where the cache contents are grouped.
	 *
	 * @return bool|mixed Value on success, false on failure.
	 */
	final public function get_from_runtime_cache( int|string|float $key, string $group = self::DEFAULT_GROUP ): mixed {
		$key = $this->key( $key, $group );

		return $this->cache[ $key ] ?? false;
	}


	/**
	 * Works out a cache id based on a given id and group
	 *
	 * @param int|string|float $id    - What the contents in the cache are called.
	 * @param string           $group The group
	 *
	 * @return string Returns the calculated cache id
	 */
	final public function key( int|string|float $id, string $group = self::DEFAULT_GROUP ): string {
		if ( '' === $group ) {
			$group = self::DEFAULT_GROUP;
		}

		if ( \in_array( $group, $this->global_groups, true ) ) {
			$prefix = $this->global_prefix;
		} else {
			$prefix = $this->blog_prefix;
		}

		// Don't flush the `last_changed` group.
		if ( static::FLUSH_KEY !== $id ) {
			$prefix .= ":{$this->get_group_last_changed( $group )}";
		}

		return (string) \preg_replace( '/\s+/', '', "{$group}:{$id}:{$prefix}:" . WP_CACHE_KEY_SALT );
	}


	/**
	 * Flush all the key in a specified group.
	 *
	 * @param string $group - Where the cache contents are grouped.
	 *
	 * @return bool
	 */
	final public function flush_group( string $group = 'default' ): bool {
		return \WP_Object_Cache::instance()->set( static::FLUSH_KEY, microtime(), $group );
	}


	/**
	 * Get the last time this group was changed.
	 *
	 * Modeled after `wp_cache_get_last_changed` but using internal
	 * calls to reduce overhead and improve unit test reliability.
	 *
	 * @see wp_cache_get_last_changed()
	 *
	 * @param string $group - Where the cache contents are grouped.
	 *
	 * @return string
	 */
	final public function get_group_last_changed( string $group ): string {
		$last_changed = \WP_Object_Cache::instance()->get( self::FLUSH_KEY, $group );
		if ( false === $last_changed ) {
			$last_changed = microtime();
			\WP_Object_Cache::instance()->set( self::FLUSH_KEY, $last_changed, $group );
		}

		return $last_changed;
	}


	/**
	 * Switch the internal blog id.
	 *
	 * This changes the blog id used to create keys in blog specific groups.
	 *
	 * @param int|string $blog_id Blog id.
	 */
	final public function switch_to_blog( int|string $blog_id ): void {
		global $table_prefix;
		$blog_id = (int) $blog_id;
		$this->blog_prefix = ( is_multisite() ? $blog_id : $table_prefix );
	}


	/**
	 * Store the action in stats if `WP_DEBUG` is enabled.
	 *
	 * @phpstan-param STAT $action
	 *
	 * @param string       $action
	 * @param string       $group
	 * @param int|string   $id
	 * @param float        $elapsed
	 * @param mixed        $sizable
	 *
	 * @return void
	 */
	final protected function update_stats( string $action, string $group, int|string $id, float $elapsed = 0, mixed $sizable = '' ): void {
		if ( ! $this->wp_debug || self::FLUSH_KEY === $id ) {
			return;
		}
		if ( ! isset( $this->stats[ $action ] ) ) {
			$this->stats[ $action ] = 0;
		}
		++$this->stats[ $action ];

		if ( 'miss' === $action ) {
			++ $this->cache_misses;
		}
		if ( 'external' === $action ) {
			++ $this->cache_hits;
		}

		$size = $this->get_data_size( $sizable );
		$this->size_total += $size;

		if ( $elapsed > 0.005 ) {
			++$this->stats['slow-ops'];
			$backtrace = '';
			if ( function_exists( 'wp_debug_backtrace_summary' ) ) {
				$backtrace = wp_debug_backtrace_summary( null, 0, true );
			}
			$this->stat_operations['slow-ops'][] = [ $action, (string) $id, $size, $elapsed, $group, $backtrace ];
		}

		if ( ! isset( $this->stat_operations[ $group ] ) ) {
			$this->stat_operations[ $group ] = [];
		}
		$this->stat_operations[ $group ][] = [ $action, (string) $id, $size, $elapsed, $group ];
	}


	/**
	 * Reset the stats while bringing back the original keys.
	 */
	final public function reset_stats(): void {
		$this->stat_operations = [];
		$this->cache_hits = 0;
		$this->cache_misses = 0;
		$this->size_total = 0;
		$this->time_total = 0;
		$this->stats = [
			'add'          => 0,
			'decr'         => 0,
			'delete'       => 0,
			'delete_multi' => 0,
			'external'     => 0,
			'get'          => 0,
			'get_multi'    => 0,
			'incr'         => 0,
			'miss'         => 0,
			'replace'      => 0,
			'set'          => 0,
			'set_multi'    => 0,
			'slow-ops'     => 0,
		];
	}


	/**
	 * Calculate the size of the data.
	 *
	 * @param mixed $data
	 *
	 * @return int
	 */
	final protected function get_data_size( $data ): int {
		if ( ! $this->wp_debug ) {
			return 0;
		}
		if ( \is_scalar( $data ) ) {
			return \strlen( (string) $data );
		}

		return \strlen( \serialize( $data ) );
	}


	/**
	 * Track microtime to determine how long an operation took.
	 *
	 * @return bool
	 */
	final protected function timer_start(): bool {
		if ( ! $this->wp_debug ) {
			return true;
		}
		$this->time_start = microtime( true );
		return true;
	}


	/**
	 * Return the time elapsed since the timer was started.
	 *
	 * @return float
	 */
	final protected function timer_stop(): float {
		if ( ! $this->wp_debug ) {
			return 0;
		}
		$total = ( microtime( true ) - $this->time_start );
		$this->time_total += $total;
		return $total;
	}


	/**
	 * Get full stats containing all operations.
	 *
	 * Used by Query Monitor within the debugging plugin.
	 *
	 * @return array{
	 *     totals: array{
	 *          query_time: float,
	 *          size: float,
	 *     },
	 *     operation_counts: array<STAT, int>,
	 *     operations: array<STAT, array<string, array{
	 *          group: string,
	 *          key: string,
	 *          size: float,
	 *          time: float,
	 *          result: STAT,
	 *          count: positive-int
	 *     }>>,
	 *     groups: string[],
	 *     slow-ops: array<STAT, array<string, array{
	 *          group: string,
	 *          key: string,
	 *          size: float,
	 *          time: float,
	 *          result: STAT,
	 *          backtrace?: string,
	 *          count: positive-int
	 *     }>>,
	 *     slow-ops-groups: string[],
	 * }
	 *
	 */
	final public function get_stats(): array {
		$stats = [
			'totals'           => [
				'query_time' => $this->time_total,
				'size'       => $this->size_total,
			],
			'operation_counts' => $this->stats,
			'operations'       => [],
			'groups'           => [],
			'slow-ops'         => [],
			'slow-ops-groups'  => [],
		];

		foreach ( $this->stat_operations as $group => $ops ) {
			foreach ( $ops as $data ) {
				[ $action, $key, $size, $time ] = $data;

				if ( 'slow-ops' === $group ) {
					$type = 'slow-ops';
					$groups_key = 'slow-ops-groups';
				} else {
					$type = 'operations';
					$groups_key = 'groups';
				}

				$values = [
					'group'  => $group,
					'key'    => $key,
					'size'   => $size,
					'time'   => $time,
					'result' => $action,
				];

				if ( 'slow-ops' === $group ) {
					$values['group'] = $data[4];
					$values['backtrace'] = $data[5] ?? '';
				}
				$stat_key = "{$key}.{$group}";
				if ( isset( $stats[ $type ][ $action ][ $stat_key ] ) ) {
					++$stats[ $type ][ $action ][ $stat_key ]['count'];
					$stats[ $type ][ $action ][ $stat_key ]['size'] += $size;
					$stats[ $type ][ $action ][ $stat_key ]['time'] += $time;
				} else {
					$values['count'] = 1;
					$stats[ $type ][ $action ][ $stat_key ] = $values;
				}

				if ( ! \in_array( $data[4], $stats[ $groups_key ], true ) ) {
					$stats[ $groups_key ][] = $data[4];
				}
			}
		}

		return $stats;
	}


	/**
	 * Singleton. Return instance of WP_Object_Cache.
	 *
	 * @return \WP_Object_Cache
	 */
	final public static function instance(): \WP_Object_Cache {
		global $wp_object_cache;

		if ( null === $wp_object_cache ) {
			$wp_object_cache = new \WP_Object_Cache();
		}

		return $wp_object_cache;
	}
}
