<?php
/**
 * The per-site llms.txt field under Settings, Reading.
 *
 * @package PloetnerLlmsTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerLlmsTxt;

/**
 * One option per site. Empty means: generate the default from the site.
 */
class Settings {

	public const OPTION = 'ploetner_llms_txt';

	/**
	 * Longest accepted content, in bytes.
	 */
	public const MAX_LENGTH = 100000;

	/**
	 * Hook setting and field registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Register the option and its field on the Reading settings page.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			'reading',
			self::OPTION,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_field(
			self::OPTION,
			__( 'llms.txt', 'ploetner-llms-txt' ),
			array( $this, 'render_field' ),
			'reading',
			'default',
			array( 'label_for' => self::OPTION )
		);
	}

	/**
	 * Keep the Markdown as typed: normalize line breaks, drop control
	 * characters, cap the length. It is served as text/plain, never as HTML.
	 *
	 * @param mixed $value Submitted value.
	 *
	 * @return string
	 */
	public function sanitize( $value ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
		$text = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		$text = trim( $text );

		if ( strlen( $text ) > self::MAX_LENGTH ) {
			$text = rtrim( mb_strcut( $text, 0, self::MAX_LENGTH, 'UTF-8' ) );
		}

		return $text;
	}

	/**
	 * Render the textarea with a link to the live file.
	 *
	 * @return void
	 */
	public function render_field(): void {
		printf(
			'<textarea id="%1$s" name="%1$s" rows="14" class="large-text code" spellcheck="false">%2$s</textarea>',
			esc_attr( self::OPTION ),
			esc_textarea( (string) get_option( self::OPTION, '' ) )
		);

		printf(
			'<p class="description">%s <a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_html__( 'Markdown for this site\'s llms.txt. Leave empty to generate it from the site title, tagline and top-level pages.', 'ploetner-llms-txt' ),
			esc_url( home_url( '/llms.txt' ) ),
			esc_html__( 'View llms.txt', 'ploetner-llms-txt' )
		);
	}
}
