<?php
/**
 * Tests for the plugin bootstrap.
 *
 * @package PloetnerLlmsTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerLlmsTxt\Tests\Unit;

use Brain\Monkey\Functions;
use lloc\PloetnerLlmsTxt\Plugin;
use lloc\PloetnerLlmsTxt\Tests\TestCase;

/**
 * @coversDefaultClass \lloc\PloetnerLlmsTxt\Plugin
 */
class PluginTest extends TestCase {

	/**
	 * @covers ::register
	 */
	public function test_register_wires_hooks(): void {
		// 1 (textdomain) + 1 (settings) + 1 (server) = 3 add_action calls.
		Functions\expect( 'add_action' )->times( 3 );

		( new Plugin() )->register();
	}

	/**
	 * @covers ::load_textdomain
	 */
	public function test_load_textdomain_points_at_the_bundled_languages_folder(): void {
		Functions\expect( 'load_plugin_textdomain' )
			->once()
			->with( 'ploetner-llms-txt', false, \Mockery::pattern( '#/languages$#' ) );

		( new Plugin() )->load_textdomain();
	}
}
