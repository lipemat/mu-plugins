# Migrating a project to `lipemat/mu-plugins`

Converts a project which carries these must-use plugins as loose files in `wp-content/mu-plugins` into one which
consumes them from the package. Work through the steps in order; each names what "done" looks like.

Paths below assume the core plugin at `wp-content/plugins/core`. Adjust every path if it lives elsewhere.

## 1. Install the package

```bash
composer require lipemat/mu-plugins
```

Run from `wp-content/plugins/core`. Done when `wp-content/plugins/core/vendor/lipemat/mu-plugins/load.php` exists.

## 2. Record the current force-plugin-activation configuration

Open `wp-content/mu-plugins/force-plugin-activation.php` and copy the values of the `FORCE_ACTIVE`, `FORCE_DEBUG`, and
`FORCE_DEACTIVATE` constants somewhere you can paste from. They are project-specific and step 4 needs them.

Done when all three lists are written down, including empty ones.

## 3. Delete the extracted files

Remove from `wp-content/mu-plugins`:

- `cache-groups.php`
- `display-actions.php`
- `escape.php`
- `force-plugin-activation.php`
- `memoize.php`
- `mu-loader.php`
- `template-crumbs.php`
- `use-the-force.php`
- the `wp-cache/` directory

Leave every other file alone. Project-local development tooling such as `jest-authentication.php`,
`local-dev-host.php`, and host-specific plugins stay where they are.

Done when the nine entries above are gone and nothing else in the directory changed.

## 4. Add the project stub

Copy `vendor/lipemat/mu-plugins/stubs/mu-plugin-loader.php` to `wp-content/mu-plugins/lipe-mu-plugins.php`, then paste
the step 2 values into `LIPE_MU_FORCE_ACTIVE`, `LIPE_MU_FORCE_DEBUG`, and `LIPE_MU_FORCE_DEACTIVATE`.

Done when the three constants match what step 2 recorded.

## 5. Replace the object cache drop-in

Copy `vendor/lipemat/mu-plugins/stubs/object-cache.php` over `wp-content/object-cache.php`.

Done when the file's `require` resolves to an existing `object-cache/base.php`.

## 6. Repoint `OBJECT_CACHE_HANDLER`

Search the project for `OBJECT_CACHE_HANDLER`. Any definition pointing into the old `wp-content/mu-plugins/wp-cache`
directory now points at nothing, and the handler filenames changed:

| Old                                | New                                   |
|------------------------------------|---------------------------------------|
| `wp-cache/object-cache-memcache.php` | `vendor/lipemat/mu-plugins/object-cache/memcached.php` |
| `wp-cache/object-cache-opcache.php`  | `vendor/lipemat/mu-plugins/object-cache/opcache.php`   |

Check `local-config.php`, `dev/wp-unit/local-config.php`, `local-config-sample.php`, and any IDE run configuration.

Done when every occurrence of `OBJECT_CACHE_HANDLER` in the project resolves to a file which exists.

## 7. Update the analysis configuration

- `phpstan.neon.dist` — keep `wp-content/mu-plugins` in `paths` so the remaining files and the new stub stay covered.
  The package analyses itself.
- `phpcs.xml.dist` — if the project declares `es` or `sn` as allowed output functions, the declarations still apply.
- IDE scopes and run configurations which name `wp-content/mu-plugins/wp-cache` need the new path.

Done when `phpcs` and `phpstan` both run clean from the project root.

## 8. Move or delete the migrated tests

The package owns the tests for this code. Delete from the project's `dev/wp-unit/tests`:

- `cache/` (the `Cache_*` object cache tests)
- `UseTheForceTest.php`
- `Memoize_Test.php`

Done when `phpunit` passes from the project's `dev/wp-unit` directory with those files removed.

## 9. Verify against a running site

1. Load a page. It renders, and `WP_Object_Cache::instance()` is the package class.
2. Open the plugins screen. Every plugin in `LIPE_MU_FORCE_ACTIVE` is active with no Deactivate link.
3. With `WP_DEBUG` on and a non-production environment, view source. Template crumb comments appear.
4. Request a file under `wp-content/cache` over HTTP. It returns 403 or empty, never cache contents.

Done when all four hold.
