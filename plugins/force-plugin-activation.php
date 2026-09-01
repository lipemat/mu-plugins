<?php
declare( strict_types=1 );
/**
 * Plugin Name: Force Plugin Activation/Deactivation
 * Plugin URI: https://github.com/lipemat/mu-plugins
 * Description: Make sure the required plugins are always active.
 * Version: 4.0.0
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 *
 * Configured by the project stub via the `LIPE_MU_FORCE_ACTIVE`,
 * `LIPE_MU_FORCE_DEBUG`, and `LIPE_MU_FORCE_DEACTIVATE` constants.
 *
 * @see stubs/mu-plugin-loader.php
 */

new class() {
	/**
	 * Read one of the project stub's configuration constants.
	 *
	 * The stub owns these, so anything which is not a list of plugin
	 * files is treated as unconfigured.
	 *
	 * @param string $constant - `LIPE_MU_FORCE_ACTIVE` and friends.
	 *
	 * @return array<int, string>
	 */
	private function configured( string $constant ): array {
		if ( ! \defined( $constant ) ) {
			return [];
		}
		$value = \constant( $constant );
		if ( ! \is_array( $value ) ) {
			return [];
		}

		return \array_values( \array_filter( $value, \is_string( ... ) ) );
	}


	public function __construct() {
		add_filter( 'option_active_plugins', [ $this, 'force_plugins' ] );
		add_filter( 'site_option_active_sitewide_plugins', [ $this, 'force_plugins' ] );
		add_filter( 'default_site_option_active_sitewide_plugins', [ $this, 'force_plugins' ] );

		add_filter( 'plugin_action_links', [ $this, 'plugin_action_links' ], 99, 2 );
		add_filter( 'network_admin_plugin_action_links', [ $this, 'plugin_action_links' ], 99, 2 );

		add_action( 'activate_plugin', function() {
			remove_filter( 'default_site_option_active_sitewide_plugins', [ $this, 'force_plugins' ] );
		} );
	}


	/**
	 * @return array<int|string, mixed>
	 */
	public function force_plugins( mixed $plugins ): array {
		if ( ! \is_array( $plugins ) ) {
			$plugins = (array) $plugins;
		}
		$current_filter = (string) current_filter();

		if ( \str_contains( $current_filter, 'active_sitewide_plugins' ) ) {
			$plugins = \array_keys( $plugins );
		}

		$plugins = \array_merge( $plugins, $this->get_force_active() );
		$plugins = \array_diff( $plugins, $this->get_force_deactivate() );

		$plugins = \array_unique( $plugins );

		if ( \str_contains( $current_filter, 'active_sitewide_plugins' ) ) {
			if ( isset( $plugins[0] ) ) {
				$plugins[ \time() ] = $plugins[0];
				unset( $plugins[0] );
			}
			$plugins = \array_flip( $plugins );
		}

		return $plugins;
	}


	/**
	 * @param array<string, string> $actions
	 *
	 * @return array<string, string>
	 */
	public function plugin_action_links( array $actions, string $plugin_file ): array {
		if ( \in_array( $plugin_file, $this->get_force_active(), true ) ) {
			unset( $actions['deactivate'] );
		}

		if ( \in_array( $plugin_file, $this->get_force_deactivate(), true ) ) {
			unset( $actions['activate'], $actions['delete'] );
		}

		return $actions;
	}


	/**
	 * @return array<int, string>
	 */
	private function get_force_deactivate(): array {
		return apply_filters( 'lipe/mu/force-plugin-activation/get-force-deactivate', $this->configured( 'LIPE_MU_FORCE_DEACTIVATE' ) );
	}


	/**
	 * @return array<int, string>
	 */
	private function get_force_active(): array {
		$active = $this->configured( 'LIPE_MU_FORCE_ACTIVE' );
		if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$active = [ ...$active, ...$this->configured( 'LIPE_MU_FORCE_DEBUG' ) ];
		}

		return apply_filters( 'lipe/mu/force-plugin-activation/get-force-active', $active );
	}
};
