<?php
/**
 * Automation-profile persistence and normalization.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists automation configuration without executing automation.
 */
final class AICS_Automation_Profile_Repository {
	private const MODES = array( 'autopilot', 'approval', 'manual' );
	private const STATUSES = array( 'disabled', 'active', 'paused', 'error' );
	private const JSON_FIELDS = array( 'business_context', 'content_settings', 'schedule_settings', 'workflow_rules', 'publishing_settings' );
	private const UPDATE_FIELDS = array( 'profile_name', 'mode', 'status', 'business_context', 'content_settings', 'schedule_settings', 'workflow_rules', 'publishing_settings', 'next_run_at', 'last_run_at', 'last_error_code' );

	/** Creates a normalized profile. */
	public function create( array $data ): array {
		global $wpdb;

		$slug = self::normalize_slug( $data['profile_slug'] ?? '' );
		if ( '' === $slug ) {
			return self::result( false, 0, 'invalid_profile_slug' );
		}
		if ( null !== $this->get_by_slug( $slug ) ) {
			return self::result( false, 0, 'duplicate_profile_slug' );
		}

		$mode   = sanitize_key( (string) ( $data['mode'] ?? 'autopilot' ) );
		$status = sanitize_key( (string) ( $data['status'] ?? 'disabled' ) );
		if ( ! in_array( $mode, self::MODES, true ) || ! in_array( $status, self::STATUSES, true ) ) {
			return self::result( false, 0, 'invalid_profile_data' );
		}

		$user_id = isset( $data['created_by'] ) ? absint( $data['created_by'] ) : get_current_user_id();
		$now     = current_time( 'mysql', true );
		$row     = array(
			'profile_slug'        => $slug,
			'profile_name'        => self::limit( sanitize_text_field( (string) ( $data['profile_name'] ?? '' ) ), 191 ),
			'mode'                => $mode,
			'status'              => $status,
			'business_context'    => self::encode( self::business_context( $data['business_context'] ?? array() ) ),
			'content_settings'    => self::encode( self::content_settings( $data['content_settings'] ?? array() ) ),
			'schedule_settings'   => self::encode( self::schedule_settings( $data['schedule_settings'] ?? array() ) ),
			'workflow_rules'      => self::encode( self::workflow_rules( $data['workflow_rules'] ?? array() ) ),
			'publishing_settings' => self::encode( self::publishing_settings( $data['publishing_settings'] ?? array() ) ),
			'next_run_at'         => self::datetime( $data['next_run_at'] ?? null ),
			'last_run_at'         => self::datetime( $data['last_run_at'] ?? null ),
			'last_error_code'     => self::error_code( $data['last_error_code'] ?? '' ),
			'created_by'          => $user_id,
			'updated_by'          => isset( $data['updated_by'] ) ? absint( $data['updated_by'] ) : $user_id,
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		$result = $wpdb->insert( $this->table(), $row, self::formats( $row ) );
		if ( false === $result ) {
			$duplicate = null !== $this->get_by_slug( $slug );
			return self::result( false, 0, $duplicate ? 'duplicate_profile_slug' : 'database_insert_failed' );
		}

		return self::result( true, (int) $wpdb->insert_id, 'profile_created' );
	}

	/** Updates only explicitly supplied configuration fields; profile slugs are immutable. */
	public function update( $profile_id, array $data ): array {
		$id = absint( $profile_id );
		if ( 0 === $id || null === $this->get_by_id( $id ) ) {
			return self::result( false, 0, 'profile_not_found' );
		}

		$row = array();
		foreach ( self::UPDATE_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}
			$value = $this->normalize_field( $field, $data[ $field ] );
			if ( null === $value && in_array( $field, array( 'mode', 'status' ), true ) ) {
				return self::result( false, 0, 'invalid_profile_data' );
			}
			$row[ $field ] = $value;
		}
		if ( empty( $row ) ) {
			return self::result( false, 0, 'no_profile_changes' );
		}
		$row['updated_by'] = isset( $data['updated_by'] ) ? absint( $data['updated_by'] ) : get_current_user_id();
		$row['updated_at'] = current_time( 'mysql', true );

		global $wpdb;
		$result = $wpdb->update( $this->table(), $row, array( 'id' => $id ), self::formats( $row ), array( '%d' ) );
		return false === $result ? self::result( false, 0, 'database_update_failed' ) : self::result( true, $id, 'profile_updated' );
	}

	/** Gets one profile by ID. */
	public function get_by_id( $profile_id ): ?array {
		global $wpdb;
		$id  = absint( $profile_id );
		$sql = $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->profile( $wpdb->get_row( $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Gets one profile by stable slug. */
	public function get_by_slug( $profile_slug ): ?array {
		global $wpdb;
		$slug = self::normalize_slug( $profile_slug );
		if ( '' === $slug ) {
			return null;
		}
		$sql = $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE profile_slug = %s LIMIT 1", $slug ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->profile( $wpdb->get_row( $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Returns a bounded, safely ordered profile list. */
	public function get_profiles( array $args = array() ): array {
		global $wpdb;
		$orderby_allowed = array( 'id', 'profile_name', 'status', 'next_run_at', 'created_at', 'updated_at' );
		$orderby = in_array( $args['orderby'] ?? '', $orderby_allowed, true ) ? $args['orderby'] : 'id';
		$order   = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		$limit   = max( 1, min( 100, absint( $args['limit'] ?? 20 ) ) );
		$offset  = absint( $args['offset'] ?? 0 );
		$where   = array( '1=1' );
		$values  = array();
		if ( isset( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$where[] = 'status = %s'; $values[] = $args['status'];
		}
		if ( isset( $args['mode'] ) && in_array( $args['mode'], self::MODES, true ) ) {
			$where[] = 'mode = %s'; $values[] = $args['mode'];
		}
		$values[] = $limit; $values[] = $offset;
		$sql = "SELECT * FROM {$this->table()} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_values( array_filter( array_map( array( $this, 'profile' ), $wpdb->get_results( $sql, ARRAY_A ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Returns active profiles due at or before a strict UTC database datetime. */
	public function get_due_profiles( $utc_now, $limit = 10 ): array {
		global $wpdb;
		$now = self::datetime( $utc_now );
		if ( null === $now ) {
			return array();
		}
		$limit = max( 1, min( 100, absint( $limit ) ) );
		$sql = $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE status = %s AND next_run_at IS NOT NULL AND next_run_at <= %s ORDER BY next_run_at ASC, id ASC LIMIT %d", 'active', $now, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_values( array_filter( array_map( array( $this, 'profile' ), $wpdb->get_results( $sql, ARRAY_A ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Updates runtime state without touching profile configuration. */
	public function update_runtime_fields( $profile_id, array $runtime_data ): array {
		$id = absint( $profile_id );
		if ( 0 === $id || null === $this->get_by_id( $id ) ) {
			return self::result( false, 0, 'profile_not_found' );
		}
		$row = array();
		foreach ( array( 'status', 'next_run_at', 'last_run_at', 'last_error_code' ) as $field ) {
			if ( array_key_exists( $field, $runtime_data ) ) {
				$row[ $field ] = $this->normalize_field( $field, $runtime_data[ $field ] );
			}
		}
		if ( isset( $row['status'] ) && null === $row['status'] ) {
			return self::result( false, 0, 'invalid_profile_data' );
		}
		if ( empty( $row ) ) {
			return self::result( false, 0, 'no_runtime_changes' );
		}
		$row['updated_by'] = isset( $runtime_data['updated_by'] ) ? absint( $runtime_data['updated_by'] ) : get_current_user_id();
		$row['updated_at'] = current_time( 'mysql', true );
		global $wpdb;
		$result = $wpdb->update( $this->table(), $row, array( 'id' => $id ), self::formats( $row ), array( '%d' ) );
		return false === $result ? self::result( false, 0, 'database_update_failed' ) : self::result( true, $id, 'runtime_updated' );
	}

	/** Checks whether the prefixed profile table exists. */
	public function table_exists(): bool {
		global $wpdb;
		$table = $this->table();
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	private function table(): string { global $wpdb; return $wpdb->prefix . 'aics_automation_profiles'; }
	private static function result( bool $success, int $id, string $code ): array { return array( 'success' => $success, 'profile_id' => $id, 'code' => $code ); }
	private static function normalize_slug( $value ): string { return self::limit( sanitize_key( is_scalar( $value ) ? (string) $value : '' ), 191 ); }
	private static function limit( string $value, int $length ): string { return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length ); }
	private static function encode( array $value ): string { $json = wp_json_encode( $value ); return false === $json ? '[]' : $json; }
	private static function decode( $value ): array { $decoded = json_decode( is_string( $value ) ? $value : '', true ); return is_array( $decoded ) ? $decoded : array(); }
	private static function error_code( $value ): string { return self::limit( sanitize_key( is_scalar( $value ) ? (string) $value : '' ), 100 ); }
	private static function datetime( $value ): ?string {
		if ( null === $value || '' === $value ) { return null; }
		$value = is_scalar( $value ) ? (string) $value : '';
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		return false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $date->format( 'Y-m-d H:i:s' ) === $value ? $value : null;
	}
	private static function date( $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( '' === $value ) { return ''; }
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		return false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}
	private static function boolean( $value ): bool { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
	private static function integer( $value, int $minimum, int $maximum, int $default ): int { $number = is_numeric( $value ) ? (int) $value : $default; return max( $minimum, min( $maximum, $number ) ); }
	private static function text_array( $value ): array {
		if ( ! is_array( $value ) ) { return array(); }
		$items = array();
		foreach ( $value as $item ) { if ( is_scalar( $item ) ) { $item = self::limit( sanitize_text_field( (string) $item ), 250 ); if ( '' !== $item ) { $items[] = $item; } } }
		return array_slice( array_values( array_unique( $items ) ), 0, 50 );
	}
	private static function business_context( $value ): array {
		if ( ! is_array( $value ) ) { return array(); }
		$out = array();
		$arrays = array( 'core_topics', 'topics_to_avoid', 'prohibited_claims' );
		$limits = array( 'business_name'=>191, 'business_description'=>5000, 'industry'=>191, 'products_services'=>5000, 'target_audience'=>3000, 'primary_location'=>191, 'website_purpose'=>3000, 'brand_voice'=>3000, 'preferred_tone'=>30, 'preferred_cta'=>2000 );
		$keys = array( 'business_name', 'business_description', 'industry', 'products_services', 'target_audience', 'primary_location', 'website_purpose', 'brand_voice', 'preferred_tone', 'core_topics', 'topics_to_avoid', 'preferred_cta', 'prohibited_claims' );
		foreach ( $keys as $key ) { if ( array_key_exists( $key, $value ) ) { $scalar = is_scalar( $value[ $key ] ) ? (string) $value[ $key ] : ''; $out[ $key ] = in_array( $key, $arrays, true ) ? self::text_array( $value[ $key ] ) : self::limit( isset( $limits[ $key ] ) && $limits[ $key ] > 191 ? sanitize_textarea_field( $scalar ) : sanitize_text_field( $scalar ), $limits[ $key ] ?? 300 ); } }
		return $out;
	}
	private static function content_settings( $value ): array {
		if ( ! is_array( $value ) ) { return array(); }
		$ideas = self::integer( $value['ideas_per_cycle'] ?? 5, 1, 20, 5 );
		$tones = array( 'professional', 'friendly', 'conversational', 'informative', 'persuasive' );
		$lengths = array( 'short', 'medium', 'long' );
		return array( 'ideas_per_cycle' => $ideas, 'selected_ideas_per_cycle' => min( $ideas, self::integer( $value['selected_ideas_per_cycle'] ?? 1, 1, 20, 1 ) ), 'default_tone' => in_array( $value['default_tone'] ?? '', $tones, true ) ? $value['default_tone'] : 'professional', 'article_length' => in_array( $value['article_length'] ?? '', $lengths, true ) ? $value['article_length'] : 'medium', 'duplicate_lookback_days' => self::integer( $value['duplicate_lookback_days'] ?? 180, 0, 3650, 180 ), 'include_faq' => self::boolean( $value['include_faq'] ?? false ), 'allow_tables' => self::boolean( $value['allow_tables'] ?? false ), 'allow_lists' => self::boolean( $value['allow_lists'] ?? false ) );
	}
	private static function schedule_settings( $value ): array {
		if ( ! is_array( $value ) ) { return array(); }
		$frequencies = array( 'daily', 'weekly', 'monthly' ); $weekdays = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$submitted_days = array(); foreach ( is_array( $value['days_of_week'] ?? null ) ? $value['days_of_week'] : array() as $submitted_day ) { if ( is_scalar( $submitted_day ) ) { $submitted_days[] = sanitize_key( (string) $submitted_day ); } }
		$days = array(); foreach ( $weekdays as $day ) { if ( in_array( $day, $submitted_days, true ) ) { $days[] = $day; } }
		$time = is_scalar( $value['publish_time'] ?? null ) ? (string) $value['publish_time'] : ''; if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) { $time = ''; }
		return array( 'frequency' => in_array( $value['frequency'] ?? '', $frequencies, true ) ? $value['frequency'] : 'weekly', 'interval' => self::integer( $value['interval'] ?? 1, 1, 31, 1 ), 'days_of_week' => $days, 'publish_time' => $time, 'posts_per_period' => self::integer( $value['posts_per_period'] ?? 1, 1, 31, 1 ), 'start_date' => self::date( $value['start_date'] ?? '' ), 'end_date' => self::date( $value['end_date'] ?? '' ), 'monthly_day' => self::integer( $value['monthly_day'] ?? 1, 1, 31, 1 ) );
	}
	private static function workflow_rules( $value ): array { if ( ! is_array( $value ) ) { return array(); } return array( 'require_idea_approval' => self::boolean( $value['require_idea_approval'] ?? false ), 'require_article_approval' => self::boolean( $value['require_article_approval'] ?? false ), 'require_publish_approval' => self::boolean( $value['require_publish_approval'] ?? false ) ); }
	private static function publishing_settings( $value ): array { if ( ! is_array( $value ) ) { return array(); } $modes = array( 'draft', 'schedule', 'publish' ); $statuses = array( 'draft', 'future', 'publish' ); return array( 'publishing_mode' => in_array( $value['publishing_mode'] ?? '', $modes, true ) ? $value['publishing_mode'] : 'draft', 'post_status_after_generation' => in_array( $value['post_status_after_generation'] ?? '', $statuses, true ) ? $value['post_status_after_generation'] : 'draft', 'category_id' => absint( $value['category_id'] ?? 0 ), 'author_id' => absint( $value['author_id'] ?? 0 ) ); }
	private function normalize_field( string $field, $value ) {
		if ( in_array( $field, self::JSON_FIELDS, true ) ) { $method = $field; return self::encode( self::$method( $value ) ); }
		if ( 'profile_name' === $field ) { return self::limit( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), 191 ); }
		if ( 'mode' === $field ) { $value = sanitize_key( (string) $value ); return in_array( $value, self::MODES, true ) ? $value : null; }
		if ( 'status' === $field ) { $value = sanitize_key( (string) $value ); return in_array( $value, self::STATUSES, true ) ? $value : null; }
		if ( in_array( $field, array( 'next_run_at', 'last_run_at' ), true ) ) { return self::datetime( $value ); }
		return self::error_code( $value );
	}
	private function profile( $row ): ?array {
		if ( ! is_array( $row ) ) { return null; }
		$row['id'] = absint( $row['id'] ?? 0 ); $row['created_by'] = absint( $row['created_by'] ?? 0 ); $row['updated_by'] = absint( $row['updated_by'] ?? 0 );
		$row['mode'] = in_array( $row['mode'] ?? '', self::MODES, true ) ? $row['mode'] : 'autopilot'; $row['status'] = in_array( $row['status'] ?? '', self::STATUSES, true ) ? $row['status'] : 'disabled';
		foreach ( self::JSON_FIELDS as $field ) { $row[ $field ] = self::decode( $row[ $field ] ?? '' ); }
		$row['next_run_at'] = self::datetime( $row['next_run_at'] ?? null ); $row['last_run_at'] = self::datetime( $row['last_run_at'] ?? null ); $row['last_error_code'] = self::error_code( $row['last_error_code'] ?? '' );
		return $row;
	}
	private static function formats( array $row ): array { $integer_fields = array( 'created_by', 'updated_by' ); return array_map( static fn( string $field ): string => in_array( $field, $integer_fields, true ) ? '%d' : '%s', array_keys( $row ) ); }
}
