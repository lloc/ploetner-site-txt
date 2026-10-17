<?php
/**
 * Builds the security.txt (RFC 9116) of the current site.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt;

/**
 * Served at /.well-known/security.txt below the site's home URL. RFC 9116
 * wants the file at the root of a host, so in practice the site at the root
 * of each domain answers: the main site of a subdirectory network, or every
 * site with its own domain. Sites in a subdirectory get no field for it.
 *
 * Contact and Expires are required; without a contact the file answers 404.
 *
 * @phpstan-type Fields array{contact: string, expires: string, encryption: string, policy: string, acknowledgments: string, languages: string}
 */
class SecurityTxt implements TextFile {

	public const PATH = '/.well-known/security.txt';

	/**
	 * Days before Expires that the dashboard starts to remind.
	 */
	public const REMIND_DAYS = 30;

	/**
	 * Empty set of fields.
	 *
	 * @var array<string, string>
	 */
	public const DEFAULTS = array(
		'contact'         => '',
		'expires'         => '',
		'encryption'      => '',
		'policy'          => '',
		'acknowledgments' => '',
		'languages'       => '',
	);

	/**
	 * Optional https fields and their names in the file, in output order.
	 *
	 * @var array<string, string>
	 */
	public const URL_FIELDS = array(
		'encryption'      => 'Encryption',
		'acknowledgments' => 'Acknowledgments',
		'policy'          => 'Policy',
	);

	/**
	 * {@inheritDoc}
	 */
	public function path(): string {
		return self::PATH;
	}

	/**
	 * Whether the current site sits at the root of its host, the only place
	 * RFC 9116 looks for the file.
	 *
	 * @return bool
	 */
	public static function is_host_root(): bool {
		$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		return '' === trim( $path, '/' );
	}

	/**
	 * Stored fields of the current site, completed with defaults.
	 *
	 * @return array
	 * @phpstan-return Fields
	 */
	public static function fields(): array {
		$stored = get_option( Settings::SECURITY_OPTION, array() );
		$fields = self::DEFAULTS;

		if ( is_array( $stored ) ) {
			foreach ( array_keys( self::DEFAULTS ) as $key ) {
				$fields[ $key ] = isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
			}
		}

		/** @phpstan-var Fields $fields */
		return $fields;
	}

	/**
	 * The contact lines as URIs: e-mail addresses become mailto:, https and
	 * tel: URIs pass, anything else is dropped.
	 *
	 * @param string $contact One contact per line.
	 *
	 * @return list<string>
	 */
	public static function contacts( string $contact ): array {
		$uris  = array();
		$lines = preg_split( '/\R/', $contact );
		foreach ( false === $lines ? array() : $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( is_email( $line ) ) {
				$uris[] = 'mailto:' . $line;
			} elseif ( preg_match( '#^mailto:(.+)$#i', $line, $m ) && is_email( $m[1] ) ) {
				$uris[] = 'mailto:' . $m[1];
			} elseif ( preg_match( '#^https://\S+$#i', $line ) || preg_match( '#^tel:\+?[0-9 ().-]+$#i', $line ) ) {
				$uris[] = $line;
			}
		}

		return array_values( array_unique( $uris ) );
	}

	/**
	 * Expiry as a Unix timestamp (end of the stored day, UTC), or null.
	 *
	 * @param string $expires Date as Y-m-d.
	 *
	 * @return int|null
	 */
	public static function expires_at( string $expires ): ?int {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ) {
			return null;
		}

		$time = strtotime( $expires . 'T23:59:59Z' );

		return false === $time ? null : $time;
	}

	/**
	 * {@inheritDoc}
	 */
	public function content(): string {
		$fields   = self::fields();
		$contacts = self::contacts( $fields['contact'] );
		$expires  = self::expires_at( $fields['expires'] );

		$lines = array();
		if ( array() !== $contacts && null !== $expires ) {
			foreach ( $contacts as $uri ) {
				$lines[] = 'Contact: ' . $uri;
			}
			$lines[] = 'Expires: ' . gmdate( 'Y-m-d\TH:i:s\Z', $expires );

			foreach ( self::URL_FIELDS as $key => $name ) {
				if ( preg_match( '#^https://\S+$#i', $fields[ $key ] ) ) {
					$lines[] = $name . ': ' . $fields[ $key ];
				}
			}

			$languages = '' !== trim( $fields['languages'] ) ? $fields['languages'] : self::site_language();
			if ( '' !== $languages ) {
				$lines[] = 'Preferred-Languages: ' . $languages;
			}

			$lines[] = 'Canonical: ' . home_url( self::PATH );
		}

		$content = implode( "\n", $lines );

		/**
		 * Filter the security.txt of the current site. Return an empty string to answer 404.
		 *
		 * @param string $content security.txt content.
		 * @param int    $blog_id Current site ID.
		 */
		$content = (string) apply_filters( 'ploetner_security_txt', $content, get_current_blog_id() );

		return '' === trim( $content ) ? '' : rtrim( $content ) . "\n";
	}

	/**
	 * Language of the site as a tag for Preferred-Languages, e.g. "de".
	 *
	 * @return string
	 */
	public static function site_language(): string {
		$locale = (string) get_locale();

		return strtolower( (string) strtok( $locale, '_' ) );
	}
}
