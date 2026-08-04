<?php
/**
 * Automation-profile application validation.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Profile_Service {
	private const SLUG = 'default';
	private const TONES = array( 'professional', 'friendly', 'conversational', 'informative', 'persuasive' );
	private const WEEKDAYS = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

	private AICS_Automation_Profile_Repository $repository;
	private AICS_Schedule_Calculator $calculator;

	public function __construct( ?AICS_Automation_Profile_Repository $repository = null, ?AICS_Schedule_Calculator $calculator = null ) {
		$this->repository = $repository ?? new AICS_Automation_Profile_Repository();
		$this->calculator = $calculator ?? new AICS_Schedule_Calculator();
	}

	/** Returns saved configuration or safe unsaved defaults. */
	public function get_default_profile(): array {
		return $this->repository->get_by_slug( self::SLUG ) ?? self::defaults();
	}

	/** Validates and creates or updates the single default profile. */
	public function save( array $input ): array {
		$validated = $this->validate( $input );
		if ( ! $validated['success'] ) {
			return $validated;
		}

		$existing = $this->repository->get_by_slug( self::SLUG );
		$data     = $validated['data'];
		if ( null === $existing ) {
			$data['profile_slug'] = self::SLUG;
			$result = $this->repository->create( $data );
			$code   = 'automation_profile_created';
		} else {
			$result = $this->repository->update( $existing['id'], $data );
			$code   = 'automation_profile_updated';
		}

		if ( ! $result['success'] ) {
			return self::result( false, 0, 'database_save_failed', array( 'database_save_failed' ), $data );
		}

		$profile_id = (int) $result['profile_id'];
		if ( 'disabled' === $data['status'] ) {
			$runtime = $this->repository->update_runtime_fields( $profile_id, array( 'next_run_at'=>null, 'updated_by'=>get_current_user_id() ) );
			return $runtime['success'] ? self::result( true, $profile_id, 'automation_saved_disabled', array(), $data ) : self::result( true, $profile_id, 'schedule_persistence_failed', array(), $data );
		}

		$calculation = $this->calculator->calculate_next_run( $data['schedule_settings'] );
		$next_run    = $calculation['success'] ? $calculation['next_run_utc'] : null;
		$runtime     = $this->repository->update_runtime_fields( $profile_id, array( 'next_run_at'=>$next_run, 'updated_by'=>get_current_user_id() ) );
		if ( ! $runtime['success'] ) { return self::result( true, $profile_id, 'schedule_persistence_failed', array(), $data ); }
		if ( ! $calculation['success'] ) { return self::result( true, $profile_id, $calculation['code'], array(), $data ); }
		return self::result( true, $profile_id, 'automation_saved_next_run', array(), $data );
	}

	/** Validates administrator input without reading request globals. */
	public function validate( array $input ): array {
		$errors = array();
		$name   = self::text( $input['profile_name'] ?? '' );
		if ( '' === $name || self::length( $name ) > 191 ) { $errors[] = 'invalid_automation_name'; }

		$mode = self::key( $input['mode'] ?? '' );
		if ( ! in_array( $mode, array( 'autopilot', 'approval' ), true ) ) { $errors[] = 'invalid_mode'; $mode = 'autopilot'; }
		$status = self::boolean( $input['enabled'] ?? false ) ? 'active' : 'disabled';

		$business = $this->business( is_array( $input['business_context'] ?? null ) ? $input['business_context'] : array(), $errors );
		$content  = $this->content( is_array( $input['content_settings'] ?? null ) ? $input['content_settings'] : array(), $errors );
		$image_input = is_array( $input['featured_image_settings'] ?? null ) ? $input['featured_image_settings'] : ( is_array( $input['content_settings']['featured_images'] ?? null ) ? $input['content_settings']['featured_images'] : array() );
		$image    = AICS_Automation_Featured_Image_Settings::validate( $image_input );
		if ( is_wp_error( $image ) ) { $errors[] = $image->get_error_code(); $image = AICS_Automation_Featured_Image_Settings::defaults(); }
		elseif ( $image['enabled'] && is_wp_error( AICS_Automation_Featured_Image_Settings::resolve_for_run( $image ) ) ) { $errors[] = 'featured_image_configuration_invalid'; }
		$content['featured_images'] = $image;
		$seo_input=is_array($input['seo_settings']??null)?$input['seo_settings']:(is_array($input['content_settings']['seo']??null)?$input['content_settings']['seo']:array());
		$seo=AICS_SEO_Configuration::validate($seo_input,false);if(is_wp_error($seo)){$errors[]=$seo->get_error_code();$seo=AICS_SEO_Configuration::defaults(false);}$content['seo']=$seo;
		$schedule = $this->schedule( is_array( $input['schedule_settings'] ?? null ) ? $input['schedule_settings'] : array(), $errors );
		$rules    = $this->rules( is_array( $input['workflow_rules'] ?? null ) ? $input['workflow_rules'] : array(), $mode, $errors );
		$publish  = $this->publishing( is_array( $input['publishing_settings'] ?? null ) ? $input['publishing_settings'] : array(), $errors );

		$data = array(
			'profile_name'        => self::cut( $name, 191 ),
			'mode'                => $mode,
			'status'              => $status,
			'business_context'    => $business,
			'content_settings'    => $content,
			'schedule_settings'   => $schedule,
			'workflow_rules'      => $rules,
			'publishing_settings' => $publish,
			'updated_by'          => get_current_user_id(),
		);

		return self::result( empty( $errors ), 0, empty( $errors ) ? 'valid' : $errors[0], array_values( array_unique( $errors ) ), $data );
	}

	public static function defaults(): array {
		$user_id = get_current_user_id();
		$category_id = absint( get_option( 'default_category', 0 ) );
		return array(
			'id' => 0, 'profile_slug' => self::SLUG, 'profile_name' => __( 'Default Automation', 'ai-content-studio' ), 'mode' => 'autopilot', 'status' => 'disabled',
			'business_context' => array( 'business_name'=>'', 'business_description'=>'', 'industry'=>'', 'products_services'=>'', 'target_audience'=>'', 'primary_location'=>'', 'website_purpose'=>'', 'brand_voice'=>'', 'preferred_tone'=>'professional', 'core_topics'=>array(), 'topics_to_avoid'=>array(), 'preferred_cta'=>'', 'prohibited_claims'=>array() ),
			'content_settings' => array( 'ideas_per_cycle'=>5, 'selected_ideas_per_cycle'=>1, 'default_tone'=>'professional', 'article_length'=>'medium', 'duplicate_lookback_days'=>180, 'include_faq'=>false, 'allow_tables'=>false, 'allow_lists'=>true, 'idea_instructions'=>'','article_instructions'=>'','featured_image_instructions'=>'','featured_images'=>AICS_Automation_Featured_Image_Settings::defaults(), 'seo'=>AICS_SEO_Configuration::defaults(false) ),
			'schedule_settings' => array( 'frequency'=>'weekly', 'interval'=>1, 'days_of_week'=>array( 'monday' ), 'publish_time'=>'10:00', 'posts_per_period'=>1, 'start_date'=>'', 'end_date'=>'', 'monthly_day'=>(int)current_datetime()->format('j') ),
			'workflow_rules' => array( 'require_idea_approval'=>false, 'require_article_approval'=>false, 'require_publish_approval'=>false ),
			'publishing_settings' => array( 'publishing_mode'=>'draft', 'post_status_after_generation'=>'draft', 'category_id'=>$category_id, 'author_id'=>$user_id ),
			'next_run_at'=>null, 'last_run_at'=>null, 'last_error_code'=>'', 'created_by'=>0, 'updated_by'=>0, 'created_at'=>'', 'updated_at'=>'',
		);
	}

	private function business( array $input, array &$errors ): array {
		$limits = array( 'business_name'=>191, 'business_description'=>5000, 'industry'=>191, 'products_services'=>5000, 'target_audience'=>3000, 'primary_location'=>191, 'website_purpose'=>3000, 'brand_voice'=>3000, 'preferred_cta'=>2000 );
		$out = array();
		foreach ( $limits as $key => $limit ) {
			$value = in_array( $key, array( 'business_description','products_services','target_audience','website_purpose','brand_voice','preferred_cta' ), true ) ? self::textarea( $input[$key] ?? '' ) : self::text( $input[$key] ?? '' );
			if ( self::length( $value ) > $limit ) { $errors[] = 'invalid_business_context'; }
			$out[$key] = self::cut( $value, $limit );
		}
		$tone = self::key( $input['preferred_tone'] ?? 'professional' );
		if ( ! in_array( $tone, self::TONES, true ) ) { $errors[] = 'invalid_business_context'; $tone = 'professional'; }
		$out['preferred_tone'] = $tone;
		foreach ( array( 'core_topics','topics_to_avoid','prohibited_claims' ) as $key ) { $out[$key] = self::lines( $input[$key] ?? '', $errors ); }
		return $out;
	}

	private function content( array $input, array &$errors ): array {
		$ideas = self::strict_int( $input['ideas_per_cycle'] ?? 5, 1, 20 );
		$selected = self::strict_int( $input['selected_ideas_per_cycle'] ?? 1, 1, 20 );
		$lookback = self::strict_int( $input['duplicate_lookback_days'] ?? 180, 0, 3650 );
		if ( null === $ideas || null === $selected || null === $lookback ) { $errors[] = 'invalid_content_settings'; }
		$ideas = $ideas ?? 5; $selected = $selected ?? 1; $lookback = $lookback ?? 180;
		if ( $selected > $ideas ) { $errors[] = 'selected_ideas_exceed_generated'; }
		$tone = self::key( $input['default_tone'] ?? 'professional' ); $length = self::key( $input['article_length'] ?? 'medium' );
		if ( ! in_array( $tone, self::TONES, true ) || ! in_array( $length, array('short','medium','long'), true ) ) { $errors[] = 'invalid_content_settings'; }
		return array( 'ideas_per_cycle'=>$ideas, 'selected_ideas_per_cycle'=>min($selected,$ideas), 'default_tone'=>in_array($tone,self::TONES,true)?$tone:'professional', 'article_length'=>in_array($length,array('short','medium','long'),true)?$length:'medium', 'duplicate_lookback_days'=>$lookback, 'include_faq'=>self::boolean($input['include_faq']??false), 'allow_tables'=>self::boolean($input['allow_tables']??false), 'allow_lists'=>self::boolean($input['allow_lists']??false), 'idea_instructions'=>self::cut(self::textarea($input['idea_instructions']??''),3000), 'article_instructions'=>self::cut(self::textarea($input['article_instructions']??''),3000), 'featured_image_instructions'=>self::cut(self::textarea($input['featured_image_instructions']??''),3000) );
	}

	private function schedule( array $input, array &$errors ): array {
		$frequency = self::key( $input['frequency'] ?? 'weekly' );
		$interval = self::strict_int( $input['interval'] ?? 1, 1, 31 ); $posts = self::strict_int( $input['posts_per_period'] ?? 1, 1, 31 ); $monthly = self::strict_int( $input['monthly_day'] ?? current_datetime()->format('j'), 1, 31 );
		if ( ! in_array($frequency,array('daily','weekly','monthly'),true) || null===$interval || null===$posts ) { $errors[]='invalid_schedule_settings'; }
		if ( 'monthly' === $frequency && null === $monthly ) { $errors[]='invalid_monthly_day'; }
		$frequency=in_array($frequency,array('daily','weekly','monthly'),true)?$frequency:'weekly';
		$submitted=is_array($input['days_of_week']??null)?array_map(array(self::class,'key'),array_filter($input['days_of_week'],'is_scalar')):array(); $days=array_values(array_intersect(self::WEEKDAYS,$submitted));
		if ('weekly'===$frequency && empty($days)) { $errors[]='weekly_days_required'; }
		if ('weekly'!==$frequency) { $days=array(); }
		$time=is_scalar($input['publish_time']??null)?(string)$input['publish_time']:''; if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time)){ $errors[]='invalid_schedule_settings'; $time='10:00'; }
		$start=self::date($input['start_date']??''); $end=self::date($input['end_date']??'');
		$raw_start=is_scalar($input['start_date']??null)?(string)$input['start_date']:''; $raw_end=is_scalar($input['end_date']??null)?(string)$input['end_date']:'';
		if ((''!==$raw_start && ''===$start) || (''!==$raw_end && ''===$end)) { $errors[]='invalid_schedule_settings'; }
		if (''!==$start && ''!==$end && $end<$start) { $errors[]='end_date_before_start'; }
		return array('frequency'=>$frequency,'interval'=>$interval??1,'days_of_week'=>$days,'publish_time'=>$time,'posts_per_period'=>$posts??1,'start_date'=>$start,'end_date'=>$end,'monthly_day'=>$monthly??1);
	}

	private function rules( array $input, string $mode, array &$errors ): array {
		$rules=array('require_idea_approval'=>self::boolean($input['require_idea_approval']??false),'require_article_approval'=>self::boolean($input['require_article_approval']??false),'require_publish_approval'=>self::boolean($input['require_publish_approval']??false));
		if('autopilot'===$mode){ return array_map(static fn():bool=>false,$rules); }
		if(!in_array(true,$rules,true)){ $errors[]='approval_gate_required'; }
		return $rules;
	}

	private function publishing( array $input, array &$errors ): array {
		$mode=self::key($input['publishing_mode']??'draft'); $map=array('draft'=>'draft','schedule'=>'future','publish'=>'publish');
		if(!isset($map[$mode])){ $errors[]='invalid_publishing_settings'; $mode='draft'; }
		$author=absint($input['author_id']??0); $user=$author?get_userdata($author):false;
		if(!$user instanceof WP_User || !user_can($user,'edit_posts')){ $errors[]='invalid_author'; $author=0; }
		$category=absint($input['category_id']??0); $term=$category?get_term($category,'category'):false;
		if(!$term instanceof WP_Term || is_wp_error($term)){ $errors[]='invalid_category'; $category=0; }
		return array('publishing_mode'=>$mode,'post_status_after_generation'=>$map[$mode],'category_id'=>$category,'author_id'=>$author);
	}

	private static function result(bool $success,int $id,string $code,array $errors,array $data):array{return array('success'=>$success,'profile_id'=>$id,'code'=>$code,'errors'=>$errors,'data'=>$data);}
	private static function text($v):string{return sanitize_text_field(is_scalar($v)?(string)$v:'');}
	private static function textarea($v):string{return sanitize_textarea_field(is_scalar($v)?(string)$v:'');}
	public static function key($v):string{return sanitize_key(is_scalar($v)?(string)$v:'');}
	private static function boolean($v):bool{return filter_var($v,FILTER_VALIDATE_BOOLEAN);}
	private static function length(string $v):int{return function_exists('mb_strlen')?mb_strlen($v):strlen($v);}
	private static function cut(string $v,int $n):string{return function_exists('mb_substr')?mb_substr($v,0,$n):substr($v,0,$n);}
	private static function strict_int($v,int $min,int $max):?int{if(!is_scalar($v)||!preg_match('/^-?\d+$/',(string)$v)){return null;}$v=(int)$v;return $v>=$min&&$v<=$max?$v:null;}
	private static function lines($v,array &$errors):array{$lines=preg_split('/\R/u',is_scalar($v)?(string)$v:'')?:array();$out=array();foreach($lines as $line){$line=self::text($line);if(''===$line){continue;}if(self::length($line)>250){$errors[]='invalid_business_context';}$out[]=self::cut($line,250);}if(count($out)>50){$errors[]='invalid_business_context';$out=array_slice($out,0,50);}return array_values(array_unique($out));}
	private static function date($v):string{$v=is_scalar($v)?(string)$v:'';if(''===$v){return '';}$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('UTC'));$e=DateTimeImmutable::getLastErrors();return false!==$d&&(false===$e||(0===$e['warning_count']&&0===$e['error_count']))&&$d->format('Y-m-d')===$v?$v:'';}
}
