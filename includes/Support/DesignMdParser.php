<?php
/**
 * Parse DESIGN.md YAML front matter and markdown sections.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses DESIGN.md structure into tokens and prose sections.
 */
class WP_Designmd_DesignMdParser {

	/**
	 * @return array{tokens: array<string, mixed>, prose: array<string, string>, raw: string}|null
	 */
	public function parse( string $markdown ): ?array {
		if ( ! preg_match( '/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $markdown, $matches ) ) {
			return null;
		}

		$tokens = $this->parse_simple_yaml( $matches[1] );
		$prose  = $this->parse_sections( $matches[2] );

		return array(
			'tokens' => $tokens,
			'prose'  => $prose,
			'raw'    => $markdown,
		);
	}

	/**
	 * Minimal YAML parser for token front matter — uses yaml_parse if available,
	 * falls back to Spyc when present.
	 *
	 * @return array<string, mixed>
	 */
	private function parse_simple_yaml( string $yaml ): array {
		if ( function_exists( 'yaml_parse' ) ) {
			$parsed = yaml_parse( $yaml );
			return is_array( $parsed ) ? $parsed : array();
		}

		return $this->parse_yaml_lines( $yaml );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parse_yaml_lines( string $yaml ): array {
		if ( class_exists( 'Spyc' ) ) {
			$parsed = Spyc::YAMLLoadString( $yaml );
			return is_array( $parsed ) ? $parsed : array();
		}

		return array();
	}

	/**
	 * @return array<string, string>
	 */
	private function parse_sections( string $body ): array {
		$sections = array();

		if ( preg_match_all( '/^##\s+(.+?)\s*\n(.*?)(?=^##\s+|\z)/ms', $body, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$sections[ trim( $match[1] ) ] = trim( $match[2] );
			}
		}

		return $sections;
	}
}
