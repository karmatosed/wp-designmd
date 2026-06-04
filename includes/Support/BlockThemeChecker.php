<?php
/**
 * Block theme detection helper.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether the active theme is a block theme.
 */
class WP_Designmd_BlockThemeChecker {

	/**
	 * Whether the site uses a block theme.
	 */
	public function is_block_theme(): bool {
		return wp_is_block_theme();
	}
}
