<?php
/**
 * Plugin deactivation handler.
 *
 * @package AIContentStudio
 */

namespace AIContentStudio\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs deactivation tasks.
 */
final class Deactivator {
	/**
	 * Deactivates the plugin without deleting persistent data.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'aics_scheduled_event' );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
