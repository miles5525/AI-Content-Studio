<?php
/** Validated generated-image upload through WordPress media APIs. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Media_Library_Image_Service {
	private const MIMES=array('png'=>'image/png','jpeg'=>'image/jpeg','webp'=>'image/webp');

	/** @return array<string,mixed>|WP_Error */
	public function upload(AICS_Temporary_Image_File $temporary,array $article,array $generation,string $alt_text){
		$checked=$this->validate($temporary);if(is_wp_error($checked)){return $checked;}
		$post_id=absint($article['wordpress_post_id']??0);$article_id=absint($article['id']??0);$uuid=sanitize_text_field((string)($article['article_uuid']??''));
		if(!$article_id||!wp_is_uuid($uuid)||!$post_id){return new WP_Error('media_library_upload_failed',__('The generated image does not have a valid article association.','ai-content-studio'));}
		if(!function_exists('media_handle_sideload')){require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';}
		$short=str_replace('-','',substr($uuid,0,13));$name=sanitize_file_name('aics-featured-'.$article_id.'-'.$short.'.'.$checked['format']);
		$file=array('name'=>$name,'type'=>$checked['mime'],'tmp_name'=>$temporary->get_path(),'error'=>0,'size'=>$checked['size']);
		$attachment=media_handle_sideload($file,$post_id,sanitize_text_field((string)($article['title']??'')));
		if(is_wp_error($attachment)){return new WP_Error('media_library_upload_failed',__('The generated image could not be added to the Media Library.','ai-content-studio'));}
		$attachment=absint($attachment);$prompt=is_scalar($generation['prompt']??null)?(string)$generation['prompt']:'';
		$metadata=array('_aics_generated_image'=>'1','_aics_image_source'=>'generated','_aics_article_id'=>(string)$article_id,'_aics_article_uuid'=>$uuid,'_aics_post_id'=>(string)$post_id,'_aics_provider'=>sanitize_key((string)($generation['provider']??'')),'_aics_model'=>sanitize_text_field((string)($generation['model']??'')),'_aics_prompt_hash'=>hash('sha256',$prompt));
		foreach(array('_aics_run_id'=>'run_id','_aics_profile_id'=>'profile_id') as $key=>$field){$related=absint($article[$field]??0);if($related>0){$metadata[$key]=(string)$related;}}
		foreach($metadata as $key=>$value){update_post_meta($attachment,$key,$value);if((string)get_post_meta($attachment,$key,true)!==(string)$value){wp_delete_attachment($attachment,true);return new WP_Error('attachment_persistence_failed',__('The generated attachment ownership could not be stored.','ai-content-studio'));}}
		$alt=$this->alt_text($alt_text,$article);update_post_meta($attachment,'_wp_attachment_image_alt',$alt);if((string)get_post_meta($attachment,'_wp_attachment_image_alt',true)!==$alt){wp_delete_attachment($attachment,true);return new WP_Error('attachment_persistence_failed',__('The generated attachment alt text could not be stored.','ai-content-studio'));}
		return array('success'=>true,'attachment_id'=>$attachment,'mime_type'=>$checked['mime'],'width'=>$checked['width'],'height'=>$checked['height'],'file_size'=>$checked['size'],'output_format'=>$checked['format'],'alt_text'=>$alt);
	}

	private function validate(AICS_Temporary_Image_File $temporary){$path=$temporary->get_path();if(!$temporary->exists()||!is_file($path)){return new WP_Error('image_file_validation_failed',__('The generated temporary image is missing.','ai-content-studio'));}$size=filesize($path);if(false===$size||$size<1||$size>AICS_Temporary_Image_File::MAX_BYTES){return new WP_Error($size>AICS_Temporary_Image_File::MAX_BYTES?'image_file_too_large':'image_file_validation_failed',__('The generated temporary image failed size validation.','ai-content-studio'));}$format=$temporary->get_output_format();if(!isset(self::MIMES[$format])||strtolower(pathinfo($path,PATHINFO_EXTENSION))!==$format){return new WP_Error('unsupported_image_format',__('The generated image extension is not supported.','ai-content-studio'));}$info=@getimagesize($path);if(false===$info||empty($info[0])||empty($info[1])||strtolower((string)($info['mime']??''))!==self::MIMES[$format]||$temporary->get_mime_type()!==self::MIMES[$format]){return new WP_Error('image_file_validation_failed',__('The generated image did not pass Media Library validation.','ai-content-studio'));}return array('size'=>(int)$size,'format'=>$format,'mime'=>self::MIMES[$format],'width'=>(int)$info[0],'height'=>(int)$info[1]);}
	private function alt_text(string $value,array $article):string{$value=trim(preg_replace('/\s+/u',' ',wp_strip_all_tags($value,true))??'');if(''===$value){$title=trim(sanitize_text_field((string)($article['title']??'')));$value=sprintf(__('Featured image for %s','ai-content-studio'),$title);}return function_exists('mb_substr')?mb_substr($value,0,250,'UTF-8'):substr($value,0,250);}
}
