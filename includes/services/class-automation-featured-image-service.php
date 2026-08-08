<?php
/** Coordinates one immutable-config automation image unit per worker invocation. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Featured_Image_Service {
	private const RETRYABLE = array( 'image_provider_rate_limited','image_provider_timeout','image_provider_request_failed','media_library_upload_failed','featured_image_assignment_failed','temporary_image_creation_failed','attachment_persistence_failed' );
	private AICS_Article_Repository $articles;
	private AICS_Featured_Image_Pipeline_Service $pipeline;
	private AICS_Featured_Image_Ownership_Service $ownership;

	public function __construct(?AICS_Article_Repository $articles=null,?AICS_Featured_Image_Pipeline_Service $pipeline=null,?AICS_Featured_Image_Ownership_Service $ownership=null){$this->articles=$articles??new AICS_Article_Repository();$this->ownership=$ownership??new AICS_Featured_Image_Ownership_Service();$this->pipeline=$pipeline??new AICS_Featured_Image_Pipeline_Service($this->articles);}

	public function process_one(array $run,array $configuration):array{
		$image=AICS_Automation_Featured_Image_Settings::validate(is_array($configuration['featured_image_settings']??null)?$configuration['featured_image_settings']:array());
		if(is_wp_error($image)){return $this->failure('featured_image_configuration_invalid',false,0);}
		if(empty($image['enabled'])){return array('success'=>true,'complete'=>true,'code'=>'featured_images_disabled','article_id'=>0,'retryable'=>false);}
		$articles=$this->articles->get_articles_for_run(absint($run['id']??0),array('profile_id'=>absint($run['profile_id']??0),'limit'=>100,'orderby'=>'created_at','order'=>'ASC'));
		if(!$articles){return $this->failure('run_has_no_articles',false,0);}
		$candidate=null;
		foreach(array('pending','uploaded','retrying','generating') as $state){foreach($articles as $article){if('rejected'!==$article['status']&&$state===$article['featured_image_status']){$candidate=$article;break 2;}}}
		if($candidate){
			if(''===trim((string)($candidate['featured_image_prompt']??''))){$prepared=$this->prepare_prompt($candidate,$configuration);if(empty($prepared['success'])){return $this->failure($prepared['code'],false,$candidate['id']);}$candidate=$this->articles->get_by_id($candidate['id']);}
			if('generating'===$candidate['featured_image_status']){$changed=$this->articles->record_featured_image_error($candidate['id'],'generating','retrying','image_provider_request_failed');if(empty($changed['success'])){return $this->failure('featured_image_state_changed',true,$candidate['id']);}}
			$result=$this->pipeline->run($candidate['id'],0,true,$image);
			if(!empty($result['success'])){return array('success'=>true,'complete'=>false,'code'=>$result['code'],'article_id'=>$candidate['id'],'retryable'=>false);}
			$fresh=$this->articles->get_by_id($candidate['id']);$code=AICS_Featured_Image_State::is_error_supported($result['code']??'')?$result['code']:'image_provider_request_failed';$retryable=in_array($code,self::RETRYABLE,true)&&$fresh&&'retrying'===$fresh['featured_image_status'];
			if(!$retryable&&$fresh&&'retrying'===$fresh['featured_image_status']){$this->articles->record_featured_image_error($fresh['id'],'retrying','needs_attention',$code);$fresh=$this->articles->get_by_id($fresh['id']);}
			if($retryable){return $this->failure($code,true,$candidate['id']);}
			return !empty($image['required'])?$this->failure($this->required_code($code),false,$candidate['id']):array('success'=>true,'complete'=>false,'code'=>'optional_featured_image_failed','article_id'=>$candidate['id'],'retryable'=>false);
		}
		foreach($articles as $article){if('rejected'===$article['status']){continue;}$state=$article['featured_image_status'];if('attached'===$state){$owned=$this->ownership->validate_attachment(absint($article['featured_image_attachment_id']),$article);if(empty($owned['success'])||absint(get_post_thumbnail_id($article['wordpress_post_id']))!==absint($article['featured_image_attachment_id'])){return $this->failure('featured_image_ownership_conflict',false,$article['id']);}continue;}if(!$image['required']&&in_array($state,array('failed','needs_attention','skipped'),true)){continue;}return $this->failure($image['required']?'required_featured_image_failed':'featured_image_state_changed',false,$article['id']);}
		return array('success'=>true,'complete'=>true,'code'=>'featured_images_complete','article_id'=>0,'retryable'=>false);
	}

	public function prepare_prompt(array $article,array $configuration):array{
		$image=AICS_Automation_Featured_Image_Settings::validate(is_array($configuration['featured_image_settings']??null)?$configuration['featured_image_settings']:array());
		if(is_wp_error($image)){return array('success'=>false,'code'=>'featured_image_configuration_invalid');}
		if(empty($image['enabled'])){return array('success'=>true,'code'=>'featured_images_disabled');}
		if(''!==trim((string)($article['featured_image_prompt']??''))){return array('success'=>true,'code'=>'featured_image_prompt_exists');}
		$idea=(new AICS_Content_Idea_Repository())->get_by_id(absint($article['idea_id']??0));$content=is_array($configuration['content_settings']??null)?$configuration['content_settings']:array();
		$prompt=(new AICS_Featured_Image_Prompt_Builder())->build(array('title'=>$article['title'],'excerpt'=>$article['excerpt'],'content'=>$article['content'],'primary_keyword'=>$idea['primary_keyword']??'','seo_focus_keyword'=>$article['seo_focus_keyword']??'','featured_image_instructions'=>$content['featured_image_instructions']??'','aspect_ratio'=>$image['aspect_ratio'],'visual_style'=>$image['visual_style']));
		if(is_wp_error($prompt)){return array('success'=>false,'code'=>$prompt->get_error_code());}
		return $this->articles->update_featured_image_prompt($article['id'],$prompt,0);
	}

	public function delivery_allowed(array $article,array $image):array{
		if(empty($image['enabled'])){return array('success'=>true,'code'=>'featured_images_disabled');}$state=$article['featured_image_status'];if('attached'===$state){$owned=$this->ownership->validate_attachment(absint($article['featured_image_attachment_id']),$article);$valid=!empty($owned['success'])&&absint(get_post_thumbnail_id($article['wordpress_post_id']))===absint($article['featured_image_attachment_id']);return array('success'=>$valid,'code'=>$valid?'featured_image_attached':'featured_image_ownership_conflict');}if(empty($image['required'])&&in_array($state,array('failed','needs_attention','skipped'),true)){return array('success'=>true,'code'=>'optional_featured_image_failed');}return array('success'=>false,'code'=>!empty($image['required'])?'required_featured_image_failed':'featured_image_processing_incomplete');
	}

	private function required_code(string $code):string{return 'featured_image_ownership_conflict'===$code?'featured_image_ownership_conflict':('image_retry_exhausted'===$code?'featured_image_retry_exhausted':'required_featured_image_failed');}
	private function failure(string $code,bool $retryable,int $article):array{return array('success'=>false,'complete'=>false,'code'=>$code,'article_id'=>$article,'retryable'=>$retryable);}
}
