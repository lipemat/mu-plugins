# MU Plugins

<p>
  <a href="https://github.com/lipemat/mu-plugins/releases/latest">
    <img alt="Version" src="https://img.shields.io/packagist/v/lipemat/mu-plugins.svg?label=version" />
  </a>
  <img alt="WordPress" src="https://img.shields.io/badge/wordpress->=6.4.0-green.svg">
  <img alt="PHP" src="https://img.shields.io/packagist/php-v/lipemat/mu-plugins.svg?color=brown" />
  <img alt="License" src="https://img.shields.io/packagist/l/lipemat/mu-plugins.svg">
</p>

Shared WordPress must-use plugins and a drop-in object cache, kept in one place instead of copied between projects.

## Installation

```bash
composer require lipemat/mu-plugins
```

Then copy the two stubs into the project. They stay with the project because WordPress requires both locations, and both
run before the Composer autoloader exists.

| Stub                        | Copy to                                    |
|-----------------------------|--------------------------------------------|
| `stubs/mu-plugin-loader.php` | `wp-content/mu-plugins/lipe-mu-plugins.php` |
| `stubs/object-cache.php`     | `wp-content/object-cache.php`               |

The "Migrating an existing project" section of [AGENTS.md](AGENTS.md) walks through converting a project which
already has these files loose in `mu-plugins`.

## Configuration

The mu-plugin stub defines its constants before requiring `load.php`.

| Constant                   | Purpose                                                       |
|----------------------------|---------------------------------------------------------------|
| `LIPE_MU_FORCE_ACTIVE`     | Plugins which are always active and cannot be deactivated.    |
| `LIPE_MU_FORCE_DEBUG`      | Additional plugins forced active while `WP_DEBUG` is on.      |
| `LIPE_MU_FORCE_DEACTIVATE` | Plugins which cannot be activated.                            |
| `LIPE_MU_DISABLED_MODULES` | Module file names to skip, without the `.php` extension.      |

Runtime overrides remain available through the `lipe/mu/force-plugin-activation/get-force-active` and
`lipe/mu/force-plugin-activation/get-force-deactivate` filters.

## Modules

| Module                     | Provides                                                                                     |
|----------------------------|----------------------------------------------------------------------------------------------|
| `cache-groups`             | Persistent caching for the `counts` group and for theme lookups.                              |
| `display-actions`          | Prints every firing hook name. Requires `DEBUG_DISPLAY_ACTIONS` and a `local` environment.    |
| `escape`                   | The `es()` and `sn()` output helpers.                                                         |
| `force-plugin-activation`  | Keeps required plugins active and blocked plugins inactive.                                   |
| `memoize`                  | The `memoize()` and `once()` template tags.                                                   |
| `template-crumbs`          | HTML comments naming the template which produced the markup, plus `lipe_template_part()`.     |
| `use-the-force`            | Runs actions, filters, container calls, and closures declared in `$GLOBALS['use_the_force']`. |

Each module claims global function names. When a name is already taken the package leaves the existing declaration
alone and reports it through `_doing_it_wrong()` under `WP_DEBUG`. Add the module to `LIPE_MU_DISABLED_MODULES` to
silence the notice.

### `es()` does not escape

`es()` casts a value to a string. It exists to keep generated CSS module class names cheap, and it is **not** an
escaping function. Use `esc_attr()`, `esc_url()`, or `esc_html()` for anything else, and add `es` to the project's
`phpcs.xml` and PHPStan configuration so the sniffs know what it is.

### `$GLOBALS['use_the_force']` executes callables

The array holds callables which are invoked as given. Populate it from `wp-config.php` or `local-config.php` only,
never from a request, the database, or any other untrusted source.

## Object cache

`object-cache/base.php` declares the `wp_cache_*` functions and picks a handler:

1. `OBJECT_CACHE_HANDLER`, when defined, is required as-is.
2. `object-cache/memcached.php`, when the `Memcached` class exists.
3. `object-cache/opcache.php` otherwise.

### Security notes

**`WP_CACHE_KEY_SALT` defaults to `DB_NAME`.** Every key is salted with it so sites sharing a Memcached instance cannot
read or overwrite each other's entries. A database name is guessable, so define `WP_CACHE_KEY_SALT` as a secret random
string in `wp-config.php` on any shared cache server.

**The opcache handler writes and executes PHP.** Entries are stored as `*.cache.php` files under `WP_CACHE_DIR` and read
back with `include`, so write access to that directory is code execution. The handler creates the directory with an
`.htaccess` deny rule and an `index.php`, and the `.php` extension keeps a direct request from returning the cached
value as text. Only Apache honors the `.htaccess` — on any other server, point `WP_CACHE_DIR` at a path outside the
document root.

**Only known classes are persisted.** `var_export()` renders an unrecognised object as `\Some\Class::__set_state(…)`,
which the next read would execute. Values containing any class outside `lipe/mu/object-cache/set-state-map` stay in the
runtime cache and are never written to disk. Extend the map to both allow a class and describe how to rebuild it.

## Requirements

- PHP 8.4+
- WordPress 6.4+
- `lipemat/wordpress-libs`
