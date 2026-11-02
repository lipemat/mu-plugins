<?php
declare( strict_types=1 );
/**
 * Plugin Name: Login Page
 * Plugin URI: https://github.com/lipemat/mu-plugins
 * Description: Brand the WordPress login screen with the OnPoint identity.
 * Version: 1.0.0
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 *
 * Opting out is all or nothing: add `login-page` to `LIPE_MU_DISABLED_MODULES`.
 *
 * @see stubs/mu-plugin-loader.php
 */

add_action( 'login_enqueue_scripts', function(): void {
	wp_enqueue_style( 'lipe/mu/login-page', content_url( LIPE_MU_PATH . '/css/login.css' ), [ 'login' ], LIPE_MU_VERSION );
} );

add_filter( 'login_headerurl', function(): string {
	return 'https://onpointplugins.com/';
} );

/**
 * Core echoes this inside its own `<h1><a>`, so the mark is inlined, never linked.
 */
add_filter( 'login_headertext', function(): string {
	$logo = <<<'SVG'
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="1em" height="1em" aria-hidden="true" focusable="false"><g fill="currentColor"><rect x="34" y="6" width="8" height="22" rx="4" /><rect x="58" y="6" width="8" height="22" rx="4" /></g><circle cx="50" cy="60" r="29" fill="none" stroke="currentColor" stroke-width="10" /><circle cx="50" cy="60" r="10" fill="#8fae1b" /></svg>
		SVG;

	return 'Handcrafted by <br />' . $logo . ' OnPoint';
} );
