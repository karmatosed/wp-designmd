<?php
/**
 * Front-end URL sampler for CSS variables and block classes.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches public URLs and extracts design tokens from rendered HTML.
 */
class WP_Designmd_FrontEndSampler {

	private const MAX_URLS = 8;

	/**
	 * Sample front-end pages for CSS variables and block usage.
	 *
	 * @param array<int, int> $feature_page_ids Optional page IDs to include.
	 * @return array{urls: array<int, string>, css_vars: array<string, string>, blocks: array<int, string>, skipped: bool}
	 */
	public function sample( array $feature_page_ids = array() ): array {
		$urls    = $this->discover_urls( $feature_page_ids );
		$vars    = array();
		$blocks  = array();
		$skipped = false;

		foreach ( $urls as $url ) {
			$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
			if ( is_wp_error( $response ) ) {
				$skipped = true;
				continue;
			}
			$body   = wp_remote_retrieve_body( $response );
			$vars   = array_merge( $vars, $this->extract_css_vars( $body ) );
			$blocks = array_merge( $blocks, $this->extract_blocks( $body ) );
		}

		return array(
			'urls'     => $urls,
			'css_vars' => $vars,
			'blocks'   => array_values( array_unique( $blocks ) ),
			'skipped'  => $skipped && empty( $vars ),
		);
	}

	/**
	 * Discover public URLs to sample.
	 *
	 * @param array<int, int> $feature_page_ids Optional page IDs to include.
	 * @return array<int, string>
	 */
	private function discover_urls( array $feature_page_ids ): array {
		$auto_urls = array( home_url( '/' ) );

		$post = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
		if ( ! empty( $post ) ) {
			$auto_urls[] = get_permalink( $post[0] );
		}

		$page = get_posts(
			array(
				'numberposts' => 1,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		if ( ! empty( $page ) ) {
			$auto_urls[] = get_permalink( $page[0] );
		}

		$cat = get_categories( array( 'number' => 1 ) );
		if ( ! empty( $cat ) ) {
			$link = get_category_link( $cat[0]->term_id );
			if ( ! is_wp_error( $link ) ) {
				$auto_urls[] = $link;
			}
		}

		$auto_urls = array_values( array_unique( array_filter( $auto_urls ) ) );
		$auto_urls = array_slice( $auto_urls, 0, self::MAX_URLS );

		$feature_urls = array();
		foreach ( $feature_page_ids as $page_id ) {
			$permalink = get_permalink( (int) $page_id );
			if ( $permalink ) {
				$feature_urls[] = $permalink;
			}
		}

		return array_values( array_unique( array_merge( $auto_urls, $feature_urls ) ) );
	}

	/**
	 * Extract WordPress preset CSS custom properties from HTML.
	 *
	 * @return array<string, string>
	 */
	private function extract_css_vars( string $html ): array {
		$vars = array();
		if ( preg_match_all( '/(--wp--preset--(?:color|font-size|spacing)--[a-z0-9-]+)\s*:\s*([^;}{]+)/i', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$vars[ trim( $match[1] ) ] = trim( $match[2] );
			}
		}
		return $vars;
	}

	/**
	 * Extract wp-block-* class names from HTML.
	 *
	 * @return array<int, string>
	 */
	private function extract_blocks( string $html ): array {
		$blocks = array();
		if ( preg_match_all( '/wp-block-([a-z0-9-]+)/i', $html, $matches ) ) {
			$blocks = $matches[1];
		}
		return $blocks;
	}
}
