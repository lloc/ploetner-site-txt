<?php
/**
 * Tests for the must-use loader notice in the plugin list.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt\Tests\Unit;

use Brain\Monkey\Functions;
use lloc\PloetnerSiteTxt\MuLoaderNotice;
use lloc\PloetnerSiteTxt\Tests\TestCase;
use Mockery;

/**
 * @coversDefaultClass \lloc\PloetnerSiteTxt\MuLoaderNotice
 */
class MuLoaderNoticeTest extends TestCase {

	private const BASENAME = 'ploetner-site-txt/ploetner-site-txt.php';

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubs( array( 'esc_html__' ) );
	}

	/**
	 * @covers ::__construct
	 * @covers ::register
	 */
	public function test_register_hooks_the_plugin_list(): void {
		Functions\expect( 'add_filter' )->once()->with( 'plugin_row_meta', Mockery::type( 'array' ), 10, 2 );
		Functions\expect( 'add_filter' )->once()->with( 'plugin_action_links_' . self::BASENAME, Mockery::type( 'array' ) );
		Functions\expect( 'add_filter' )->once()->with( 'network_admin_plugin_action_links_' . self::BASENAME, Mockery::type( 'array' ) );

		( new MuLoaderNotice( self::BASENAME ) )->register();
	}

	/**
	 * @covers ::row_meta
	 * @covers ::action_links
	 * @covers ::loaded_by_mu_plugin
	 */
	public function test_inactive_but_running_means_mu_loader(): void {
		Functions\when( 'is_plugin_active' )->justReturn( false );
		$notice = new MuLoaderNotice( self::BASENAME );

		$meta = $notice->row_meta( array( 'Version 2.0.0' ), self::BASENAME );
		$this->assertCount( 2, $meta );
		$this->assertStringContainsString( 'must-use loader', $meta[1] );

		$this->assertSame( array( 'Version 2.0.0' ), $notice->row_meta( array( 'Version 2.0.0' ), 'other/other.php' ), 'Other rows stay.' );

		$this->assertSame(
			array( 'delete' => '<a>Delete</a>' ),
			$notice->action_links(
				array(
					'activate'         => '<a>Activate</a>',
					'network_activate' => '<a>Network Activate</a>',
					'delete'           => '<a>Delete</a>',
				)
			)
		);
	}

	/**
	 * @covers ::row_meta
	 * @covers ::action_links
	 * @covers ::loaded_by_mu_plugin
	 */
	public function test_an_activated_plugin_is_left_alone(): void {
		Functions\when( 'is_plugin_active' )->justReturn( true );
		$notice = new MuLoaderNotice( self::BASENAME );

		$this->assertSame( array( 'Version 2.0.0' ), $notice->row_meta( array( 'Version 2.0.0' ), self::BASENAME ) );
		$this->assertSame( array( 'deactivate' => '<a>Deactivate</a>' ), $notice->action_links( array( 'deactivate' => '<a>Deactivate</a>' ) ) );
	}
}
