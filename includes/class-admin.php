<?php
/**
 * Admin shell registration for DesignMD.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Appearance > DesignMD page shell.
 */
class WP_Designmd_Admin {

	private const PAGE_SLUG = 'wp-designmd';

	/**
	 * Attach admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Appearance page.
	 */
	public function register_page(): void {
		add_theme_page(
			__( 'DesignMD', 'wp-designmd' ),
			__( 'DesignMD', 'wp-designmd' ),
			'edit_theme_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Whether the current request is the DesignMD admin screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	private function is_designmd_screen( string $hook_suffix ): bool {
		if ( 'appearance_page_' . self::PAGE_SLUG === $hook_suffix ) {
			return true;
		}

		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return self::PAGE_SLUG === $page;
	}

	/**
	 * Enqueue the React admin bundle on the DesignMD page only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! $this->is_designmd_screen( $hook_suffix ) ) {
			return;
		}

		$script_file = WP_DESIGNMD_PATH . 'build/index.js';
		$style_file  = WP_DESIGNMD_PATH . 'build/index.css';
		$asset_file  = WP_DESIGNMD_PATH . 'build/index.asset.php';

		if ( ! file_exists( $script_file ) || ! file_exists( $asset_file ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'DesignMD: Run npm install && npm run build in the plugin directory.', 'wp-designmd' );
					echo '</p></div>';
				}
			);
			return;
		}

		$asset = include $asset_file;

		if ( ! is_array( $asset ) ) {
			return;
		}

		$dependencies = $asset['dependencies'] ?? array();
		$version      = $asset['version'] ?? WP_DESIGNMD_VERSION;

		$this->register_design_token_styles( $version );

		$script_dependencies = array_values(
			array_filter(
				$dependencies,
				static function ( $handle ) {
					return is_string( $handle ) && ! str_contains( $handle, '.css' );
				}
			)
		);

		wp_enqueue_script(
			'wp-designmd-admin',
			WP_DESIGNMD_URL . 'build/index.js',
			$script_dependencies,
			$version,
			true
		);

		wp_set_script_translations( 'wp-designmd-admin', 'wp-designmd' );

		if ( file_exists( $style_file ) ) {
			wp_enqueue_style(
				'wp-designmd-admin',
				WP_DESIGNMD_URL . 'build/index.css',
				array( 'wp-components', 'wp-designmd-design-tokens' ),
				$version
			);
		}
	}

	/**
	 * Register WPDS design token stylesheet (externalized by @wordpress/scripts).
	 */
	private function register_design_token_styles( string $version ): void {
		$tokens_file = WP_DESIGNMD_PATH . 'build/design-tokens.css';

		if ( ! file_exists( $tokens_file ) ) {
			return;
		}

		wp_register_style(
			'wp-designmd-design-tokens',
			WP_DESIGNMD_URL . 'build/design-tokens.css',
			array(),
			$version
		);

		wp_enqueue_style( 'wp-designmd-design-tokens' );
	}

	/**
	 * Render React app mount node.
	 */
	public function render_page(): void {
		echo '<div class="wrap"><div id="wp-designmd-admin"></div></div>';
	}
}
