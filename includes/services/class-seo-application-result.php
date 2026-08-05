<?php
if(!defined('ABSPATH')){exit;}
final class AICS_SEO_Application_Result{
	public function __construct(private array $data){$this->data=array_merge(array('success'=>false,'adapter_key'=>'','adapter_label'=>'','post_id'=>0,'applied_fields'=>array(),'skipped_fields'=>array(),'verified_fields'=>array(),'warnings'=>array(),'error_code'=>'','applied_at'=>null,'native_applied'=>false,'plugin_applied'=>false,'verification_passed'=>false,'refresh_requested'=>false),$data);}
	public static function success(array $data):self{return new self(array_merge($data,array('success'=>true,'error_code'=>'')));}
	public static function failure(string $code,array $data=array()):self{return new self(array_merge($data,array('success'=>false,'error_code'=>substr(sanitize_key($code),0,100))));}
	public function is_success():bool{return !empty($this->data['success']);}
	public function to_array():array{return $this->data;}
}
