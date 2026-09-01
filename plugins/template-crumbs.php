<?php
declare( strict_types=1 );
/**
 * Plugin Name: Template Crumbs
 * Description: Show comments in the HTML markup that lead to displayed template.
 * Plugin URI: https://github.com/lipemat/mu-plugins
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 3.0.0
 *
 * @notice Crumbs expose absolute template paths, so they are limited to
 *         `WP_DEBUG` on a non-production environment.
 *
 * @requires WP 5.2+
 */
if ( ! \defined( 'WP_DEBUG' ) ) {
	return;
}

/**
 * Exclude a template from crumbs.
 *
 * Must include both full path and slug if you want both excluded.
 * Block templates require `get_stylesheet(). '//<slug>'`.
 *
 * @param string $template - Full path or slug.
 */
function template_crumbs_exclude( string $template ): void {
	if ( ! isset( $GLOBALS['template-crumbs/excluded'] ) ) {
		$GLOBALS['template-crumbs/excluded'] = [];
	}
	$GLOBALS['template-crumbs/excluded'][] = wp_normalize_path( $template );
}

/**
 * Are crumbs allowed to be printed for this request?
 *
 * Absolute template paths are a filesystem disclosure, so a site left with
 * `WP_DEBUG` enabled in production must not print them.
 *
 * @return bool
 */
function _lipe_template_crumbs_enabled(): bool {
	return WP_DEBUG && ! \defined( 'WP_UNIT_DIR' ) && 'production' !== wp_get_environment_type();
}

if ( _lipe_template_crumbs_enabled() ) {
	$tp = function( $template ) {
		if ( ! _lipe_template_crumbs_enabled() || _lipe_template_crumbs_is_excluded( $template ) ) {
			return $template;
		}

		echo '<!-- ' . esc_html( \str_replace( [
				wp_normalize_path( WP_CONTENT_DIR . '/' ),
				'.php',
			], '', wp_normalize_path( (string) $template ) ) ) . '.php -->';

		return $template;
	};
	$base_file = function( string $base, ?string $name = null ) use ( $tp ) {
		if ( null === $name || '' === $name ) {
			$tp( $base );
		} else {
			$tp( "{$base}-{$name}" );
		}
	};

	add_action( 'woocommerce_before_template_part', function( $template, $t, $path ) use ( $tp ) {
		$tp( $path );
		$tp( $template );
	}, 1, 3 );
	add_filter( 'wc_get_template_part', function( $template, $slug, $name ) use ( $tp, $base_file ) {
		$tp( $template );
		$base_file( $slug, $name );

		return $template;
	}, 99, 3 );
	add_filter( 'get_block_template', function( ?\WP_Block_Template $template, $slug, $type ) {
		if ( _lipe_template_crumbs_is_excluded( $slug ) ) {
			return $template;
		}
		$dir = 'wp_template' === $type ? 'templates' : 'part';
		$path = '<!-- ' . esc_html( $dir . \str_replace( get_stylesheet() . '/', '', (string) $slug ) ) . '.html -->';
		if ( $template instanceof \WP_Block_Template ) {
			$template->content = $path . $template->content;
		}
		return $template;
	}, 1, 3 );
	add_filter( 'template_include', $tp, 99 );
	add_action( 'get_header', function( $name ) use ( $base_file ) {
		$base_file( 'header', $name );
	}, 1 );
	add_action( 'get_footer', function( $name ) use ( $base_file ) {
		$base_file( 'footer', $name );
	}, 1 );
	add_action( 'get_sidebar', function( $name ) use ( $base_file ) {
		$base_file( 'sidebar', $name );
	}, 1 );
	add_action( 'get_template_part', $base_file, 1, 2 );
	add_filter( 'comments_template', $tp, 99 );
}

/**
 * Load a template part from the 'template-parts' directory.
 * A breadcrumb will be included if `WP_DEBUG` is on.
 *
 * @param string               $file - Slug of PHP file without .php.
 * @param array<string, mixed> $args - Args passed to the template via `get_template_part`.
 *
 * @return void
 */
function lipe_template_part( string $file, array $args = [] ): void {
	_lipe_template_crumbs_called_from( debug_backtrace()[0], $file );
	get_template_part( 'template-parts/' . $file, null, $args );
}

/**
 * Return contents of a template part from the 'template-parts' directory.
 * A breadcrumb will be included if `WP_DEBUG` is on.
 *
 * @param string               $file - Slug of PHP file without .php.
 * @param array<string, mixed> $args - Args passed to the template via `get_template_part`.
 *
 * @return string
 */
function lipe_template_contents( string $file, array $args = [] ): string {
	\ob_start();
	_lipe_template_crumbs_called_from( debug_backtrace()[0], $file );
	get_template_part( 'template-parts/' . $file, null, $args );
	return (string) \ob_get_clean();
}

/**
 * Load a block template from the 'parts' directory.
 * A breadcrumb will be included if `WP_DEBUG` is on.
 *
 * @param string $file - Slug of HTML file without .html.
 *
 * @return void
 */
function lipe_block_template_part( string $file ): void {
	_lipe_template_crumbs_called_from( debug_backtrace()[0], $file );
	block_template_part( $file );
}

/**
 * Output the "Called from:" HTML comment
 *
 * @param array{
 *     function: string,
 *     line?: int,
 *     file?: string,
 *     class?: class-string,
 *     type?: string,
 *     args?: array<int, string>,
 *     object?: object
 * }             $caller - From debug_backtrace.
 * @param string $file
 *
 * @return void
 */
function _lipe_template_crumbs_called_from( array $caller, string $file ): void {
	if ( _lipe_template_crumbs_enabled() && ! _lipe_template_crumbs_is_excluded( $file ) ) {
		?>
		<!-- <?= esc_html( 'Called from: ' . \str_replace( WP_CONTENT_DIR . DIRECTORY_SEPARATOR, '', $caller['file'] ?? '' ) . ' Line: ' . ( $caller['line'] ?? '' ) ) ?> -->
		<?php
	}
}

/**
 * Is the file excluded from the crumbs?
 *
 * @param string $file
 *
 * @return bool
 */
function _lipe_template_crumbs_is_excluded( string $file ): bool {
	if ( ! isset( $GLOBALS['template-crumbs/excluded'] ) || ! \is_array( $GLOBALS['template-crumbs/excluded'] ) ) {
		return false;
	}
	$file = wp_normalize_path( $file );
	$possibles = [
		$file,
		"template-parts/{$file}",
		"{$file}.php",
		\str_replace( '.php', '', $file ),
	];

	return [] !== \array_intersect( $possibles, $GLOBALS['template-crumbs/excluded'] );
}
