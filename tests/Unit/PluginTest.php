<?php
/**
 * Tests for the plugin bootstrap.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt\Tests\Unit;

use Brain\Monkey\Functions;
use lloc\PloetnerSiteTxt\Plugin;
use lloc\PloetnerSiteTxt\Tests\TestCase;

/**
 * @coversDefaultClass \lloc\PloetnerSiteTxt\Plugin
 */
class PluginTest extends TestCase {

	/**
	 * @covers ::register
	 */
	public function test_register_wires_hooks(): void {
		// 1 (textdomain) + 2 (settings, expiry notice) + 1 (server) = 4 add_action calls.
		Functions\expect( 'add_action' )->times( 4 );
		Functions\when( 'is_admin' )->justReturn( false );

		( new Plugin() )->register();
	}

	/**
	 * @covers ::register
	 */
	public function test_register_adds_the_mu_loader_notice_in_the_admin(): void {
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\expect( 'add_filter' )->times( 3 );

		( new Plugin() )->register();
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_points_at_the_bundled_languages_folder(): void {
		Functions\expect( 'load_plugin_textdomain' )
			->once()
			->with( 'ploetner-site-txt', false, \Mockery::pattern( '#/languages$#' ) );

		( new Plugin() )->load_textdomain();
	}
}
