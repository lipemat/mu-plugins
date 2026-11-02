<?php
/**
 * Package declarations for phpstan.
 *
 * Holds any classes, constants, or functions which are otherwise
 * not available via included files and directories.
 *
 * @link https://phpstan.org/user-guide/discovering-symbols
 *
 *  scanFiles:
 *  - dev/stubs/stubs.php
 */
namespace {
	/**
	 * Phpstan only discovers a `define()` holding a constant expression.
	 *
	 * `@var` keeps the placeholder from narrowing to its own literal.
	 *
	 * @see load.php
	 *
	 * @var string
	 */
	const LIPE_MU_PATH = '';
}
