<?php
/** Read-only AICS generated-image ownership checks. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Featured_Image_Ownership_Service {
	public function validate_attachment( $attachment_id, array $article ): array {
		$id=absint($attachment_id);$post=$id?get_post($id):null;
		if(!$post instanceof WP_Post||'attachment'!==$post->post_type||!wp_attachment_is_image($id)){return $this->result(false,'featured_image_attachment_missing',0);}
		if('1'!==(string)get_post_meta($id,'_aics_generated_image',true)||'generated'!==(string)get_post_meta($id,'_aics_image_source',true)){return $this->result(false,'featured_image_ownership_conflict',$id);}
		if(absint(get_post_meta($id,'_aics_article_id',true))!==absint($article['id']??0)||sanitize_text_field((string)get_post_meta($id,'_aics_article_uuid',true))!==sanitize_text_field((string)($article['article_uuid']??''))){return $this->result(false,'featured_image_ownership_conflict',$id);}
		foreach(array('_aics_run_id'=>'run_id','_aics_profile_id'=>'profile_id','_aics_post_id'=>'wordpress_post_id') as $meta=>$field){$expected=absint($article[$field]??0);$stored=absint(get_post_meta($id,$meta,true));if($expected>0&&$stored!==$expected){return $this->result(false,'featured_image_ownership_incomplete',$id);}}
		return $this->result(true,'featured_image_attachment_owned',$id);
	}

	public function find_for_article( array $article ): array {
		$stored=absint($article['featured_image_attachment_id']??0);
		if($stored){return $this->validate_attachment($stored,$article);}
		$ids=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','fields'=>'ids','posts_per_page'=>2,'no_found_rows'=>true,'meta_query'=>array('relation'=>'AND',array('key'=>'_aics_generated_image','value'=>'1'),array('key'=>'_aics_article_id','value'=>(string)absint($article['id']??0)))));
		if(!$ids){return $this->result(false,'featured_image_attachment_not_found',0);}
		if(count($ids)>1){return $this->result(false,'featured_image_ownership_conflict',0);}
		return $this->validate_attachment(absint($ids[0]),$article);
	}

	public function validate_post( array $article ): array {
		$post_id=absint($article['wordpress_post_id']??0);$post=$post_id?get_post($post_id):null;
		if(!$post instanceof WP_Post||'post'!==$post->post_type||'trash'===$post->post_status||!in_array($post->post_status,array('draft','pending','future','publish'),true)){return $this->result(false,'featured_image_post_invalid',0);}
		if('1'!==(string)get_post_meta($post_id,'_aics_generated_post',true)||absint(get_post_meta($post_id,'_aics_article_id',true))!==absint($article['id']??0)||sanitize_text_field((string)get_post_meta($post_id,'_aics_article_uuid',true))!==sanitize_text_field((string)($article['article_uuid']??''))){return $this->result(false,'featured_image_ownership_conflict',0);}
		return array('success'=>true,'code'=>'featured_image_post_owned','attachment_id'=>0,'post'=>$post);
	}

	private function result(bool $success,string $code,int $attachment):array{return array('success'=>$success,'code'=>$code,'attachment_id'=>$attachment);}
}
