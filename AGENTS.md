# AGENTS.md

Shared WordPress must-use plugins and a drop-in object cache, published as `lipemat/mu-plugins`.

Migrating a project which still has these files loose in `wp-content/mu-plugins`, or wiring the package into a project
for the first time: follow [docs/MIGRATION.md](docs/MIGRATION.md).

## Load order

Three tiers run before the Composer autoloader exists, which is why nothing here is autoloaded.

| Tier             | Entry                                       | Loaded by                                              |
|------------------|---------------------------------------------|--------------------------------------------------------|
| Object cache     | `object-cache/base.php`                     | `wp-content/object-cache.php`, from `wp_start_object_cache()` |
| Must-use plugins | `load.php`                                  | `wp-content/mu-plugins/lipe-mu-plugins.php`             |
| Classes          | `src/`, PSR-4 `Lipe\Mu\`                    | `require_once` from `object-cache/base.php`             |

`src/` is PSR-4 for static analysis and IDEs. Every runtime load is an explicit `require_once`, so adding a class to
`src/` means adding its `require_once` too.

## Layout

- `plugins/` — one procedural module per file, loaded by `load.php` in a closure so module variables stay out of the
  global scope. Add a module by creating the file and adding it to the `$modules` map in `load.php`, keyed by file
  name with the global function names it declares as the value.
- `object-cache/` — `base.php` declares the `wp_cache_*` functions and requires a handler; `memcached.php` and
  `opcache.php` each declare a global `WP_Object_Cache`.
- `stubs/` — the two files a consuming project copies and owns.
- `src/Cache/` — the `ObjectCache` interface and the `Object_Cache_Base` shared implementation.

## Rules

Every global function a module declares is listed against that module in `load.php`, which checks the names with
`\function_exists()` before requiring the file and skips the whole module when one is taken. The check lives there
rather than inside the module because PHP hoists top level function declarations: a module compiled far enough to run
a check has already declared its own functions. Adding a function to a module means adding its name to that list —
this package installs onto sites it does not control.

Per-project values reach the package through the `LIPE_MU_*` constants defined in the mu-plugin stub, read with
`\defined()`. Keep new configuration on that path so the package stays project-agnostic.

The opcache handler writes PHP into `WP_CACHE_DIR` and `include`s it back. Anything reaching that file is executed,
so a value is written only when every object inside it appears in `get_set_state_map()`.

## Verification

- `phpcs` and `phpstan` from the package root.
- `phpunit` from `dev/wp-unit/`, once against Memcached and once with `OBJECT_CACHE_HANDLER` pointed at
  `object-cache/opcache.php`, since the two handlers share the suite.
