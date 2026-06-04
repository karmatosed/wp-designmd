<?php
/**
 * Main plugin bootstrap.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin loader.
 */
class WP_Designmd_Plugin {

	private ?WP_Designmd_DesignMdController $rest_controller = null;
	private ?WP_Designmd_Admin $admin = null;

	/**
	 * Register hooks and activation handler.
	 */
	public function init(): void {
		register_activation_hook( WP_DESIGNMD_FILE, array( $this, 'activate' ) );
		add_action( 'plugins_loaded', array( $this, 'load' ) );
	}

	/**
	 * Load plugin dependencies.
	 */
	public function load(): void {
		require_once WP_DESIGNMD_PATH . 'includes/Support/BlockThemeChecker.php';
		require_once WP_DESIGNMD_PATH . 'includes/Support/DesignMdParser.php';
		require_once WP_DESIGNMD_PATH . 'includes/Scanner/SiteScanner.php';
		require_once WP_DESIGNMD_PATH . 'includes/Scanner/FrontEndSampler.php';
		require_once WP_DESIGNMD_PATH . 'includes/Generator/TokenMapper.php';
		require_once WP_DESIGNMD_PATH . 'includes/Generator/ProseGenerator.php';
		require_once WP_DESIGNMD_PATH . 'includes/Generator/GapFiller.php';
		require_once WP_DESIGNMD_PATH . 'includes/Generator/DesignMdWriter.php';
		require_once WP_DESIGNMD_PATH . 'includes/Linter/DesignMdLinter.php';
		require_once WP_DESIGNMD_PATH . 'includes/Rest/DesignMdController.php';
		require_once WP_DESIGNMD_PATH . 'includes/class-admin.php';

		$this->rest_controller = new WP_Designmd_DesignMdController();
		$this->rest_controller->register();

		if ( is_admin() ) {
			$this->admin = new WP_Designmd_Admin();
			$this->admin->register();
		}
	}

	/**
	 * Run on plugin activation.
	 */
	public function activate(): void {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return;
		}

		wp_mkdir_p( $upload_dir['basedir'] . '/wp-designmd' );
	}
}
