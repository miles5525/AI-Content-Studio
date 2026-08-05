<?php
/** Read-only safe recovery planning for automation runs. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Run_Recovery_Planner {
	private const EXECUTABLE = array( 'pending','generate_ideas','evaluate_ideas','queue_idea','generate_article','create_post','generate_featured_image','generate_seo','apply_seo','schedule_post','publish_post','finalize' );
	private const RETRYABLE = array( 'provider_request_failed','provider_network_error','provider_rate_limit','idea_persistence_failed','idea_transition_failed','article_persistence_failed','article_transition_failed','wordpress_post_creation_failed','wordpress_schedule_failed','wordpress_publish_failed','no_future_publishing_slot','planned_publish_time_save_failed','article_schedule_sync_failed','article_publish_sync_failed','image_provider_rate_limited','image_provider_timeout','image_provider_request_failed','media_library_upload_failed','featured_image_assignment_failed','temporary_image_creation_failed','attachment_persistence_failed','seo_generation_failed','invalid_seo_generation_response','seo_native_application_failed','seo_term_creation_failed','seo_adapter_application_failed','seo_plugin_refresh_failed' );
	private AICS_Automation_Run_Repository $runs;
	private AICS_Content_Idea_Repository $ideas;
	private AICS_Article_Repository $articles;

	public function __construct(){ $this->runs=new AICS_Automation_Run_Repository();$this->ideas=new AICS_Content_Idea_Repository();$this->articles=new AICS_Article_Repository(); }

	public function eligibility(array $run):array{
		$lock=$this->lock_state($run);$out=array('lock_state'=>$lock,'lock_blocks'=>'active'===$lock,'has_posts'=>$this->articles->count_associated_posts_for_run(absint($run['id']),absint($run['profile_id']))>0);
		foreach(array('retry','resume','cancel') as $action){$out[$action]=$this->plan($run,$action);}
		return $out;
	}

	public function plan(array $run,string $action):array{
		if(!in_array($action,array('retry','resume','cancel'),true)){return $this->deny($action,'invalid_action');}
		if('active'===$this->lock_state($run)){return $this->deny($action,'run_lock_active');}
		$status=$run['status']??'';$step=$run['current_step']??'';
		if(in_array($status,array('completed','cancelled'),true)){return $this->deny($action,'run_is_terminal');}
		if('cancel'===$action){return in_array($status,array('queued','retrying','running'),true)&&in_array($step,array_merge(self::EXECUTABLE,array('waiting_idea_approval','waiting_article_approval','waiting_publish_approval')),true)?$this->allow($action,'cancelled',$step,false,'administrator_cancelled'):$this->deny($action,'cancel_not_allowed');}
		if(!$this->configuration_is_safe($run)){return $this->deny($action,'missing_run_configuration');}
		if('retry'===$action){return $this->retry($run);}
		return $this->resume($run);
	}

	private function retry(array $run):array{
		if(!in_array($run['status'],array('retrying','failed'),true)||!in_array($run['current_step'],self::EXECUTABLE,true)){return $this->deny('retry','retry_not_allowed');}
		$error=(string)($run['last_error_code']??'');$articles=$this->articles->get_articles_for_run($run['id'],array('limit'=>100));
		$article_retry=false;foreach($articles as $article){if(in_array($article['status'],array('needs_attention','failed'),true)&&(in_array($article['last_error_code'],self::RETRYABLE,true)||in_array($article['featured_image_last_error_code']??'',self::RETRYABLE,true))){$article_retry=true;break;}}
		if(!in_array($error,self::RETRYABLE,true)&&!$article_retry){return $this->deny('retry','non_retryable_error');}
		$safety=$this->post_safety($run,$articles);if(true!==$safety){return $this->deny('retry',$safety);}
		return $this->allow('retry','queued',$run['current_step'],'failed'===$run['status'],$error?:'retryable_article_error');
	}

	private function resume(array $run):array{
		$status=$run['status'];$step=$run['current_step'];$target=$step;$reason='stale_run_resumed';
		if('running'===$status&&!in_array($step,self::EXECUTABLE,true)){return $this->deny('resume','resume_not_allowed');}
		if('queued'===$status&&'expired'===$this->lock_state($run)&&in_array($step,self::EXECUTABLE,true)){$reason='expired_lock_cleared';}
		elseif('running'!==$status){
			if('queued'!==$status){return $this->deny('resume','resume_not_allowed');}
			if('waiting_idea_approval'===$step){$pending=$this->ideas->count_ideas(array('run_id'=>$run['id'],'status'=>'pending_approval'));$approved=$this->ideas->count_ideas(array('run_id'=>$run['id'],'statuses'=>array('approved','queued','article_generating','article_generated','completed')));if($pending||!$approved){return $this->deny('resume',$pending?'approval_still_pending':'resume_not_allowed');}$target='queue_idea';$reason='idea_approval_completed';}
			elseif('waiting_article_approval'===$step){$pending=$this->articles->count_articles(array('run_id'=>$run['id'],'status'=>'pending_approval'));$approved=$this->articles->count_articles(array('run_id'=>$run['id'],'statuses'=>array('approved','draft_created','scheduled','published')));if($pending||!$approved){return $this->deny('resume',$pending?'approval_still_pending':'resume_not_allowed');}$target='create_post';$reason='article_approval_completed';}
			else{return $this->deny('resume','approval_still_pending');}
		}
		$safety=$this->post_safety(array_merge($run,array('current_step'=>$target)));if(true!==$safety){return $this->deny('resume',$safety);}
		return $this->allow('resume','queued',$target,false,$reason);
	}

	private function post_safety(array $run,?array $articles=null){$step=$run['current_step'];if(!in_array($step,array('create_post','generate_featured_image','schedule_post','publish_post','finalize'),true)){return true;}$articles=$articles??$this->articles->get_articles_for_run($run['id'],array('limit'=>100));if(!$articles){return 'resume_not_allowed';}$delivery=new AICS_Automation_Delivery_Service();foreach($articles as $article){if('rejected'===$article['status']){continue;}$post_id=absint($article['wordpress_post_id']);if(!$post_id){if('create_post'===$step){continue;}return 'ownership_conflict';}$owned=$delivery->validate_article_post_ownership($article);if(empty($owned['success'])){return 'ownership_conflict';}}return true;}
	private function configuration_is_safe(array $run):bool{$profile=(new AICS_Automation_Profile_Repository())->get_by_id(absint($run['profile_id']??0));if(!$profile){return false;}$effective=$this->runs->get_effective_configuration($run,$profile);return !empty($effective['success']);}
	private function lock_state(array $run):string{if(empty($run['has_lock_token'])){return 'absent';}$expires=$run['lock_expires_at']??null;return is_string($expires)&&$expires>current_time('mysql',true)?'active':'expired';}
	private function allow(string $action,string $status,string $step,bool $reset,string $reason):array{return array('allowed'=>true,'action'=>$action,'status'=>$status,'step'=>$step,'reset_attempts'=>$reset,'reason_code'=>$reason,'message_code'=>$action.'_available');}
	private function deny(string $action,string $code):array{return array('allowed'=>false,'action'=>$action,'reason_code'=>$code,'message_code'=>$code);}
}
