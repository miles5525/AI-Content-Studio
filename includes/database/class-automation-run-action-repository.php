<?php
/** Administrator automation-run action audit persistence. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Run_Action_Repository {
	private const ACTIONS = array( 'retry', 'resume', 'cancel' );

	public function insert( array $action ): bool {
		global $wpdb;
		$type = sanitize_key( (string) ( $action['action_type'] ?? '' ) );
		if ( ! in_array( $type, self::ACTIONS, true ) || 0 === absint( $action['run_id'] ?? 0 ) ) { return false; }
		$reason = sanitize_key( (string) ( $action['reason_code'] ?? '' ) );
		return false !== $wpdb->insert( $this->table(), array(
			'run_id' => absint( $action['run_id'] ), 'action_type' => $type,
			'previous_status' => sanitize_key( (string) $action['previous_status'] ), 'previous_step' => sanitize_key( (string) $action['previous_step'] ),
			'resulting_status' => sanitize_key( (string) $action['resulting_status'] ), 'resulting_step' => sanitize_key( (string) $action['resulting_step'] ),
			'previous_attempt_count' => absint( $action['previous_attempt_count'] ), 'resulting_attempt_count' => absint( $action['resulting_attempt_count'] ),
			'actor_user_id' => absint( $action['actor_user_id'] ) ?: null, 'reason_code' => '' === $reason ? null : $reason,
			'created_at' => current_time( 'mysql', true ),
		), array( '%d','%s','%s','%s','%s','%s','%d','%d','%d','%s','%s' ) );
	}

	public function get_for_run( $run_id, $limit = 20 ): array {
		global $wpdb; $id = absint( $run_id ); $limit = max( 1, min( 50, absint( $limit ) ) );
		if ( 0 === $id ) { return array(); }
		$sql = $wpdb->prepare( "SELECT id,run_id,action_type,previous_status,previous_step,resulting_status,resulting_step,previous_attempt_count,resulting_attempt_count,actor_user_id,reason_code,created_at FROM {$this->table()} WHERE run_id=%d ORDER BY id DESC LIMIT %d", $id, $limit );
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	private function table(): string { global $wpdb; return $wpdb->prefix . 'aics_automation_run_actions'; }
}
