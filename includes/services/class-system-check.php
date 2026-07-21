<?php
/** System diagnostics without rendering or mutation. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_System_Check {
	/** @return array<int,array{id:string,label:string,status:string,value:string,message:string}> */
	public static function run(): array {
		global $wpdb, $wp_version;
		$table       = $wpdb->prefix . 'aics_usage_logs';
		$table_ok    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		$runs_table  = $wpdb->prefix . 'aics_automation_runs';
		$runs_ok     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $runs_table ) ) === $runs_table;
		$ideas_table = $wpdb->prefix . 'aics_content_ideas';
		$ideas_ok    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ideas_table ) ) === $ideas_table;
		$articles_table = $wpdb->prefix . 'aics_articles';
		$articles_ok    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $articles_table ) ) === $articles_table;
		$db_ok       = '1' === (string) $wpdb->get_var( 'SELECT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$uploads     = wp_upload_dir();
		$upload_ok   = empty( $uploads['error'] ) && is_dir( $uploads['basedir'] ) && wp_is_writable( $uploads['basedir'] );
		$cron_times  = self::cron_times();
		$cron_next   = wp_next_scheduled( 'aics_cleanup_usage_logs' );
		$automation_next = wp_next_scheduled( AICS_Automation_Scheduler::HOOK );
		$worker_next     = wp_next_scheduled( AICS_Automation_Scheduler::WORKER_HOOK );
		$cron_disabled   = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$key         = AICS_Settings::has_openai_api_key();
		$model       = AICS_Settings::get_openai_model();
		$https       = str_starts_with( admin_url(), 'https://' );
		$wp_good     = version_compare( (string) $wp_version, AICS_MINIMUM_WP_VERSION, '>=' );
		$php_min     = version_compare( PHP_VERSION, AICS_MINIMUM_PHP_VERSION, '>=' );
		$php_rec     = version_compare( PHP_VERSION, '8.1', '>=' );
		$schema_ok   = AICS_DB_VERSION === (string) get_option( 'aics_db_version', '' );

		return array(
			self::item( 'wordpress_version', __( 'WordPress Version', 'ai-content-studio' ), $wp_good ? 'good' : 'critical', (string) $wp_version, $wp_good ? __( 'WordPress meets the supported minimum.', 'ai-content-studio' ) : __( 'WordPress is below the supported minimum.', 'ai-content-studio' ) ),
			self::item( 'php_version', __( 'PHP Version', 'ai-content-studio' ), ! $php_min ? 'critical' : ( $php_rec ? 'good' : 'warning' ), PHP_VERSION, $php_rec ? __( 'PHP meets the recommended version.', 'ai-content-studio' ) : __( 'PHP is supported, but PHP 8.1 or newer is recommended.', 'ai-content-studio' ) ),
			self::item( 'https', __( 'HTTPS', 'ai-content-studio' ), $https ? 'good' : 'warning', $https ? __( 'Available', 'ai-content-studio' ) : __( 'Not detected', 'ai-content-studio' ), $https ? __( 'The administration URL uses HTTPS.', 'ai-content-studio' ) : __( 'HTTP is acceptable for local development; production should use HTTPS.', 'ai-content-studio' ) ),
			self::item( 'rest_api', __( 'WordPress REST API', 'ai-content-studio' ), class_exists( 'WP_REST_Server' ) && function_exists( 'rest_get_server' ) ? 'good' : 'critical', class_exists( 'WP_REST_Server' ) ? __( 'Available', 'ai-content-studio' ) : __( 'Unavailable', 'ai-content-studio' ), __( 'Checks local WordPress REST infrastructure without an external request.', 'ai-content-studio' ) ),
			self::extension( 'curl', 'cURL' ), self::extension( 'json', 'JSON' ), self::extension( 'openssl', 'OpenSSL' ),
			self::item( 'http_api', __( 'WordPress HTTP API', 'ai-content-studio' ), function_exists( 'wp_remote_post' ) ? 'good' : 'critical', function_exists( 'wp_remote_post' ) ? __( 'Available', 'ai-content-studio' ) : __( 'Unavailable', 'ai-content-studio' ), __( 'Required for provider requests.', 'ai-content-studio' ) ),
			self::item( 'database', __( 'Database Connectivity', 'ai-content-studio' ), $db_ok ? 'good' : 'critical', $db_ok ? __( 'Connected', 'ai-content-studio' ) : __( 'Unavailable', 'ai-content-studio' ), __( 'A minimal local database query was used.', 'ai-content-studio' ) ),
			self::item( 'usage_table', __( 'Usage Log Table', 'ai-content-studio' ), $table_ok ? 'good' : 'critical', $table_ok ? __( 'Available', 'ai-content-studio' ) : __( 'Missing', 'ai-content-studio' ), __( 'No usage records were read.', 'ai-content-studio' ) ),
			self::item( 'automation_runs_table', __( 'Automation Run Table', 'ai-content-studio' ), $runs_ok ? 'good' : 'critical', $runs_ok ? __( 'Available', 'ai-content-studio' ) : __( 'Missing', 'ai-content-studio' ), __( 'Only table availability was checked; no run records were read.', 'ai-content-studio' ) ),
			self::item( 'content_ideas_table', __( 'Content Ideas Table', 'ai-content-studio' ), $ideas_ok ? 'good' : 'critical', $ideas_ok ? __( 'Available', 'ai-content-studio' ) : __( 'Missing', 'ai-content-studio' ), $ideas_ok ? __( 'The required content-ideas table exists.', 'ai-content-studio' ) : __( 'The required content-ideas table is missing.', 'ai-content-studio' ) ),
			self::item( 'content_articles_table', __( 'Content Articles Table', 'ai-content-studio' ), $articles_ok ? 'good' : 'critical', $articles_ok ? __( 'Available', 'ai-content-studio' ) : __( 'Missing', 'ai-content-studio' ), $articles_ok ? __( 'The required content-articles table exists.', 'ai-content-studio' ) : __( 'The required content-articles table is missing.', 'ai-content-studio' ) ),
			self::item( 'schema_version', __( 'Usage Schema Version', 'ai-content-studio' ), $table_ok && $schema_ok ? 'good' : ( $table_ok ? 'warning' : 'critical' ), (string) get_option( 'aics_db_version', __( 'Not installed', 'ai-content-studio' ) ) . ' / ' . AICS_DB_VERSION, $schema_ok ? __( 'Installed and code schema versions match.', 'ai-content-studio' ) : __( 'The normal upgrade routine should resolve this mismatch.', 'ai-content-studio' ) ),
			self::item( 'wp_cron', __( 'WordPress Cron', 'ai-content-studio' ), defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'warning' : 'good', defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Disabled', 'ai-content-studio' ) : __( 'Enabled', 'ai-content-studio' ), __( 'Disabled WP-Cron requires an external scheduler.', 'ai-content-studio' ) ),
			self::item( 'cleanup_cron', __( 'AICS Cleanup Cron', 'ai-content-studio' ), false === $cron_next ? 'warning' : ( count( $cron_times ) > 1 ? 'warning' : 'good' ), false === $cron_next ? __( 'Not scheduled', 'ai-content-studio' ) : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cron_next, wp_timezone() ), count( $cron_times ) > 1 ? __( 'Duplicate cleanup events were detected.', 'ai-content-studio' ) : __( 'The daily retention event is scheduled once.', 'ai-content-studio' ) ),
			self::item( 'automation_scheduler', __( 'Automation Scheduler', 'ai-content-studio' ), false === $automation_next ? 'critical' : ( $cron_disabled ? 'warning' : 'good' ), false === $automation_next ? __( 'Not scheduled', 'ai-content-studio' ) : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $automation_next, wp_timezone() ), false === $automation_next ? __( 'The global automation dispatcher event is missing.', 'ai-content-studio' ) : ( $cron_disabled ? __( 'The event exists, but WP-Cron is disabled. A real server cron may still trigger WordPress Cron.', 'ai-content-studio' ) : __( 'The global automation dispatcher event is scheduled.', 'ai-content-studio' ) ) ),
			self::item( 'automation_worker', __( 'Automation Worker', 'ai-content-studio' ), false === $worker_next || ! $runs_ok || ! $ideas_ok ? 'critical' : ( $cron_disabled ? 'warning' : 'good' ), false === $worker_next ? __( 'Not scheduled', 'ai-content-studio' ) : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $worker_next, wp_timezone() ), ! $runs_ok || ! $ideas_ok ? __( 'A required automation table is missing.', 'ai-content-studio' ) : ( false === $worker_next ? __( 'The global automation worker event is missing.', 'ai-content-studio' ) : ( $cron_disabled ? __( 'The event exists, but WP-Cron is disabled. A real server cron may still trigger WordPress Cron.', 'ai-content-studio' ) : __( 'The automation worker is scheduled and required tables exist.', 'ai-content-studio' ) ) ) ),
			self::item( 'uploads', __( 'Uploads Directory', 'ai-content-studio' ), $upload_ok ? 'good' : 'warning', $upload_ok ? __( 'Writable', 'ai-content-studio' ) : __( 'Not writable', 'ai-content-studio' ), __( 'The filesystem path is intentionally hidden.', 'ai-content-studio' ) ),
			self::item( 'provider', __( 'AI Provider', 'ai-content-studio' ), 'good', 'OpenAI', __( 'No live provider request is made by this page.', 'ai-content-studio' ) ),
			self::item( 'api_key', __( 'OpenAI API Key', 'ai-content-studio' ), $key ? 'good' : 'warning', $key ? __( 'Configured', 'ai-content-studio' ) : __( 'Not configured', 'ai-content-studio' ), __( 'The credential itself is never displayed.', 'ai-content-studio' ) ),
			self::item( 'model', __( 'OpenAI Model', 'ai-content-studio' ), in_array( $model, AICS_Settings::get_allowed_models(), true ) ? 'good' : 'warning', $model ?: __( 'Not selected', 'ai-content-studio' ), __( 'The saved model is checked against the plugin allowlist.', 'ai-content-studio' ) ),
			self::item( 'create_posts', __( 'Create Posts Permission', 'ai-content-studio' ), current_user_can( 'edit_posts' ) ? 'good' : 'warning', current_user_can( 'edit_posts' ) ? __( 'Allowed', 'ai-content-studio' ) : __( 'Not allowed', 'ai-content-studio' ), __( 'Required to create WordPress drafts.', 'ai-content-studio' ) ),
			self::item( 'permalinks', __( 'Permalink Structure', 'ai-content-studio' ), '' !== (string) get_option( 'permalink_structure', '' ) ? 'good' : 'warning', '' !== (string) get_option( 'permalink_structure', '' ) ? __( 'Configured', 'ai-content-studio' ) : __( 'Plain', 'ai-content-studio' ), __( 'Pretty permalinks are recommended.', 'ai-content-studio' ) ),
			self::item( 'debug', __( 'Debug Mode', 'ai-content-studio' ), defined( 'WP_DEBUG' ) && WP_DEBUG ? 'warning' : 'good', defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'Enabled', 'ai-content-studio' ) : __( 'Disabled', 'ai-content-studio' ), __( 'Debug mode is useful locally but should normally be disabled in production.', 'ai-content-studio' ) ),
		);
	}

	public static function diagnostics( array $checks ): string {
		$map = array(); foreach ( $checks as $check ) { $map[ $check['id'] ] = $check['value']; }
		$lines = array( 'AI Content Studio Diagnostics', 'Plugin version: ' . AICS_VERSION, 'Schema version: ' . AICS_DB_VERSION, 'WordPress version: ' . get_bloginfo( 'version' ), 'PHP version: ' . PHP_VERSION, 'Site language: ' . get_locale(), 'Multisite: ' . ( is_multisite() ? 'yes' : 'no' ), 'HTTPS: ' . ( $map['https'] ?? 'unknown' ), 'REST API: ' . ( $map['rest_api'] ?? 'unknown' ), 'cURL: ' . ( $map['curl'] ?? 'unknown' ), 'JSON: ' . ( $map['json'] ?? 'unknown' ), 'OpenSSL: ' . ( $map['openssl'] ?? 'unknown' ), 'Uploads: ' . ( $map['uploads'] ?? 'unknown' ), 'Provider: OpenAI', 'API key configured: ' . ( AICS_Settings::has_openai_api_key() ? 'yes' : 'no' ), 'Selected model: ' . AICS_Settings::get_openai_model(), 'Usage table: ' . ( $map['usage_table'] ?? 'unknown' ), 'Cleanup cron: ' . ( false !== wp_next_scheduled( 'aics_cleanup_usage_logs' ) ? 'scheduled' : 'not scheduled' ), 'Automation scheduler: ' . ( false !== wp_next_scheduled( AICS_Automation_Scheduler::HOOK ) ? 'scheduled' : 'not scheduled' ), 'WP_DEBUG: ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'enabled' : 'disabled' ), 'PHP memory limit: ' . ini_get( 'memory_limit' ), 'Maximum execution time: ' . (string) ini_get( 'max_execution_time' ) );
		return implode( "\n", array_map( 'sanitize_text_field', $lines ) );
	}

	private static function item( string $id, string $label, string $status, string $value, string $message ): array { return compact( 'id', 'label', 'status', 'value', 'message' ); }
	private static function extension( string $id, string $label ): array { $ok = extension_loaded( $id ); return self::item( $id, $label, $ok ? 'good' : 'warning', $ok ? __( 'Available', 'ai-content-studio' ) : __( 'Unavailable', 'ai-content-studio' ), $ok ? __( 'The PHP extension is loaded.', 'ai-content-studio' ) : __( 'Some functionality may be unavailable.', 'ai-content-studio' ) ); }
	private static function cron_times(): array { $times = array(); foreach ( (array) _get_cron_array() as $timestamp => $hooks ) { if ( isset( $hooks['aics_cleanup_usage_logs'] ) ) { $times[] = $timestamp; } } return $times; }
	private function __construct() {}
}
