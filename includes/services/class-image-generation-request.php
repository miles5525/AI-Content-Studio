<?php
/** Immutable provider-neutral image generation request. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Image_Generation_Request {
	private const ASPECT_RATIOS = array( 'landscape', 'square', 'portrait' );
	private const QUALITIES = array( 'standard', 'high' );
	private const FORMATS = array( 'png', 'jpeg', 'webp' );
	private const PROMPT_MAXIMUM = 5000;

	private int $article_id;
	private string $prompt;
	private string $aspect_ratio;
	private string $quality;
	private string $output_format;
	private string $provider;
	private string $model;

	private function __construct( int $article_id, string $prompt, string $aspect_ratio, string $quality, string $output_format, string $provider, string $model ) {
		$this->article_id=$article_id;$this->prompt=$prompt;$this->aspect_ratio=$aspect_ratio;$this->quality=$quality;$this->output_format=$output_format;$this->provider=$provider;$this->model=$model;
	}

	/** @return self|WP_Error */
	public static function from_array( array $data ) {
		$article_id=absint($data['article_id']??0);if(array_key_exists('article_id',$data)&&0===$article_id){return new WP_Error('invalid_image_article_id',__('The image article ID is invalid.','ai-content-studio'));}
		$prompt=is_scalar($data['prompt']??null)?trim(sanitize_textarea_field((string)$data['prompt'])):'';if(''===$prompt||self::length($prompt)>self::PROMPT_MAXIMUM){return new WP_Error('invalid_image_prompt',__('The image prompt is missing or too long.','ai-content-studio'));}
		$aspect=self::controlled($data['aspect_ratio']??'',self::ASPECT_RATIOS);$quality=self::controlled($data['quality']??'',self::QUALITIES);$format=self::controlled($data['output_format']??'',self::FORMATS);
		if(''===$aspect){return new WP_Error('invalid_image_aspect_ratio',__('The image aspect ratio is invalid.','ai-content-studio'));}if(''===$quality){return new WP_Error('invalid_image_quality',__('The image quality is invalid.','ai-content-studio'));}if(''===$format){return new WP_Error('invalid_image_output_format',__('The image output format is invalid.','ai-content-studio'));}
		$provider=self::identifier($data['provider']??'',64);$model=self::identifier($data['model']??'',100);if(''===$provider){return new WP_Error('invalid_image_provider',__('The image provider is invalid.','ai-content-studio'));}if(''===$model){return new WP_Error('invalid_image_model',__('The image model is invalid.','ai-content-studio'));}
		return new self($article_id,$prompt,$aspect,$quality,$format,$provider,$model);
	}

	public function get_article_id():int{return $this->article_id;} public function get_prompt():string{return $this->prompt;} public function get_aspect_ratio():string{return $this->aspect_ratio;} public function get_quality():string{return $this->quality;} public function get_output_format():string{return $this->output_format;} public function get_provider():string{return $this->provider;} public function get_model():string{return $this->model;}
	private static function controlled($value,array $allowed):string{$value=is_scalar($value)?sanitize_key((string)$value):'';return in_array($value,$allowed,true)?$value:'';}
	private static function identifier($value,int $max):string{if(!is_scalar($value)){return '';}$value=trim(sanitize_text_field((string)$value));return ''!==$value&&self::length($value)<=$max&&1===preg_match('/^[a-zA-Z0-9._:-]+$/',$value)?$value:'';}
	private static function length(string $value):int{return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value);}
}
