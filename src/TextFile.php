<?php
/**
 * A text file served per site.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt;

/**
 * One file the Server answers for, relative to the current site's home URL.
 */
interface TextFile {

	/**
	 * Path below the site's home URL, with a leading slash, e.g. "/llms.txt".
	 *
	 * @return string
	 */
	public function path(): string;

	/**
	 * Content of the current site's file, ending with a newline, or an empty
	 * string to answer 404.
	 *
	 * @return string
	 */
	public function content(): string;
}
