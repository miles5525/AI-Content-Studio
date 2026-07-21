<?php
/**
 * Plugin activation handler.
 *
 * @package AIContentStudio
 */

namespace AIContentStudio\Core;

use AIContentStudio\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs activation tasks.
 */
final class Activator {
	/**
	 * Activates the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::check_requirements();
		Installer::install();
	}

	/**
	 * Validates the server and WordPress versions.
	 *
	 * @return void
	 */
	private static function check_requirements(): void {
		global $wp_version;

		if ( version_compare( PHP_VERSION, AICS_MINIMUM_PHP_VERSION, '<' ) ) {
			self::fail_activation( __( 'AI Content Studio requires PHP 8.0 or newer.', 'ai-content-studio' ) );
		}

		if ( version_compare( (string) $wp_version, AICS_MINIMUM_WP_VERSION, '<' ) ) {
			self::fail_activation( __( 'AI Content Studio requires WordPress 6.4 or newer.', 'ai-content-studio' ) );
		}
	}

	/**
	 * Stops activation with a controlled WordPress error screen.
	 *
	 * @param string $message Activation failure message.
	 * @return void
	 */
	private static function fail_activation( string $message ): void {
		deactivate_plugins( plugin_basename( AICS_PLUGIN_FILE ) );

		wp_die(
			esc_html( $message ),
			esc_html__( 'AI Content Studio activation error', 'ai-content-studio' ),
			array( 'back_link' => true )
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
