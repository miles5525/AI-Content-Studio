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
			array( 'aics-create-content', __( 'Create Content', 'ai-content-studio' ), 'render_create_content' ),
			array( 'aics-automations', __( 'Automations', 'ai-content-studio' ), 'render_automations' ),
			array( 'aics-approvals', __( 'Approvals', 'ai-content-studio' ), 'render_approvals' ),
			array( 'aics-settings', __( 'Settings', 'ai-content-studio' ), 'render_settings' ),
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

		// Preserve established placeholder URLs without showing unfinished beta navigation.
		add_submenu_page( null, __( 'Content Ideas', 'ai-content-studio' ), __( 'Content Ideas', 'ai-content-studio' ), Permissions::manage(), 'aics-content-ideas', array( $this, 'render_content_ideas' ) );
		add_submenu_page( null, __( 'Brand Profile', 'ai-content-studio' ), __( 'Brand Profile', 'ai-content-studio' ), Permissions::manage(), 'aics-brand-profile', array( $this, 'render_brand_profile' ) );
		add_submenu_page( null, __( 'Automation Runs', 'ai-content-studio' ), __( 'Automation Runs', 'ai-content-studio' ), Permissions::manage(), 'aics-automation-runs', array( $this, 'redirect_automation_runs' ) );
		add_submenu_page( null, __( 'Content History', 'ai-content-studio' ), __( 'Content History', 'ai-content-studio' ), Permissions::manage(), 'aics-content-history', array( $this, 'redirect_content_history' ) );
		add_submenu_page( null, __( 'System Status', 'ai-content-studio' ), __( 'System Status', 'ai-content-studio' ), Permissions::manage(), 'aics-system-status', array( $this, 'redirect_system_status' ) );
		add_submenu_page( null, __( 'AI Content Studio Setup', 'ai-content-studio' ), __( 'AI Content Studio Setup', 'ai-content-studio' ), Permissions::manage(), 'aics-setup', array( $this, 'render_setup' ) );
	}

	public function render_dashboard(): void { \AICS_Dashboard_Page::render(); }
	public function render_content_ideas(): void { $this->render_page( __( 'Content Ideas', 'ai-content-studio' ) ); }
	public function render_create_content(): void { \AICS_Content_Studio_Page::render(); }
	public function render_automations(): void { \AICS_Automations_Page::render(); }
	public function render_automation_runs(): void { \AICS_Automation_Runs_Page::render(); }
	public function render_approvals(): void { \AICS_Approvals_Page::render(); }
	public function render_content_history(): void { \AICS_Content_History_Page::render(); }
	public function render_brand_profile(): void { $this->render_page( __( 'Brand Profile', 'ai-content-studio' ) ); }
	public function render_settings(): void { \AICS_Settings_Page::render(); }
	public function render_system_status(): void { \AICS_System_Status_Page::render(); }
	public function render_setup(): void { \AICS_Setup_Wizard_Page::render(); }
	public function redirect_automation_runs():void{$this->redirect_legacy('automation-runs',array('view','run_id','run_view','paged','status','current_step'));}
	public function redirect_content_history():void{$this->redirect_legacy('content-history',array('content_search','content_status','content_author','date_from','date_to','content_source','paged'));}
	public function redirect_system_status():void{$this->redirect_legacy('system-status',array());}
	private function redirect_legacy(string $section,array $allowed):void{if(!current_user_can(Permissions::manage())){wp_die(esc_html__('You do not have permission to access this page.','ai-content-studio'));}$args=array('page'=>'aics-settings','section'=>$section);foreach($allowed as $key){if(isset($_GET[$key])&&is_scalar($_GET[$key])){$args[$key]=sanitize_text_field(wp_unslash((string)$_GET[$key]));}}wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));exit;}

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
