<?php
/** Allowlisted Settings workspace sections. @package AIContentStudio */
if(!defined('ABSPATH')){exit;}
final class AICS_Settings_Section_Registry{
	public static function all():array{$cap=\AIContentStudio\Core\Permissions::manage();return array(
		'general'=>array('label'=>__('General','ai-content-studio'),'description'=>__('Configure AI providers, featured images, and plugin defaults.','ai-content-studio'),'capability'=>$cap,'renderer'=>array('AICS_Settings_Page','render_general')),
		'setup'=>array('label'=>__('Getting Started','ai-content-studio'),'description'=>__('Review or resume the guided setup wizard.','ai-content-studio'),'capability'=>$cap,'renderer'=>array('AICS_Setup_Wizard_Page','render_embedded')),
		'automation-runs'=>array('label'=>__('Automation Runs','ai-content-studio'),'description'=>__('Review automation cycles, investigate issues, and use safe recovery actions.','ai-content-studio'),'capability'=>$cap,'renderer'=>array('AICS_Automation_Runs_Page','render')),
		'content-history'=>array('label'=>__('Content History','ai-content-studio'),'description'=>__('Search and review WordPress posts created by AI Content Studio.','ai-content-studio'),'capability'=>$cap,'renderer'=>array('AICS_Content_History_Page','render')),
		'system-status'=>array('label'=>__('System Status','ai-content-studio'),'description'=>__('Review configuration, compatibility, and environment checks.','ai-content-studio'),'capability'=>$cap,'renderer'=>array('AICS_System_Status_Page','render')),
	);}
	public static function active($value=null):string{$key=sanitize_key(is_scalar($value)?(string)$value:(string)($_GET['section']??'general'));return isset(self::all()[$key])?$key:'general';}
	public static function get(string $key):array{return self::all()[self::active($key)];}
}
