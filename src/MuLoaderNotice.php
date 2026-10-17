<?php
/**
 * Explains in the plugin list that the plugin runs through a must-use loader.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt;

/**
 * In a multi-network install the plugin is loaded by a one-line file in
 * mu-plugins, so it serves every site of every network. The plugin list then
 * shows it as inactive, which looks wrong and invites a second activation
 * (loading it twice). When the plugin runs without being activated, its row
 * says so and the Activate link is removed.
 */
class MuLoaderNotice {

	/**
	 * Plugin basename, e.g. "ploetner-site-txt/ploetner-site-txt.php".
	 *
	 * @var string
	 */
	private string $basename;

	/**
	 * Constructor.
	 *
	 * @param string|null $basename Plugin basename, defaults to this plugin's.
	 */
	public function __construct( ?string $basename = null ) {
		// Folder name plus main file, like plugin_basename(), but also right when the folder is a symlink.
		$this->basename = $basename ?? basename( dirname( __DIR__ ) ) . '/ploetner-site-txt.php';
	}

	/**
	 * Hook into the plugin list (site and network admin).
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'action_links' ) );
		add_filter( 'network_admin_plugin_action_links_' . $this->basename, array( $this, 'action_links' ) );
	}

	/**
	 * Whether the plugin runs although it is not activated, i.e. through the
	 * must-use loader.
	 *
	 * @return bool
	 */
	public function loaded_by_mu_plugin(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			return false;
		}

		return ! is_plugin_active( $this->basename );
	}

	/**
	 * Add a line under the description of this plugin.
	 *
	 * @param mixed $meta Row meta links.
	 * @param mixed $file Plugin basename of the row.
	 *
	 * @return array<int|string, string>
	 */
	public function row_meta( $meta, $file ): array {
		$meta = is_array( $meta ) ? $meta : array();
		if ( $this->basename !== $file || ! $this->loaded_by_mu_plugin() ) {
			return $meta;
		}

		$meta[] = '<strong>' . esc_html__( 'Loaded on every site by a must-use loader (mu-plugins). No activation needed.', 'ploetner-site-txt' ) . '</strong>';

		return $meta;
	}

	/**
	 * Drop the Activate links, they would load the plugin a second time.
	 *
	 * @param mixed $actions Action links.
	 *
	 * @return array<int|string, string>
	 */
	public function action_links( $actions ): array {
		$actions = is_array( $actions ) ? $actions : array();
		if ( ! $this->loaded_by_mu_plugin() ) {
			return $actions;
		}

		unset( $actions['activate'], $actions['network_activate'] );

		return $actions;
	}
}
