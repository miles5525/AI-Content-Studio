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
	 * Saves installation metadata and installs the current schema.
	 *
	 * @return void
	 */
	public static function install(): void {
		update_option( 'aics_version', AICS_VERSION, false );

		if ( false === get_option( 'aics_installed_at', false ) ) {
			add_option( 'aics_installed_at', current_time( 'mysql', true ), '', false );
		}

		self::maybe_upgrade();
	}

	/**
	 * Applies schema changes only when the installed version is behind.
	 */
	public static function maybe_upgrade(): void {
		if ( AICS_VERSION !== (string) get_option( 'aics_version', '' ) ) {
			update_option( 'aics_version', AICS_VERSION, false );
		}

		$installed = (string) get_option( 'aics_db_version', '0.0.0' );

		if ( ! version_compare( $installed, AICS_DB_VERSION, '<' ) ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . 'aics_usage_logs';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(50) NOT NULL,
			operation varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			provider varchar(50) NOT NULL DEFAULT '',
			model varchar(100) NOT NULL DEFAULT '',
			error_code varchar(100) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			duration_ms bigint(20) unsigned NOT NULL DEFAULT 0,
			metadata longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY event_type (event_type),
			KEY operation (operation),
			KEY status (status),
			KEY created_at (created_at),
			KEY object_id (object_id)
		) {$charset_collate};";

		$wpdb->last_error = '';
		dbDelta( $sql );

		if ( '' === $wpdb->last_error ) {
			update_option( 'aics_db_version', AICS_DB_VERSION, false );
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
