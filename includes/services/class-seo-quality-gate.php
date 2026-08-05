<?php
if(!defined('ABSPATH')){exit;}
final class AICS_SEO_Quality_Gate{
	private const BLOCKING_ANALYSIS_IDS=array('focus_keyword_exists','seo_title_exists','meta_description_exists','slug_structure','word_count','excerpt');
	public function evaluate(array $article,array $settings,bool $applied=false,bool $verified=false):array{
		$q=AICS_SEO_Configuration::quality_gate($settings);$analysis=is_array($article['seo_analysis']??null)?$article['seo_analysis']:array();$raw=$analysis['score']??$analysis['readiness_score']??0;$score=is_numeric($raw)?(float)$raw:0.0;$score=max(0.0,min(100.0,$score));$minimum=(float)$q['minimum_readiness_score'];
		$missing=array();foreach(array('seo_focus_keyword','seo_title','seo_meta_description','seo_slug','excerpt') as $field){if(''===trim((string)($article[$field]??''))){$missing[]=$field;}}if(empty($analysis)||!isset($analysis['score'])){$missing[]='analysis';}
		$analysis_blockers=array();$warnings=is_array($analysis['warnings']??null)?$analysis['warnings']:array();foreach(is_array($analysis['blocking_issues']??null)?$analysis['blocking_issues']:array() as $issue){$id=sanitize_key(is_array($issue)?(string)($issue['id']??''):(string)$issue);if(in_array($id,self::BLOCKING_ANALYSIS_IDS,true)){$analysis_blockers[]=$id;}elseif(''!==$id){$warnings[]=is_array($issue)?$issue:array('id'=>$id,'message'=>$id);}}
		$reasons=array();if($q['require_core_metadata']&&$missing){$reasons[]='seo_core_metadata_missing';}if($q['enabled']&&$score<$minimum){$reasons[]='seo_readiness_below_minimum';}if($analysis_blockers){$reasons[]='seo_blocking_analysis_issue';}if($q['require_successful_application']&&!$applied){$reasons[]='seo_native_application_failed';}if($q['require_adapter_verification']&&!$verified){$reasons[]='seo_adapter_verification_failed';}
		$environment=array();if('0'===(string)get_option('blog_public','1')){$environment[]=array('id'=>'site_noindex','message'=>__('This site discourages search engines from indexing it. This environment notice does not affect article SEO Readiness.','ai-content-studio'));}
		return array('passed'=>empty($reasons),'score'=>(int)round($score),'minimum'=>(int)round($minimum),'missing_fields'=>array_values(array_unique($missing)),'failed_conditions'=>array_values(array_unique($reasons)),'blocking_analysis_ids'=>array_values(array_unique($analysis_blockers)),'warnings'=>array_values($warnings),'environment_notices'=>$environment,'configuration'=>$q);
	}
}
