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
	private const FEATURED_IMAGE_COLUMNS = array(
		'featured_image_required',
		'featured_image_status',
		'featured_image_attachment_id',
		'featured_image_prompt',
		'featured_image_alt_text',
		'featured_image_provider',
		'featured_image_model',
		'featured_image_attempts',
		'featured_image_generated_at',
		'featured_image_uploaded_at',
		'featured_image_attached_at',
		'featured_image_last_error_code',
	);

	private const FEATURED_IMAGE_INDEXES = array(
		'featured_image_status'        => 'featured_image_status',
		'featured_image_attachment_id' => 'featured_image_attachment_id',
	);

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
	 * Applies version upgrades and verifies the featured-image repair before completion.
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
		$actions_table   = $wpdb->prefix . 'aics_automation_run_actions';
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
			featured_image_required tinyint(1) unsigned NOT NULL DEFAULT 0,
			featured_image_status varchar(32) NOT NULL DEFAULT 'not_requested',
			featured_image_attachment_id bigint(20) unsigned NULL,
			featured_image_prompt text NULL,
			featured_image_alt_text text NULL,
			featured_image_provider varchar(64) NULL,
			featured_image_model varchar(100) NULL,
			featured_image_attempts int(10) unsigned NOT NULL DEFAULT 0,
			featured_image_generated_at datetime NULL,
			featured_image_uploaded_at datetime NULL,
			featured_image_attached_at datetime NULL,
			featured_image_last_error_code varchar(100) NULL,
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
			KEY featured_image_status (featured_image_status),
			KEY featured_image_attachment_id (featured_image_attachment_id),
			KEY created_at (created_at),
			KEY updated_at (updated_at)
		) {$charset_collate};";
		$actions_sql     = "CREATE TABLE {$actions_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id bigint(20) unsigned NOT NULL,
			action_type varchar(32) NOT NULL,
			previous_status varchar(32) NOT NULL,
			previous_step varchar(64) NOT NULL,
			resulting_status varchar(32) NOT NULL,
			resulting_step varchar(64) NOT NULL,
			previous_attempt_count int(10) unsigned NOT NULL DEFAULT 0,
			resulting_attempt_count int(10) unsigned NOT NULL DEFAULT 0,
			actor_user_id bigint(20) unsigned NULL,
			reason_code varchar(100) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY action_type (action_type),
			KEY actor_user_id (actor_user_id),
			KEY created_at (created_at)
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
		$wpdb->last_error = '';
		dbDelta( $actions_sql );
		$actions_error = $wpdb->last_error;

		$usage_exists    = $usage_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $usage_table ) ) );
		$profiles_exists = $profiles_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $profiles_table ) ) );
		$runs_exists     = $runs_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $runs_table ) ) );
		$snapshot_exists = $runs_exists && null !== $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$runs_table} LIKE %s", 'configuration_snapshot' ) );
		$ideas_exists    = $ideas_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $ideas_table ) ) );
		$articles_exists = $articles_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $articles_table ) ) );
		$featured_schema_exists = $articles_exists && self::featured_image_schema_complete();
		$actions_exists  = $actions_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $actions_table ) ) );

		if ( '' === $usage_error && '' === $profiles_error && '' === $runs_error && '' === $ideas_error && '' === $articles_error && '' === $actions_error && $usage_exists && $profiles_exists && $runs_exists && $snapshot_exists && $ideas_exists && $articles_exists && $featured_schema_exists && $actions_exists ) {
			update_option( 'aics_db_version', AICS_DB_VERSION, false );
		}
	}

	/**
	 * Confirms that every Task 1.1 column and index exists on the articles table.
	 */
	private static function featured_image_schema_complete(): bool {
		global $wpdb;
		$articles_table = $wpdb->prefix . 'aics_articles';

		if ( $articles_table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $articles_table ) ) ) ) {
			return false;
		}

		foreach ( self::FEATURED_IMAGE_COLUMNS as $column ) {
			if ( null === $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$articles_table} LIKE %s", $column ) ) ) {
				return false;
			}
		}

		foreach ( self::FEATURED_IMAGE_INDEXES as $index_name => $column_name ) {
			$index_rows = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM {$articles_table} WHERE Key_name = %s", $index_name ), ARRAY_A );
			if ( 1 !== count( $index_rows ) || $column_name !== ( $index_rows[0]['Column_name'] ?? '' ) || 1 !== (int) ( $index_rows[0]['Seq_in_index'] ?? 0 ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
