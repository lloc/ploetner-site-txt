<?php
/**
 * Plugin Name:       Ploetner Site TXT
 * Plugin URI:        https://github.com/lloc/ploetner-site-txt
 * Description:       Serves per-site text files in multisite and multi-network installs: llms.txt (from a text field, or generated from the site) and /.well-known/security.txt (RFC 9116), both under Settings, Reading.
 * Version:           2.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Network:           true
 * Author:            Dennis Ploetner
 * Author URI:        https://ploetner.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ploetner-site-txt
 * Domain Path:       /languages
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

add_action( 'plugins_loaded', static fn () => ( new Plugin() )->register() );
