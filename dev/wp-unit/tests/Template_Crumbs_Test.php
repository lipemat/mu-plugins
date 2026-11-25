<?php
declare( strict_types=1 );

namespace Lipe\Mu;

/**
 * @author Mat Lipe
 * @since  November 2026
 */
final class Template_Crumbs_Test extends \WP_UnitTestCase {
	private const string ENABLED_FILTER = 'lipe/mu/template-crumbs/enabled';


	public function test_enabled_off_under_unit_tests(): void {
		$actual = _lipe_template_crumbs_enabled();

		$this->assertFalse( $actual );
	}


	public function test_enabled_filter_receives_default(): void {
		$received = [];
		add_filter( self::ENABLED_FILTER, function( bool $enabled ) use ( &$received ): bool {
			$received[] = $enabled;
			return $enabled;
		} );

		_lipe_template_crumbs_enabled();

		$this->assertSame( [ false ], $received );
	}


	public function test_enabled_filter_turns_on(): void {
		add_filter( self::ENABLED_FILTER, '__return_true' );

		$actual = _lipe_template_crumbs_enabled();

		$this->assertTrue( $actual );
	}


	public function test_enabled_filter_requires_boolean_true(): void {
		add_filter( self::ENABLED_FILTER, fn(): string => '1' );

		$actual = _lipe_template_crumbs_enabled();

		$this->assertFalse( $actual );
	}
}
