<?php
/**
 * Plugin Name:       wp-designmd
 * Description:       Generate DESIGN.md from your block theme's visual identity.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.0
 * Author:            wp-designmd
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_DESIGNMD_VERSION', '0.1.0' );
define( 'WP_DESIGNMD_FILE', __FILE__ );
define( 'WP_DESIGNMD_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_DESIGNMD_URL', plugin_dir_url( __FILE__ ) );

$autoload = WP_DESIGNMD_PATH . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

require_once WP_DESIGNMD_PATH . 'includes/class-plugin.php';

function wp_designmd(): WP_Designmd_Plugin {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new WP_Designmd_Plugin();
	}
	return $instance;
}

wp_designmd()->init();
