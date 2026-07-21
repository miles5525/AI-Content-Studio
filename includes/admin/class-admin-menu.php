<?php
/**
 * WordPress admin menu registration.
 *
 * @package AIContentStudio
 */

namespace AIContentStudio\Admin;

use AIContentStudio\Core\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin admin screens.
 */
final class Admin_Menu {
	private const MENU_SLUG = 'ai-content-studio';

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
	}

	/**
	 * Adds the top-level menu and submenu pages.
	 *
	 * @return void
	 */
	public function add_menu_pages(): void {
		add_menu_page(
			esc_html__( 'AI Content Studio', 'ai-content-studio' ),
			esc_html__( 'AI Content Studio', 'ai-content-studio' ),
			Permissions::manage(),
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-edit-page',
			25
		);

		$pages = array(
			array( self::MENU_SLUG, __( 'Dashboard', 'ai-content-studio' ), 'render_dashboard' ),
			array( 'aics-content-ideas', __( 'Content Ideas', 'ai-content-studio' ), 'render_content_ideas' ),
			array( 'aics-create-content', __( 'Create Content', 'ai-content-studio' ), 'render_create_content' ),
			array( 'aics-content-history', __( 'Content History', 'ai-content-studio' ), 'render_content_history' ),
			array( 'aics-brand-profile', __( 'Brand Profile', 'ai-content-studio' ), 'render_brand_profile' ),
			array( 'aics-settings', __( 'Settings', 'ai-content-studio' ), 'render_settings' ),
			array( 'aics-system-status', __( 'System Status', 'ai-content-studio' ), 'render_system_status' ),
		);

		foreach ( $pages as $page ) {
			add_submenu_page(
				self::MENU_SLUG,
				esc_html( $page[1] ),
				esc_html( $page[1] ),
				Permissions::manage(),
				$page[0],
				array( $this, $page[2] )
			);
		}
	}

	public function render_dashboard(): void { $this->render_page( __( 'Dashboard', 'ai-content-studio' ) ); }
	public function render_content_ideas(): void { $this->render_page( __( 'Content Ideas', 'ai-content-studio' ) ); }
	public function render_create_content(): void { $this->render_page( __( 'Create Content', 'ai-content-studio' ) ); }
	public function render_content_history(): void { $this->render_page( __( 'Content History', 'ai-content-studio' ) ); }
	public function render_brand_profile(): void { $this->render_page( __( 'Brand Profile', 'ai-content-studio' ) ); }
	public function render_settings(): void { $this->render_page( __( 'Settings', 'ai-content-studio' ) ); }
	public function render_system_status(): void { $this->render_page( __( 'System Status', 'ai-content-studio' ) ); }

	/**
	 * Renders a protected placeholder page.
	 *
	 * @param string $page_title Translated page title.
	 * @return void
	 */
	private function render_page( string $page_title ): void {
		if ( ! current_user_can( Permissions::manage() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-content-studio' ) );
		}

		$template = AICS_PLUGIN_DIR . 'templates/admin-placeholder.php';

		if ( is_readable( $template ) ) {
			include $template;
		}
	}
}
