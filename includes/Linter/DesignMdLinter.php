<?php
/**
 * Validate DESIGN.md structure, tokens, and section order.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lints DESIGN.md content against spec-aligned rules.
 */
class WP_Designmd_DesignMdLinter {

	/**
	 * @param array<string, string> $rendered_css_vars CSS vars from front-end sample.
	 * @return array{findings: array<int, array<string, string>>, summary: array{errors: int, warnings: int, info: int}}
	 */
	public function lint( string $markdown, array $rendered_css_vars = array() ): array {
		$parser   = new WP_Designmd_DesignMdParser();
		$parsed   = $parser->parse( $markdown );
		$findings = array();

		if ( ! $parsed ) {
			$findings[] = array(
				'severity' => 'error',
				'path'     => 'file',
				'message'  => 'Invalid DESIGN.md structure.',
			);
			return $this->summarize( $findings );
		}

		$tokens = $parsed['tokens'];
		if ( ! empty( $tokens['colors'] ) && empty( $tokens['colors']['primary'] ) ) {
			$findings[] = array(
				'severity' => 'warning',
				'path'     => 'colors.primary',
				'message'  => 'Missing primary color.',
			);
		}
		if ( ! empty( $tokens['colors'] ) && empty( $tokens['typography'] ) ) {
			$findings[] = array(
				'severity' => 'warning',
				'path'     => 'typography',
				'message'  => 'Colors defined but no typography tokens.',
			);
		}
		$findings = array_merge( $findings, $this->check_broken_refs( $tokens ) );
		$findings = array_merge( $findings, $this->check_section_order( $parsed['prose'] ) );
		$findings = array_merge( $findings, $this->check_orphaned_colors( $tokens, $rendered_css_vars ) );

		return $this->summarize( $findings );
	}

	/**
	 * Warn when color tokens were not seen in rendered CSS variables.
	 *
	 * @param array<string, mixed> $tokens
	 * @param array<string, string> $rendered_css_vars
	 * @return array<int, array<string, string>>
	 */
	private function check_orphaned_colors( array $tokens, array $rendered_css_vars ): array {
		if ( empty( $tokens['colors'] ) || empty( $rendered_css_vars ) ) {
			return array();
		}

		$rendered_slugs = array();
		foreach ( array_keys( $rendered_css_vars ) as $var_name ) {
			if ( preg_match( '/--wp--preset--color--([a-z0-9-]+)/i', $var_name, $match ) ) {
				$rendered_slugs[] = $match[1];
			}
		}

		if ( empty( $rendered_slugs ) ) {
			return array();
		}

		$findings = array();
		foreach ( array_keys( $tokens['colors'] ) as $token_name ) {
			$slug = match ( $token_name ) {
				'primary'  => 'contrast',
				'neutral'  => 'base',
				'tertiary' => 'accent-1',
				default    => $token_name,
			};
			if ( ! in_array( $slug, $rendered_slugs, true ) && ! in_array( $token_name, $rendered_slugs, true ) ) {
				$findings[] = array(
					'severity' => 'warning',
					'path'     => 'colors.' . $token_name,
					'message'  => 'Color token not observed on sampled front-end pages.',
				);
			}
		}

		return $findings;
	}

	/**
	 * @param array<string, mixed> $tokens
	 * @return array<int, array<string, string>>
	 */
	private function check_broken_refs( array $tokens ): array {
		$findings = array();
		$flat     = wp_json_encode( $tokens );
		if ( preg_match_all( '/\{([a-z0-9_.]+)\}/i', $flat, $matches ) ) {
			foreach ( $matches[1] as $ref ) {
				if ( ! $this->ref_exists( $tokens, $ref ) ) {
					$findings[] = array(
						'severity' => 'error',
						'path'     => $ref,
						'message'  => "Broken token reference: {{$ref}}",
					);
				}
			}
		}
		return $findings;
	}

	/**
	 * @param array<string, mixed> $tokens
	 */
	private function ref_exists( array $tokens, string $ref ): bool {
		$parts = explode( '.', $ref );
		$cur   = $tokens;
		foreach ( $parts as $part ) {
			if ( ! is_array( $cur ) || ! array_key_exists( $part, $cur ) ) {
				return false;
			}
			$cur = $cur[ $part ];
		}
		return true;
	}

	/**
	 * @param array<string, string> $prose
	 * @return array<int, array<string, string>>
	 */
	private function check_section_order( array $prose ): array {
		$order    = array( 'Overview', 'Colors', 'Typography', 'Layout', 'Elevation & Depth', 'Shapes', 'Components', "Do's and Don'ts" );
		$present  = array_keys( $prose );
		$findings = array();
		$last     = -1;
		foreach ( $present as $section ) {
			$idx = array_search( $section, $order, true );
			if ( false !== $idx && $idx < $last ) {
				$findings[] = array(
					'severity' => 'warning',
					'path'     => $section,
					'message'  => 'Section out of canonical order.',
				);
			}
			if ( false !== $idx ) {
				$last = $idx;
			}
		}
		return $findings;
	}

	/**
	 * @param array<int, array<string, string>> $findings
	 * @return array{findings: array<int, array<string, string>>, summary: array{errors: int, warnings: int, info: int}}
	 */
	private function summarize( array $findings ): array {
		$summary = array(
			'errors'   => 0,
			'warnings' => 0,
			'info'     => 0,
		);
		foreach ( $findings as $f ) {
			++$summary[ 'error' === $f['severity'] ? 'errors' : ( 'warning' === $f['severity'] ? 'warnings' : 'info' ) ];
		}
		return array(
			'findings' => $findings,
			'summary'  => $summary,
		);
	}
}
