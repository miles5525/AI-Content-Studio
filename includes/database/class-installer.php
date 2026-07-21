<?php
/**
 * Installation and schema version management.
 *
 * @package AIContentStudio
 */

namespace AIContentStudio\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores installation metadata and will own future schema upgrades.
 */
final class Installer {
	/**
	 * Saves installation metadata. No custom tables are created.
	 *
	 * @return void
	 */
	public static function install(): void {
		update_option( 'aics_version', AICS_VERSION, false );
		update_option( 'aics_db_version', AICS_DB_VERSION, false );
		update_option( 'aics_installed_at', current_time( 'mysql', true ), false );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
