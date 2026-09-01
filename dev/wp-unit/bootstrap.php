<?php
declare( strict_types=1 );

$GLOBALS['wp_tests_options']['permalink_structure'] = '%postname%/';

require __DIR__ . '/helpers.php';
require __DIR__ . '/wp-tests-config.php';

// Prevent side effects from the current install's plugins.
require_once WP_UNIT_DIR . '/includes/functions.php';
tests_add_filter( 'option_active_plugins', '__return_empty_array', 99 );
tests_add_filter( 'site_option_active_sitewide_plugins', '__return_empty_array', 99 );

tests_add_filter( 'muplugins_loaded', function(): void {
	// vendor/lipemat/mu-plugins/dev/wp-unit -> vendor/autoload.php.
	if ( \is_readable( \dirname( __DIR__, 4 ) . '/autoload.php' ) ) {
		require_once \dirname( __DIR__, 4 ) . '/autoload.php';
	}

	require_once \dirname( __DIR__, 2 ) . '/load.php';
}, 1 );

// Load the WP-Unit environment.
require BOOTSTRAP;
