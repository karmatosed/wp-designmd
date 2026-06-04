<?php
/**
 * Block theme design data scanner.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects theme.json, templates, template parts, and pattern slugs.
 */
class WP_Designmd_SiteScanner {

	/**
	 * Scan active block theme design sources.
	 *
	 * @return array<string, mixed>
	 */
	public function scan(): array {
		return array(
			'theme'          => $this->get_theme_slug(),
			'theme_name'     => $this->get_theme_name(),
			'theme_json'     => $this->get_theme_json_data(),
			'templates'      => $this->get_templates(),
			'template_parts' => $this->get_template_parts(),
			'pattern_slugs'  => $this->get_pattern_slugs(),
		);
	}

	/**
	 * Active theme stylesheet slug.
	 */
	private function get_theme_slug(): string {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return '';
		}

		return wp_get_theme()->get_stylesheet();
	}

	/**
	 * Active theme display name.
	 */
	private function get_theme_name(): string {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return '';
		}

		$name = wp_get_theme()->get( 'Name' );

		return is_string( $name ) ? $name : '';
	}

	/**
	 * Merged theme.json data.
	 *
	 * @return array<string, mixed>
	 */
	private function get_theme_json_data(): array {
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			return array();
		}

		$theme_json = WP_Theme_JSON_Resolver::get_merged_data();
		$data       = $theme_json->get_data();

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Registered block templates.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_templates(): array {
		if ( ! function_exists( 'get_block_templates' ) ) {
			return array();
		}

		$templates = get_block_templates( array(), 'wp_template' );

		if ( ! is_array( $templates ) ) {
			return array();
		}

		return array_map(
			static function ( $template ): array {
				return array(
					'slug'  => isset( $template->slug ) ? (string) $template->slug : '',
					'title' => isset( $template->title ) ? (string) $template->title : '',
				);
			},
			$templates
		);
	}

	/**
	 * Registered template parts.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_template_parts(): array {
		if ( ! function_exists( 'get_block_templates' ) ) {
			return array();
		}

		$parts = get_block_templates(
			array( 'post_type' => 'wp_template_part' ),
			'wp_template_part'
		);

		if ( ! is_array( $parts ) ) {
			return array();
		}

		return array_map(
			static function ( $part ): array {
				return array(
					'slug'  => isset( $part->slug ) ? (string) $part->slug : '',
					'area'  => isset( $part->area ) ? (string) $part->area : '',
					'title' => isset( $part->title ) ? (string) $part->title : '',
				);
			},
			$parts
		);
	}

	/**
	 * All registered block pattern slugs.
	 *
	 * @return array<int, string>
	 */
	private function get_pattern_slugs(): array {
		if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
			return array();
		}

		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( ! method_exists( $registry, 'get_all_registered' ) ) {
			return array();
		}

		$patterns = $registry->get_all_registered();

		if ( ! is_array( $patterns ) ) {
			return array();
		}

		return array_keys( $patterns );
	}
}
