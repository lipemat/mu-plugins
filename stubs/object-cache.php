<?php
declare( strict_types=1 );

/**
 * Copy to `wp-content/object-cache.php`.
 *
 * WordPress requires drop-ins to live in `wp-content`, so this stub stays with
 * the project and points at the package.
 *
 * Loaded from `wp_start_object_cache()`, long before the Composer autoloader
 * exists, which is why the path is hard coded instead of resolved.
 *
 * Adjust the path if the core plugin lives somewhere else.
 *
 * @version 8.0.0
 */
require WP_CONTENT_DIR . '/plugins/core/vendor/lipemat/mu-plugins/object-cache/base.php';
