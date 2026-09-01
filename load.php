<?php
declare( strict_types=1 );
/**
 * Plugin Name: Lipe MU Plugins
 * Plugin URI: https://github.com/lipemat/mu-plugins
 * Description: Shared must-use plugins loaded from a stub in the `mu-plugins` directory.
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 1.0.0
 * License: MIT
 *
 * Required by a project owned stub, which lives in `wp-content/mu-plugins`
 * and defines the `LIPE_MU_*` configuration constants before requiring this file.
 *
 * @see stubs/mu-plugin-loader.php
 * @see docs/MIGRATION.md
 */

/**
 * Skip loading during installation to prevent issues with
 * database tables not yet existing.
 */
if ( wp_installing() ) {
	return;
}

/**
 * Guard against a second load.
 *
 * `require_once` compares resolved paths, which is not enough when the same
 * file is reached through two different spellings of the same directory.
 */
if ( \defined( 'LIPE_MU_DIR' ) ) {
	return;
}
\define( 'LIPE_MU_DIR', __DIR__ );

if ( ! \defined( 'LIPE_MU_VERSION' ) ) {
	\define( 'LIPE_MU_VERSION', '1.0.0' );
}

/**
 * Modules load inside a closure, so a module's top level variables stay
 * out of the global scope.
 *
 * Each module is listed with the global function names it declares. A name
 * already taken means the whole module is skipped: PHP hoists top level
 * function declarations, so a module cannot check this for itself once it
 * has been compiled.
 *
 * `LIPE_MU_DISABLED_MODULES` holds the file names of any modules a project
 * does not want, without the `.php` extension.
 */
( function(): void {
	$modules = [
		'cache-groups'            => [],
		'display-actions'         => [],
		'escape'                  => [ 'es', 'sn' ],
		'force-plugin-activation' => [],
		'memoize'                 => [ 'memoize', 'once' ],
		'template-crumbs'         => [
			'_lipe_template_crumbs_called_from',
			'_lipe_template_crumbs_enabled',
			'_lipe_template_crumbs_is_excluded',
			'lipe_block_template_part',
			'lipe_template_contents',
			'lipe_template_part',
			'template_crumbs_exclude',
		],
		'use-the-force'           => [],
	];

	$disabled = \defined( 'LIPE_MU_DISABLED_MODULES' ) ? \constant( 'LIPE_MU_DISABLED_MODULES' ) : [];
	if ( ! \is_array( $disabled ) ) {
		$disabled = [];
	}

	foreach ( $modules as $module => $declares ) {
		if ( \in_array( $module, $disabled, true ) ) {
			continue;
		}

		$taken = \array_filter( $declares, '\function_exists' );
		if ( [] !== $taken ) {
			lipe_mu_conflicting_function( \reset( $taken ), $module );
			continue;
		}

		require_once __DIR__ . "/plugins/{$module}.php";
	}
} )();

/**
 * Report a global function name this package was unable to claim.
 *
 * The existing declaration is left alone so the site keeps running, but any
 * caller expecting this package's version receives the other one instead.
 *
 * Notices are deferred until `plugins_loaded` because mu-plugins run before
 * it is safe to emit output.
 *
 * @param string $name   - Name of the conflicting function.
 * @param string $module - Module which declares it, for disabling.
 */
function lipe_mu_conflicting_function( string $name, string $module ): void {
	add_action( 'plugins_loaded', function() use ( $name, $module ): void {
		_doing_it_wrong(
			esc_html( $name . '()' ),
			esc_html( \sprintf( 'Already declared elsewhere, so the `lipemat/mu-plugins` version was skipped. Add `%s` to `LIPE_MU_DISABLED_MODULES` to silence this.', $module ) ),
			esc_html( LIPE_MU_VERSION )
		);
	} );
}
