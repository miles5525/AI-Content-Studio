<?php
/**
 * Admin asset loader.
 *
 * @package AIContentStudio
 */

namespace AIContentStudio\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads assets only on plugin admin screens.
 */
final class Assets {
	/**
	 * Registers asset hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues plugin styles and scripts.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'toplevel_page_ai-content-studio' !== $hook_suffix && ! str_contains( $hook_suffix, '_page_aics-' ) ) {
			return;
		}

		wp_enqueue_style(
			'aics-admin',
			AICS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AICS_VERSION
		);

		wp_enqueue_script(
			'aics-admin',
			AICS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			AICS_VERSION,
			true
		);
	}
}
