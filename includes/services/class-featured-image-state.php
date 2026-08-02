<?php
/** Shared featured-image lifecycle policy. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Featured_Image_State {
	private const STATUSES = array( 'not_requested','pending','generating','uploaded','attached','retrying','failed','needs_attention','skipped' );
	private const TRANSITIONS = array(
		'not_requested'=>array('pending','skipped'),'pending'=>array('generating','skipped','failed'),
		'generating'=>array('uploaded','retrying','failed','needs_attention'),'retrying'=>array('generating','failed','needs_attention'),
		'failed'=>array('pending','needs_attention','skipped'),'needs_attention'=>array('pending','skipped'),
		'uploaded'=>array('attached','retrying','failed','needs_attention'),'attached'=>array(),'skipped'=>array('pending'),
	);
	private const ERRORS = array(
		'image_provider_request_failed'=>'The image provider request failed.','image_provider_timeout'=>'The image provider request timed out.',
		'image_provider_rate_limited'=>'The image provider rate limit was reached.','invalid_image_response'=>'The image provider returned invalid image data.',
		'unsupported_image_format'=>'The generated image format is unsupported.','image_download_failed'=>'The generated image could not be downloaded.',
		'image_file_validation_failed'=>'The image file did not pass validation.','media_library_upload_failed'=>'The image could not be added to the Media Library.',
		'attachment_persistence_failed'=>'The image attachment could not be stored.','featured_image_assignment_failed'=>'The featured image could not be assigned.',
		'featured_image_ownership_conflict'=>'Featured image ownership could not be verified.','image_retry_exhausted'=>'The featured image retry limit was reached.',
	);
	public static function statuses():array{return self::STATUSES;}
	public static function is_supported($status):bool{return is_scalar($status)&&in_array(sanitize_key((string)$status),self::STATUSES,true);}
	public static function normalize($status):string{$value=is_scalar($status)?sanitize_key((string)$status):'';return in_array($value,self::STATUSES,true)?$value:'needs_attention';}
	public static function can_transition($from,$to):bool{if(!self::is_supported($from)||!self::is_supported($to)){return false;}$from=sanitize_key((string)$from);return in_array(sanitize_key((string)$to),self::TRANSITIONS[$from],true);}
	public static function is_terminal($status):bool{return self::is_supported($status)&&in_array(sanitize_key((string)$status),array('attached','skipped'),true);}
	public static function is_retryable($status):bool{return self::is_supported($status)&&in_array(sanitize_key((string)$status),array('retrying','failed','needs_attention'),true);}
	public static function status_label($status):string{$labels=array('not_requested'=>'Not Requested','pending'=>'Pending','generating'=>'Generating','uploaded'=>'Uploaded','attached'=>'Attached','retrying'=>'Retrying','failed'=>'Failed','needs_attention'=>'Needs Attention','skipped'=>'Skipped');$normalized=self::normalize($status);return $labels[$normalized];}
	public static function is_error_supported($code):bool{return is_scalar($code)&&isset(self::ERRORS[sanitize_key((string)$code)]);}
	public static function error_label($code):string{$key=is_scalar($code)?sanitize_key((string)$code):'';return self::ERRORS[$key]??'A featured image operation failed.';}
	private function __construct(){}
}
