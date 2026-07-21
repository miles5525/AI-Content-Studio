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

		$usage_table     = $wpdb->prefix . 'aics_usage_logs';
		$profiles_table  = $wpdb->prefix . 'aics_automation_profiles';
		$charset_collate = $wpdb->get_charset_collate();
		$usage_sql       = "CREATE TABLE {$usage_table} (
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
		$profiles_sql    = "CREATE TABLE {$profiles_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			profile_slug varchar(191) NOT NULL,
			profile_name varchar(191) NOT NULL DEFAULT '',
			mode varchar(30) NOT NULL DEFAULT 'autopilot',
			status varchar(30) NOT NULL DEFAULT 'disabled',
			business_context longtext NULL,
			content_settings longtext NULL,
			schedule_settings longtext NULL,
			workflow_rules longtext NULL,
			publishing_settings longtext NULL,
			next_run_at datetime NULL,
			last_run_at datetime NULL,
			last_error_code varchar(100) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY profile_slug (profile_slug),
			KEY mode (mode),
			KEY status (status),
			KEY next_run_at (next_run_at),
			KEY created_by (created_by),
			KEY updated_by (updated_by)
		) {$charset_collate};";

		$wpdb->last_error = '';
		dbDelta( $usage_sql );
		$usage_error = $wpdb->last_error;
		$wpdb->last_error = '';
		dbDelta( $profiles_sql );
		$profiles_error = $wpdb->last_error;

		$usage_exists    = $usage_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $usage_table ) ) );
		$profiles_exists = $profiles_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $profiles_table ) ) );

		if ( '' === $usage_error && '' === $profiles_error && $usage_exists && $profiles_exists ) {
			update_option( 'aics_db_version', AICS_DB_VERSION, false );
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
