<?php
/**
 * Minimal WordPress class stubs for the unit tests.
 *
 * @package PloetnerLlmsTxt
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Commenting, Generic.Commenting.DocComment.MissingShort

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID               = 0;
		public string $post_title    = '';
		public string $post_name     = '';
		public string $post_excerpt  = '';
		public string $post_password = '';
	}
}
