<?php
/** OpenAI image provider adapter structure. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_OpenAI_Image_Provider implements AICS_Image_Provider_Interface {
	public const PROVIDER_KEY='openai'; public const DEFAULT_MODEL='gpt-image-2'; private const MODELS=array('gpt-image-2');
	public function get_provider_key():string{return self::PROVIDER_KEY;} public function get_display_name():string{return 'OpenAI';}
	public function is_configured():bool{return AICS_Settings::has_openai_api_key();}
	public function validate_configuration():array{$settings=AICS_Featured_Image_Settings::get_effective();if(!$this->is_configured()){return $this->validation(false,'image_provider_credentials_missing',__('Provider credentials are missing.','ai-content-studio'));}if(self::PROVIDER_KEY!==$settings['provider']||!in_array($settings['model'],self::MODELS,true)){return $this->validation(false,'image_provider_model_invalid',__('The selected image model is not configured.','ai-content-studio'));}return $this->validation(true,'image_provider_configured',__('Ready for image generation.','ai-content-studio'));}
	public function get_capabilities():array{return array('supported_models'=>self::MODELS,'default_model'=>self::DEFAULT_MODEL,'supported_aspect_ratios'=>array('landscape','square','portrait'),'supported_quality_levels'=>array('standard','high'),'supported_output_formats'=>array('png','jpeg','webp'),'supports_prompt_revision'=>true,'supports_base64_output'=>true,'supports_url_output'=>false);}
	public function generate(AICS_Image_Generation_Request $request):AICS_Image_Generation_Result{return AICS_Image_Generation_Result::failure('image_generation_not_implemented',__('Image generation is not implemented yet.','ai-content-studio'),self::PROVIDER_KEY,false,0,$request->get_model());}
	private function validation(bool $success,string $code,string $message):array{return array('success'=>$success,'code'=>$code,'message'=>$message);}
}
