<?php
if(!defined('ABSPATH')){exit;}
final class AICS_External_Link_Recommendation_Service{
	public function __construct(private ?AICS_External_Link_Validation_Service $validator=null){$this->validator=$validator??new AICS_External_Link_Validation_Service();}
	public function recommend(array $article,array $settings,int $maximum):array{
		$maximum=max(0,min(10,$maximum));$raw=is_array($settings['trusted_external_urls']??null)?$settings['trusted_external_urls']:array();$urls=array_slice(array_values(array_unique(array_filter(array_map('strval',$raw)))),0,20);$domains=array_map('strtolower',array_map('strval',is_array($settings['trusted_external_domains']??null)?$settings['trusted_external_domains']:array()));$out=array();
		foreach($urls as $url){if(count($out)>=$maximum){break;}$verified=$this->validator->validate($url,true);if(is_wp_error($verified)){continue;}if($domains&&!in_array($verified['host'],$domains,true)){continue;}$anchor=$this->anchor($verified['host'],(string)$article['content']);if(''===$anchor){continue;}$out[]=array('verified_domain'=>$verified['host'],'verified_url'=>$verified['url'],'anchor_text'=>$anchor,'verification_status'=>'verified','http_status'=>$verified['status_code'],'verified_at'=>$verified['verified_at'],'insertion_context'=>'paragraph','status'=>'recommended','reason'=>'trusted_source_verified');}return $out;
	}
	private function anchor(string $host,string $content):string{$base=preg_replace('/^www\./','',$host);$term=strtok($base,'.');return $term&&preg_match('/(?<![\pL\pN])'.preg_quote($term,'/').'(?![\pL\pN])/iu',wp_strip_all_tags($content))?$term:'';}
}
