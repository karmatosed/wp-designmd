<?php
/**
 * Assemble and write DESIGN.md to uploads.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes and reads the generated DESIGN.md file.
 */
class WP_Designmd_DesignMdWriter {

	/**
	 * Absolute path to the generated DESIGN.md file.
	 */
	public function get_file_path(): string {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'wp-designmd/DESIGN.md';
	}

	/**
	 * @param array<string, mixed> $tokens Token YAML structure.
	 * @param array<string, string> $prose  Markdown section bodies keyed by heading.
	 */
	public function write( array $tokens, array $prose ): bool|WP_Error {
		return $this->write_content( $this->assemble( $tokens, $prose ) );
	}

	/**
	 * Write pre-assembled DESIGN.md content.
	 */
	public function write_content( string $content ): bool|WP_Error {
		$path = $this->get_file_path();
		$dir  = dirname( $path );

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'wp_designmd_not_writable', __( 'Cannot create uploads directory.', 'wp-designmd' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $path, $content );

		if ( false === $written ) {
			return new WP_Error( 'wp_designmd_write_failed', __( 'Failed to write DESIGN.md.', 'wp-designmd' ) );
		}

		return true;
	}

	/**
	 * Read existing DESIGN.md contents, or empty string if missing.
	 */
	public function read(): string {
		$path = $this->get_file_path();

		return file_exists( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * @param array<string, mixed> $tokens Token YAML structure.
	 * @param array<string, string> $prose  Markdown section bodies keyed by heading.
	 */
	public function assemble( array $tokens, array $prose ): string {
		$yaml = $this->encode_yaml( $tokens );
		$body = $this->assemble_prose( $prose );

		return "---\n{$yaml}---\n\n{$body}";
	}

	/**
	 * @param array<string, mixed> $tokens Token YAML structure.
	 */
	private function encode_yaml( array $tokens ): string {
		if ( class_exists( 'Spyc' ) ) {
			return Spyc::YAMLDump( $tokens, 2, 0, true );
		}

		$lines = array();

		foreach ( $tokens as $key => $value ) {
			if ( is_array( $value ) ) {
				$lines[] = "{$key}:";
				foreach ( $value as $k => $v ) {
					if ( is_array( $v ) ) {
						$lines[] = "  {$k}:";
						foreach ( $v as $pk => $pv ) {
							$lines[] = is_string( $pv ) ? "    {$pk}: {$pv}" : "    {$pk}: " . wp_json_encode( $pv );
						}
					} else {
						$lines[] = is_string( $v ) ? "  {$k}: \"{$v}\"" : "  {$k}: {$v}";
					}
				}
			} else {
				$lines[] = "{$key}: {$value}";
			}
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @param array<string, string> $prose Markdown section bodies keyed by heading.
	 */
	private function assemble_prose( array $prose ): string {
		$order = array(
			'Overview',
			'Colors',
			'Typography',
			'Layout',
			'Elevation & Depth',
			'Shapes',
			'Components',
			"Do's and Don'ts",
		);

		$out = array();

		foreach ( $order as $section ) {
			if ( ! empty( $prose[ $section ] ) ) {
				$out[] = "## {$section}\n\n{$prose[ $section ]}";
			}
		}

		return implode( "\n\n", $out ) . "\n";
	}
}
