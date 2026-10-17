<?php
/**
 * The per-site fields under Settings, Reading.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt;

/**
 * One section "Site text files" on the Reading page with a field for llms.txt
 * and one for security.txt. Each site keeps its own options. A reminder shows
 * in the dashboard when the security.txt is about to expire.
 */
class Settings {

	public const SECTION = 'ploetner_site_txt';

	/**
	 * Option names. The llms.txt name is kept from version 1, so existing
	 * content stays where it is.
	 */
	public const LLMS_OPTION     = 'ploetner_llms_txt';
	public const SECURITY_OPTION = 'ploetner_security_txt';

	/**
	 * Longest accepted llms.txt, in bytes.
	 */
	public const MAX_LENGTH = 100000;

	/**
	 * Hook setting registration and the expiry reminder.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_notices', array( $this, 'expiry_notice' ) );
	}

	/**
	 * Register the options, the section and its fields on the Reading page.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			'reading',
			self::LLMS_OPTION,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'reading',
			self::SECURITY_OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_security' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			self::SECTION,
			__( 'Site text files', 'ploetner-site-txt' ),
			'__return_null',
			'reading'
		);

		add_settings_field(
			self::LLMS_OPTION,
			__( 'llms.txt', 'ploetner-site-txt' ),
			array( $this, 'render_field' ),
			'reading',
			self::SECTION,
			array( 'label_for' => self::LLMS_OPTION )
		);

		add_settings_field(
			self::SECURITY_OPTION,
			__( 'security.txt', 'ploetner-site-txt' ),
			array( $this, 'render_security_field' ),
			'reading',
			self::SECTION,
			array( 'label_for' => self::SECURITY_OPTION . '_contact' )
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
	 * Clean the security.txt fields. Contacts are kept only if usable, URLs
	 * must be https, and a missing or past expiry date becomes the day before
	 * one year from today (RFC 9116 recommends less than a year).
	 *
	 * @param mixed $value Submitted value.
	 *
	 * @return array<string, string>
	 */
	public function sanitize_security( $value ): array {
		$value  = is_array( $value ) ? $value : array();
		$fields = SecurityTxt::DEFAULTS;

		$fields['contact'] = implode( "\n", SecurityTxt::contacts( isset( $value['contact'] ) && is_string( $value['contact'] ) ? $value['contact'] : '' ) );

		foreach ( array_keys( SecurityTxt::URL_FIELDS ) as $key ) {
			$url            = isset( $value[ $key ] ) && is_string( $value[ $key ] ) ? trim( $value[ $key ] ) : '';
			$fields[ $key ] = preg_match( '#^https://\S+$#i', $url ) ? esc_url_raw( $url, array( 'https' ) ) : '';
		}

		$languages           = isset( $value['languages'] ) && is_string( $value['languages'] ) ? strtolower( $value['languages'] ) : '';
		$tags                = array_filter( array_map( 'trim', explode( ',', $languages ) ), static fn ( string $tag ): bool => (bool) preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/', $tag ) );
		$fields['languages'] = implode( ', ', array_unique( $tags ) );

		$expires = isset( $value['expires'] ) && is_string( $value['expires'] ) ? trim( $value['expires'] ) : '';
		$at      = SecurityTxt::expires_at( $expires );
		if ( '' !== $fields['contact'] && ( null === $at || $at < time() ) ) {
			$expires = gmdate( 'Y-m-d', strtotime( '+1 year -1 day' ) );
		}
		$fields['expires'] = null === SecurityTxt::expires_at( $expires ) ? '' : $expires;

		return $fields;
	}

	/**
	 * Render the llms.txt textarea with a link to the live file.
	 *
	 * @return void
	 */
	public function render_field(): void {
		printf(
			'<textarea id="%1$s" name="%1$s" rows="14" class="large-text code" spellcheck="false">%2$s</textarea>',
			esc_attr( self::LLMS_OPTION ),
			esc_textarea( (string) get_option( self::LLMS_OPTION, '' ) )
		);

		printf(
			'<p class="description">%s <a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_html__( 'Markdown for this site\'s llms.txt. Leave empty to generate it from the site title, tagline and top-level pages.', 'ploetner-site-txt' ),
			esc_url( home_url( '/llms.txt' ) ),
			esc_html__( 'View llms.txt', 'ploetner-site-txt' )
		);
	}

	/**
	 * Render the security.txt fields, or a note on sites below the host root.
	 *
	 * @return void
	 */
	public function render_security_field(): void {
		if ( ! SecurityTxt::is_host_root() ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'A security.txt belongs to the root of a domain. It is set on the site at the root of this domain, not here.', 'ploetner-site-txt' )
			);
			return;
		}

		$fields = SecurityTxt::fields();
		$name   = static fn ( string $key ): string => self::SECURITY_OPTION . '[' . $key . ']';
		$id     = static fn ( string $key ): string => self::SECURITY_OPTION . '_' . $key;

		echo '<fieldset>';

		printf(
			'<p><label for="%1$s">%2$s</label><br><textarea id="%1$s" name="%3$s" rows="3" class="large-text code" spellcheck="false" placeholder="security@example.com">%4$s</textarea></p>',
			esc_attr( $id( 'contact' ) ),
			esc_html__( 'Contact (required): e-mail addresses or https links, one per line. Leave empty to serve no security.txt.', 'ploetner-site-txt' ),
			esc_attr( $name( 'contact' ) ),
			esc_textarea( implode( "\n", array_map( static fn ( string $uri ): string => (string) preg_replace( '#^mailto:#', '', $uri ), SecurityTxt::contacts( $fields['contact'] ) ) ) )
		);

		printf(
			'<p><label for="%1$s">%2$s</label><br><input type="date" id="%1$s" name="%3$s" value="%4$s"></p>',
			esc_attr( $id( 'expires' ) ),
			esc_html__( 'Expires: when the details should be reviewed. Empty or past: one year from today.', 'ploetner-site-txt' ),
			esc_attr( $name( 'expires' ) ),
			esc_attr( $fields['expires'] )
		);

		$labels = array(
			'policy'          => __( 'Policy (optional): https link to your disclosure policy', 'ploetner-site-txt' ),
			'encryption'      => __( 'Encryption (optional): https link to your OpenPGP key', 'ploetner-site-txt' ),
			'acknowledgments' => __( 'Acknowledgments (optional): https link to a thank-you page', 'ploetner-site-txt' ),
		);
		foreach ( $labels as $key => $label ) {
			printf(
				'<p><label for="%1$s">%2$s</label><br><input type="url" id="%1$s" name="%3$s" value="%4$s" class="regular-text code" placeholder="https://"></p>',
				esc_attr( $id( $key ) ),
				esc_html( $label ),
				esc_attr( $name( $key ) ),
				esc_attr( $fields[ $key ] )
			);
		}

		printf(
			'<p><label for="%1$s">%2$s</label><br><input type="text" id="%1$s" name="%3$s" value="%4$s" class="regular-text code" placeholder="%5$s"></p>',
			esc_attr( $id( 'languages' ) ),
			esc_html__( 'Preferred languages (optional), e.g. "en, de". Empty: the site language.', 'ploetner-site-txt' ),
			esc_attr( $name( 'languages' ) ),
			esc_attr( $fields['languages'] ),
			esc_attr( SecurityTxt::site_language() )
		);

		echo '</fieldset>';

		printf(
			'<p class="description">%s <a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_html__( 'Served at /.well-known/security.txt (RFC 9116).', 'ploetner-site-txt' ),
			esc_url( home_url( SecurityTxt::PATH ) ),
			esc_html__( 'View security.txt', 'ploetner-site-txt' )
		);
	}

	/**
	 * Remind administrators when the security.txt expires soon or has expired.
	 *
	 * @return void
	 */
	public function expiry_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! SecurityTxt::is_host_root() ) {
			return;
		}

		$fields = SecurityTxt::fields();
		$at     = SecurityTxt::expires_at( $fields['expires'] );
		if ( array() === SecurityTxt::contacts( $fields['contact'] ) || null === $at ) {
			return;
		}

		$days = (int) floor( ( $at - time() ) / DAY_IN_SECONDS );
		if ( $days > SecurityTxt::REMIND_DAYS ) {
			return;
		}

		if ( $days < 0 ) {
			$text = __( 'The security.txt of this site has expired. Please review the contact details and set a new date.', 'ploetner-site-txt' );
		} elseif ( 0 === $days ) {
			$text = __( 'The security.txt of this site expires today. Please review it and set a new date.', 'ploetner-site-txt' );
		} else {
			$text = sprintf(
				/* translators: %d: number of days. */
				_n( 'The security.txt of this site expires in %d day. Please review it and set a new date.', 'The security.txt of this site expires in %d days. Please review it and set a new date.', $days, 'ploetner-site-txt' ),
				$days
			);
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html( $text ),
			esc_url( admin_url( 'options-reading.php#' . self::SECURITY_OPTION . '_contact' ) ),
			esc_html__( 'Review security.txt', 'ploetner-site-txt' )
		);
	}
}
