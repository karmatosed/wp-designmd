<?php
/**
 * Uninstall wp-designmd.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wp_designmd_settings' );
delete_option( 'wp_designmd_meta' );

$upload_dir = wp_upload_dir();

if ( empty( $upload_dir['error'] ) ) {
	$wp_designmd_dir = $upload_dir['basedir'] . '/wp-designmd';

	if ( is_dir( $wp_designmd_dir ) ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem ) {
			$wp_filesystem->rmdir( $wp_designmd_dir, true );
		}
	}
}
