<?php
/**
 * Tests for the settings field.
 *
 * @package PloetnerLlmsTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerLlmsTxt\Tests\Unit;

use Brain\Monkey\Functions;
use lloc\PloetnerLlmsTxt\Settings;
use lloc\PloetnerLlmsTxt\Tests\TestCase;
use Mockery;

/**
 * @coversDefaultClass \lloc\PloetnerLlmsTxt\Settings
 */
class SettingsTest extends TestCase {

	/**
	 * @covers ::register
	 */
	public function test_register_hooks_admin_init(): void {
		Functions\expect( 'add_action' )->once()->with( 'admin_init', Mockery::type( 'array' ) );

		( new Settings() )->register();
	}

	/**
	 * @covers ::register_setting
	 */
	public function test_register_setting_adds_option_and_field_to_reading(): void {
		Functions\stubs( array( '__' ) );
		Functions\expect( 'register_setting' )
			->once()
			->with( 'reading', 'ploetner_llms_txt', Mockery::on( static fn ( array $args ): bool => 'string' === $args['type'] && '' === $args['default'] ) );
		Functions\expect( 'add_settings_field' )
			->once()
			->with( 'ploetner_llms_txt', 'llms.txt', Mockery::type( 'array' ), 'reading', 'default', array( 'label_for' => 'ploetner_llms_txt' ) );

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
}
