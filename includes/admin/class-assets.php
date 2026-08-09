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
			'ai-content-studio_page_aics-automation-runs',
			'ai-content-studio_page_aics-approvals',
			'ai-content-studio_page_aics-content-history',
			'ai-content-studio_page_aics-settings',
			'ai-content-studio_page_aics-system-status',
			'admin_page_aics-content-ideas',
			'admin_page_aics-brand-profile',
			'admin_page_aics-setup',
		);

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		$admin_css_version = $this->asset_version( 'assets/css/admin.css' );
		$admin_js_version  = $this->asset_version( 'assets/js/admin.js' );

		wp_enqueue_style(
			'aics-admin',
			AICS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$admin_css_version
		);

		wp_enqueue_script(
			'aics-admin',
			AICS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			$admin_js_version,
			true
		);

		wp_localize_script(
			'aics-admin',
			'aicsRunStatus',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'action'       => 'aics_admin_run_status',
				'nonce'        => wp_create_nonce( 'aics_admin_run_status' ),
				'pollInterval' => 10000,
				'showBanner'   => 'ai-content-studio_page_aics-create-content' !== $hook_suffix,
			)
		);

		if ( 'ai-content-studio_page_aics-create-content' === $hook_suffix ) {
			wp_enqueue_script(
				'aics-manual-studio-wizard',
				AICS_PLUGIN_URL . 'assets/js/manual-studio-wizard.js',
				array(),
				$this->asset_version( 'assets/js/manual-studio-wizard.js' ),
				true
			);
		}
		if ( 'ai-content-studio_page_aics-automations' === $hook_suffix ) {
			wp_enqueue_script( 'aics-automation-wizard', AICS_PLUGIN_URL . 'assets/js/automation-wizard.js', array(), $this->asset_version( 'assets/js/automation-wizard.js' ), true );
		}
		$settings_section = isset( $_GET['section'] ) && is_scalar( $_GET['section'] ) ? sanitize_key( wp_unslash( (string) $_GET['section'] ) ) : 'general';
		if ( 'admin_page_aics-setup' === $hook_suffix || ( 'ai-content-studio_page_aics-settings' === $hook_suffix && 'setup' === $settings_section ) ) {
			wp_enqueue_script( 'aics-setup-wizard', AICS_PLUGIN_URL . 'assets/js/setup-wizard.js', array(), $this->asset_version( 'assets/js/setup-wizard.js' ), true );
		}
		if ( 'ai-content-studio_page_aics-content-history' === $hook_suffix || ( 'ai-content-studio_page_aics-settings' === $hook_suffix && 'content-history' === $settings_section ) ) {
			wp_enqueue_script( 'aics-content-history', AICS_PLUGIN_URL . 'assets/js/content-history.js', array(), $this->asset_version( 'assets/js/content-history.js' ), true );
		}

		if ( 'ai-content-studio_page_aics-system-status' === $hook_suffix || ( 'ai-content-studio_page_aics-settings' === $hook_suffix && 'system-status' === $settings_section ) ) {
			wp_enqueue_script( 'aics-system-status', AICS_PLUGIN_URL . 'assets/js/system-status.js', array(), $this->asset_version( 'assets/js/system-status.js' ), true );
		}
	}

	/**
	 * Returns a cache-safe version for a local plugin asset.
	 *
	 * @param string $relative_path Plugin-relative asset path.
	 * @return string
	 */
	private function asset_version( string $relative_path ): string {
		$modified = filemtime( AICS_PLUGIN_DIR . $relative_path );
		return AICS_VERSION . '.' . ( false === $modified ? '0' : (string) $modified );
	}
}
