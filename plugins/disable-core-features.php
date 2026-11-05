<?php
declare( strict_types=1 );
/**
 * Plugin Name: Disable Core Features
 * Description: Display features WordPress core has which add overhead and are not useful.
 * Plugin URI: https://github.com/lipemat/mu-plugins
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 1.0.0
 */
if ( ! \defined( 'ABSPATH' ) ) {
	return;
}
/**
 * Disable the random content redirect which is a performance hit.
 *
 * Done at priority 1 in case other plugins or themes opt in using a filter.
 *
 * @link https://make.wordpress.org/core/2026/11/05/your-content-is-going-to-get-more-random/
 */
add_filter( 'wp_enable_random_content_redirect', __return_false( ... ), 1 );
