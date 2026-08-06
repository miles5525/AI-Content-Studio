<?php
/** Central, non-sensitive first-run setup state. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class AICS_Setup_Wizard_State_Service {
	public const OPTION='aics_setup_state'; public const REDIRECT_OPTION='aics_setup_redirect'; public const STEPS=array('welcome','ai-provider','featured-images','seo-integration','content-defaults','finish');
	private static function base():array{return array('setup_status'=>'not_started','setup_current_step'=>'welcome','setup_completed_steps'=>array(),'setup_skipped_steps'=>array(),'setup_completed_version'=>'','setup_completed_at'=>'','provider_test'=>array(),'defaults'=>array('tone'=>'professional','article_length'=>'medium','content_format'=>'plain','post_status'=>'draft'),'seo_target'=>'auto');}
	public static function activate(bool $existing,bool $network):void{if(false!==get_option(self::OPTION,false)){return;}self::save($existing?array('setup_status'=>'legacy_configured'):array());if(!$existing&&!$network){add_option(self::REDIRECT_OPTION,time(),'','no');}}
	public static function state():array{$raw=get_option(self::OPTION,array());$s=array_merge(self::base(),is_array($raw)?$raw:array());$s['setup_status']=in_array($s['setup_status'],array('not_started','in_progress','completed','dismissed','legacy_configured'),true)?$s['setup_status']:'not_started';$s['setup_current_step']=in_array($s['setup_current_step'],self::STEPS,true)?$s['setup_current_step']:'welcome';return $s;}
	public static function save(array $changes):array{$s=array_merge(self::state(),$changes);foreach(array('setup_completed_steps','setup_skipped_steps') as $k){$s[$k]=array_values(array_intersect(self::STEPS,is_array($s[$k]??null)?$s[$k]:array()));}false===get_option(self::OPTION,false)?add_option(self::OPTION,$s,'','no'):update_option(self::OPTION,$s,false);return $s;}
	public static function can_open(string $step):bool{$s=self::state();$wanted=array_search($step,self::STEPS,true);$current=array_search($s['setup_current_step'],self::STEPS,true);return false!==$wanted&&('completed'===$s['setup_status']||'legacy_configured'===$s['setup_status']||$wanted<=$current);}
	public static function advance(string $done,string $next,bool $skipped=false):void{$s=self::state();$s['setup_completed_steps'][]=$done;if($skipped){$s['setup_skipped_steps'][]=$done;}self::save(array('setup_status'=>'in_progress','setup_current_step'=>$next,'setup_completed_steps'=>array_unique($s['setup_completed_steps']),'setup_skipped_steps'=>array_unique($s['setup_skipped_steps'])));}
	public static function provider_ready():bool{$t=self::state()['provider_test'];return AICS_Settings::has_openai_api_key()&&is_array($t)&&AICS_Settings::get_openai_model()===($t['model']??'')&&!empty($t['tested_at']);}
	public static function complete():bool{if(!self::provider_ready()){return false;}self::save(array('setup_status'=>'completed','setup_current_step'=>'finish','setup_completed_steps'=>self::STEPS,'setup_completed_version'=>AICS_VERSION,'setup_completed_at'=>current_time('mysql',true)));return true;}
	public static function dismiss():void{self::save(array('setup_status'=>'dismissed'));}
	public static function restart():void{self::save(array('setup_status'=>'in_progress','setup_current_step'=>'welcome','setup_completed_steps'=>array(),'setup_skipped_steps'=>array()));}
	public static function defaults():array{return self::state()['defaults'];}
	private function __construct(){}
}
