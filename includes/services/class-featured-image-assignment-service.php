<?php
/** Safe, idempotent WordPress featured-image assignment. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Featured_Image_Assignment_Service {
	private AICS_Article_Repository $articles;private AICS_Featured_Image_Ownership_Service $ownership;
	public function __construct(?AICS_Article_Repository $articles=null,?AICS_Featured_Image_Ownership_Service $ownership=null){$this->articles=$articles??new AICS_Article_Repository();$this->ownership=$ownership??new AICS_Featured_Image_Ownership_Service();}
	public function assign(array $article,$attachment_id,$actor_id=0):array{$attachment=absint($attachment_id);$post_check=$this->ownership->validate_post($article);if(empty($post_check['success'])){return $this->result(false,$post_check['code'],0);}$post=$post_check['post'];$actor=absint($actor_id);if($actor&& ! user_can($actor,'edit_post',$post->ID)){return $this->result(false,'featured_image_post_invalid',0);}$owned=$this->ownership->validate_attachment($attachment,$article);if(empty($owned['success'])){return $this->result(false,$owned['code'],0);}$current=absint(get_post_thumbnail_id($post->ID));if($current&&$current!==$attachment){return $this->result(false,'featured_image_ownership_conflict',$current);}
		if($current!==$attachment&&!set_post_thumbnail($post->ID,$attachment)){return $this->result(false,'featured_image_assignment_failed',$attachment);}if(absint(get_post_thumbnail_id($post->ID))!==$attachment){return $this->result(false,'featured_image_assignment_failed',$attachment);}
		$saved=$this->articles->associate_featured_image_attachment($article['id'],'uploaded',$attachment,array('alt_text'=>(string)($article['featured_image_alt_text']??'')));if(empty($saved['success'])){return $this->result(false,'attachment_persistence_failed',$attachment);}return $this->result(true,$current===$attachment?'featured_image_already_assigned':'featured_image_assigned',$attachment);}
	private function result(bool $success,string $code,int $attachment):array{return array('success'=>$success,'code'=>$code,'attachment_id'=>$attachment);}
}
