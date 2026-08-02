<?php
/** Transactional administrator automation-run controls. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Run_Control_Service {
	private AICS_Automation_Run_Repository $runs; private AICS_Automation_Run_Recovery_Planner $planner; private AICS_Automation_Run_Action_Repository $actions;
	public function __construct(){ $this->runs=new AICS_Automation_Run_Repository();$this->planner=new AICS_Automation_Run_Recovery_Planner();$this->actions=new AICS_Automation_Run_Action_Repository(); }

	public function execute($run_id,string $action,$actor_user_id,array $expected){
		global $wpdb;$id=absint($run_id);$actor=absint($actor_user_id);
		if(!$id){return new WP_Error('invalid_run');}if(!in_array($action,array('retry','resume','cancel'),true)){return new WP_Error('invalid_action');}if(!$actor){return new WP_Error('permission_denied');}
		$run=$this->runs->get_run_for_control($id);if(!$run){return new WP_Error('invalid_run');}
		if((string)($expected['status']??'')!==$run['status']||(string)($expected['step']??'')!==$run['current_step']||(string)($expected['updated_at']??'')!==$run['updated_at']){return new WP_Error('run_state_changed');}
		$plan=$this->planner->plan($run,$action);if(empty($plan['allowed'])){return new WP_Error($plan['reason_code']??$action.'_not_allowed');}
		$wpdb->query('START TRANSACTION');
		if(!$this->runs->atomic_control_transition($run,$plan)){$wpdb->query('ROLLBACK');$fresh=$this->runs->get_run_for_control($id);if($fresh&&'cancel'!==$action&&$fresh['active_profile_key']!==$fresh['profile_id']){$other=$this->runs->get_active_run_for_profile($fresh['profile_id']);if($other&&$other['id']!==$id){return new WP_Error('another_profile_run_active');}}return new WP_Error('run_state_changed');}
		if('retry'===$action&&'generate_article'===$plan['step']&&!(new AICS_Article_Repository())->prepare_for_administrator_retry($id,array('provider_request_failed','provider_network_error','provider_rate_limit','article_persistence_failed','article_transition_failed'))){$wpdb->query('ROLLBACK');return new WP_Error('run_transition_failed');}
		$result_attempts=!empty($plan['reset_attempts'])?0:$run['attempt_count'];
		$logged=$this->actions->insert(array('run_id'=>$id,'action_type'=>$action,'previous_status'=>$run['status'],'previous_step'=>$run['current_step'],'resulting_status'=>$plan['status'],'resulting_step'=>$plan['step'],'previous_attempt_count'=>$run['attempt_count'],'resulting_attempt_count'=>$result_attempts,'actor_user_id'=>$actor,'reason_code'=>$plan['reason_code']));
		if(!$logged){$wpdb->query('ROLLBACK');return new WP_Error('action_audit_failed');}
		if(false===$wpdb->query('COMMIT')){$wpdb->query('ROLLBACK');return new WP_Error('run_transition_failed');}
		return array('success'=>true,'code'=>array('retry'=>'run_retry_queued','resume'=>'run_resumed','cancel'=>'run_cancelled')[$action],'run_id'=>$id);
	}
}
