<?php
/**
 * Tests for the settings field.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt\Tests\Unit;

use Brain\Monkey\Functions;
use lloc\PloetnerSiteTxt\SecurityTxt;
use lloc\PloetnerSiteTxt\Settings;
use lloc\PloetnerSiteTxt\Tests\TestCase;
use Mockery;

/**
 * @coversDefaultClass \lloc\PloetnerSiteTxt\Settings
 */
class SettingsTest extends TestCase {

	/**
	 * @covers ::register
	 */
	public function test_register_hooks_admin_init_and_notices(): void {
		Functions\expect( 'add_action' )->once()->with( 'admin_init', Mockery::type( 'array' ) );
		Functions\expect( 'add_action' )->once()->with( 'admin_notices', Mockery::type( 'array' ) );

		( new Settings() )->register();
	}

	/**
	 * @covers ::register_setting
	 */
	public function test_register_setting_adds_options_section_and_fields_to_reading(): void {
		Functions\stubs( array( '__' ) );
		Functions\expect( 'register_setting' )
			->once()
			->with( 'reading', 'ploetner_llms_txt', Mockery::on( static fn ( array $args ): bool => 'string' === $args['type'] && '' === $args['default'] ) );
		Functions\expect( 'register_setting' )
			->once()
			->with( 'reading', 'ploetner_security_txt', Mockery::on( static fn ( array $args ): bool => 'array' === $args['type'] ) );
		Functions\expect( 'add_settings_section' )->once()->with( Settings::SECTION, 'Site text files', '__return_null', 'reading' );
		Functions\expect( 'add_settings_field' )
			->once()
			->with( 'ploetner_llms_txt', 'llms.txt', Mockery::type( 'array' ), 'reading', Settings::SECTION, array( 'label_for' => 'ploetner_llms_txt' ) );
		Functions\expect( 'add_settings_field' )
			->once()
			->with( 'ploetner_security_txt', 'security.txt', Mockery::type( 'array' ), 'reading', Settings::SECTION, array( 'label_for' => 'ploetner_security_txt_contact' ) );

		( new Settings() )->register_setting();
	}

	/**
	 * @covers ::sanitize
	 */
	public function test_sanitize_keeps_markdown_and_normalizes(): void {
		$in = "  # Site\r\n\r\n> Summary with <angle> & stuff\x07\r\n- [Link](https://example.com)  \n\n";

		$this->assertSame(
			"# Site\n\n> Summary with <angle> & stuff\n- [Link](https://example.com)",
			( new Settings() )->sanitize( $in )
		);
	}

	/**
	 * @covers ::sanitize
	 */
	public function test_sanitize_caps_the_length_on_a_character_boundary(): void {
		$result = ( new Settings() )->sanitize( str_repeat( 'ä', Settings::MAX_LENGTH ) );

		$this->assertLessThanOrEqual( Settings::MAX_LENGTH, strlen( $result ) );
		$this->assertTrue( mb_check_encoding( $result, 'UTF-8' ) );
	}

	/**
	 * @covers ::render_field
	 */
	public function test_render_field_prints_the_textarea_and_the_link(): void {
		Functions\when( 'get_option' )->justReturn( '# Site <b>' );
		Functions\when( 'home_url' )->justReturn( 'https://ploetner.cloud/llms.txt' );
		Functions\stubs( array( 'esc_attr', 'esc_url', 'esc_html__' ) );
		Functions\when( 'esc_textarea' )->alias( static fn ( string $text ): string => htmlspecialchars( $text ) );

		ob_start();
		( new Settings() )->render_field();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<textarea id="ploetner_llms_txt" name="ploetner_llms_txt"', $html );
		$this->assertStringContainsString( '# Site &lt;b&gt;</textarea>', $html );
		$this->assertStringContainsString( 'href="https://ploetner.cloud/llms.txt"', $html );
	}

	/**
	 * @covers ::sanitize_security
	 */
	public function test_sanitize_security_cleans_every_field(): void {
		Functions\when( 'is_email' )->alias( static fn ( string $mail ) => filter_var( $mail, FILTER_VALIDATE_EMAIL ) ? $mail : false );
		Functions\when( 'esc_url_raw' )->returnArg();

		$fields = ( new Settings() )->sanitize_security(
			array(
				'contact'         => "security@example.com\nhttp://insecure.example.com\nhttps://example.com/report\nnonsense",
				'expires'         => '2020-01-01',
				'policy'          => 'http://example.com/policy',
				'encryption'      => 'https://example.com/pgp.asc',
				'acknowledgments' => 'javascript:alert(1)',
				'languages'       => 'EN, de, not a tag, de',
			)
		);

		$this->assertSame( "mailto:security@example.com\nhttps://example.com/report", $fields['contact'] );
		$this->assertSame( gmdate( 'Y-m-d', strtotime( '+1 year -1 day' ) ), $fields['expires'], 'A past date becomes just under one year from today.' );
		$this->assertSame( '', $fields['policy'] );
		$this->assertSame( 'https://example.com/pgp.asc', $fields['encryption'] );
		$this->assertSame( '', $fields['acknowledgments'] );
		$this->assertSame( 'en, de', $fields['languages'] );
	}

	/**
	 * @covers ::sanitize_security
	 */
	public function test_sanitize_security_keeps_a_future_date_and_needs_no_date_without_contact(): void {
		Functions\when( 'is_email' )->justReturn( false );
		Functions\when( 'esc_url_raw' )->returnArg();

		$future = gmdate( 'Y-m-d', strtotime( '+3 months' ) );
		$this->assertSame(
			$future,
			( new Settings() )->sanitize_security(
				array(
					'contact' => 'https://example.com/r',
					'expires' => $future,
				)
			)['expires']
		);
		$this->assertSame(
			'',
			( new Settings() )->sanitize_security(
				array(
					'contact' => '',
					'expires' => 'soon',
				)
			)['expires']
		);
		$this->assertSame( SecurityTxt::DEFAULTS, ( new Settings() )->sanitize_security( 'garbage' ) );
	}

	/**
	 * @covers ::render_security_field
	 */
	public function test_security_field_is_only_a_note_below_the_host_root(): void {
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com/de' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url, int $component = -1 ) => parse_url( $url, $component ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\stubs( array( 'esc_html__' ) );

		ob_start();
		( new Settings() )->render_security_field();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'belongs to the root of a domain', $html );
		$this->assertStringNotContainsString( '<input', $html );
	}

	/**
	 * @covers ::render_security_field
	 */
	public function test_security_field_renders_inputs_on_the_host_root(): void {
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://ploetner.cloud' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url, int $component = -1 ) => parse_url( $url, $component ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\when( 'get_option' )->justReturn(
			array(
				'contact' => 'mailto:security@ploetner.cloud',
				'expires' => '2027-10-01',
			)
		);
		Functions\when( 'get_locale' )->justReturn( 'de_DE' );
		Functions\when( 'is_email' )->alias( static fn ( string $mail ) => filter_var( $mail, FILTER_VALIDATE_EMAIL ) ? $mail : false );
		Functions\stubs( array( 'esc_attr', 'esc_url', 'esc_html', 'esc_html__', '__' ) );
		Functions\when( 'esc_textarea' )->returnArg();

		ob_start();
		( new Settings() )->render_security_field();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="ploetner_security_txt[contact]"', $html );
		$this->assertStringContainsString( '>security@ploetner.cloud</textarea>', $html );
		$this->assertStringContainsString( 'value="2027-10-01"', $html );
		$this->assertStringContainsString( 'placeholder="de"', $html );
		$this->assertStringContainsString( 'href="https://ploetner.cloud/.well-known/security.txt"', $html );
	}

	/**
	 * @covers ::expiry_notice
	 */
	public function test_expiry_notice_only_when_due(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://ploetner.cloud' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url, int $component = -1 ) => parse_url( $url, $component ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\when( 'is_email' )->justReturn( false );
		Functions\when( '_n' )->alias( static fn ( string $one, string $many, int $n ): string => 1 === $n ? $one : $many );
		Functions\stubs( array( 'esc_html', 'esc_url', 'esc_html__', '__', 'admin_url' ) );

		$expires = gmdate( 'Y-m-d', strtotime( '+10 days' ) );
		Functions\when( 'get_option' )->justReturn(
			array(
				'contact' => 'https://example.com/r',
				'expires' => $expires,
			)
		);
		ob_start();
		( new Settings() )->expiry_notice();
		$this->assertStringContainsString( 'expires in 10 days', (string) ob_get_clean() );

		$expires = gmdate( 'Y-m-d', strtotime( '+200 days' ) );
		Functions\when( 'get_option' )->justReturn(
			array(
				'contact' => 'https://example.com/r',
				'expires' => $expires,
			)
		);
		ob_start();
		( new Settings() )->expiry_notice();
		$this->assertSame( '', (string) ob_get_clean() );

		Functions\when( 'get_option' )->justReturn(
			array(
				'contact' => 'https://example.com/r',
				'expires' => gmdate( 'Y-m-d' ),
			)
		);
		ob_start();
		( new Settings() )->expiry_notice();
		$this->assertStringContainsString( 'expires today', (string) ob_get_clean() );
	}
}
