<?php
declare( strict_types=1 );
/**
 * Plugin Name: Use The Force
 *
 * Description: I'm not a Star Wars fan, however, we all know what "Use The Force" means.
 * In this case we can add special actions, filters, container calls, and well.. closures.
 * Instead of a bunch of mu-plugins, which we have to remember to .gitignore or end up accidentally pushing out local
 * configurations to production, we can commit this plugin. It sits here waiting, doing nothing if not invoked. It
 * won't hurt a fly or send all emails to your personal email address. Now you can add all these special calls to your
 * local-config.php, and they stick to your environment.
 *
 * @notice The `$GLOBALS['use_the_force']` array holds callables which are executed as-is. It is intended to be
 *         populated from `wp-config.php` or `local-config.php` only. Never build it from a request, a database
 *         value, or any other untrusted source.
 *
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 2.2.0
 */

if ( ! isset( $GLOBALS['use_the_force'] ) || ! \is_array( $GLOBALS['use_the_force'] ) ) {
	return;
}
$force = $GLOBALS['use_the_force'];
unset( $GLOBALS['use_the_force'] );

// container.
if ( isset( $force['container'] ) ) {
	add_action( 'lipe/project/container-loaded', function( $container ) use ( $force ) {
		foreach ( (array) $force['container'] as $_callable ) {
			$_callable( $container );
		}
	}, 1 );
}

// actions.
if ( isset( $force['actions'] ) ) {
	foreach ( (array) $force['actions'] as $_action => $_item ) {
		if ( \is_array( $_item ) ) {
			[ $callable, $priority ] = $_item;
			add_action( $_action, $callable, $priority, 20 );
		} else {
			add_action( $_action, $_item, 10, 20 );
		}
	}
}

// filters.
if ( isset( $force['filters'] ) ) {
	foreach ( (array) $force['filters'] as $_action => $_item ) {
		if ( \is_array( $_item ) ) {
			[ $callable, $priority ] = $_item;
			add_filter( $_action, $callable, $priority, 20 );
		} else {
			add_filter( $_action, $_item, 10, 20 );
		}
	}
}

// anything else.
if ( isset( $force['use_the_force'] ) ) {
	foreach ( (array) $force['use_the_force'] as $_callable ) {
		$_callable();
	}
}
