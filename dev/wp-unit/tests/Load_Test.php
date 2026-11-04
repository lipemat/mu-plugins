<?php
declare( strict_types=1 );

namespace Lipe\Mu;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @author Mat Lipe
 * @since  November 2026
 */
#[CoversFunction( 'lipe_mu_modules_to_load' )]
final class Load_Test extends \WP_UnitTestCase {
	/**
	 * @param array<int|string, mixed> $disabled
	 * @param list<string>             $expected
	 */
	#[DataProvider( 'providerModulesToLoad' )]
	public function test_modules_to_load( bool $installing, array $disabled, array $expected, string $message ): void {
		$actual = lipe_mu_modules_to_load( $installing, $disabled );

		$this->assertSame( $expected, $actual, $message );
	}


	/**
	 * @return array<string, array{
	 *     installing: bool,
	 *     disabled: array<int|string, mixed>,
	 *     expected: list<string>,
	 *     message: string,
	 * }>
	 */
	public static function providerModulesToLoad(): array {
		return [
			'Not installing'                => [
				'installing' => false,
				'disabled'   => [],
				'expected'   => [
					'cache-groups',
					'display-actions',
					'escape',
					'force-plugin-activation',
					'login-page',
					'memoize',
					'template-crumbs',
					'use-the-force',
				],
				'message'    => 'Every module loads outside of an install.',
			],
			'Not installing, disabled'      => [
				'installing' => false,
				'disabled'   => [ 'cache-groups', 'escape' ],
				'expected'   => [
					'display-actions',
					'force-plugin-activation',
					'login-page',
					'memoize',
					'template-crumbs',
					'use-the-force',
				],
				'message'    => 'Disabled modules are skipped.',
			],
			'Installing'                    => [
				'installing' => true,
				'disabled'   => [],
				'expected'   => [ 'escape', 'memoize', 'template-crumbs' ],
				'message'    => 'Only the API modules a theme calls load during an install.',
			],
			'Installing, disabled'          => [
				'installing' => true,
				'disabled'   => [ 'memoize', 'use-the-force' ],
				'expected'   => [ 'escape', 'template-crumbs' ],
				'message'    => 'Disabled modules are skipped during an install.',
			],
			'Installing, all API disabled'  => [
				'installing' => true,
				'disabled'   => [ 'escape', 'memoize', 'template-crumbs' ],
				'expected'   => [],
				'message'    => 'Nothing loads when every install module is disabled.',
			],
			'Unknown and malformed disabled' => [
				'installing' => false,
				'disabled'   => [ 'unknown', 'escape.php', 'Memoize', 0, null, true ],
				'expected'   => [
					'cache-groups',
					'display-actions',
					'escape',
					'force-plugin-activation',
					'login-page',
					'memoize',
					'template-crumbs',
					'use-the-force',
				],
				'message'    => 'Only exact module names disable a module.',
			],
		];
	}
}
