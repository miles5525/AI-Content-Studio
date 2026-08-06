<?php
/**
 * Main plugin coordinator.
 *
 * @package AIContentStudio
 */

namespace AIContentStudio\Core;

use AIContentStudio\Admin\Admin_Menu;
use AIContentStudio\Admin\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates plugin services and hooks.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers runtime hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		\AICS_Automation_Scheduler::register();
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( 'AIContentStudio\\Database\\Installer', 'maybe_upgrade' ) );
		add_action( 'admin_init', array( Privacy::class, 'add_policy_content' ) );
		add_action( 'aics_cleanup_usage_logs', array( 'AICS_Usage_Logger', 'cleanup' ) );

		if ( ! wp_next_scheduled( 'aics_cleanup_usage_logs' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aics_cleanup_usage_logs' );
		}

		if ( is_admin() ) {
			( new Admin_Menu() )->register();
			( new Assets() )->register();
			\AICS_Settings_Page::register();
			\AICS_Content_Studio_Page::register();
			\AICS_Automations_Page::register();
			\AICS_Automation_Runs_Page::register();
			\AICS_Approvals_Page::register();
		}
	}

	/**
	 * Loads translation files.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'ai-content-studio',
			false,
			dirname( plugin_basename( AICS_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}
}
