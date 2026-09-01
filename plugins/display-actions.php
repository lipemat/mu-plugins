<?php
declare( strict_types=1 );
/**
 * Plugin Name: Display Actions
 * Description: Display action names where the actions fire.
 * Plugin URI: https://github.com/lipemat/mu-plugins
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 1.2.0
 *
 * @requires WP 5.2+
 */
if ( ! \defined( 'WP_DEBUG' ) ) {
	return;
}

if ( ( \defined( 'DEBUG_DISPLAY_ACTIONS' ) && DEBUG_DISPLAY_ACTIONS ) && 'local' === wp_get_environment_type() ) {
	add_action( 'all', function( $tag ) {
		static $displayed;
		static $styles = false;

		if ( ! \is_string( $tag ) || isset( $displayed[ $tag ] ) || \str_starts_with( $tag, 'gettext' ) || \str_starts_with( $tag, 'option' ) || \str_starts_with( $tag, 'pre_' ) || \str_starts_with( $tag, 'default_' ) ) {
			return;
		}
		if ( headers_sent() ) {
			if ( ! $styles ) {
				?>
				<style id="display-actions">
					pre {
						background: rgba(0, 0, 0, 0.8);
						display: inline-block;
						border-radius: 3px;
						color: #fff;
						padding: 3px 5px;
						margin: 0 2px;
						position: relative;
						z-index: 99999;
						font-size: 14px;
					}
				</style>
				<?php
				$styles = true;
			}

			echo '<pre>' . esc_html( $tag ) . '</pre>';
			$displayed[ $tag ] = 1;
		}
	} );
}
