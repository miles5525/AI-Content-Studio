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
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			( new Admin_Menu() )->register();
			( new Assets() )->register();
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
