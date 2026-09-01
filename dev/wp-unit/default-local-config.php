<?php
declare( strict_types=1 );

/**
 * Sensible defaults for local-config.php.
 *
 * Loaded if no local-config.php is provided.
 *
 * Uses the shared `wplibs` database credentials so no credential
 * lives in this repository.
 */

// Point to local memcache servers (Requirement of hosts like WPE).
$GLOBALS['memcached_servers'] = [ '127.0.0.1:11211' ];

// vendor/lipemat/mu-plugins/dev/wp-unit -> the site root.
$root = \dirname( __DIR__, 8 );

define( 'BOOTSTRAP', 'E:/SVN/wp-unit/includes/bootstrap.php' );
define( 'DB_HOST', 'localhost' );
define( 'DB_NAME', 'wordpress' );
define( 'DB_PASSWORD', \getenv( 'WP_LIBS_DB_PASS' ) );
define( 'DB_USER', 'wplibs' );
define( 'DOMAIN_CURRENT_SITE', 'mu-plugins.loc' );
define( 'WP_SITE_ROOT', $root . DIRECTORY_SEPARATOR );
define( 'WP_TESTS_DOMAIN', 'mu-plugins.loc' );
define( 'WP_TESTS_EMAIL', 'unit-tests@test.com' );
define( 'WP_TESTS_TITLE', 'MU Plugins Tests' );
define( 'WP_UNIT_DIR', 'E:/SVN/wp-unit' );

define( 'ABSPATH', WP_SITE_ROOT . 'wp/' );
