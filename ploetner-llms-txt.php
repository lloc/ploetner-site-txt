<?php
/**
 * Plugin Name:       Ploetner llms.txt
 * Plugin URI:        https://github.com/lloc/ploetner-llms-txt
 * Description:       Serves a per-site llms.txt in multisite and multi-network installs: from a text field under Settings, Reading, or generated from the site's title, tagline and top-level pages.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Network:           true
 * Author:            Dennis Ploetner
 * Author URI:        https://ploetner.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ploetner-llms-txt
 * Domain Path:       /languages
 *
 * @package PloetnerLlmsTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerLlmsTxt;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

add_action( 'plugins_loaded', static fn () => ( new Plugin() )->register() );
