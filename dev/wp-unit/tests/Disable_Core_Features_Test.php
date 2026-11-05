<?php
declare( strict_types=1 );

namespace Lipe\Mu;

/**
 * @author Mat Lipe
 * @since  November 2026
 */
final class Disable_Core_Features_Test extends \WP_UnitTestCase {
	private const string RANDOM_CONTENT_REDIRECT = 'wp_enable_random_content_redirect';


	public function test_random_content_redirect_disabled(): void {
		$actual = apply_filters( self::RANDOM_CONTENT_REDIRECT, true );

		$this->assertFalse( $actual, 'The redirect is turned off even when core enables it' );
	}


	public function test_random_content_redirect_reenabled_by_later_filter(): void {
		add_filter( self::RANDOM_CONTENT_REDIRECT, fn(): bool => true );

		$actual = apply_filters( self::RANDOM_CONTENT_REDIRECT, false );

		$this->assertTrue( $actual, 'A project may opt back in with a later priority' );
	}
}
