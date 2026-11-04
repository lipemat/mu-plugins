<?php
declare( strict_types=1 );
/**
 * Plugin Name: Lipe MU Plugins
 * Plugin URI: https://github.com/lipemat/mu-plugins
 * Description: Shared must-use plugins loaded from a stub in the `mu-plugins` directory.
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 1.1.1
 * License: MIT
 *
 * Required by a project owned stub, which lives in `wp-content/mu-plugins`
 * and defines the `LIPE_MU_*` configuration constants before requiring this file.
 *
 * @see stubs/mu-plugin-loader.php
 * @see AGENTS.md
 */

if ( \defined( 'LIPE_MU_DIR' ) ) {
	return;
}
\define( 'LIPE_MU_VERSION', '1.1.1' );
\define( 'LIPE_MU_DIR', __DIR__ );

/**
 * This package's location relative to the content directory.
 *
 * @see AGENTS.md for why this is a path and not a URL.
 */
\define( 'LIPE_MU_PATH', \str_replace( wp_normalize_path( WP_CONTENT_DIR ), '', wp_normalize_path( __DIR__ ) ) );

/**
 * Modules load inside a closure, so their top level variables stay out of the global scope.
 *
 * @see AGENTS.md for why a module with a taken function name is skipped.
 */
( function(): void {
	$disabled = \defined( 'LIPE_MU_DISABLED_MODULES' ) ? \constant( 'LIPE_MU_DISABLED_MODULES' ) : [];
	if ( ! \is_array( $disabled ) ) {
		$disabled = [];
	}

	$modules = lipe_mu_modules();
	foreach ( lipe_mu_modules_to_load( wp_installing(), $disabled ) as $module ) {
		$taken = \array_filter( $modules[ $module ]['declares'], '\function_exists' );
		if ( [] !== $taken ) {
			lipe_mu_conflicting_function( \reset( $taken ), $module );
			continue;
		}

		require_once __DIR__ . "/plugins/{$module}.php";
	}
} )();

/**
 * Every module, keyed by file name without the `.php` extension.
 *
 * - `declares`: global function names the module declares.
 * - `installing`: load while WordPress is installing. Default `false`.
 *
 * @return array<string, array{declares: list<string>, installing?: bool}>
 */
function lipe_mu_modules(): array {
	return [
		'cache-groups'            => [
			'declares' => [],
		],
		'display-actions'         => [
			'declares' => [],
		],
		'escape'                  => [
			'declares'   => [ 'es', 'sn' ],
			'installing' => true,
		],
		'force-plugin-activation' => [
			'declares' => [],
		],
		'login-page'              => [
			'declares' => [],
		],
		'memoize'                 => [
			'declares'   => [ 'memoize', 'once' ],
			'installing' => true,
		],
		'template-crumbs'         => [
			'declares'   => [
				'_lipe_template_crumbs_called_from',
				'_lipe_template_crumbs_enabled',
				'_lipe_template_crumbs_is_excluded',
				'lipe_block_template_part',
				'lipe_template_contents',
				'lipe_template_part',
				'template_crumbs_exclude',
			],
			'installing' => true,
		],
		'use-the-force'           => [
			'declares' => [],
		],
	];
}


/**
 * Modules to load, in load order.
 *
 * @param bool                     $installing - Whether WordPress is installing.
 * @param array<int|string, mixed> $disabled   - `LIPE_MU_DISABLED_MODULES`.
 *
 * @return list<string>
 */
function lipe_mu_modules_to_load( bool $installing, array $disabled ): array {
	$to_load = [];
	foreach ( lipe_mu_modules() as $module => $config ) {
		if ( \in_array( $module, $disabled, true ) ) {
			continue;
		}
		if ( false === $installing || ( isset( $config['installing'] ) && true === $config['installing'] ) ) {
			$to_load[] = $module;
		}
	}
	return $to_load;
}


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
