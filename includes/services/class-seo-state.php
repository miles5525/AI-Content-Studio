<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class AICS_SEO_State {
	public const NOT_REQUESTED='not_requested', PENDING='pending', GENERATING='generating', GENERATED='generated', REVIEW_REQUIRED='review_required', APPROVED='approved', APPLIED='applied', RETRYING='retrying', FAILED='failed', NEEDS_ATTENTION='needs_attention', SKIPPED='skipped';
	private const TRANSITIONS=array('not_requested'=>array('pending','skipped'),'pending'=>array('generating','skipped'),'generating'=>array('generated','retrying','failed','needs_attention'),'generated'=>array('pending','review_required','approved'),'review_required'=>array('pending','approved','needs_attention'),'approved'=>array('pending','applied','needs_attention'),'retrying'=>array('generating','failed','needs_attention'),'failed'=>array('pending','retrying','needs_attention','skipped'),'needs_attention'=>array('pending','retrying','skipped'),'applied'=>array(),'skipped'=>array('pending'));
	public static function statuses():array{return array_keys(self::TRANSITIONS);}
	public static function is_valid($status):bool{return is_string($status)&&isset(self::TRANSITIONS[$status]);}
	public static function can_transition($from,$to):bool{return self::is_valid($from)&&self::is_valid($to)&&in_array($to,self::TRANSITIONS[$from],true);}
	public static function is_processing($status):bool{return in_array($status,array(self::PENDING,self::GENERATING,self::RETRYING),true);}
	public static function is_success($status):bool{return in_array($status,array(self::GENERATED,self::APPROVED,self::APPLIED),true);}
	public static function is_failure($status):bool{return in_array($status,array(self::FAILED,self::NEEDS_ATTENTION),true);}
	public static function label($status):string{$labels=array('not_requested'=>__('Not requested','ai-content-studio'),'pending'=>__('Pending','ai-content-studio'),'generating'=>__('Generating','ai-content-studio'),'generated'=>__('Generated','ai-content-studio'),'review_required'=>__('Review required','ai-content-studio'),'approved'=>__('Approved','ai-content-studio'),'applied'=>__('Applied','ai-content-studio'),'retrying'=>__('Retrying','ai-content-studio'),'failed'=>__('Failed','ai-content-studio'),'needs_attention'=>__('Needs attention','ai-content-studio'),'skipped'=>__('Skipped','ai-content-studio'));return $labels[$status]??__('Unknown','ai-content-studio');}
}
