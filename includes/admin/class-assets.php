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
		$allowed_hooks = array(
			'toplevel_page_ai-content-studio',
			'ai-content-studio_page_aics-create-content',
			'ai-content-studio_page_aics-automations',
			'ai-content-studio_page_aics-content-history',
			'ai-content-studio_page_aics-settings',
			'ai-content-studio_page_aics-system-status',
			'admin_page_aics-content-ideas',
			'admin_page_aics-brand-profile',
		);

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
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

		if ( 'ai-content-studio_page_aics-system-status' === $hook_suffix ) {
			wp_enqueue_script( 'aics-system-status', AICS_PLUGIN_URL . 'assets/js/system-status.js', array(), AICS_VERSION, true );
		}
	}
}
