<?php
declare( strict_types=1 );

use Lipe\Lib\Traits\Memoize;

/**
 * Plugin Name: Memoize Template Tags
 * Description: Memorize helper template tags for functional memoization.
 * Plugin URI: https://github.com/lipemat/mu-plugins
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 1.1.0
 */

/**
 * Create a callback that will return the same result every time it is
 * called with the same arguments.
 *
 * If the arguments change, the callback will return a result matching the change.
 *
 * The passed function will only be called one time for the same set of arguments.
 *
 * @example $memo = \memoize( 'get_the_title' );
 *                  $memo( 1 ); // Gets title of post id 1.
 *                  $memo( 5 ); // Gets title of post id 5.
 *
 * @param callable $callback
 *
 * @return \Closure
 */
function memoize( callable $callback ): \Closure {
	$class = new class() {
		use Memoize;
	};
	return function( ...$args ) use ( $callback, $class ) {
		return $class->memoize( $callback, __FUNCTION__, ...$args );
	};
}

/**
 * Create a callback that will return the same result every time it is
 * called no matter what the passed arguments are.
 *
 * The passed function will only be called once no matter where it called from
 * and what the arguments are.
 *
 * The callback will always return the value received from the callback on its first run.
 *
 * @example $once = \once( 'get_the_title' );
 *          $once( 1 ); // Get title of post id 1.
 *          $once( 5 ); // Still gets title of post id 1.
 *
 * @param callable $callback
 *
 * @return \Closure
 */
function once( callable $callback ): \Closure {
	$class = new class() {
		use Memoize;
	};
	return function( ...$args ) use ( $callback, $class ) {
		return $class->once( $callback, __FUNCTION__, ...$args );
	};
}
