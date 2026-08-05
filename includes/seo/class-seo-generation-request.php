<?php
if(!defined('ABSPATH')){exit;}
final class AICS_SEO_Generation_Request{
	private array $data;
	public function __construct(array $article,array $settings,string $target,array $context=array()){
		if(empty($article['id'])||empty($article['title'])||empty($article['content'])||!in_array($target,array('native','yoast','rank_math','aioseo'),true)){throw new InvalidArgumentException('Invalid SEO generation request.');}
		$content=wp_kses_post((string)$article['content']);$this->data=array('article_id'=>absint($article['id']),'article_title'=>substr(sanitize_text_field((string)$article['title']),0,250),'article_excerpt'=>substr(sanitize_textarea_field((string)($article['excerpt']??'')),0,1000),'article_content'=>substr($content,0,40000),'language'=>substr(sanitize_key((string)($context['language']??get_locale())),0,20),'website_name'=>substr(sanitize_text_field((string)($context['website_name']??get_bloginfo('name'))),0,200),'website_description'=>substr(sanitize_textarea_field((string)($context['website_description']??get_bloginfo('description'))),0,1000),'target_audience'=>substr(sanitize_textarea_field((string)($context['target_audience']??'')),0,1000),'target_plugin'=>$target,'seo_instructions'=>substr(sanitize_textarea_field((string)($settings['instructions']??'')),0,3000),'title'=>$settings['title'],'keyword'=>$settings['keyword'],'links'=>$settings['links'],'featured_image_alt'=>substr(sanitize_text_field((string)($article['featured_image_alt_text']??'')),0,250));
	}
	public function all():array{return $this->data;}
}
