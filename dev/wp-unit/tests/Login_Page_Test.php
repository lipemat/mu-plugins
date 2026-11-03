<?php
declare( strict_types=1 );

namespace Lipe\Mu;

/**
 * @author Mat Lipe
 * @since  November 2026
 */
final class Login_Page_Test extends \WP_UnitTestCase {
	private const string CDN_URL = 'https://cdn.test/content';
	private const string CORE_LABEL = 'Remember Me';
	private const string CORE_SUBMIT = 'Log In';
	private const string CORE_TEXT = 'Powered by WordPress';
	private const string CORE_URL = 'https://wordpress.org/';
	private const string ONPOINT_URL = 'https://onpointplugins.com/';
	private const string OTHER_TEXT_DOMAIN = 'lipe';
	private const string SWAPPED_LABEL = 'Keep me logged in for two weeks';


	/**
	 * `wp_enqueue_style()` leaves an already registered handle alone.
	 */
	public function setUp(): void {
		parent::setUp();

		wp_deregister_style( 'lipe/mu/login-page' );
	}


	public function test_headertext_inlines_logo(): void {
		$actual = self::header_text();

		$this->assertStringContainsString( '<svg ', $actual, 'The mark is inlined, never loaded from a file' );
		$this->assertStringContainsString( 'fill="currentColor"', $actual, 'The bars track the header color' );
		$this->assertStringContainsString( 'stroke="currentColor"', $actual, 'The ring tracks the header color' );
		$this->assertStringContainsString( '#8fae1b', $actual, 'The center dot stays the literal brand green' );
		$this->assertStringContainsString( 'Handcrafted by <br />', $actual, 'The attribution leads the mark' );
		$this->assertStringContainsString( '</svg> OnPoint', $actual, 'The word follows the mark' );
	}


	public function test_headertext_discards_core_text(): void {
		$actual = self::header_text();

		$this->assertStringNotContainsString( self::CORE_TEXT, $actual, 'Core passes its own text in, and it is replaced rather than appended to' );
	}


	public function test_headertext_hides_logo_from_assistive_tech(): void {
		$actual = self::header_text();

		$this->assertStringContainsString( 'aria-hidden="true"', $actual, 'The mark is decorative beside the word' );
		$this->assertStringContainsString( 'focusable="false"', $actual, 'The mark never takes tab focus inside the link' );
		$this->assertStringNotContainsString( 'aria-label', $actual, 'Core\'s anchor keeps the single accessible name' );
	}


	public function test_header_renders_single_anchor(): void {
		$actual = self::render_header();

		$this->assertStringNotContainsString( '<a', self::header_text(), 'The header text contributes no anchor of its own' );
		$this->assertSame( 1, \substr_count( $actual, '<a ' ), 'Core supplies the only opening anchor tag' );
		$this->assertSame( 1, \substr_count( $actual, '</a>' ), 'Core supplies the only closing anchor tag' );
	}


	public function test_headerurl_points_at_onpoint(): void {
		$actual = self::header_url();

		$this->assertSame( self::ONPOINT_URL, $actual, 'The header logo links to OnPoint' );
	}


	public function test_stylesheet_enqueues_from_the_package(): void {
		do_action( 'login_enqueue_scripts' );

		$actual = self::stylesheet_src();

		$this->assertStringStartsWith( content_url(), $actual, 'The sheet is served out of the content directory' );
		$this->assertStringEndsWith( LIPE_MU_PATH . '/css/login.css', $actual, 'The sheet is this package\'s own, wherever it is vendored' );
	}


	/**
	 * Regression: a URL built in `load.php` predates the filter.
	 *
	 * @see AGENTS.md
	 */
	public function test_stylesheet_honors_filtered_content_url(): void {
		add_filter( 'content_url', fn( string $url, string $path ): string => self::CDN_URL . '/' . \ltrim( $path, '/' ), 10, 2 );

		do_action( 'login_enqueue_scripts' );
		$actual = self::stylesheet_src();

		$this->assertStringStartsWith( self::CDN_URL, $actual, 'The URL is built at enqueue time, so a late `content_url` filter still applies' );
	}


	public function test_remember_me_label_swapped(): void {
		do_action( 'login_form' );

		$actual = self::translate_label();

		$this->assertSame( self::SWAPPED_LABEL, $actual, 'The checkbox names the duration it buys' );
	}


	public function test_label_untouched_before_login_form(): void {
		$actual = self::translate_label();

		$this->assertSame( self::CORE_LABEL, $actual, 'A request which never renders the form pays nothing' );
	}


	public function test_other_default_domain_string_untouched(): void {
		do_action( 'login_form' );

		$actual = translate( self::CORE_SUBMIT );

		$this->assertSame( self::CORE_SUBMIT, $actual, 'Only the one string in the `default` domain is swapped' );
	}


	public function test_remember_me_in_other_domain_untouched(): void {
		do_action( 'login_form' );

		$actual = translate( self::CORE_LABEL, self::OTHER_TEXT_DOMAIN );

		$this->assertSame( self::CORE_LABEL, $actual, 'Another plugin\'s identical string keeps its own translation' );
	}


	public function test_gettext_kept_until_it_matches(): void {
		do_action( 'login_form' );
		translate( self::CORE_LABEL, self::OTHER_TEXT_DOMAIN );

		$actual = self::translate_label();

		$this->assertSame( self::SWAPPED_LABEL, $actual, 'A near miss does not spend the one swap' );
	}


	public function test_gettext_unhooked_once_it_matches(): void {
		do_action( 'login_form' );

		self::translate_label();

		$this->assertFalse( has_filter( 'gettext' ), 'The rest of the request pays nothing for the swap' );
	}


	public function test_remember_me_label_swapped_once(): void {
		do_action( 'login_form' );
		self::translate_label();

		$actual = self::translate_label();

		$this->assertSame( self::CORE_LABEL, $actual, 'A later use of the same string is core\'s own again' );
	}


	/**
	 * Mirror the `<h1>` core renders in `login_header()`, which echoes the
	 * filtered text unescaped inside its own anchor.
	 *
	 * @see login_header()
	 */
	private static function render_header(): string {
		return \sprintf(
			'<h1 role="presentation" class="wp-login-logo"><a href="%s">%s</a></h1>',
			esc_url( self::header_url() ),
			self::header_text()
		);
	}


	private static function header_text(): string {
		return (string) apply_filters( 'login_headertext', self::CORE_TEXT );
	}


	private static function header_url(): string {
		return (string) apply_filters( 'login_headerurl', self::CORE_URL );
	}


	/**
	 * Core reaches the label through `esc_html_e( 'Remember Me' )`, which
	 * translates against the `default` domain.
	 *
	 * @see wp-login.php
	 */
	private static function translate_label(): string {
		return translate( self::CORE_LABEL );
	}


	private static function stylesheet_src(): string {
		$style = wp_styles()->registered['lipe/mu/login-page'] ?? null;

		return $style instanceof \_WP_Dependency ? (string) $style->src : '';
	}
}
