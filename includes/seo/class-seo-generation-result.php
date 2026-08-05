<?php
if(!defined('ABSPATH')){exit;}
final class AICS_SEO_Generation_Result{
	private function __construct(private bool $success,private string $code,private string $message,private ?AICS_SEO_Data $data=null,private array $analysis=array(),private string $provider='',private string $model=''){}
	public static function success(AICS_SEO_Data $data,array $analysis,string $provider,string $model):self{return new self(true,'seo_generated',__('SEO data generated successfully.','ai-content-studio'),$data,$analysis,$provider,$model);}
	public static function failure(string $code,string $message):self{return new self(false,sanitize_key($code),sanitize_text_field($message));}
	public function is_success():bool{return $this->success;}public function get_code():string{return $this->code;}public function get_message():string{return $this->message;}public function get_data():?AICS_SEO_Data{return $this->data;}public function get_analysis():array{return $this->analysis;}public function get_provider():string{return $this->provider;}public function get_model():string{return $this->model;}
}
