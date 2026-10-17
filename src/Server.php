<?php
/**
 * Answers requests for the per-site text files.
 *
 * @package PloetnerSiteTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerSiteTxt;

use WP;

/**
 * Serves each TextFile below the current site's home URL. No rewrite rules to
 * flush: the request path is compared on `parse_request`, which also works for
 * sites in subdirectories (example.com/site/llms.txt) and with domain mapping.
 *
 * A physical llms.txt or .well-known/security.txt in the document root would
 * be served for every site of the network by the web server, before WordPress
 * runs. Do not create one.
 */
class Server {

	/**
	 * Seconds browsers and proxies (e.g. Cloudflare) may cache the answer.
	 */
	public const MAX_AGE = 3600;

	/**
	 * Files this server answers for.
	 *
	 * @var list<TextFile>
	 */
	private array $files;

	/**
	 * Constructor.
	 *
	 * @param list<TextFile>|null $files Files to serve, defaults to llms.txt and security.txt.
	 */
	public function __construct( ?array $files = null ) {
		$this->files = $files ?? array( new LlmsTxt(), new SecurityTxt() );
	}

	/**
	 * Hook into request parsing, early.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'parse_request', array( $this, 'maybe_serve' ), 0 );
	}

	/**
	 * Serve a file and stop, if one was requested.
	 *
	 * @param WP $wp Current WordPress environment (unused).
	 *
	 * @return void
	 */
	public function maybe_serve( $wp ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Hook signature.
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$file = $this->match( $uri );
		if ( null === $file ) {
			return;
		}

		$this->send( $file->content() );
		exit;
	}

	/**
	 * The file a request URI asks for, if any.
	 *
	 * @param string $request_uri Request URI, e.g. "/llms.txt" or "/site/llms.txt?x=1".
	 *
	 * @return TextFile|null
	 */
	public function match( string $request_uri ): ?TextFile {
		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( '' === $path ) {
			return null;
		}

		foreach ( $this->files as $file ) {
			if ( (string) wp_parse_url( home_url( $file->path() ), PHP_URL_PATH ) === $path ) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * Send headers and body. An empty body answers 404.
	 *
	 * @param string $content Plain-text content.
	 *
	 * @return void
	 */
	public function send( string $content ): void {
		if ( '' === $content ) {
			status_header( 404 );
			nocache_headers();
			$this->header( 'Content-Type: text/plain; charset=utf-8' );
			return;
		}

		status_header( 200 );
		$this->header( 'Content-Type: text/plain; charset=utf-8' );
		$this->header( 'Cache-Control: public, max-age=' . self::MAX_AGE );
		$this->header( 'X-Robots-Tag: noindex' );
		$this->header( 'X-Content-Type-Options: nosniff' );

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text response, not HTML.
	}

	/**
	 * Send one header line, unless output has already started.
	 *
	 * @param string $line Header line.
	 *
	 * @return void
	 */
	private function header( string $line ): void {
		if ( ! headers_sent() ) {
			header( $line );
		}
	}
}
