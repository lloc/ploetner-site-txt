<?php
/**
 * Tests for the llms.txt server.
 *
 * @package PloetnerLlmsTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerLlmsTxt\Tests\Unit;

use Brain\Monkey\Functions;
use lloc\PloetnerLlmsTxt\Server;
use lloc\PloetnerLlmsTxt\Tests\TestCase;
use Mockery;

/**
 * @coversDefaultClass \lloc\PloetnerLlmsTxt\Server
 */
class ServerTest extends TestCase {

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url, int $component = -1 ) => parse_url( $url, $component ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		unset( $_SERVER['REQUEST_METHOD'] );
		parent::tearDown();
	}

	/**
	 * @covers ::__construct
	 * @covers ::register
	 */
	public function test_register_hooks_parse_request_early(): void {
		Functions\expect( 'add_action' )->once()->with( 'parse_request', Mockery::type( 'array' ), 0 );

		( new Server() )->register();
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public static function path_provider(): array {
		return array(
			'root site'                => array( 'https://ploetner.cloud/llms.txt', '/llms.txt', true ),
			'root site with query'     => array( 'https://ploetner.cloud/llms.txt', '/llms.txt?ref=x', true ),
			'other file'               => array( 'https://ploetner.cloud/llms.txt', '/llms-full.txt', false ),
			'nested path'              => array( 'https://ploetner.cloud/llms.txt', '/audit/llms.txt', false ),
			'subdirectory site'        => array( 'https://example.com/site/llms.txt', '/site/llms.txt', true ),
			'root file on subdir site' => array( 'https://example.com/site/llms.txt', '/llms.txt', false ),
			'empty uri'                => array( 'https://ploetner.cloud/llms.txt', '', false ),
		);
	}

	/**
	 * @covers ::is_llms_txt
	 * @dataProvider path_provider
	 *
	 * @param string $home     URL returned by home_url( '/llms.txt' ).
	 * @param string $uri      Request URI.
	 * @param bool   $expected Expected result.
	 */
	public function test_is_llms_txt( string $home, string $uri, bool $expected ): void {
		Functions\when( 'home_url' )->justReturn( $home );

		$this->assertSame( $expected, ( new Server() )->is_llms_txt( $uri ) );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_outputs_the_content_with_status_200(): void {
		Functions\expect( 'status_header' )->once()->with( 200 );
		Functions\expect( 'nocache_headers' )->never();

		$this->expectOutputString( "# Site\n" );
		( new Server() )->send( "# Site\n" );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_skips_the_body_for_head_requests(): void {
		$_SERVER['REQUEST_METHOD'] = 'HEAD';
		Functions\expect( 'status_header' )->once()->with( 200 );

		$this->expectOutputString( '' );
		( new Server() )->send( "# Site\n" );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_answers_404_for_empty_content(): void {
		Functions\expect( 'status_header' )->once()->with( 404 );
		Functions\expect( 'nocache_headers' )->once();

		$this->expectOutputString( '' );
		( new Server() )->send( '' );
	}
}
