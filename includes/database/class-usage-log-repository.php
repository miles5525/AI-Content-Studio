<?php
/**
 * Usage-log persistence.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AICS_Usage_Log_Repository {
	public function insert( array $entry ): bool {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'aics_usage_logs',
			$entry,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return false !== $result;
	}

	/** @return array<string,int> */
	public function get_summary(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'aics_usage_logs';
		$sql   = "SELECT
			SUM(CASE WHEN operation IN ('generate_blog_ideas','evaluate_content_ideas','generate_article_draft') THEN 1 ELSE 0 END) total_ai_requests,
			SUM(CASE WHEN operation IN ('generate_blog_ideas','evaluate_content_ideas','generate_article_draft') AND status = 'success' THEN 1 ELSE 0 END) successful_ai_requests,
			SUM(CASE WHEN operation IN ('generate_blog_ideas','evaluate_content_ideas','generate_article_draft') AND status = 'failed' THEN 1 ELSE 0 END) failed_ai_requests,
			SUM(CASE WHEN operation = 'generate_article_draft' AND status = 'success' THEN 1 ELSE 0 END) articles_generated,
			SUM(CASE WHEN operation = 'create_wordpress_draft' AND status = 'success' THEN 1 ELSE 0 END) drafts_created
			FROM {$table}";
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$defaults = array( 'total_ai_requests' => 0, 'successful_ai_requests' => 0, 'failed_ai_requests' => 0, 'articles_generated' => 0, 'drafts_created' => 0 );
		foreach ( $defaults as $key => $value ) {
			$defaults[ $key ] = isset( $row[ $key ] ) ? absint( $row[ $key ] ) : 0;
		}

		return $defaults;
	}

	/** @return array<int,object> */
	public function get_recent( int $limit = 10 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'aics_usage_logs';
		$sql   = $wpdb->prepare( "SELECT id,user_id,event_type,operation,status,provider,model,error_code,object_id,duration_ms,metadata,created_at FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", max( 1, min( 50, $limit ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function delete_expired( string $cutoff ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'aics_usage_logs';
		$sql   = $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false === $result ? 0 : (int) $result;
	}
}
