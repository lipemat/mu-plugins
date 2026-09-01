<?php
declare( strict_types=1 );

/**
 * General cache group modifications.
 *
 * @version 1.1.1
 */

/**
 * Add the `count` group to persistent cache.
 *
 * Improves performance and reduces queries, but has not
 * been changed in WP core yet.
 *
 * @link    https://core.trac.wordpress.org/ticket/35430
 *
 * @notice  It is possible this could cause issues with counting posts due to
 *         user permissions used in count queries.
 * @link    https://core.trac.wordpress.org/ticket/47884#comment:8
 * @see     \wp_count_posts
 *
 * Requires our special object cache handler version 4.1.0+.
 *
 * @version 1.0.3
 */
add_action( 'plugins_loaded', function(): void {
	if ( ! \function_exists( 'wp_cache_remove_non_persistent_groups' ) ) {
		return;
	}
	wp_cache_remove_non_persistent_groups( 'counts' );
} );

/**
 * Enable persistent caching for themes.
 *
 * Improves performance for theme lookup and loading in situations
 * where theme files will not be changed via FTP.
 *
 * @link    https://core.trac.wordpress.org/ticket/20103#comment:7
 *
 * @version 1.0.0
 */
add_filter( 'wp_cache_themes_persistently', fn() => MONTH_IN_SECONDS );
