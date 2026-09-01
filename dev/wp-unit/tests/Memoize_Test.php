<?php

namespace Lipe\Mu;

/**
 * @author Mat Lipe
 * @since  June 2022
 *
 */
class Memoize_Test extends \WP_UnitTestCase {
	public function test_template_tags() : void {
		[ $post1, $post2 ] = self::factory()->post->create_many( 2 );
		$once = \once( 'get_the_title' );
		$memo = \memoize( 'get_the_title' );
		$title_1 = get_the_title( $post1 );
		$title_2 = get_the_title( $post2 );
		$this->assertEquals( $title_1, $once( $post1 ) );
		$this->assertEquals( $title_1, $memo( $post1 ) );
		wp_update_post( [
			'ID'         => $post1,
			'post_title' => 'xxxx',
		] );
		$this->assertEquals( 'xxxx', get_the_title( $post1 ) );
		$this->assertEquals( $title_1, $once( $post1 ) );
		$this->assertEquals( $title_1, $memo( $post1 ) );

		$this->assertEquals( $title_1, $once( $post2 ) );
		$this->assertEquals( $title_2, $memo( $post2 ) );
		wp_update_post( [
			'ID'         => $post2,
			'post_title' => 'xxxx2',
		] );
		$this->assertEquals( 'xxxx2', get_the_title( $post2 ) );
		$this->assertEquals( $title_1, $once( $post2 ) );
		$this->assertEquals( $title_2, $memo( $post2 ) );
	}
}
