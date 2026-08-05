<?php
if(!defined('ABSPATH')){exit;}
final class AICS_Rank_Math_SEO_Adapter extends AICS_Abstract_Postmeta_SEO_Adapter{
	private const META=array('seo_title'=>'rank_math_title','meta_description'=>'rank_math_description','focus_keyword'=>'rank_math_focus_keyword');
	protected function metadata_keys():array{return self::META;}
	protected function normalize_comparison_value(string $value,string $field):string{$value=parent::normalize_comparison_value($value,$field);if('focus_keyword'===$field&&str_contains($value,',')){$value=trim(explode(',',$value,2)[0]);}return $value;}
	public function get_key():string{return 'rank_math';}public function get_label():string{return 'Rank Math SEO';}public function is_available():bool{return defined('RANK_MATH_VERSION')||class_exists('RankMath');}public function get_version():string{return defined('RANK_MATH_VERSION')?(string)RANK_MATH_VERSION:'';}public function get_compatibility_status():string{return $this->is_available()?'compatible':'unavailable';}public function refresh_post_seo_data(int $post_id){clean_post_cache($post_id);do_action('rank_math/updated_post_meta',$post_id);return true;}
}
