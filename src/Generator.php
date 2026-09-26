<?php
/**
 * Builds the llms.txt content of the current site.
 *
 * @package PloetnerLlmsTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerLlmsTxt;

/**
 * The content is the text from Settings, Reading if the site has one, else a
 * sensible default generated from the site: title, tagline and top-level pages.
 * Everything works on the current site, so it behaves the same in single-site,
 * multisite and multi-network installs.
 */
class Generator {

	/**
	 * Most top-level pages listed in the generated default.
	 */
	public const MAX_PAGES = 25;

	/**
	 * The llms.txt of the current site, or an empty string to serve none.
	 *
	 * @return string
	 */
	public function content(): string {
		$stored  = trim( (string) get_option( Settings::OPTION, '' ) );
		$content = '' !== $stored ? $stored : $this->generate();

		/**
		 * Filter the llms.txt of the current site. Return an empty string to answer 404.
		 *
		 * @param string $content Markdown content.
		 * @param int    $blog_id Current site ID.
		 * @param bool   $stored  Whether the content comes from the site's setting.
		 */
		$content = (string) apply_filters( 'ploetner_llms_txt', $content, get_current_blog_id(), '' !== $stored );

		return '' === trim( $content ) ? '' : rtrim( $content ) . "\n";
	}

	/**
	 * Default content from the site's own data.
	 *
	 * @return string
	 */
	public function generate(): string {
		$name = self::plain( (string) get_bloginfo( 'name' ) );
		if ( '' === $name ) {
			$name = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		}

		$lines = array( '# ' . $name, '' );

		$tagline = self::plain( (string) get_bloginfo( 'description' ) );
		if ( '' !== $tagline ) {
			$lines[] = '> ' . $tagline;
			$lines[] = '';
		}

		$links = $this->page_links();
		if ( array() !== $links ) {
			$lines[] = '## Pages';
			$lines[] = '';
			foreach ( $links as $link ) {
				$lines[] = $link;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Markdown links to the published top-level pages, front page first.
	 *
	 * @return list<string>
	 */
	private function page_links(): array {
		$pages = get_pages(
			array(
				'parent'      => 0,
				'sort_column' => 'menu_order,post_title',
				'number'      => self::MAX_PAGES,
			)
		);

		if ( ! is_array( $pages ) ) {
			return array();
		}

		$front = (int) get_option( 'page_on_front' );
		usort(
			$pages,
			static fn ( \WP_Post $a, \WP_Post $b ): int => (int) ( $b->ID === $front ) <=> (int) ( $a->ID === $front )
		);

		$links = array();
		foreach ( $pages as $page ) {
			if ( '' !== $page->post_password ) {
				continue;
			}

			$title = self::plain( get_the_title( $page ) );
			$url   = (string) get_permalink( $page );
			if ( '' === $title || '' === $url ) {
				continue;
			}

			$line    = sprintf( '- [%s](%s)', str_replace( array( '[', ']' ), array( '(', ')' ), $title ), $url );
			$excerpt = self::plain( $page->post_excerpt );
			if ( '' !== $excerpt ) {
				$line .= ': ' . $excerpt;
			}
			$links[] = $line;
		}

		return $links;
	}

	/**
	 * One line of plain text: no tags, no entities, collapsed whitespace.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	private static function plain( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
