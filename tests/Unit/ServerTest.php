<?php
/**
 * Tests for the text file server.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt\Tests\Unit;

use Brain\Monkey\Functions;
use lloc\PloetnerSiteTxt\LlmsTxt;
use lloc\PloetnerSiteTxt\SecurityTxt;
use lloc\PloetnerSiteTxt\Server;
use lloc\PloetnerSiteTxt\TextFile;
use lloc\PloetnerSiteTxt\Tests\TestCase;
use Mockery;

/**
 * @coversDefaultClass \lloc\PloetnerSiteTxt\Server
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
	 * A file double with a fixed path.
	 *
	 * @param string $path Path below the home URL.
	 *
	 * @return TextFile
	 */
	private static function file( string $path ): TextFile {
		return new class( $path ) implements TextFile {
			public function __construct( private string $path ) {}
			public function path(): string {
				return $this->path;
			}
			public function content(): string {
				return 'x';
			}
		};
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string|null}>
	 */
	public static function path_provider(): array {
		return array(
			'root site llms'           => array( 'https://ploetner.cloud', '/llms.txt', '/llms.txt' ),
			'root site with query'     => array( 'https://ploetner.cloud', '/llms.txt?ref=x', '/llms.txt' ),
			'root site security'       => array( 'https://ploetner.cloud', '/.well-known/security.txt', '/.well-known/security.txt' ),
			'other file'               => array( 'https://ploetner.cloud', '/llms-full.txt', null ),
			'nested path'              => array( 'https://ploetner.cloud', '/audit/llms.txt', null ),
			'subdirectory site'        => array( 'https://example.com/site', '/site/llms.txt', '/llms.txt' ),
			'root file on subdir site' => array( 'https://example.com/site', '/llms.txt', null ),
			'root security on subdir'  => array( 'https://example.com/site', '/.well-known/security.txt', null ),
			'empty uri'                => array( 'https://ploetner.cloud', '', null ),
		);
	}

	/**
	 * @covers ::match
	 * @dataProvider path_provider
	 *
	 * @param string      $home     Home URL of the current site.
	 * @param string      $uri      Request URI.
	 * @param string|null $expected Path of the matched file, or null.
	 */
	public function test_match( string $home, string $uri, ?string $expected ): void {
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => $home . $path );

		$server = new Server( array( self::file( '/llms.txt' ), self::file( '/.well-known/security.txt' ) ) );
		$file   = $server->match( $uri );

		$this->assertSame( $expected, null === $file ? null : $file->path() );
	}

	/**
	 * @covers ::__construct
	 */
	public function test_default_files_are_llms_and_security_txt(): void {
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://ploetner.cloud' . $path );

		$server = new Server();

		$this->assertInstanceOf( LlmsTxt::class, $server->match( '/llms.txt' ) );
		$this->assertInstanceOf( SecurityTxt::class, $server->match( '/.well-known/security.txt' ) );
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
