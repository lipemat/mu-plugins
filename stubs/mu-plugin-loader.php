<?php
declare( strict_types=1 );
/**
 * Plugin Name: Lipe MU Plugins Loader
 * Plugin URI: https://github.com/lipemat/mu-plugins
 * Description: Project configuration for the `lipemat/mu-plugins` package.
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 1.0.0
 *
 * Copy to `wp-content/mu-plugins/lipe-mu-plugins.php`.
 *
 * WordPress only auto-loads PHP files sitting directly in `mu-plugins`, and
 * must-use plugins run before the Composer autoloader exists. This stub is the
 * project's own file: it holds the per-project configuration and requires the
 * package.
 *
 * @version 1.0.0
 */

/**
 * Plugins which are always active and cannot be deactivated.
 *
 * @var array<int, string> - `directory/file.php` relative to the plugins directory.
 */
const LIPE_MU_FORCE_ACTIVE = [
	'core/core.php',
];

/**
 * Plugins which are additionally forced active while `WP_DEBUG` is on.
 *
 * @var array<int, string>
 */
const LIPE_MU_FORCE_DEBUG = [
	'debugging/debugging.php',
];

/**
 * Plugins which are deactivated and cannot be activated.
 *
 * @var array<int, string>
 */
const LIPE_MU_FORCE_DEACTIVATE = [];

/**
 * Modules to skip, by file name without the extension.
 *
 * @var array<int, string> - cache-groups, display-actions, escape,
 *                         force-plugin-activation, memoize, template-crumbs,
 *                         use-the-force.
 */
const LIPE_MU_DISABLED_MODULES = [];

require WP_CONTENT_DIR . '/plugins/core/vendor/lipemat/mu-plugins/load.php';
