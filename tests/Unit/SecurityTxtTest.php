<?php
/**
 * Tests for the security.txt builder.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use lloc\PloetnerSiteTxt\SecurityTxt;
use lloc\PloetnerSiteTxt\Settings;
use lloc\PloetnerSiteTxt\Tests\TestCase;

/**
 * @coversDefaultClass \lloc\PloetnerSiteTxt\SecurityTxt
 */
class SecurityTxtTest extends TestCase {

	/**
	 * Stored option value.
	 *
	 * @var mixed
	 */
	private $stored = array();

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			function ( string $name ) {
				return Settings::SECURITY_OPTION === $name ? $this->stored : false;
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://ploetner.cloud' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url, int $component = -1 ) => parse_url( $url, $component ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\when( 'is_email' )->alias( static fn ( string $mail ) => filter_var( $mail, FILTER_VALIDATE_EMAIL ) ? $mail : false );
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'get_locale' )->justReturn( 'de_DE' );
	}

	/**
	 * @covers ::path
	 */
	public function test_path_is_well_known(): void {
		$this->assertSame( '/.well-known/security.txt', ( new SecurityTxt() )->path() );
	}

	/**
	 * @covers ::content
	 * @covers ::fields
	 * @covers ::contacts
	 * @covers ::expires_at
	 * @covers ::site_language
	 */
	public function test_content_follows_rfc_9116(): void {
		$this->stored = array(
			'contact'    => "mailto:security@ploetner.cloud\nhttps://ploetner.cloud/contact/",
			'expires'    => '2027-10-01',
			'encryption' => 'https://ploetner.cloud/pgp.asc',
			'policy'     => 'http://not-https.example.com',
		);

		$this->assertSame(
			"Contact: mailto:security@ploetner.cloud\n"
			. "Contact: https://ploetner.cloud/contact/\n"
			. "Expires: 2027-10-01T23:59:59Z\n"
			. "Encryption: https://ploetner.cloud/pgp.asc\n"
			. "Preferred-Languages: de\n"
			. "Canonical: https://ploetner.cloud/.well-known/security.txt\n",
			( new SecurityTxt() )->content()
		);
	}

	/**
	 * @covers ::content
	 */
	public function test_no_contact_or_no_date_means_no_file(): void {
		$this->stored = array( 'expires' => '2027-10-01' );
		$this->assertSame( '', ( new SecurityTxt() )->content() );

		$this->stored = array( 'contact' => 'mailto:a@example.com' );
		$this->assertSame( '', ( new SecurityTxt() )->content() );

		$this->stored = 'not an array';
		$this->assertSame( '', ( new SecurityTxt() )->content() );
	}

	/**
	 * @covers ::content
	 */
	public function test_filter_can_change_the_content(): void {
		$this->stored = array();
		Filters\expectApplied( 'ploetner_security_txt' )->once()->with( '', 1 )->andReturn( 'Contact: mailto:x@example.com' );

		$this->assertSame( "Contact: mailto:x@example.com\n", ( new SecurityTxt() )->content() );
	}

	/**
	 * @covers ::contacts
	 */
	public function test_contacts_accept_mail_https_and_tel_only(): void {
		$this->assertSame(
			array( 'mailto:a@example.com', 'https://example.com/r', 'tel:+39021234' ),
			SecurityTxt::contacts( "a@example.com\r\nMAILTO:a@example.com\nhttps://example.com/r\nhttp://example.com\ntel:+39 02 1234\nftp://x\n\n" )
		);
	}

	/**
	 * @covers ::is_host_root
	 */
	public function test_is_host_root(): void {
		$this->assertTrue( SecurityTxt::is_host_root() );

		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/de' . $path );
		$this->assertFalse( SecurityTxt::is_host_root() );
	}

	/**
	 * @covers ::expires_at
	 */
	public function test_expires_at_needs_a_date(): void {
		$this->assertSame( strtotime( '2027-10-01T23:59:59Z' ), SecurityTxt::expires_at( '2027-10-01' ) );
		$this->assertNull( SecurityTxt::expires_at( 'next year' ) );
		$this->assertNull( SecurityTxt::expires_at( '2027-02-31' ), 'No rollover into March.' );
		$this->assertNull( SecurityTxt::expires_at( '' ) );
	}
}
