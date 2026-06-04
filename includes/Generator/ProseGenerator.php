<?php
/**
 * Templated and optional AI prose for DESIGN.md sections.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates markdown section bodies from tokens and scan data.
 */
class WP_Designmd_ProseGenerator {

	/**
	 * @param array<string, mixed> $tokens Mapped design tokens.
	 * @param array<string, mixed> $scan   Site scan payload.
	 * @return array<string, string> Section bodies keyed by heading.
	 */
	public function generate( array $tokens, array $scan, bool $use_ai = false ): array {
		$prose = $this->generate_templated( $tokens, $scan );

		if ( $use_ai && function_exists( 'wp_supports_ai' ) && wp_supports_ai() ) {
			$prose = $this->generate_with_ai( $tokens, $prose );
		}

		return $prose;
	}

	/**
	 * @param array<string, mixed> $tokens Mapped design tokens.
	 * @param array<string, mixed> $scan   Site scan payload.
	 * @return array<string, string>
	 */
	private function generate_templated( array $tokens, array $scan ): array {
		unset( $scan );

		$name  = $tokens['name'] ?? 'Site Design';
		$prose = array();

		$prose['Overview'] = "{$name} is a block theme design system extracted from the active WordPress theme.";
		$prose['Colors']   = $this->color_prose( $tokens['colors'] ?? array() );
		$prose['Typography'] = '- Typography levels mapped from theme.json font size presets.';
		$prose['Layout']   = '- Layout follows block theme content and wide size constraints.';
		$prose['Shapes']   = '- Corner radii derived from block button styles where available.';
		$prose['Components'] = '- Core button styles mapped to button-primary component tokens.';
		$prose["Do's and Don'ts"] = "- Do use theme presets for consistency.\n- Don't hardcode colors outside the palette.";

		return $prose;
	}

	/**
	 * @param array<string, string> $colors Color slug => hex.
	 */
	private function color_prose( array $colors ): string {
		$lines = array();

		foreach ( $colors as $name => $hex ) {
			$lines[] = '- **' . ucfirst( $name ) . " ({$hex}):** Theme preset color.";
		}

		return implode( "\n", $lines );
	}

	/**
	 * @param array<string, mixed>  $tokens Mapped design tokens.
	 * @param array<string, string> $prose  Existing section bodies.
	 * @return array<string, string>
	 */
	private function generate_with_ai( array $tokens, array $prose ): array {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return $prose;
		}

		$prompt_text = 'Rewrite these DESIGN.md prose sections to be cohesive and professional. '
			. 'Return a JSON object keyed by section name; each value is the markdown section body only (no ## headings). '
			. 'Tokens: '
			. wp_json_encode( $tokens )
			. ' Current prose: '
			. wp_json_encode( $prose );

		$result = wp_ai_client_prompt( $prompt_text )->generate_text();

		if ( is_wp_error( $result ) || ! is_string( $result ) || '' === trim( $result ) ) {
			return $prose;
		}

		return $this->parse_ai_prose_response( $result, $prose );
	}

	/**
	 * @param array<string, string> $fallback Section bodies to keep on parse failure.
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
