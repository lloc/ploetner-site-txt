<?php
/**
 * Plugin bootstrap.
 *
 * @package PloetnerLlmsTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerLlmsTxt;

/**
 * Wires every component of the plugin to WordPress.
 */
class Plugin {

	/**
	 * Register every component.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Settings() )->register();
		( new Server() )->register();
	}

	/**
	 * Load the bundled translations from the plugin's `languages/` folder.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'ploetner-llms-txt', false, basename( dirname( __DIR__ ) ) . '/languages' );
	}
}
