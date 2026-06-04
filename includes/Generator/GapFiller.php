<?php
/**
 * AI gap-fill for sparse DESIGN.md prose sections.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects sparse sections and fills them with AI without overwriting tokens.
 */
class WP_Designmd_GapFiller {

	/**
	 * @param WP_Designmd_DesignMdParser $parser DESIGN.md parser.
	 * @param WP_Designmd_DesignMdWriter $writer DESIGN.md writer.
	 */
	public function __construct(
		private WP_Designmd_DesignMdParser $parser,
		private WP_Designmd_DesignMdWriter $writer
	) {}

	/**
	 * Fill sparse prose sections via AI and rewrite DESIGN.md.
	 */
	public function fill(): bool|WP_Error {
		if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
			return new WP_Error( 'wp_designmd_no_ai', __( 'AI is not available.', 'wp-designmd' ) );
		}

		$raw    = $this->writer->read();
		$parsed = $this->parser->parse( $raw );

		if ( ! $parsed ) {
			return new WP_Error( 'wp_designmd_no_file', __( 'No DESIGN.md to fill.', 'wp-designmd' ) );
		}

		$sparse = $this->detect_sparse( $parsed );

		if ( empty( $sparse ) ) {
			return true;
		}

		$filled = $this->fill_sparse_sections_with_ai( $parsed['tokens'], $parsed['prose'], $sparse );

		if ( is_wp_error( $filled ) ) {
			return $filled;
		}

		$parsed['prose'] = array_merge( $parsed['prose'], $filled );

		return $this->writer->write( $parsed['tokens'], $parsed['prose'] );
	}

	/**
	 * @param array{tokens: array<string, mixed>, prose: array<string, string>} $parsed Parsed DESIGN.md.
	 * @return array<int, string> Sparse section headings.
	 */
	public function detect_sparse( array $parsed ): array {
		$sparse   = array();
		$overview = $parsed['prose']['Overview'] ?? '';

		if ( str_word_count( $overview ) < 40 ) {
			$sparse[] = 'Overview';
		}

		foreach ( $parsed['prose'] as $section => $content ) {
			if ( substr_count( $content, '- ' ) < 2 && 'Overview' !== $section ) {
				$sparse[] = $section;
			}
		}

		if ( count( $parsed['tokens']['components'] ?? array() ) < 3 ) {
			$sparse[] = 'Components';
		}

		return array_values( array_unique( $sparse ) );
	}

	/**
	 * @param array{tokens: array<string, mixed>, prose: array<string, string>} $parsed Parsed DESIGN.md.
	 */
	public function is_sparse( array $parsed ): bool {
		return ! empty( $this->detect_sparse( $parsed ) );
	}

	/**
	 * @param array<string, mixed>  $tokens Mapped design tokens.
	 * @param array<string, string> $prose  Existing section bodies.
	 * @param array<int, string>    $sparse Section headings to fill.
	 * @return array<string, string>|WP_Error Filled section bodies keyed by heading.
	 */
	private function fill_sparse_sections_with_ai( array $tokens, array $prose, array $sparse ): array|WP_Error {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'wp_designmd_no_ai', __( 'AI is not available.', 'wp-designmd' ) );
		}

		$subset = array();

		foreach ( $sparse as $section ) {
			if ( isset( $prose[ $section ] ) ) {
				$subset[ $section ] = $prose[ $section ];
			}
		}

		$prompt_text = 'Expand these sparse DESIGN.md prose sections with clear, professional markdown. '
			. 'Use bullet lists where appropriate. Return a JSON object keyed by section name; '
			. 'each value is the markdown section body only (no ## headings). '
			. 'Sections to fill: '
			. wp_json_encode( array_values( $sparse ) )
			. ' Tokens: '
			. wp_json_encode( $tokens )
			. ' Current content: '
			. wp_json_encode( $subset );

		$result = wp_ai_client_prompt( $prompt_text )->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! is_string( $result ) || '' === trim( $result ) ) {
			return new WP_Error( 'wp_designmd_ai_empty', __( 'AI returned no content.', 'wp-designmd' ) );
		}

		$filled = $this->parse_ai_prose_response( $result, array() );

		if ( empty( $filled ) ) {
			return new WP_Error( 'wp_designmd_ai_parse_failed', __( 'Could not parse AI response.', 'wp-designmd' ) );
		}

		$out = array();

		foreach ( $sparse as $section ) {
			if ( ! empty( $filled[ $section ] ) ) {
				$out[ $section ] = $filled[ $section ];
			}
		}

		if ( empty( $out ) ) {
			return new WP_Error( 'wp_designmd_ai_parse_failed', __( 'Could not parse AI response.', 'wp-designmd' ) );
		}

		return $out;
	}

	/**
	 * @param array<string, string> $fallback Section bodies to merge on partial parse.
	 * @return array<string, string>
	 */
	private function parse_ai_prose_response( string $result, array $fallback ): array {
		$result = trim( $result );

		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $result, $fence ) ) {
			$result = trim( $fence[1] );
		}

		$decoded = json_decode( $result, true );

		if ( is_array( $decoded ) ) {
			return $this->merge_ai_sections( $decoded, $fallback );
		}

		if ( preg_match_all( '/^##\s+(.+?)\s*\n(.*?)(?=^##\s+|\z)/ms', $result, $matches, PREG_SET_ORDER ) ) {
			$from_headings = array();

			foreach ( $matches as $match ) {
				$from_headings[ trim( $match[1] ) ] = trim( $match[2] );
			}

			if ( ! empty( $from_headings ) ) {
				return $this->merge_ai_sections( $from_headings, $fallback );
			}
		}

		return $fallback;
	}

	/**
	 * @param array<string, mixed>  $sections AI-parsed section map.
	 * @param array<string, string> $fallback Existing section bodies.
	 * @return array<string, string>
	 */
	private function merge_ai_sections( array $sections, array $fallback ): array {
		$merged = $fallback;

		foreach ( $sections as $section => $content ) {
			if ( ! is_string( $section ) || ! is_string( $content ) ) {
				continue;
			}

			$body = trim( $content );

			if ( '' !== $body ) {
				$merged[ $section ] = $body;
			}
		}

		return $merged;
	}
}
