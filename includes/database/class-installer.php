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
		$runs_table      = $wpdb->prefix . 'aics_automation_runs';
		$ideas_table     = $wpdb->prefix . 'aics_content_ideas';
		$articles_table  = $wpdb->prefix . 'aics_articles';
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
		$runs_sql        = "CREATE TABLE {$runs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_uuid char(36) NOT NULL,
			profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
			active_profile_key bigint(20) unsigned NULL,
			trigger_type varchar(30) NOT NULL DEFAULT 'scheduled',
			status varchar(30) NOT NULL DEFAULT 'queued',
			current_step varchar(50) NOT NULL DEFAULT 'pending',
			configuration_snapshot longtext NULL,
			lock_token varchar(64) NOT NULL DEFAULT '',
			locked_at datetime NULL,
			lock_expires_at datetime NULL,
			attempt_count smallint(5) unsigned NOT NULL DEFAULT 0,
			max_attempts smallint(5) unsigned NOT NULL DEFAULT 3,
			next_retry_at datetime NULL,
			last_error_code varchar(100) NOT NULL DEFAULT '',
			started_at datetime NULL,
			completed_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY run_uuid (run_uuid),
			UNIQUE KEY active_profile_key (active_profile_key),
			KEY profile_id (profile_id),
			KEY trigger_type (trigger_type),
			KEY status (status),
			KEY current_step (current_step),
			KEY lock_expires_at (lock_expires_at),
			KEY next_retry_at (next_retry_at),
			KEY created_at (created_at),
			KEY updated_at (updated_at)
		) {$charset_collate};";
		$ideas_sql       = "CREATE TABLE {$ideas_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			idea_uuid char(36) NOT NULL,
			profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
			run_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_type varchar(20) NOT NULL DEFAULT 'automation',
			title varchar(250) NOT NULL DEFAULT '',
			normalized_title varchar(250) NOT NULL DEFAULT '',
			summary text NULL,
			primary_keyword varchar(191) NOT NULL DEFAULT '',
			normalized_keyword varchar(191) NOT NULL DEFAULT '',
			secondary_keywords longtext NULL,
			search_intent varchar(30) NOT NULL DEFAULT 'informational',
			suggested_category varchar(191) NOT NULL DEFAULT '',
			outline longtext NULL,
			content_fingerprint char(64) NOT NULL DEFAULT '',
			score decimal(5,2) NOT NULL DEFAULT 0.00,
			status varchar(30) NOT NULL DEFAULT 'generated',
			priority smallint(5) unsigned NOT NULL DEFAULT 0,
			evaluated_at datetime NULL,
			planned_publish_at datetime NULL,
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			approved_at datetime NULL,
			rejected_by bigint(20) unsigned NOT NULL DEFAULT 0,
			rejected_at datetime NULL,
			rejection_code varchar(100) NOT NULL DEFAULT '',
			last_error_code varchar(100) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idea_uuid (idea_uuid),
			KEY profile_id (profile_id),
			KEY run_id (run_id),
			KEY source_type (source_type),
			KEY status (status),
			KEY search_intent (search_intent),
			KEY content_fingerprint (content_fingerprint),
			KEY normalized_keyword (normalized_keyword),
			KEY planned_publish_at (planned_publish_at),
			KEY evaluated_at (evaluated_at),
			KEY approved_by (approved_by),
			KEY created_at (created_at),
			KEY updated_at (updated_at),
			KEY normalized_title (normalized_title(191))
		) {$charset_collate};";
		$articles_sql    = "CREATE TABLE {$articles_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			article_uuid char(36) NOT NULL,
			idea_id bigint(20) unsigned NOT NULL DEFAULT 0,
			profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
			run_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_type varchar(20) NOT NULL DEFAULT 'automation',
			title varchar(250) NOT NULL DEFAULT '',
			excerpt text NULL,
			content longtext NULL,
			content_hash char(64) NOT NULL DEFAULT '',
			word_count int(10) unsigned NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'queued',
			planned_publish_at datetime NULL,
			wordpress_post_id bigint(20) unsigned NULL,
			generation_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			last_generation_at datetime NULL,
			generated_at datetime NULL,
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			approved_at datetime NULL,
			rejected_by bigint(20) unsigned NOT NULL DEFAULT 0,
			rejected_at datetime NULL,
			rejection_code varchar(100) NOT NULL DEFAULT '',
			post_created_at datetime NULL,
			scheduled_at datetime NULL,
			published_at datetime NULL,
			last_error_code varchar(100) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY article_uuid (article_uuid),
			UNIQUE KEY idea_id (idea_id),
			UNIQUE KEY wordpress_post_id (wordpress_post_id),
			KEY profile_id (profile_id),
			KEY run_id (run_id),
			KEY source_type (source_type),
			KEY status (status),
			KEY planned_publish_at (planned_publish_at),
			KEY generated_at (generated_at),
			KEY approved_by (approved_by),
			KEY created_at (created_at),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$wpdb->last_error = '';
		dbDelta( $usage_sql );
		$usage_error = $wpdb->last_error;
		$wpdb->last_error = '';
		dbDelta( $profiles_sql );
		$profiles_error = $wpdb->last_error;
		$wpdb->last_error = '';
		dbDelta( $runs_sql );
		$runs_error = $wpdb->last_error;
		$wpdb->last_error = '';
		dbDelta( $ideas_sql );
		$ideas_error = $wpdb->last_error;
		$wpdb->last_error = '';
		dbDelta( $articles_sql );
		$articles_error = $wpdb->last_error;

		$usage_exists    = $usage_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $usage_table ) ) );
		$profiles_exists = $profiles_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $profiles_table ) ) );
		$runs_exists     = $runs_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $runs_table ) ) );
		$snapshot_exists = $runs_exists && null !== $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$runs_table} LIKE %s", 'configuration_snapshot' ) );
		$ideas_exists    = $ideas_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $ideas_table ) ) );
		$articles_exists = $articles_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $articles_table ) ) );

		if ( '' === $usage_error && '' === $profiles_error && '' === $runs_error && '' === $ideas_error && '' === $articles_error && $usage_exists && $profiles_exists && $runs_exists && $snapshot_exists && $ideas_exists && $articles_exists ) {
			update_option( 'aics_db_version', AICS_DB_VERSION, false );
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
