# AGENTS.md

Shared WordPress must-use plugins and a drop-in object cache, published as `lipemat/mu-plugins`.

Converting a project which still carries these files loose in `wp-content/mu-plugins`: go straight to
[Migrating an existing project](#migrating-an-existing-project).

## Load order

| Tier             | Entry                    | Loaded by                                                                   |
|------------------|--------------------------|-----------------------------------------------------------------------------|
| Object cache     | `object-cache/base.php`  | `wp-content/object-cache.php`, from `wp_start_object_cache()`               |
| Autoloader       | `vendor/autoload.php`    | `wp-content/mu-plugins/lipe-mu-plugins.php`, the core plugin's autoloader   |
| Must-use plugins | `load.php`               | `wp-content/mu-plugins/lipe-mu-plugins.php`, right after the autoloader     |
| Classes          | `src/`, PSR-4 `Lipe\Mu\` | `require_once` from `object-cache/base.php`                                 |

The object cache runs before the autoloader exists, so `src/` is PSR-4 for static analysis and IDEs only. Every
runtime load is an explicit `require_once`, so adding a class to `src/` means adding its `require_once` too.

The stub loads the autoloader first so the core plugin's classes exist wherever WordPress loads the theme without
plugins, such as `wp-activate.php` during `wp_installing()`. `load.php` stays out of Composer's `files` autoload: that
would run it outside WordPress, from this package's own phpunit and phpstan bootstraps.

## Layout

- `plugins/` — one procedural module per file, loaded by `load.php` in a closure so module variables stay out of the
  global scope. Add a module by creating the file and adding it to the map in `lipe_mu_modules()`, keyed by file
  name:
  - `declares` — the global function names it declares.
  - `installing` — optional, default `false`. `true` loads the module during `wp_installing()`, for API modules a
    theme calls (`escape`, `memoize`, `template-crumbs`). `LIPE_MU_DISABLED_MODULES` still applies.
- `object-cache/` — `base.php` declares the `wp_cache_*` functions and requires a handler; `memcached.php` and
  `opcache.php` each declare a global `WP_Object_Cache`.
- `stubs/` — the two files a consuming project copies and owns.
- `dev/stubs/stubs.php` — symbols phpstan cannot discover on its own, reached through `scanFiles` and never loaded at
  runtime. `load.php` is analysed, but phpstan only picks up a `define()` whose value is a constant expression, so a
  computed constant used from another file is declared here.
- `src/Cache/` — the `ObjectCache` interface and the `Object_Cache_Base` shared implementation.
- `css/` — Sass sources beside their committed `.css` and `.css.map` output. No build step runs on install, so a
  changed `.scss` means running `yarn dist-sass` and committing the result. The sources and the Node tooling are
  `export-ignore`d, so only the compiled files reach `vendor/`. `css/login.css` is enqueued by the `login-page`
  module as `content_url( LIPE_MU_PATH . '/css/login.css' )`. `LIPE_MU_PATH` holds a path, not a URL, because
  `content_url` is filtered by plugins which load after `load.php`.

## Rules

Every global function a module declares is listed against that module in `load.php`, which checks the names with
`\function_exists()` before requiring the file and skips the whole module when one is taken. The check lives there
rather than inside the module because PHP hoists top level function declarations: a module compiled far enough to run
a check has already declared its own functions. Adding a function to a module means adding its name to that list —
this package installs onto sites it does not control.

Per-project values reach the package through the `LIPE_MU_*` constants defined in the mu-plugin stub, read with
`\defined()`. Keep new configuration on that path so the package stays project-agnostic.

`load.php` also defines `LIPE_MU_DIR`, `LIPE_MU_PATH`, and `LIPE_MU_VERSION` under the same prefix. These describe
the package rather than the project. `LIPE_MU_PATH` and `LIPE_MU_VERSION` are guarded, so a stub which defines one
keeps its own value; `LIPE_MU_DIR` doubles as the second-load guard and skips the whole file instead.

The opcache handler writes PHP into `WP_CACHE_DIR` and `include`s it back. Anything reaching that file is executed,
so a value is written only when every object inside it appears in `get_set_state_map()`.

---

## Migrating an existing project

Every project built from `lipemat/starting-point` carries these files loose in `wp-content/mu-plugins`, so the same
steps apply to all of them. Work in order; each step names what "done" looks like.

Paths assume the core plugin at `wp-content/plugins/core`. Adjust every path if it lives elsewhere.

### 1. Record the current configuration

Two values are project-specific and are lost once the files are deleted. Copy them somewhere you can paste from:

- `wp-content/mu-plugins/force-plugin-activation.php` — the `FORCE_ACTIVE`, `FORCE_DEBUG`, and `FORCE_DEACTIVATE`
  constants.
- Any `OBJECT_CACHE_HANDLER` definition. Search the whole project: `local-config.php`, `local-config-sample.php`,
  `dev/wp-unit/local-config.php`, and `.idea/runConfigurations/*.xml` all commonly hold one.

Done when all three plugin lists are written down, empty ones included, and every `OBJECT_CACHE_HANDLER` site is
listed.

### 2. Install the package

Run from `wp-content/plugins/core`:

```bash
composer require lipemat/mu-plugins
```

Done when `wp-content/plugins/core/vendor/lipemat/mu-plugins/load.php` exists.

### 3. Delete the extracted files

Remove from `wp-content/mu-plugins`:

`cache-groups.php`, `display-actions.php`, `escape.php`, `force-plugin-activation.php`, `memoize.php`,
`mu-loader.php`, `template-crumbs.php`, `use-the-force.php`, and the `wp-cache/` directory.

`mu-loader.php` goes too: its only remaining body was a `wp_installing()` bail which guarded nothing. Only
`force-plugin-activation.php` skipped installs itself; `load.php` now skips every module whose `installing` flag is
off, while the API modules keep loading.

Leave everything else. Project-local development tooling such as `jest-authentication.php`, `local-dev-host.php`, and
host-specific plugins stay where they are.

Done when those nine entries are gone and nothing else in the directory changed.

### 4. Add the project stub

Copy `vendor/lipemat/mu-plugins/stubs/mu-plugin-loader.php` to `wp-content/mu-plugins/lipe-mu-plugins.php`, then paste
the step 1 values into `LIPE_MU_FORCE_ACTIVE`, `LIPE_MU_FORCE_DEBUG`, and `LIPE_MU_FORCE_DEACTIVATE`. The stub requires
the core plugin's Composer autoloader, then `load.php`.

Done when the three constants match what step 1 recorded and both `require` paths resolve to existing files.

### 5. Load the core autoloader once

The stub now loads the core plugin's autoloader first. In `wp-content/plugins/core/core.php`, change the autoloader
`require` to `require_once`.

Done when `core.php` reaches `vendor/autoload.php` only through `require_once`.

### 6. Replace the object cache drop-in

Copy `vendor/lipemat/mu-plugins/stubs/object-cache.php` over `wp-content/object-cache.php`.

Done when that file's `require` resolves to an existing `object-cache/base.php`.

### 7. Repoint `OBJECT_CACHE_HANDLER`

The handlers moved and were renamed, so every path recorded in step 1 now points at nothing:

| Old                                             | New                                                     |
|-------------------------------------------------|---------------------------------------------------------|
| `mu-plugins/wp-cache/object-cache-memcache.php` | `vendor/lipemat/mu-plugins/object-cache/memcached.php`  |
| `mu-plugins/wp-cache/object-cache-opcache.php`  | `vendor/lipemat/mu-plugins/object-cache/opcache.php`    |

Done when every `OBJECT_CACHE_HANDLER` occurrence resolves to a file which exists.

### 8. Rename the force-plugin-activation filters

The two filters gained a prefix. A project which hooks either one keeps hooking the old name silently, so search for
both:

| Old                                            | New                                                    |
|------------------------------------------------|--------------------------------------------------------|
| `force-plugin-activation/get-force-active`     | `lipe/mu/force-plugin-activation/get-force-active`     |
| `force-plugin-activation/get-force-deactivate` | `lipe/mu/force-plugin-activation/get-force-deactivate` |

Done when neither unprefixed name appears in the project.

### 9. Update the analysis configuration

`phpstan.neon.dist` needs both of these:

- Keep `wp-content/mu-plugins` in `paths`. The remaining mu-plugins and the new stub still need analysing.
- Add the package's modules to `scanDirectories`, or every call to `es()`, `sn()`, `memoize()`, `once()`,
  `lipe_template_contents()`, and `_lipe_template_crumbs_called_from()` reports as an unknown function:

```yaml
scanDirectories:
    - wp-content/plugins/core/vendor/lipemat/mu-plugins/plugins
```

Also repoint any IDE run configuration which names `mu-plugins/wp-cache`, such as
`.idea/runConfigurations/Unit_Tests__Opcache_.xml`.

Done when `phpcs` and `phpstan` both run clean from the project root.

### 10. Delete the migrated tests

The package owns these now. Remove from the project's `dev/wp-unit/tests`: the `cache/` directory,
`UseTheForceTest.php`, and `Memoize_Test.php`.

Done when `phpunit` passes from the project's `dev/wp-unit` with those files gone.

### 11. Point the project's own AGENTS.md at the package

A future agent looking for `use-the-force` or the object cache needs to know the code left the repository. Add a short
section naming the two stubs, the vendor path, and which mu-plugins stayed behind.

Done when the project's `AGENTS.md` names `vendor/lipemat/mu-plugins` as where that code now lives.

### 12. Verify against the running site

1. Load a page. It renders, and the class of the `wp_object_cache` global resolves to a file under
   `vendor/lipemat/mu-plugins/object-cache/`.
2. Open the plugins screen. Every plugin in `LIPE_MU_FORCE_ACTIVE` is active with no Deactivate link.
3. With `WP_DEBUG` on and a non-production environment, view source. Template crumb comments appear.
4. Request a file under `wp-content/cache` over HTTP. It returns empty, never the cached value.

Done when all four hold.

---

## Verification

- `phpcs` and `phpstan` from the package root.
- `phpunit` from `dev/wp-unit/`, once against Memcached and once with `OBJECT_CACHE_HANDLER` pointed at
  `object-cache/opcache.php`, since the two handlers share the suite.
- `yarn lint` and `yarn dist-sass` from the package root when anything under `css/` changed, with the recompiled
  output committed.
