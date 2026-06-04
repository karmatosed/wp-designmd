<?php
/**
 * Maps WordPress theme.json presets to DESIGN.md token structure.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

/**
 * Deterministic WP presets → DESIGN.md YAML structure.
 */
class WP_Designmd_TokenMapper {

	/**
	 * @param array<string, mixed> $scan
	 * @param array<string, mixed> $sample Front-end sample from FrontEndSampler.
	 * @return array<string, mixed>
	 */
	public function map( array $scan, array $sample = array() ): array {
		$data   = $scan['theme_json'] ?? array();
		$tokens = array(
			'version' => 'alpha',
			'name'    => $scan['theme_name'] ?? 'Site Design',
		);

		$tokens['colors']     = $this->map_colors( $data );
		$tokens['typography'] = $this->map_typography( $data );
		$tokens['spacing']    = $this->map_spacing( $data );
		$tokens['rounded']    = $this->map_rounded( $data );
		$tokens['components'] = $this->map_components( $data, $sample, $tokens );

		return $tokens;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function map_colors( array $data ): array {
		$palette = $data['settings']['color']['palette'] ?? array();
		$mapped  = array();
		foreach ( $palette as $entry ) {
			$slug  = $entry['slug'] ?? '';
			$color = $entry['color'] ?? '';
			if ( ! $slug || ! $this->is_hex( $color ) ) {
				continue;
			}
			$key = match ( $slug ) {
				'contrast' => 'primary',
				'base'     => 'neutral',
				'accent-1' => 'tertiary',
				default    => $slug,
			};
			$mapped[ $key ] = strtoupper( $color );
		}
		return $mapped;
	}

	private function is_hex( string $color ): bool {
		return (bool) preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, array<string, mixed>>
	 */
	private function map_typography( array $data ): array {
		$out      = array();
		$families = $data['settings']['typography']['fontFamilies'] ?? array();
		$sizes    = $data['settings']['typography']['fontSizes'] ?? array();
		$default  = $families[0]['fontFamily'] ?? 'inherit';
		foreach ( $sizes as $size ) {
			$slug = $size['slug'] ?? '';
			if ( ! $slug ) {
				continue;
			}
			$name = match ( $slug ) {
				'large'  => 'headline-md',
				'medium' => 'body-md',
				'small'  => 'body-sm',
				default  => $slug,
			};
			$out[ $name ] = array(
				'fontFamily' => $default,
				'fontSize'   => $size['size'] ?? '1rem',
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function map_spacing( array $data ): array {
		$out   = array();
		$sizes = $data['settings']['spacing']['spacingSizes'] ?? array();
		foreach ( $sizes as $size ) {
			$slug = $size['slug'] ?? '';
			if ( $slug ) {
				$out[ $slug ] = $size['size'] ?? '0';
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function map_rounded( array $data ): array {
		$radius = $data['styles']['blocks']['core/button']['border']['radius'] ?? null;
		$normalized = $this->normalize_radius( $radius );

		if ( null !== $normalized ) {
			return array( 'sm' => $normalized );
		}

		return array();
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $sample
	 * @param array<string, mixed> $tokens Mapped token groups used for component refs.
	 * @return array<string, array<string, mixed>>
	 */
	private function map_components( array $data, array $sample, array $tokens ): array {
		$components = array();
		$colors     = is_array( $tokens['colors'] ?? null ) ? $tokens['colors'] : array();
		$typography = is_array( $tokens['typography'] ?? null ) ? $tokens['typography'] : array();
		$rounded    = is_array( $tokens['rounded'] ?? null ) ? $tokens['rounded'] : array();
		$button     = $data['styles']['blocks']['core/button'] ?? array();

		if ( ! empty( $button ) ) {
			$button_tokens = $this->compact_component_tokens(
				array(
					'backgroundColor' => $this->pick_color_ref( $colors, array( 'tertiary', 'secondary', 'primary' ) ),
					'textColor'       => $this->pick_color_ref( $colors, array( 'neutral', 'primary', 'secondary' ) ),
					'rounded'         => $this->rounded_ref( $rounded ),
				)
			);

			if ( ! empty( $button_tokens ) ) {
				$components['button-primary'] = $button_tokens;
			}
		}

		$blocks = $sample['blocks'] ?? array();
		if ( in_array( 'search', $blocks, true ) ) {
			$input_tokens = $this->compact_component_tokens(
				array(
					'backgroundColor' => $this->pick_color_ref( $colors, array( 'neutral', 'primary', 'secondary' ) ),
					'textColor'       => $this->pick_color_ref( $colors, array( 'primary', 'secondary', 'neutral' ) ),
					'rounded'         => $this->rounded_ref( $rounded ),
				)
			);

			if ( ! empty( $input_tokens ) ) {
				$components['input-field'] = $input_tokens;
			}
		}

		if ( in_array( 'navigation', $blocks, true ) ) {
			$nav_tokens = $this->compact_component_tokens(
				array(
					'textColor'  => $this->pick_color_ref( $colors, array( 'primary', 'secondary', 'neutral' ) ),
					'typography' => $this->typography_ref( $typography, array( 'body-md', 'body-sm', 'headline-md' ) ),
				)
			);

			if ( ! empty( $nav_tokens ) ) {
				$components['nav-link'] = $nav_tokens;
			}
		}

		return $components;
	}

	/**
	 * @param array<string, string|null> $tokens
	 * @return array<string, string>
	 */
	private function compact_component_tokens( array $tokens ): array {
		$compact = array();

		foreach ( $tokens as $key => $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$compact[ $key ] = $value;
			}
		}

		return $compact;
	}

	/**
	 * @param array<string, string> $colors
	 * @param array<int, string>    $keys
	 */
	private function pick_color_ref( array $colors, array $keys ): ?string {
		foreach ( $keys as $key ) {
			$ref = $this->color_ref( $colors, $key );
			if ( null !== $ref ) {
				return $ref;
			}
		}

		$first = array_key_first( $colors );

		return is_string( $first ) ? $this->color_ref( $colors, $first ) : null;
	}

	/**
	 * @param array<string, string> $colors
	 */
	private function color_ref( array $colors, string $key ): ?string {
		return isset( $colors[ $key ] ) ? '{colors.' . $key . '}' : null;
	}

	/**
	 * @param array<string, array<string, mixed>> $typography
	 * @param array<int, string>                    $keys
	 */
	private function typography_ref( array $typography, array $keys ): ?string {
		foreach ( $keys as $key ) {
			if ( isset( $typography[ $key ] ) ) {
				return '{typography.' . $key . '}';
			}
		}

		$first = array_key_first( $typography );

		return is_string( $first ) ? '{typography.' . $first . '}' : null;
	}

	/**
	 * @param array<string, string> $rounded
	 */
	private function rounded_ref( array $rounded, string $key = 'sm' ): ?string {
		if ( ! isset( $rounded[ $key ] ) || ! is_string( $rounded[ $key ] ) || '' === trim( $rounded[ $key ] ) ) {
			return null;
		}

		return '{rounded.' . $key . '}';
	}

	/**
	 * Normalize theme.json border radius values to a CSS string.
	 */
	private function normalize_radius( mixed $radius ): ?string {
		if ( is_string( $radius ) ) {
			$trimmed = trim( $radius );

			return '' !== $trimmed ? $trimmed : null;
		}

		if ( ! is_array( $radius ) ) {
			return null;
		}

		$values = array();

		foreach ( $radius as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$values[] = trim( $value );
			}
		}

		if ( empty( $values ) ) {
			return null;
		}

		$unique = array_unique( $values );

		if ( 1 === count( $unique ) ) {
			return reset( $unique );
		}

		return implode( ' ', $values );
	}
}
