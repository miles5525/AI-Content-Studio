<?php
/** Strict registry for image provider adapters. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Image_Provider_Factory {
	private const PROVIDERS=array('openai'=>'AICS_OpenAI_Image_Provider');
	/** @return AICS_Image_Provider_Interface|WP_Error */
	public static function create($provider_key){$key=is_scalar($provider_key)?sanitize_key((string)$provider_key):'';if(!isset(self::PROVIDERS[$key])){return new WP_Error('unknown_image_provider',__('The selected image provider is not supported.','ai-content-studio'));}$class=self::PROVIDERS[$key];$provider=new $class();return $provider instanceof AICS_Image_Provider_Interface?$provider:new WP_Error('invalid_image_provider_adapter',__('The image provider adapter is invalid.','ai-content-studio'));}
	/** @return array<string,string> */
	public static function provider_options():array{$options=array();foreach(self::PROVIDERS as $key=>$class){$provider=new $class();$options[$key]=$provider->get_display_name();}return $options;}
	private function __construct(){}
}
