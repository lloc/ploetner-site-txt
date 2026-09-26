<?php
/**
 * Answers requests for /llms.txt.
 *
 * @package PloetnerLlmsTxt
 */

declare(strict_types=1);

namespace lloc\PloetnerLlmsTxt;

use WP;

/**
 * Serves /llms.txt at the root of each site. No rewrite rules to flush: the
 * request path is compared on `parse_request`, which also works for sites in
 * subdirectories (example.com/site/llms.txt) and with domain mapping.
 *
 * A physical llms.txt in the document root would be served for every site of
 * the network by the web server, before WordPress runs. Do not create one.
 */
class Server {

	/**
	 * Seconds browsers and proxies (e.g. Cloudflare) may cache the answer.
	 */
	public const MAX_AGE = 3600;

	/**
	 * Generator of the content.
	 *
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * Constructor.
	 *
	 * @param Generator|null $generator Content generator.
	 */
	public function __construct( ?Generator $generator = null ) {
		$this->generator = $generator ?? new Generator();
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
	 * Serve llms.txt and stop, if that is what was requested.
	 *
	 * @param WP $wp Current WordPress environment (unused).
	 *
	 * @return void
	 */
	public function maybe_serve( $wp ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Hook signature.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( ! $this->is_llms_txt( $uri ) ) {
			return;
		}

		$this->send( $this->generator->content() );
		exit;
	}

	/**
	 * Whether a request URI asks for this site's llms.txt.
	 *
	 * @param string $request_uri Request URI, e.g. "/llms.txt" or "/site/llms.txt?x=1".
	 *
	 * @return bool
	 */
	public function is_llms_txt( string $request_uri ): bool {
		$path     = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$expected = (string) wp_parse_url( home_url( '/llms.txt' ), PHP_URL_PATH );

		return '' !== $path && $expected === $path;
	}

	/**
	 * Send headers and body. An empty body answers 404.
	 *
	 * @param string $content Markdown content.
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
