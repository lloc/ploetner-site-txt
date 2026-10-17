<?php
/**
 * PHPUnit bootstrap: Composer autoloader.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
