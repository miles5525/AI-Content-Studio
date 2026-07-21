<?php
/** Automated idea generation through the existing AI engine. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Idea_Generator {
	private AICS_AI_Engine $engine; private AICS_Content_Idea_Repository $ideas;
	public function __construct(?AICS_AI_Engine $engine=null,?AICS_Content_Idea_Repository $ideas=null){$this->engine=$engine??new AICS_AI_Engine();$this->ideas=$ideas??new AICS_Content_Idea_Repository();}

	public function generate(array $profile,$run_id):array{
		$result=$this->base();$profile_id=absint($profile['id']??0);$run_id=absint($run_id);$settings=is_array($profile['content_settings']??null)?$profile['content_settings']:array();$business=is_array($profile['business_context']??null)?$profile['business_context']:array();$count=absint($settings['ideas_per_cycle']??5);
		if(0===$profile_id||0===$run_id||$count<1||$count>20){$result['code']='invalid_automation_profile';return $result;}
		$result['requested']=$count;
		if(!AICS_Settings::has_openai_api_key()){$result['code']='missing_api_configuration';return $result;}
		$started=microtime(true);$response=$this->engine->generate_automation_ideas($business,$settings);$duration=AICS_Usage_Logger::duration_ms($started);
		if(!$response->is_success()){$code=$this->provider_code($response->get_error_code());$this->log($run_id,false,$code,$duration,0);$result['code']=$code;$result['retryable']=$this->retryable($response->get_error_code());return $result;}
		$data=$response->get_data();
		if(!is_array($data)||!isset($data['ideas'])||!is_array($data['ideas'])){$this->log($run_id,false,'missing_ideas_array',$duration,0);$result['code']='missing_ideas_array';$result['retryable']=true;return $result;}
		$returned=array_slice($data['ideas'],0,$count);$result['received']=count($returned);$accepted=array();$seen=array();$lookback=max(0,min(3650,absint($settings['duplicate_lookback_days']??180)));$since=gmdate('Y-m-d H:i:s',time()-($lookback*DAY_IN_SECONDS));
		foreach($returned as $item){$idea=$this->validate_item($item);if(null===$idea){++$result['invalid_items'];continue;}$fingerprint=$this->ideas->build_fingerprint($idea['title'],$idea['primary_keyword']);if(null===$fingerprint){++$result['invalid_items'];continue;}++$result['valid'];if(isset($seen[$fingerprint])){++$result['batch_duplicates'];continue;}$seen[$fingerprint]=true;if($lookback>0&&null!==$this->ideas->find_recent_duplicate($profile_id,$fingerprint,$since)){++$result['historical_duplicates'];continue;}$accepted[]=$idea;}
		if(empty($accepted)){$code=$result['valid']>0?'no_valid_unique_ideas':'no_valid_ideas';$this->log($run_id,false,$code,$duration,0);$result['code']=$code;$result['retryable']=false;return $result;}
		$batch=$this->ideas->create_many($accepted,array('profile_id'=>$profile_id,'run_id'=>$run_id,'source_type'=>'automation','status'=>'generated','created_by'=>0));$result['created']=absint($batch['created']??0);$result['idea_ids']=array_map('absint',$batch['idea_ids']??array());
		if($result['created']<1){$this->log($run_id,false,'idea_persistence_failed',$duration,0);$result['code']='idea_persistence_failed';$result['retryable']=true;return $result;}
		$result['success']=true;$result['code']='automation_ideas_generated';$this->log($run_id,true,'',$duration,$result['created']);return $result;
	}

	private function validate_item($item):?array{if(!is_array($item)){return null;}$required=array('title','summary','primary_keyword','secondary_keywords','search_intent','suggested_category','outline');foreach($required as $key){if(!array_key_exists($key,$item)){return null;}}if(!is_string($item['title'])||''===trim($item['title'])||!is_string($item['summary'])||!is_string($item['primary_keyword'])||!is_string($item['suggested_category'])||!is_array($item['secondary_keywords'])||!is_array($item['outline'])){return null;}$intent=is_string($item['search_intent'])?sanitize_key($item['search_intent']):'';if(!in_array($intent,array('informational','commercial','transactional','navigational'),true)){return null;}foreach(array_merge($item['secondary_keywords'],$item['outline']) as $value){if(!is_scalar($value)){return null;}}return array('title'=>$item['title'],'summary'=>$item['summary'],'primary_keyword'=>$item['primary_keyword'],'secondary_keywords'=>$item['secondary_keywords'],'search_intent'=>$intent,'suggested_category'=>$item['suggested_category'],'outline'=>$item['outline'],'score'=>0,'priority'=>0);}
	private function provider_code(string $code):string{$map=array('missing-api-key'=>'missing_api_configuration','invalid-api-key'=>'invalid_api_configuration','rate-limit'=>'provider_rate_limit','network-error'=>'provider_network_error','invalid-response'=>'invalid_idea_json','invalid-idea-format'=>'invalid_idea_json','quota-error'=>'provider_quota_error','model-unavailable'=>'provider_model_unavailable');return $map[$code]??'provider_request_failed';}
	private function retryable(string $code):bool{return !in_array($code,array('missing-api-key','invalid-api-key','quota-error','model-unavailable','unsupported-task','invalid-automation-profile'),true);}
	private function log(int $run_id,bool $success,string $code,int $duration,int $count):void{AICS_Usage_Logger::log(array('user_id'=>0,'event_type'=>'ai_request','operation'=>'generate_blog_ideas','status'=>$success?'success':'failed','provider'=>'openai','model'=>AICS_Settings::get_openai_model(),'error_code'=>$code,'object_id'=>$run_id,'duration_ms'=>$duration,'metadata'=>array('idea_count'=>$count,'source'=>'automation')));}
	private function base():array{return array('success'=>false,'code'=>'generation_not_started','retryable'=>false,'requested'=>0,'received'=>0,'valid'=>0,'created'=>0,'batch_duplicates'=>0,'historical_duplicates'=>0,'invalid_items'=>0,'idea_ids'=>array());}
}
