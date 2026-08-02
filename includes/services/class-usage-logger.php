<?php
/**
 * Safe usage logging service.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AICS_Usage_Logger {
	public const RETENTION_DAYS = 30;
	private const EVENTS = array( 'ai_request', 'post_creation', 'system_test' );
	private const OPERATIONS = array( 'openai_connection_test', 'image_generation_test', 'generate_blog_ideas', 'evaluate_content_ideas', 'generate_article_draft', 'create_wordpress_draft' );
	private const STATUSES = array( 'success', 'failed' );
	private const METADATA_KEYS = array( 'idea_count', 'evaluated_count', 'eligible_count', 'source', 'requested_length', 'tone', 'post_status', 'test_type', 'validation_category', 'http_status' );
	private const VALIDATION_CATEGORIES = array( 'dangerous_attribute', 'dangerous_url', 'dangerous_markup', 'length', 'sanitization', 'structure' );

	public static function log( array $entry ): bool {
		try {
			$event_type = sanitize_key( (string) ( $entry['event_type'] ?? '' ) );
			$operation  = sanitize_key( (string) ( $entry['operation'] ?? '' ) );
			$status     = sanitize_key( (string) ( $entry['status'] ?? '' ) );

			if ( ! in_array( $event_type, self::EVENTS, true ) || ! in_array( $operation, self::OPERATIONS, true ) || ! in_array( $status, self::STATUSES, true ) ) {
				return false;
			}

			$metadata = array();
			if ( isset( $entry['metadata'] ) && is_array( $entry['metadata'] ) ) {
				foreach ( self::METADATA_KEYS as $key ) {
					if ( array_key_exists( $key, $entry['metadata'] ) && is_scalar( $entry['metadata'][ $key ] ) ) {
						$value = in_array( $key, array( 'idea_count', 'evaluated_count', 'eligible_count' ), true ) ? absint( $entry['metadata'][ $key ] ) : sanitize_key( (string) $entry['metadata'][ $key ] );
						if ( 'validation_category' !== $key || in_array( $value, self::VALIDATION_CATEGORIES, true ) ) { $metadata[ $key ] = $value; }
					}
				}
			}

			return ( new AICS_Usage_Log_Repository() )->insert(
				array(
					'user_id'    => absint( $entry['user_id'] ?? get_current_user_id() ),
					'event_type' => $event_type,
					'operation'  => $operation,
					'status'     => $status,
					'provider'   => substr( sanitize_key( (string) ( $entry['provider'] ?? '' ) ), 0, 50 ),
					'model'      => substr( sanitize_text_field( (string) ( $entry['model'] ?? '' ) ), 0, 100 ),
					'error_code' => substr( sanitize_key( (string) ( $entry['error_code'] ?? '' ) ), 0, 100 ),
					'object_id'  => absint( $entry['object_id'] ?? 0 ),
					'duration_ms'=> absint( $entry['duration_ms'] ?? 0 ),
					'metadata'   => wp_json_encode( $metadata ),
					'created_at' => current_time( 'mysql', true ),
				)
			);
		} catch ( Throwable $throwable ) {
			return false;
		}
	}

	public static function duration_ms( float $started_at ): int {
		return max( 0, (int) round( ( microtime( true ) - $started_at ) * 1000 ) );
	}

	public static function cleanup(): void {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS ) );
		( new AICS_Usage_Log_Repository() )->delete_expired( $cutoff );
	}
}
