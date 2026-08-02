<?php
/**
 * Automation configuration admin page.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automations_Page {
	private const PAGE_SLUG = 'aics-automations';
	private const SAVE_ACTION = 'aics_save_automation_profile';
	private const NONCE_NAME = 'aics_automation_nonce';

	public static function register(): void { add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'handle_save' ) ); }

	public static function render(): void {
		self::require_permission();
		$service = new AICS_Automation_Profile_Service();
		$stored  = $service->get_default_profile();
		$state   = self::consume_form_state();
		$profile = is_array( $state['data'] ?? null ) ? array_replace_recursive( $stored, $state['data'] ) : $stored;
		$errors  = is_array( $state['errors'] ?? null ) ? $state['errors'] : array();
		?>
		<div class="wrap aics-admin-wrap aics-automations-page">
			<header class="aics-page-header"><h1 class="aics-page-title"><?php esc_html_e( 'Automations', 'ai-content-studio' ); ?></h1><p class="aics-page-description"><?php esc_html_e( 'Configure the persistent Autopilot or Approval Workflow profile. Approved automated articles are delivered as WordPress drafts; scheduling and publishing remain separate workflow steps.', 'ai-content-studio' ); ?></p></header>
			<p class="aics-automation-info"><?php esc_html_e( 'Changes apply to new automation cycles. An existing cycle continues with the settings saved when it started.', 'ai-content-studio' ); ?></p>
			<?php self::render_notice(); self::render_errors( $errors ); self::render_summary( $stored ); self::render_schedule_preview( $stored ); ?>
			<form class="aics-automation-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
				<?php wp_nonce_field( self::SAVE_ACTION, self::NONCE_NAME ); ?>
				<?php self::render_general( $profile ); ?>
				<?php self::render_business( $profile['business_context'] ); ?>
				<?php self::render_content( $profile['content_settings'] ); ?>
				<?php self::render_schedule( $profile['schedule_settings'] ); ?>
				<?php self::render_approvals( $profile['workflow_rules'], $profile['mode'] ); ?>
				<?php self::render_publishing( $profile['publishing_settings'] ); ?>
				<div class="aics-actions aics-automation-save"><?php submit_button( __( 'Save Automation Settings', 'ai-content-studio' ), 'primary aics-button-primary', 'submit', false ); ?></div>
			</form>
		</div>
		<?php
	}

	public static function handle_save(): void {
		self::require_permission();
		check_admin_referer( self::SAVE_ACTION, self::NONCE_NAME );
		$input = self::request_data();
		$result = ( new AICS_Automation_Profile_Service() )->save( $input );
		if ( ! $result['success'] ) {
			set_transient( self::state_key(), array( 'data'=>$result['data'], 'errors'=>$result['errors'] ), 5 * MINUTE_IN_SECONDS );
			self::redirect( $result['code'] );
		}
		self::redirect( $result['code'] );
	}

	private static function render_summary( array $profile ): void {
		$exists=$profile['id']>0; $active='active'===$profile['status']; $modes=array('autopilot'=>__('Autopilot','ai-content-studio'),'approval'=>__('Approval Workflow','ai-content-studio'),'manual'=>__('Manual','ai-content-studio'));
		$run_repo=new AICS_Automation_Run_Repository();$pending_ideas=(new AICS_Content_Idea_Repository())->count_ideas(array('status'=>'pending_approval'));$pending_articles=(new AICS_Article_Repository())->count_articles(array('status'=>'pending_approval'));$publishing_approvals=$run_repo->count_runs(array('status'=>'queued','current_step'=>'waiting_publish_approval'));$runs_attention=$run_repo->count_runs(array('status'=>'failed'));
		?>
		<section class="aics-card aics-automation-summary" aria-labelledby="aics-automation-summary-heading"><div class="aics-card-header"><h2 id="aics-automation-summary-heading"><?php esc_html_e('Automation Status','ai-content-studio');?></h2></div><div class="aics-card-body"><div class="aics-automation-summary-grid">
			<div><span><?php esc_html_e('Configuration','ai-content-studio');?></span><strong><?php echo esc_html($exists?__('Saved','ai-content-studio'):__('Not saved yet','ai-content-studio'));?></strong></div>
			<div><span><?php esc_html_e('Operating mode','ai-content-studio');?></span><strong><?php echo esc_html($modes[$profile['mode']]??__('Autopilot','ai-content-studio'));?></strong></div>
			<div><span><?php esc_html_e('Automation','ai-content-studio');?></span><strong><span class="aics-status-badge <?php echo $active?'aics-status--success':'aics-history-status--private';?>"><?php echo esc_html($active?__('Enabled','ai-content-studio'):__('Disabled','ai-content-studio'));?></span></strong></div>
			<div><span><?php esc_html_e('Last run','ai-content-studio');?></span><strong><?php echo esc_html(self::display_datetime($profile['last_run_at']??null,__('—','ai-content-studio')));?></strong></div>
			<div><span><?php esc_html_e('Next run','ai-content-studio');?></span><strong><?php echo esc_html(self::display_datetime($profile['next_run_at']??null,$active?__('No future run available','ai-content-studio'):__('Not scheduled while automation is disabled','ai-content-studio')));?></strong></div>
			<div><span><?php esc_html_e('Last error','ai-content-studio');?></span><strong><?php echo esc_html(''!==($profile['last_error_code']??'')?$profile['last_error_code']:__('None','ai-content-studio'));?></strong></div>
			<div><span><?php esc_html_e('Ideas Awaiting Approval','ai-content-studio');?></span><strong><?php echo esc_html(number_format_i18n($pending_ideas));?></strong></div><div><span><?php esc_html_e('Articles Awaiting Approval','ai-content-studio');?></span><strong><?php echo esc_html(number_format_i18n($pending_articles));?></strong></div><div><span><?php esc_html_e('Publishing Approvals','ai-content-studio');?></span><strong><?php echo esc_html(number_format_i18n($publishing_approvals));?></strong></div><div><span><?php esc_html_e('Runs Requiring Attention','ai-content-studio');?></span><strong><?php echo esc_html(number_format_i18n($runs_attention));?></strong></div>
		</div><?php if($active):?><p class="aics-automation-info"><strong><?php esc_html_e('Core Automation Engine Connected','ai-content-studio');?></strong><br><?php esc_html_e('AI Content Studio can generate ideas, create articles, apply approval checkpoints, create WordPress posts, schedule or publish them, and complete each automation cycle.','ai-content-studio');?> <a href="<?php echo esc_url(add_query_arg(array('page'=>'aics-approvals','approval_type'=>'publishing'),admin_url('admin.php')));?>"><?php esc_html_e('Open Publishing Approvals','ai-content-studio');?></a></p><?php endif;?></div></section>
		<?php
	}

	private static function render_schedule_preview( array $profile ): void {
		?>
		<section class="aics-card aics-schedule-preview" aria-labelledby="aics-schedule-preview-heading"><div class="aics-card-header"><h2 id="aics-schedule-preview-heading"><?php esc_html_e( 'Upcoming Schedule', 'ai-content-studio' ); ?></h2></div><div class="aics-card-body">
		<?php if ( empty( $profile['id'] ) ) : ?><p><?php esc_html_e( 'Save automation settings before a schedule preview is available.', 'ai-content-studio' ); ?></p>
		<?php else : $calculator=new AICS_Schedule_Calculator(); $preview=$calculator->calculate_upcoming_runs($profile['schedule_settings'],5); $dispatcher_next=AICS_Automation_Scheduler::next_scheduled(); $worker_next=AICS_Automation_Scheduler::next_worker_scheduled(); ?>
			<dl class="aics-schedule-details"><div><dt><?php esc_html_e('Site timezone','ai-content-studio');?></dt><dd><?php echo esc_html(wp_timezone_string());?></dd></div><div><dt><?php esc_html_e('Schedule','ai-content-studio');?></dt><dd><?php echo esc_html(self::schedule_summary($profile['schedule_settings']));?></dd></div><div><dt><?php esc_html_e('Profile','ai-content-studio');?></dt><dd><?php echo esc_html('active'===$profile['status']?__('Enabled','ai-content-studio'):__('Disabled','ai-content-studio'));?></dd></div><div><dt><?php esc_html_e('Next content cycle','ai-content-studio');?></dt><dd><?php echo esc_html(self::display_datetime($profile['next_run_at']??null,__('Not scheduled','ai-content-studio')));?></dd></div><div><dt><?php esc_html_e('Next dispatcher check','ai-content-studio');?></dt><dd><?php echo esc_html(false===$dispatcher_next?__('Not scheduled','ai-content-studio'):wp_date(get_option('date_format').' '.get_option('time_format'),$dispatcher_next,wp_timezone()));?></dd></div><div><dt><?php esc_html_e('Next worker check','ai-content-studio');?></dt><dd><?php echo esc_html(false===$worker_next?__('Not scheduled','ai-content-studio'):wp_date(get_option('date_format').' '.get_option('time_format'),$worker_next,wp_timezone()));?></dd></div><div><dt><?php esc_html_e('Start date','ai-content-studio');?></dt><dd><?php echo esc_html($profile['schedule_settings']['start_date']?:__('Not limited','ai-content-studio'));?></dd></div><div><dt><?php esc_html_e('End date','ai-content-studio');?></dt><dd><?php echo esc_html($profile['schedule_settings']['end_date']?:__('Not limited','ai-content-studio'));?></dd></div></dl>
			<?php if(false!==$dispatcher_next&&false!==$worker_next):?><p class="aics-automation-info"><strong><?php esc_html_e('Core Automation Engine Connected','ai-content-studio');?></strong><br><?php esc_html_e('The worker can deliver drafts, schedule or publish posts, and complete automation cycles.','ai-content-studio');?></p><?php else:?><div class="notice notice-warning inline"><p><?php esc_html_e('The automation dispatcher or worker is not currently scheduled.','ai-content-studio');?></p></div><?php endif;?>
			<?php if($preview['success']):?><h3><?php esc_html_e('Next five runs','ai-content-studio');?></h3><ol class="aics-upcoming-runs"><?php foreach($preview['runs'] as $run):?><li><strong><?php echo esc_html($calculator->format_utc_for_site($run['utc']));?></strong><code><?php echo esc_html($run['utc'].' UTC');?></code></li><?php endforeach;?></ol><?php else:?><div class="notice notice-warning inline"><p><?php esc_html_e('No future run is available for the saved date range. Update the schedule to continue.','ai-content-studio');?></p></div><?php endif;?>
		<?php endif; ?></div></section>
		<?php
	}

	private static function render_general(array $p):void{?>
		<section class="aics-section" aria-labelledby="aics-general-heading"><h2 id="aics-general-heading"><?php esc_html_e('General Automation Settings','ai-content-studio');?></h2><div class="aics-form-grid">
			<?php self::text_field('profile_name',__('Automation Name','ai-content-studio'),$p['profile_name'],191,true);?>
			<div class="aics-field"><span class="aics-field-label"><?php esc_html_e('Master control','ai-content-studio');?></span><label class="aics-checkbox-option"><input type="checkbox" name="enabled" value="1" <?php checked('active',$p['status']);?>> <span><strong><?php esc_html_e('Enable Automation','ai-content-studio');?></strong><small><?php esc_html_e('Active profiles can have due cycles added to the processing queue.','ai-content-studio');?></small></span></label></div>
			<?php self::select_field('mode',__('Operating Mode','ai-content-studio'),$p['mode'],array('autopilot'=>__('Autopilot','ai-content-studio'),'approval'=>__('Approval Workflow','ai-content-studio')),__('Manual Studio remains available under Create Content.','ai-content-studio'));?>
		</div></section><?php }

	private static function render_business(array $b):void{?>
		<section class="aics-section" aria-labelledby="aics-business-heading"><h2 id="aics-business-heading"><?php esc_html_e('Business Profile','ai-content-studio');?></h2><p class="description"><?php esc_html_e('Give future automation clear, non-sensitive business and editorial context. HTML is not accepted.','ai-content-studio');?></p><div class="aics-form-grid">
		<?php self::text_field('business_context[business_name]',__('Business Name','ai-content-studio'),$b['business_name']??'',191); self::text_field('business_context[industry]',__('Industry','ai-content-studio'),$b['industry']??'',191); self::text_field('business_context[primary_location]',__('Primary Location','ai-content-studio'),$b['primary_location']??'',191); self::select_field('business_context[preferred_tone]',__('Preferred Tone','ai-content-studio'),$b['preferred_tone']??'professional',self::tones());?>
		<?php self::textarea_field('business_context[business_description]',__('Business Description','ai-content-studio'),$b['business_description']??'',5000); self::textarea_field('business_context[products_services]',__('Products or Services','ai-content-studio'),$b['products_services']??'',5000); self::textarea_field('business_context[target_audience]',__('Target Audience','ai-content-studio'),$b['target_audience']??'',3000); self::textarea_field('business_context[website_purpose]',__('Website Purpose','ai-content-studio'),$b['website_purpose']??'',3000); self::textarea_field('business_context[brand_voice]',__('Brand Voice','ai-content-studio'),$b['brand_voice']??'',3000); self::textarea_field('business_context[preferred_cta]',__('Preferred Call to Action','ai-content-studio'),$b['preferred_cta']??'',2000);?>
		<?php self::textarea_field('business_context[core_topics]',__('Core Topics','ai-content-studio'),self::join_lines($b['core_topics']??array()),12549,__('One item per line; maximum 50 items and 250 characters each.','ai-content-studio')); self::textarea_field('business_context[topics_to_avoid]',__('Topics to Avoid','ai-content-studio'),self::join_lines($b['topics_to_avoid']??array()),12549,__('One item per line.','ai-content-studio')); self::textarea_field('business_context[prohibited_claims]',__('Prohibited Claims or Statements','ai-content-studio'),self::join_lines($b['prohibited_claims']??array()),12549,__('One item per line.','ai-content-studio'));?>
		</div></section><?php }

	private static function render_content(array $c):void{?>
		<section class="aics-section" aria-labelledby="aics-content-generation-heading"><h2 id="aics-content-generation-heading"><?php esc_html_e('Content Generation','ai-content-studio');?></h2><div class="aics-form-grid">
		<?php self::number_field('content_settings[ideas_per_cycle]',__('Ideas Generated Per Cycle','ai-content-studio'),$c['ideas_per_cycle']??5,1,20,__('Candidate topics prepared during one future cycle.','ai-content-studio')); self::number_field('content_settings[selected_ideas_per_cycle]',__('Ideas Selected Per Cycle','ai-content-studio'),$c['selected_ideas_per_cycle']??1,1,20,__('Valid ideas allowed to proceed to article generation.','ai-content-studio')); self::select_field('content_settings[default_tone]',__('Default Tone','ai-content-studio'),$c['default_tone']??'professional',self::tones()); self::select_field('content_settings[article_length]',__('Article Length','ai-content-studio'),$c['article_length']??'medium',array('short'=>__('Short','ai-content-studio'),'medium'=>__('Medium','ai-content-studio'),'long'=>__('Long','ai-content-studio'))); self::number_field('content_settings[duplicate_lookback_days]',__('Duplicate Topic Lookback','ai-content-studio'),$c['duplicate_lookback_days']??180,0,3650,__('Previous days checked before reusing a similar topic.','ai-content-studio'));?>
		<div class="aics-field aics-field--full"><span class="aics-field-label"><?php esc_html_e('Article Features','ai-content-studio');?></span><div class="aics-checkbox-grid"><?php self::checkbox('content_settings[include_faq]',__('Include FAQ Section','ai-content-studio'),$c['include_faq']??false); self::checkbox('content_settings[allow_tables]',__('Allow Tables','ai-content-studio'),$c['allow_tables']??false); self::checkbox('content_settings[allow_lists]',__('Allow Lists','ai-content-studio'),$c['allow_lists']??false);?></div></div>
		</div></section><?php }

	private static function render_schedule(array $s):void{?>
		<section class="aics-section" aria-labelledby="aics-schedule-heading"><h2 id="aics-schedule-heading"><?php esc_html_e('Publishing Schedule','ai-content-studio');?></h2><p class="description"><?php esc_html_e('These values are stored for the future scheduler; no next-run date is calculated in this task.','ai-content-studio');?></p><div class="aics-form-grid">
		<?php self::select_field('schedule_settings[frequency]',__('Frequency','ai-content-studio'),$s['frequency']??'weekly',array('daily'=>__('Daily','ai-content-studio'),'weekly'=>__('Weekly','ai-content-studio'),'monthly'=>__('Monthly','ai-content-studio'))); self::number_field('schedule_settings[interval]',__('Interval','ai-content-studio'),$s['interval']??1,1,31); self::number_field('schedule_settings[posts_per_period]',__('Posts Per Period','ai-content-studio'),$s['posts_per_period']??1,1,31);?>
		<div class="aics-monthly-day-field"><?php self::number_field('schedule_settings[monthly_day]',__('Publishing Day of Month','ai-content-studio'),$s['monthly_day']??current_datetime()->format('j'),1,31,__('Shorter months use their last valid calendar day.','ai-content-studio'));?></div>
		<div class="aics-field aics-field--full"><span class="aics-field-label"><?php esc_html_e('Publishing Days','ai-content-studio');?></span><div class="aics-checkbox-grid"><?php foreach(self::weekdays() as $v=>$l):?><label class="aics-checkbox-option"><input type="checkbox" name="schedule_settings[days_of_week][]" value="<?php echo esc_attr($v);?>" <?php checked(in_array($v,$s['days_of_week']??array(),true));?>> <span><?php echo esc_html($l);?></span></label><?php endforeach;?></div><p class="description"><?php esc_html_e('At least one day is required for weekly frequency.','ai-content-studio');?></p></div>
		<?php self::simple_input('time','schedule_settings[publish_time]',__('Preferred Publishing Time','ai-content-studio'),$s['publish_time']??'10:00'); self::simple_input('date','schedule_settings[start_date]',__('Start Date','ai-content-studio'),$s['start_date']??''); self::simple_input('date','schedule_settings[end_date]',__('End Date','ai-content-studio'),$s['end_date']??'');?>
		</div></section><?php }

	private static function render_approvals(array $r,string $mode):void{?>
		<section class="aics-section" aria-labelledby="aics-approval-heading"><h2 id="aics-approval-heading"><?php esc_html_e('Approval Workflow','ai-content-studio');?></h2><p class="description"><?php esc_html_e('Autopilot always saves all approval gates as off. Approval Workflow requires at least one checkpoint.','ai-content-studio');?></p><div class="aics-checkbox-grid"><?php self::checkbox('workflow_rules[require_idea_approval]',__('Review Ideas Before Article Generation','ai-content-studio'),$r['require_idea_approval']??false); self::checkbox('workflow_rules[require_article_approval]',__('Review Articles Before Scheduling or Publishing','ai-content-studio'),$r['require_article_approval']??false); self::checkbox('workflow_rules[require_publish_approval]',__('Require Final Approval Before Publishing','ai-content-studio'),$r['require_publish_approval']??false);?></div></section><?php }

	private static function render_publishing(array $p):void{$users=get_users(array('orderby'=>'display_name','order'=>'ASC','fields'=>'all'));$users=array_filter($users,static fn($u)=>$u instanceof WP_User&&user_can($u,'edit_posts'));?>
		<section class="aics-section" aria-labelledby="aics-publishing-heading"><h2 id="aics-publishing-heading"><?php esc_html_e('Publishing Behaviour','ai-content-studio');?></h2><div class="aics-form-grid">
		<?php self::select_field('publishing_settings[publishing_mode]',__('Publishing Mode','ai-content-studio'),$p['publishing_mode']??'draft',array('draft'=>__('Create WordPress Drafts','ai-content-studio'),'schedule'=>__('Schedule Posts Automatically','ai-content-studio'),'publish'=>__('Publish Automatically','ai-content-studio')),__('Every automated article is first created as a WordPress draft. Scheduling and publishing occur in later workflow steps.','ai-content-studio'));?>
		<div class="aics-field"><label for="aics-author-id" class="aics-field-label"><?php esc_html_e('Default Post Author','ai-content-studio');?></label><select id="aics-author-id" name="publishing_settings[author_id]" required><?php foreach($users as $u):?><option value="<?php echo esc_attr((string)$u->ID);?>" <?php selected((int)($p['author_id']??0),$u->ID);?>><?php echo esc_html($u->display_name);?></option><?php endforeach;?></select></div>
		<div class="aics-field"><label for="aics-category-id" class="aics-field-label"><?php esc_html_e('Default Category','ai-content-studio');?></label><?php wp_dropdown_categories(array('id'=>'aics-category-id','name'=>'publishing_settings[category_id]','taxonomy'=>'category','hide_empty'=>false,'selected'=>absint($p['category_id']??0),'class'=>'widefat'));?></div>
		<div class="aics-field aics-field--full aics-publish-warning"><strong><?php esc_html_e('Automatic publishing caution','ai-content-studio');?></strong><p><?php esc_html_e('Automatic publishing should be used only after the business profile, content rules, and approval requirements have been tested carefully.','ai-content-studio');?></p></div>
		</div></section><?php }

	private static function request_data():array{$post=wp_unslash($_POST);$array=static fn($k):array=>isset($post[$k])&&is_array($post[$k])?$post[$k]:array();return array('profile_name'=>isset($post['profile_name'])&&is_scalar($post['profile_name'])?(string)$post['profile_name']:'','enabled'=>isset($post['enabled'])?'1':'0','mode'=>isset($post['mode'])&&is_scalar($post['mode'])?(string)$post['mode']:'','business_context'=>$array('business_context'),'content_settings'=>$array('content_settings'),'schedule_settings'=>$array('schedule_settings'),'workflow_rules'=>$array('workflow_rules'),'publishing_settings'=>$array('publishing_settings'));}
	private static function require_permission():void{if(!is_user_logged_in()||!current_user_can(\AIContentStudio\Core\Permissions::manage())){wp_die(esc_html__('You are not allowed to manage automation.','ai-content-studio'));}}
	private static function redirect(string $notice):void{wp_safe_redirect(add_query_arg('aics_notice',sanitize_key($notice),admin_url('admin.php?page='.self::PAGE_SLUG)));exit;}
	private static function state_key():string{return 'aics_automation_errors_'.get_current_user_id();}
	private static function consume_form_state():array{$state=get_transient(self::state_key());delete_transient(self::state_key());return is_array($state)?$state:array();}
	private static function render_notice(): void {
		$code = isset( $_GET['aics_notice'] ) && is_string( $_GET['aics_notice'] ) ? sanitize_key( wp_unslash( $_GET['aics_notice'] ) ) : '';
		$map  = array(
			'automation_profile_created' => array( 'success', __( 'Automation profile created successfully.', 'ai-content-studio' ) ),
			'automation_enabled' => array( 'success', __( 'Automation configuration saved and enabled. Ideas can now be generated, evaluated, scored, and routed.', 'ai-content-studio' ) ),
			'automation_disabled' => array( 'success', __( 'Automation configuration saved and disabled.', 'ai-content-studio' ) ),
			'invalid_automation_name' => array( 'error', __( 'Enter a valid automation name.', 'ai-content-studio' ) ),
			'selected_ideas_exceed_generated' => array( 'error', __( 'Ideas selected per cycle cannot exceed ideas generated.', 'ai-content-studio' ) ),
			'weekly_days_required' => array( 'error', __( 'Weekly automation requires at least one publishing day.', 'ai-content-studio' ) ),
			'end_date_before_start' => array( 'error', __( 'End date cannot be earlier than start date.', 'ai-content-studio' ) ),
			'approval_gate_required' => array( 'error', __( 'Approval Workflow requires at least one approval checkpoint.', 'ai-content-studio' ) ),
			'invalid_author' => array( 'error', __( 'The selected author is invalid.', 'ai-content-studio' ) ),
			'invalid_category' => array( 'error', __( 'The selected category is invalid.', 'ai-content-studio' ) ),
			'database_save_failed' => array( 'error', __( 'Automation settings could not be saved.', 'ai-content-studio' ) ),
			'invalid_mode' => array( 'error', __( 'The automation settings contain invalid values.', 'ai-content-studio' ) ),
			'invalid_business_context' => array( 'error', __( 'The business profile contains invalid or oversized values.', 'ai-content-studio' ) ),
			'invalid_content_settings' => array( 'error', __( 'The content settings contain invalid values.', 'ai-content-studio' ) ),
			'invalid_schedule_settings' => array( 'error', __( 'The schedule settings contain invalid values.', 'ai-content-studio' ) ),
			'invalid_publishing_settings' => array( 'error', __( 'The publishing settings contain invalid values.', 'ai-content-studio' ) ),
			'invalid_monthly_day' => array( 'error', __( 'The monthly publishing day is invalid.', 'ai-content-studio' ) ),
			'automation_saved_next_run' => array( 'success', __( 'Automation settings saved and the next run was calculated.', 'ai-content-studio' ) ),
			'automation_saved_disabled' => array( 'success', __( 'Automation settings saved. Automation is disabled and no next run is stored.', 'ai-content-studio' ) ),
			'schedule_has_no_future_run' => array( 'warning', __( 'Automation settings were saved, but no future run exists before the end date.', 'ai-content-studio' ) ),
			'schedule_persistence_failed' => array( 'warning', __( 'Automation settings were saved, but the next run could not be stored.', 'ai-content-studio' ) ),
		);
		if ( ! isset( $map[ $code ] ) ) { return; }
		?><div class="notice notice-<?php echo esc_attr( $map[ $code ][0] ); ?> is-dismissible"><p><?php echo esc_html( $map[ $code ][1] ); ?></p></div><?php
	}
	private static function render_errors( array $errors ): void {
		if ( count( $errors ) < 2 ) { return; }
		?><div class="notice notice-error"><p><strong><?php esc_html_e( 'Please correct the following sections:', 'ai-content-studio' ); ?></strong></p><ul><?php foreach ( array_unique( $errors ) as $error ) { ?><li><?php echo esc_html( str_replace( '_', ' ', ucfirst( $error ) ) ); ?></li><?php } ?></ul></div><?php
	}
	private static function display_datetime($utc,string $fallback):string{return is_string($utc)&&''!==$utc?get_date_from_gmt($utc,get_option('date_format').' '.get_option('time_format')):$fallback;}
	private static function schedule_summary(array $s):string{$interval=absint($s['interval']??1);$time=is_string($s['publish_time']??null)?$s['publish_time']:'10:00';$parsed=DateTimeImmutable::createFromFormat('!H:i',$time,wp_timezone());$display=$parsed?wp_date(get_option('time_format'),$parsed->getTimestamp(),wp_timezone()):$time;$frequency=$s['frequency']??'weekly';if('daily'===$frequency){return 1===$interval?sprintf(__('Every day at %s.','ai-content-studio'),$display):sprintf(__('Every %1$d days at %2$s.','ai-content-studio'),$interval,$display);}if('weekly'===$frequency){$labels=self::weekdays();$days=array_map(static fn($d)=>$labels[$d]??'',is_array($s['days_of_week']??null)?$s['days_of_week']:array());$day_text=implode(', ',array_filter($days));return 1===$interval?sprintf(__('Every week on %1$s at %2$s.','ai-content-studio'),$day_text,$display):sprintf(__('Every %1$d weeks on %2$s at %3$s.','ai-content-studio'),$interval,$day_text,$display);} $day=absint($s['monthly_day']??1);return 1===$interval?sprintf(__('Every month on day %1$d at %2$s. Shorter months use their last day.','ai-content-studio'),$day,$display):sprintf(__('Every %1$d months on day %2$d at %3$s. Shorter months use their last day.','ai-content-studio'),$interval,$day,$display);}
	private static function tones():array{return array('professional'=>__('Professional','ai-content-studio'),'friendly'=>__('Friendly','ai-content-studio'),'conversational'=>__('Conversational','ai-content-studio'),'informative'=>__('Informative','ai-content-studio'),'persuasive'=>__('Persuasive','ai-content-studio'));}
	private static function weekdays():array{return array('monday'=>__('Monday','ai-content-studio'),'tuesday'=>__('Tuesday','ai-content-studio'),'wednesday'=>__('Wednesday','ai-content-studio'),'thursday'=>__('Thursday','ai-content-studio'),'friday'=>__('Friday','ai-content-studio'),'saturday'=>__('Saturday','ai-content-studio'),'sunday'=>__('Sunday','ai-content-studio'));}
	private static function join_lines($v):string{return is_array($v)?implode("\n",array_map('strval',$v)):'';}
	private static function text_field(string $name,string $label,$value,int $max,bool $required=false):void{self::simple_input('text',$name,$label,$value,$max,$required);}
	private static function number_field(string $name,string $label,$value,int $min,int $max,string $description=''):void{?><div class="aics-field"><label class="aics-field-label"><?php echo esc_html($label);?><input type="number" name="<?php echo esc_attr($name);?>" value="<?php echo esc_attr((string)$value);?>" min="<?php echo esc_attr((string)$min);?>" max="<?php echo esc_attr((string)$max);?>" required></label><?php if($description){?><p class="description"><?php echo esc_html($description);?></p><?php }?></div><?php }
	private static function simple_input(string $type,string $name,string $label,$value,int $max=0,bool $required=false):void{$id='aics-'.sanitize_html_class(str_replace(array('[',']'),'',$name));?><div class="aics-field"><label id="<?php echo esc_attr($id);?>-label" for="<?php echo esc_attr($id);?>" class="aics-field-label"><?php echo esc_html($label);?></label><input id="<?php echo esc_attr($id);?>" class="widefat" type="<?php echo esc_attr($type);?>" name="<?php echo esc_attr($name);?>" value="<?php echo esc_attr((string)$value);?>" <?php echo $max?'maxlength="'.esc_attr((string)$max).'"':'';?> <?php echo $required?'required':'';?>></div><?php }
	private static function textarea_field(string $name,string $label,$value,int $max,string $description=''):void{$id='aics-'.sanitize_html_class(str_replace(array('[',']'),'',$name));?><div class="aics-field aics-field--full"><label for="<?php echo esc_attr($id);?>" class="aics-field-label"><?php echo esc_html($label);?></label><textarea id="<?php echo esc_attr($id);?>" name="<?php echo esc_attr($name);?>" rows="4" maxlength="<?php echo esc_attr((string)$max);?>"><?php echo esc_textarea((string)$value);?></textarea><?php if($description){?><p class="description"><?php echo esc_html($description);?></p><?php }?></div><?php }
	private static function select_field(string $name,string $label,$value,array $options,string $description=''):void{$id='aics-'.sanitize_html_class(str_replace(array('[',']'),'',$name));?><div class="aics-field"><label for="<?php echo esc_attr($id);?>" class="aics-field-label"><?php echo esc_html($label);?></label><select id="<?php echo esc_attr($id);?>" name="<?php echo esc_attr($name);?>"><?php foreach($options as $v=>$l){?><option value="<?php echo esc_attr($v);?>" <?php selected($value,$v);?>><?php echo esc_html($l);?></option><?php }?></select><?php if($description){?><p class="description"><?php echo esc_html($description);?></p><?php }?></div><?php }
	private static function checkbox(string $name,string $label,$checked):void{?><label class="aics-checkbox-option"><input type="checkbox" name="<?php echo esc_attr($name);?>" value="1" <?php checked((bool)$checked);?>> <span><?php echo esc_html($label);?></span></label><?php }
	private function __construct() {}
}
