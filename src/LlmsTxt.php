<?php
/**
 * Builds the llms.txt content of the current site.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt;

/**
 * The content is the text from Settings, Reading if the site has one, else a
 * sensible default generated from the site: title, tagline and top-level pages.
 * Everything works on the current site, so it behaves the same in single-site,
 * multisite and multi-network installs.
 */
class LlmsTxt implements TextFile {

	/**
	 * Most top-level pages listed in the generated default.
	 */
	public const MAX_PAGES = 25;

	/**
	 * {@inheritDoc}
	 */
	public function path(): string {
		return '/llms.txt';
	}

	/**
	 * The llms.txt of the current site, or an empty string to serve none.
	 *
	 * @return string
	 */
	public function content(): string {
		$stored  = trim( (string) get_option( Settings::LLMS_OPTION, '' ) );
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
		$front_id = (int) get_option( 'page_on_front' );
		$front    = $front_id > 0 ? get_post( $front_id ) : null;
		$front    = $front instanceof \WP_Post && 'page' === $front->post_type && 'publish' === $front->post_status ? $front : null;

		// The front page is fetched on its own, so it is listed even if it sorts past MAX_PAGES.
		$pages = get_pages(
			array(
				'parent'      => 0,
				'sort_column' => 'menu_order,post_title',
				'number'      => self::MAX_PAGES - ( null === $front ? 0 : 1 ),
				'exclude'     => null === $front ? array() : array( $front->ID ),
			)
		);

		$pages = is_array( $pages ) ? $pages : array();
		if ( null !== $front ) {
			array_unshift( $pages, $front );
		}

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
