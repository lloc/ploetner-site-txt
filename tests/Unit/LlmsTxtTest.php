<?php
/**
 * Tests for the llms.txt generator.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use lloc\PloetnerSiteTxt\LlmsTxt;
use lloc\PloetnerSiteTxt\Settings;
use lloc\PloetnerSiteTxt\Tests\TestCase;

require_once __DIR__ . '/stubs.php';

/**
 * @coversDefaultClass \lloc\PloetnerSiteTxt\LlmsTxt
 */
class LlmsTxtTest extends TestCase {

	/**
	 * Options returned by get_option().
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->options = array(
			Settings::LLMS_OPTION => '',
			'page_on_front'       => 0,
		);
		Functions\when( 'get_option' )->alias( fn ( string $name, $fallback = false ) => $this->options[ $name ] ?? $fallback );
		Functions\when( 'get_current_blog_id' )->justReturn( 3 );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $text ): string => (string) preg_replace( '/<[^>]*>/', '', $text ) );
		Functions\when( 'get_bloginfo' )->alias(
			static fn ( string $key ): string => 'name' === $key ? 'Ploetner &amp; Cloud' : 'WordPress audits <em>and</em> fix sprints'
		);
		Functions\when( 'get_the_title' )->alias( static fn ( \WP_Post $post ): string => $post->post_title );
		Functions\when( 'get_permalink' )->alias( static fn ( \WP_Post $post ): string => 'https://ploetner.cloud/' . $post->post_name . '/' );
	}

	/**
	 * A page double.
	 *
	 * @param int    $id       ID.
	 * @param string $title    Title.
	 * @param string $slug     Slug.
	 * @param string $excerpt  Manual excerpt.
	 * @param string $password Password.
	 *
	 * @return \WP_Post
	 */
	private static function page( int $id, string $title, string $slug, string $excerpt = '', string $password = '' ): \WP_Post {
		$post                = new \WP_Post();
		$post->ID            = $id;
		$post->post_title    = $title;
		$post->post_name     = $slug;
		$post->post_excerpt  = $excerpt;
		$post->post_password = $password;

		return $post;
	}

	/**
	 * @covers ::content
	 */
	public function test_stored_content_wins_and_ends_with_one_newline(): void {
		$this->options[ Settings::LLMS_OPTION ] = "# Custom\n\n> Hand written.\n\n\n";
		Functions\expect( 'get_pages' )->never();

		$this->assertSame( "# Custom\n\n> Hand written.\n", ( new LlmsTxt() )->content() );
	}

	/**
	 * @covers ::content
	 * @covers ::generate
	 * @covers ::page_links
	 * @covers ::plain
	 */
	public function test_generated_default_from_title_tagline_and_pages(): void {
		$this->options['page_on_front'] = 2;
		Functions\when( 'get_pages' )->justReturn(
			array(
				self::page( 1, 'Audit [Sprint]', 'audit', 'What the audit covers.' ),
				self::page( 2, 'Home', 'home' ),
				self::page( 3, 'Secret', 'secret', '', 'pw' ),
			)
		);

		$expected = "# Ploetner & Cloud\n\n"
			. "> WordPress audits and fix sprints\n\n"
			. "## Pages\n\n"
			. "- [Home](https://ploetner.cloud/home/)\n"
			. "- [Audit (Sprint)](https://ploetner.cloud/audit/): What the audit covers.\n";

		$this->assertSame( $expected, ( new LlmsTxt() )->content() );
	}

	/**
	 * @covers ::generate
	 * @covers ::page_links
	 */
	public function test_generated_default_without_tagline_or_pages(): void {
		Functions\when( 'get_bloginfo' )->alias( static fn ( string $key ): string => 'name' === $key ? 'msls.co' : '' );
		Functions\when( 'get_pages' )->justReturn( false );

		$this->assertSame( "# msls.co\n", ( new LlmsTxt() )->generate() );
	}

	/**
	 * @covers ::generate
	 */
	public function test_generated_heading_falls_back_to_the_host_name(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Functions\when( 'get_pages' )->justReturn( array() );
		Functions\when( 'home_url' )->justReturn( 'https://msls.co' );
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url, int $component = -1 ) => parse_url( $url, $component ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

		$this->assertSame( "# msls.co\n", ( new LlmsTxt() )->generate() );
	}

	/**
	 * @covers ::content
	 */
	public function test_filter_can_extend_or_disable(): void {
		$this->options[ Settings::LLMS_OPTION ] = '# Site';

		Filters\expectApplied( 'ploetner_llms_txt' )
			->once()
			->with( '# Site', 3, true )
			->andReturn( "# Site\n\n## Optional\n\n- [API](https://example.test/api)" );
		$this->assertStringEndsWith( "(https://example.test/api)\n", ( new LlmsTxt() )->content() );
	}

	/**
	 * @covers ::content
	 */
	public function test_empty_filter_result_means_no_llms_txt(): void {
		$this->options[ Settings::LLMS_OPTION ] = '# Site';
		Filters\expectApplied( 'ploetner_llms_txt' )->once()->andReturn( '   ' );

		$this->assertSame( '', ( new LlmsTxt() )->content() );
	}
}
