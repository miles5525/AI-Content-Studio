<?php
/**
 * Queues due automation profiles without executing workflow steps.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AICS_Automation_Dispatcher {
	public const LOCK_OPTION = 'aics_automation_dispatcher_lock';
	private const DEFAULT_BATCH_SIZE = 10;
	private const MAX_BATCH_SIZE = 50;
	private const LOCK_TTL = 240;

	/** Dispatches one bounded batch and returns non-sensitive statistics. */
	public function dispatch( $batch_size = self::DEFAULT_BATCH_SIZE ): array {
		$result = $this->empty_result();
		$token  = $this->acquire_lock();

		if ( null === $token ) {
			$result['code'] = 'dispatcher_already_running';
			return $result;
		}

		try {
			$profiles = new AICS_Automation_Profile_Repository();
			$runs     = new AICS_Automation_Run_Repository();
			if ( ! $profiles->table_exists() || ! $runs->table_exists() ) {
				$result['code'] = 'database_tables_missing';
				return $result;
			}

			$now      = current_time( 'mysql', true );
			$recovery = $runs->recover_expired_locks( $now, 20 );
			if ( ! ( $recovery['success'] ?? false ) ) {
				$result['code'] = 'expired_lock_recovery_failed';
				return $result;
			}
			$result['expired_locks_requeued'] = absint( $recovery['recovered'] ?? 0 );
			$result['expired_locks_failed']   = absint( $recovery['failed'] ?? 0 );

			$limit = max( 1, min( self::MAX_BATCH_SIZE, absint( $batch_size ) ?: self::DEFAULT_BATCH_SIZE ) );
			global $wpdb;
			$wpdb->last_error = '';
			$due = $profiles->get_due_profiles( $now, $limit );
			if ( '' !== $wpdb->last_error ) {
				$result['code'] = 'due_profile_query_failed';
				return $result;
			}
			$result['due_profiles'] = count( $due );
			$calculator             = new AICS_Schedule_Calculator();
			$profile_service        = new AICS_Automation_Profile_Service( $profiles, $calculator );

			foreach ( $due as $profile ) {
				$profile_id = absint( $profile['id'] ?? 0 );
				$fresh      = $profiles->get_by_id( $profile_id );
				if ( ! $fresh || 'active' !== $fresh['status'] || empty( $fresh['next_run_at'] ) || $fresh['next_run_at'] > $now ) {
					++$result['profiles_failed'];
					continue;
				}
				if ( $runs->get_active_run_for_profile( $profile_id ) ) {
					++$result['active_runs_skipped'];
					continue;
				}

				$next        = $calculator->calculate_next_run( $fresh['schedule_settings'], $now );
				$no_future   = ! ( $next['success'] ?? false ) && 'schedule_has_no_future_run' === ( $next['code'] ?? '' );
				if ( ! ( $next['success'] ?? false ) && ! $no_future ) {
					++$result['profiles_failed'];
					continue;
				}

				$validation_input            = $fresh;
				$validation_input['enabled'] = 'active' === $fresh['status'];
				$validated                   = $profile_service->validate( $validation_input );
				if ( empty( $validated['success'] ) ) {
					if ( in_array( 'featured_image_configuration_invalid', $validated['errors'] ?? array(), true ) ) {
						$profiles->update_runtime_fields( $profile_id, array( 'last_error_code'=>'featured_image_configuration_invalid', 'updated_by'=>0 ) );
						$result['code'] = 'featured_image_configuration_invalid';
					} else {
						$result['code'] = 'run_configuration_snapshot_failed';
					}
					++$result['profiles_failed'];
					continue;
				}
				$normalized = $validated['data'];
				$image = AICS_Automation_Featured_Image_Settings::resolve_for_run( $normalized['content_settings']['featured_images'] ?? array() );
				if ( is_wp_error( $image ) ) {
					$profiles->update_runtime_fields( $profile_id, array( 'last_error_code'=>'featured_image_configuration_invalid', 'updated_by'=>0 ) );
					++$result['profiles_failed'];
					$result['code'] = 'featured_image_configuration_invalid';
					continue;
				}
				$snapshot_content = $normalized['content_settings'];
				unset( $snapshot_content['featured_images'] );
				$snapshot   = array(
					'snapshot_version'    => 1,
					'mode'                => $normalized['mode'],
					'business_context'    => $normalized['business_context'],
					'content_settings'    => $snapshot_content,
					'schedule_settings'   => $normalized['schedule_settings'],
					'workflow_rules'      => $normalized['workflow_rules'],
					'publishing_settings' => $normalized['publishing_settings'],
					'featured_image_settings' => $image,
				);
				$snapshot_json = wp_json_encode( $snapshot );
				if ( ! is_string( $snapshot_json ) || '' === $snapshot_json ) {
					++$result['profiles_failed'];
					$result['code'] = 'run_configuration_snapshot_failed';
					continue;
				}

				$created = $runs->create_run( $profile_id, array( 'trigger_type' => 'scheduled', 'configuration_snapshot' => $snapshot_json ) );
				if ( ! ( $created['success'] ?? false ) ) {
					if ( 'active_run_exists' === ( $created['code'] ?? '' ) ) {
						++$result['active_runs_skipped'];
					} else {
						++$result['profiles_failed'];
					}
					continue;
				}

				$advanced = $profiles->update_runtime_fields(
					$profile_id,
					array( 'next_run_at' => $no_future ? null : $next['next_run_utc'], 'last_error_code'=>'', 'updated_by' => 0 )
				);
				if ( ! ( $advanced['success'] ?? false ) ) {
					$runs->mark_cancelled( absint( $created['run_id'] ?? 0 ) );
					++$result['profiles_failed'];
					continue;
				}
				++$result['runs_created'];
				if ( $no_future ) {
					++$result['no_future_runs'];
				}
			}

			$result['success'] = true;
			$result['code']    = 'dispatch_completed';
			return $result;
		} catch ( Throwable $exception ) {
			$result['code'] = 'unexpected_dispatch_error';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'AI Content Studio dispatcher stopped with controlled code: unexpected_dispatch_error' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return $result;
		} finally {
			$this->release_lock( $token );
		}
	}

	private function acquire_lock(): ?string {
		$token = wp_generate_password( 64, false, false );
		if ( 64 !== strlen( $token ) ) {
			return null;
		}
		$lock = array( 'token' => $token, 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::LOCK_TTL ) );
		if ( add_option( self::LOCK_OPTION, $lock, '', false ) ) {
			return $token;
		}

		$existing = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $existing ) && isset( $existing['expires_at'] ) && (string) $existing['expires_at'] <= current_time( 'mysql', true ) && $existing === get_option( self::LOCK_OPTION, array() ) ) {
			delete_option( self::LOCK_OPTION );
			return add_option( self::LOCK_OPTION, $lock, '', false ) ? $token : null;
		}
		return null;
	}

	private function release_lock( string $token ): void {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	private function empty_result(): array {
		return array( 'success' => false, 'code' => 'dispatch_not_started', 'due_profiles' => 0, 'runs_created' => 0, 'active_runs_skipped' => 0, 'profiles_failed' => 0, 'no_future_runs' => 0, 'expired_locks_requeued' => 0, 'expired_locks_failed' => 0 );
	}
}
