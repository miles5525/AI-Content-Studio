<?php
/** Processes one supported automation workflow unit per cron invocation. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Worker {
	private const MAX_STEPS_PER_INVOCATION = 12;
	private const MAX_RUNS_PER_INVOCATION = 2;
	private const DEFAULT_EXECUTION_BUDGET = 90;
	private AICS_Automation_Run_Repository $runs;
	private AICS_Automation_Profile_Repository $profiles;
	private AICS_Content_Idea_Repository $ideas;
	private AICS_Automation_Idea_Generator $generator;
	private AICS_Automation_Idea_Evaluator $evaluator;
	private AICS_Article_Repository $articles;
	private AICS_Automation_Article_Generator $article_generator;
	private AICS_Automation_Post_Creator $post_creator;
	private AICS_Automation_Delivery_Service $delivery;
	private AICS_Schedule_Calculator $schedule_calculator;

	public function __construct( ?AICS_Automation_Run_Repository $runs = null, ?AICS_Automation_Profile_Repository $profiles = null, ?AICS_Content_Idea_Repository $ideas = null, ?AICS_Automation_Idea_Generator $generator = null, ?AICS_Automation_Idea_Evaluator $evaluator = null, ?AICS_Article_Repository $articles = null, ?AICS_Automation_Article_Generator $article_generator = null, ?AICS_Automation_Post_Creator $post_creator = null, ?AICS_Automation_Delivery_Service $delivery = null, ?AICS_Schedule_Calculator $schedule_calculator = null ) {
		$this->runs = $runs ?? new AICS_Automation_Run_Repository(); $this->profiles = $profiles ?? new AICS_Automation_Profile_Repository(); $this->ideas = $ideas ?? new AICS_Content_Idea_Repository();
		$this->generator = $generator ?? new AICS_Automation_Idea_Generator( null, $this->ideas ); $this->evaluator = $evaluator ?? new AICS_Automation_Idea_Evaluator( null, $this->ideas );
		$this->articles = $articles ?? new AICS_Article_Repository(); $this->article_generator = $article_generator ?? new AICS_Automation_Article_Generator( null, $this->ideas, $this->articles );
		$this->post_creator = $post_creator ?? new AICS_Automation_Post_Creator( $this->articles, $this->ideas );
		$this->delivery = $delivery ?? new AICS_Automation_Delivery_Service( $this->articles ); $this->schedule_calculator = $schedule_calculator ?? new AICS_Schedule_Calculator();
	}

	public function process(): array {
		$totals = array( 'success'=>true,'code'=>'no_claimable_runs','runs_checked'=>0,'runs_claimed'=>0,'ideas_created'=>0,'duplicates'=>0,'ideas_evaluated'=>0,'articles_generated'=>0,'posts_created'=>0 );
		$started = microtime( true );
		$maximum = absint( ini_get( 'max_execution_time' ) );
		$budget  = 0 === $maximum ? self::DEFAULT_EXECUTION_BUDGET : max( 1, min( self::DEFAULT_EXECUTION_BUDGET, $maximum - 5 ) );
		$run_id  = 0;
		$run_ids = array();

		for ( $step = 0; $step < self::MAX_STEPS_PER_INVOCATION && microtime( true ) - $started < $budget; ++$step ) {
			$result = $this->process_one( $run_id );
			foreach ( array( 'runs_checked','runs_claimed','ideas_created','duplicates','ideas_evaluated','articles_generated','posts_created' ) as $key ) {
				$totals[ $key ] += absint( $result[ $key ] ?? 0 );
			}
			$totals['code'] = $result['code'];
			if ( empty( $result['success'] ) ) { $totals['success'] = false; break; }
			$processed_id = absint( $result['run_id'] ?? 0 );
			if ( 0 === $processed_id ) { break; }
			$run_ids[ $processed_id ] = true;
			if ( ! empty( $result['defer_worker'] ) ) { break; }

			$fresh = $this->runs->get_by_id( $processed_id );
			if ( $fresh && 'queued' === $fresh['status'] && in_array( $fresh['current_step'], $this->claimable_steps(), true ) ) {
				$run_id = $processed_id;
				continue;
			}

			$run_id = 0;
			if ( count( $run_ids ) >= self::MAX_RUNS_PER_INVOCATION ) { break; }
		}

		return $totals;
	}

	private function process_one( int $preferred_run_id = 0 ): array {
		$result = array( 'success'=>true,'code'=>'no_claimable_runs','run_id'=>0,'defer_worker'=>false,'runs_checked'=>0,'runs_claimed'=>0,'ideas_created'=>0,'duplicates'=>0,'ideas_evaluated'=>0,'articles_generated'=>0,'posts_created'=>0 );
		try {
			$steps = $this->claimable_steps();
			$candidates = $preferred_run_id > 0 ? array_filter( array( $this->runs->get_by_id( $preferred_run_id ) ) ) : $this->runs->get_claimable_runs( current_time( 'mysql', true ), $steps, 1 );
			if ( ! $candidates ) { return $result; }
			$result['run_id'] = absint( $candidates[0]['id'] ?? 0 );
			$result['runs_checked'] = 1; $ttl = in_array( $candidates[0]['current_step'], array( 'queue_idea', 'generate_article' ), true ) ? 1800 : ( in_array( $candidates[0]['current_step'], array( 'create_post', 'generate_featured_image', 'schedule_post', 'publish_post', 'finalize' ), true ) ? 600 : 900 );
			$claim = $this->runs->claim_run( $candidates[0]['id'], $ttl );
			if ( ! ( $claim['success'] ?? false ) ) { return $this->failed( $result, 'run_claim_failed' ); }
			$result['runs_claimed'] = 1; $token = $claim['lock_token']; $run = $this->runs->get_by_id( $candidates[0]['id'] );
			if ( ! $run ) { return $this->failed( $result, 'run_missing_after_claim' ); }
			$profile = $this->profiles->get_by_id( $run['profile_id'] );
			if ( ! $profile ) { $this->runs->mark_failed( $run['id'], $token, 'automation_profile_missing' ); return $this->failed( $result, 'automation_profile_missing' ); }
			if ( 'active' !== $profile['status'] ) { $this->runs->cancel_claimed_run( $run['id'], $token, 'profile_not_active' ); $result['code'] = 'profile_not_active'; return $result; }
			$effective = $this->runs->get_effective_configuration( $run, $profile );
			if ( empty( $effective['success'] ) ) { $this->runs->mark_failed( $run['id'], $token, $effective['code'] ); return $this->failed( $result, $effective['code'] ); }
			$profile = array_merge( $profile, $effective['configuration'] );
			$profile['publish_at'] = $this->runs->get_publish_at( $run );
			if ( 'publish' === ( $profile['publishing_settings']['publishing_mode'] ?? '' ) && is_string( $profile['publish_at'] ) && $profile['publish_at'] > current_time( 'mysql', true ) ) {
				$profile['publishing_settings']['publishing_mode'] = 'schedule';
				$profile['publishing_settings']['post_status_after_generation'] = 'future';
			}
			if ( 'generate_featured_image' === $run['current_step'] ) { return $this->featured_image_step( $profile, $run, $token, $result ); }
			if ( in_array( $run['current_step'], array( 'generate_seo','apply_seo' ), true ) ) { return $this->seo_step( $profile, $run, $token, $result ); }
			if ( 'evaluate_ideas' === $run['current_step'] ) { return $this->evaluate_step( $profile, $run, $token, $result ); }
			if ( 'create_post' === $run['current_step'] ) { return $this->create_post_step( $profile, $run, $token, $result ); }
			if ( in_array( $run['current_step'], array( 'schedule_post','publish_post' ), true ) ) { return $this->delivery_step( $profile, $run, $token, $result ); }
			if ( 'finalize' === $run['current_step'] ) { return $this->finalize_step( $profile, $run, $token, $result ); }
			if ( in_array( $run['current_step'], array( 'queue_idea', 'generate_article' ), true ) ) { return $this->article_step( $profile, $run, $token, $result ); }
			if ( ! in_array( $run['current_step'], array( 'pending', 'generate_ideas' ), true ) ) { $this->runs->release_lock( $run['id'], $token ); $result['code'] = 'unsupported_run_step'; return $result; }
			if ( $this->ideas->count_ideas( array( 'run_id'=>$run['id'] ) ) > 0 ) { $this->runs->update_step( $run['id'], $token, 'evaluate_ideas' ); $this->runs->release_lock( $run['id'], $token ); $result['code'] = 'ideas_already_generated'; return $result; }
			if ( 'pending' === $run['current_step'] && ! ( $this->runs->update_step( $run['id'], $token, 'generate_ideas' )['success'] ?? false ) ) { return $this->failed( $result, 'run_step_update_failed' ); }
			$generated = $this->generator->generate( $profile, $run['id'] );
			if ( ! ( $this->runs->refresh_lock( $run['id'], $token, 900 )['success'] ?? false ) ) { return $this->failed( $result, 'execution_lock_lost' ); }
			if ( $generated['success'] ) { $result['ideas_created'] = absint( $generated['created'] ); $result['duplicates'] = absint( $generated['batch_duplicates'] ) + absint( $generated['historical_duplicates'] ); $this->runs->update_step( $run['id'], $token, 'evaluate_ideas' ); $this->runs->release_lock( $run['id'], $token ); $result['code'] = 'worker_completed'; return $result; }
			return $this->handle_failure( $run, $token, $generated, $result );
		} catch ( Throwable $exception ) { if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( 'AI Content Studio worker stopped with controlled code: unexpected_worker_error' ); } return $this->failed( $result, 'unexpected_worker_error' ); }
	}

	private function featured_image_step(array $profile,array $run,string $token,array $result):array{
		$processed=(new AICS_Automation_Featured_Image_Service($this->articles))->process_one($run,$profile);
		if(empty($processed['success'])){return $this->handle_failure($run,$token,$processed,$result);}
		if(empty($processed['complete'])){if(!($this->runs->release_lock($run['id'],$token)['success']??false)){return $this->failed($result,'execution_lock_lost');}$result['code']=$processed['code'];$result['images_processed']=1;$result['defer_worker']=true;return $result;}
		$settings=is_array($profile['publishing_settings']??null)?$profile['publishing_settings']:array();$rules=is_array($profile['workflow_rules']??null)?$profile['workflow_rules']:array();$mode=$settings['publishing_mode']??'draft';$step=!empty($profile['seo_settings']['enabled'])?'generate_seo':('draft'===$mode?'finalize':(!empty($rules['require_publish_approval'])?'waiting_publish_approval':('schedule'===$mode?'schedule_post':'publish_post')));
		if(!($this->runs->update_step($run['id'],$token,$step)['success']??false)){return $this->failed($result,'run_step_update_failed');}if(!($this->runs->release_lock($run['id'],$token)['success']??false)){return $this->failed($result,'execution_lock_lost');}$result['code']='featured_images_complete';return $result;
	}

	private function seo_step(array $profile,array $run,string $token,array $result):array{$phase='generate_seo'===$run['current_step']?'generate':'apply';$processed=(new AICS_Automation_SEO_Service($this->articles))->process_one($run,$profile,$phase);if(empty($processed['success'])){return $this->handle_failure($run,$token,$processed,$result);}if(empty($processed['complete'])){$this->runs->release_lock($run['id'],$token);$result['code']=$processed['code'];return $result;}if('generate'===$phase){$next='apply_seo';}else{$mode=$profile['publishing_settings']['publishing_mode']??'draft';$next='draft'===$mode?'finalize':(!empty($profile['workflow_rules']['require_publish_approval'])?'waiting_publish_approval':('schedule'===$mode?'schedule_post':'publish_post'));}if(!($this->runs->update_step($run['id'],$token,$next)['success']??false)){return $this->failed($result,'run_step_update_failed');}$this->runs->release_lock($run['id'],$token);$result['code']=$processed['code'];return $result;}

	private function delivery_step( array $profile, array $run, string $token, array $result ): array {
		$mode=$profile['publishing_settings']['publishing_mode']??'';$expected='schedule_post'===$run['current_step']?'schedule':'publish';if($mode!==$expected&&!('schedule'===$mode&&'publish_post'===$run['current_step'])){return $this->handle_failure($run,$token,array('code'=>'invalid_publishing_mode','retryable'=>false),$result);}
		$articles=$this->articles->get_articles_for_run($run['id'],array('profile_id'=>$profile['id'],'limit'=>100,'orderby'=>'created_at','order'=>'ASC'));usort($articles,static function($a,$b){if(null===$a['planned_publish_at']&&null!==$b['planned_publish_at']){return 1;}if(null!==$a['planned_publish_at']&&null===$b['planned_publish_at']){return -1;}return ($a['planned_publish_at']<=>$b['planned_publish_at'])?:($a['created_at']<=>$b['created_at'])?:($a['id']<=>$b['id']);});
		if(!$articles){return $this->handle_failure($run,$token,array('code'=>'run_not_ready_to_finalize','retryable'=>false),$result);}$candidate=null;foreach($articles as $article){if('rejected'===$article['status']){continue;}if('draft_created'===$article['status']){$candidate=$article;break;}if(!in_array($article['status'],array('scheduled','published'),true)){return $this->delivery_failure($run,$token,array('code'=>'article_post_missing','retryable'=>false,'article_id'=>$article['id']),$result);}$owned=$this->delivery->validate_article_post_ownership($article);if(empty($owned['success'])){return $this->delivery_failure($run,$token,array('code'=>$owned['code'],'retryable'=>false,'article_id'=>$article['id']),$result);}if('scheduled'===$article['status']&&'publish'===$owned['post']->post_status){$candidate=$article;break;}if('scheduled'===$article['status']&&'future'!==$owned['post']->post_status){return $this->delivery_failure($run,$token,array('code'=>'invalid_wordpress_post_status','retryable'=>false,'article_id'=>$article['id']),$result);}if('published'===$article['status']&&'publish'!==$owned['post']->post_status){return $this->delivery_failure($run,$token,array('code'=>'invalid_wordpress_post_status','retryable'=>false,'article_id'=>$article['id']),$result);}}
		if($candidate){$image_guard=(new AICS_Automation_Featured_Image_Service($this->articles))->delivery_allowed($candidate,is_array($profile['featured_image_settings']??null)?$profile['featured_image_settings']:array());if(empty($image_guard['success'])){return $this->delivery_failure($run,$token,array('code'=>$image_guard['code'],'retryable'=>false,'article_id'=>$candidate['id']),$result);}$publish_at=$candidate['planned_publish_at']??($profile['publish_at']??null);$now=current_time('mysql',true);if(is_string($publish_at)&&$publish_at>$now&&strtotime($publish_at.' UTC')-strtotime($now.' UTC')<=MINUTE_IN_SECONDS){if(!($this->runs->release_lock($run['id'],$token)['success']??false)){return $this->failed($result,'execution_lock_lost');}$result['code']='waiting_for_publish_at';$result['defer_worker']=true;return $result;}if(is_string($publish_at)&&$publish_at>$now){$delivered=$this->delivery->schedule_article_post($candidate['id'],$publish_at);}elseif('schedule'===$mode){$slot=$this->publishing_slot($profile,$articles,$candidate);if(!$slot){return $this->delivery_failure($run,$token,array('code'=>'no_future_publishing_slot','retryable'=>false,'article_id'=>$candidate['id']),$result);}$delivered=$this->delivery->schedule_article_post($candidate['id'],$slot);}else{$delivered=$this->delivery->publish_article_post($candidate['id']);}if(empty($delivered['success'])){return $this->delivery_failure($run,$token,$delivered,$result);}if($this->articles->count_articles(array('run_id'=>$run['id'],'profile_id'=>$profile['id'],'status'=>'draft_created'))>0){if(!($this->runs->release_lock($run['id'],$token)['success']??false)){return $this->failed($result,'execution_lock_lost');}$result['code']=$delivered['code'];return $result;}}
		if(!($this->runs->refresh_lock($run['id'],$token,600)['success']??false)){return $this->failed($result,'execution_lock_lost');}if(!($this->runs->update_step($run['id'],$token,'finalize')['success']??false)){return $this->failed($result,'run_step_update_failed');}if(!($this->runs->release_lock($run['id'],$token)['success']??false)){return $this->failed($result,'execution_lock_lost');}$result['code']='delivery_completed';return $result;
	}

	private function publishing_slot(array $profile,array $articles,array $candidate):?string{$now=current_time('mysql',true);if($candidate['planned_publish_at']&&$candidate['planned_publish_at']>$now){$duplicate=false;foreach($articles as $a){if($a['id']!==$candidate['id']&&$a['planned_publish_at']===$candidate['planned_publish_at']){$duplicate=true;break;}}if(!$duplicate){return $candidate['planned_publish_at'];}}$reference=(new DateTimeImmutable($now,new DateTimeZone('UTC')))->modify('+5 minutes')->format('Y-m-d H:i:s');foreach($articles as $a){if($a['id']!==$candidate['id']&&$a['planned_publish_at']&&$a['planned_publish_at']>$reference){$reference=$a['planned_publish_at'];}}$calculated=$this->schedule_calculator->calculate_next_run(is_array($profile['schedule_settings']??null)?$profile['schedule_settings']:array(),$reference);return !empty($calculated['success'])?$calculated['next_run_utc']:null;}

	private function finalize_step(array $profile,array $run,string $token,array $result):array{$mode=$profile['publishing_settings']['publishing_mode']??'draft';if(!in_array($mode,array('draft','schedule','publish'),true)){return $this->handle_failure($run,$token,array('code'=>'invalid_publishing_mode','retryable'=>false),$result);}$articles=$this->articles->get_articles_for_run($run['id'],array('profile_id'=>$profile['id'],'limit'=>100,'orderby'=>'created_at','order'=>'ASC'));if(!$articles){return $this->handle_failure($run,$token,array('code'=>'run_not_ready_to_finalize','retryable'=>false),$result);}foreach($articles as $article){if('rejected'===$article['status']){continue;}$allowed='draft'===$mode?array('draft_created','scheduled','published'):('schedule'===$mode?array('scheduled','published'):array('published'));if(!in_array($article['status'],$allowed,true)){if('draft_created'===$article['status']&&'draft'!==$mode){$step='schedule'===$mode?'schedule_post':'publish_post';if(!($this->runs->update_step($run['id'],$token,$step)['success']??false)){return $this->failed($result,'run_step_update_failed');}if(!($this->runs->release_lock($run['id'],$token)['success']??false)){return $this->failed($result,'execution_lock_lost');}$result['code']='run_returned_to_delivery';return $result;}return $this->handle_failure($run,$token,array('code'=>'run_not_ready_to_finalize','retryable'=>false),$result);}$owned=$this->delivery->validate_article_post_ownership($article);if(empty($owned['success'])){return $this->delivery_failure($run,$token,array('code'=>$owned['code'],'retryable'=>false,'article_id'=>$article['id']),$result);}$post_status=$owned['post']->post_status;$post_allowed='draft'===$mode?array('draft','future','publish'):('schedule'===$mode?array('future','publish'):array('publish'));if(!in_array($post_status,$post_allowed,true)){return $this->handle_failure($run,$token,array('code'=>'run_not_ready_to_finalize','retryable'=>false),$result);}$idea=$this->ideas->get_by_id($article['idea_id']);if(!$idea||$idea['run_id']!==$run['id']){return $this->handle_failure($run,$token,array('code'=>'idea_completion_failed','retryable'=>false),$result);}if('article_generated'===$idea['status']){$changed=$this->ideas->transition_status($idea['id'],array('article_generated'),'completed',array('updated_by'=>0));if(empty($changed['success'])){return $this->handle_failure($run,$token,array('code'=>'idea_completion_failed','retryable'=>true),$result);}}elseif('completed'!==$idea['status']){return $this->handle_failure($run,$token,array('code'=>'idea_completion_failed','retryable'=>false),$result);}}
		$readiness=$this->runs->validate_finalization($run,$profile);if(empty($readiness['success'])){return $this->handle_failure($run,$token,array('code'=>$readiness['code'],'retryable'=>false),$result);}$updated=$this->profiles->update_runtime_fields($profile['id'],array('last_run_at'=>current_time('mysql',true),'updated_by'=>0));if(empty($updated['success'])){return $this->handle_failure($run,$token,array('code'=>'profile_runtime_update_failed','retryable'=>true),$result);}if(!($this->runs->mark_completed($run['id'],$token)['success']??false)){return $this->failed($result,'execution_lock_lost');}$result['code']='automation_run_completed';return $result;}

	private function delivery_failure(array $run,string $token,array $failure,array $result):array{$id=absint($failure['article_id']??0);$fresh=$this->runs->get_by_id($run['id']);$terminal=empty($failure['retryable'])||absint($fresh['attempt_count']??1)>=absint($fresh['max_attempts']??3);if($id){$this->articles->update_error_code($id,$failure['code']??'delivery_failed',0);if($terminal){$article=$this->articles->get_by_id($id);if($article&&in_array($article['status'],array('draft_created','scheduled'),true)){$this->articles->transition_status($id,array($article['status']),'needs_attention',array('updated_by'=>0,'error_code'=>$failure['code']??'delivery_failed'));}}}return $this->handle_failure($run,$token,$failure,$result);}

	private function create_post_step( array $profile, array $run, string $token, array $result ): array {
		$article = $this->articles->get_next_approved_for_post_creation( $run['id'], $profile['id'] );
		if ( $article ) {
			$created = $this->post_creator->create_post_for_article( $article['id'], $profile );
			if ( empty( $created['success'] ) ) { return $this->handle_post_failure( $run, $token, $created, $result ); }
			$result['posts_created'] = ! empty( $created['created'] ) ? 1 : 0;
			if ( $this->articles->get_next_approved_for_post_creation( $run['id'], $profile['id'] ) ) { if(!($this->runs->release_lock($run['id'],$token)['success']??false)){return $this->failed($result,'execution_lock_lost');} $result['code']=$created['code']; return $result; }
		}
		$all = $this->articles->get_articles_for_run( $run['id'], array( 'limit'=>100, 'orderby'=>'created_at', 'order'=>'ASC' ) );
		foreach ( $all as $item ) { if ( $item['profile_id']===absint($profile['id'])&&'automation'===$item['source_type']&&in_array($item['status'],array('queued','generating','generated','pending_approval','failed','paused','needs_attention'),true) ) { return $this->handle_post_failure($run,$token,array('code'=>'invalid_article_workflow','retryable'=>false,'article_id'=>$item['id']),$result); } }
		foreach ( $all as $item ) { if ( $item['profile_id']===absint($profile['id'])&&'automation'===$item['source_type']&&in_array($item['status'],array('draft_created','scheduled','published'),true) ) { $post=get_post($item['wordpress_post_id']); if(!$post||'post'!==$post->post_type||'trash'===$post->post_status||absint(get_post_meta($post->ID,'_aics_article_id',true))!==$item['id']||sanitize_text_field((string)get_post_meta($post->ID,'_aics_article_uuid',true))!==$item['article_uuid']){ return $this->handle_post_failure($run,$token,array('code'=>'article_post_association_failed','retryable'=>false,'article_id'=>$item['id']),$result); } } }
		$delivered = array_values( array_filter( $all, static fn($item)=>$item['profile_id']===absint($profile['id'])&&'automation'===$item['source_type']&&in_array($item['status'],array('draft_created','scheduled','published'),true)&&$item['wordpress_post_id']>0 ) );
		if ( empty( $delivered ) ) { return $this->handle_post_failure( $run, $token, array('code'=>'no_approved_articles','retryable'=>false,'article_id'=>0), $result ); }
		$settings=is_array($profile['publishing_settings']??null)?$profile['publishing_settings']:array();$rules=is_array($profile['workflow_rules']??null)?$profile['workflow_rules']:array();$mode=$settings['publishing_mode']??'draft';
		$step=!empty($profile['featured_image_settings']['enabled'])?'generate_featured_image':(!empty($profile['seo_settings']['enabled'])?'generate_seo':('draft'===$mode?'finalize':(!empty($rules['require_publish_approval'])?'waiting_publish_approval':('schedule'===$mode?'schedule_post':'publish_post'))));
		if ( ! ( $this->runs->refresh_lock( $run['id'], $token, 600 )['success'] ?? false ) ) { return $this->failed( $result, 'execution_lock_lost' ); }
		if ( ! ( $this->runs->update_step( $run['id'], $token, $step )['success'] ?? false ) ) { return $this->failed( $result, 'run_step_update_failed' ); }
		if(!($this->runs->release_lock($run['id'],$token)['success']??false)){return $this->failed($result,'execution_lock_lost');} $result['code']='wordpress_drafts_ready'; return $result;
	}

	private function handle_post_failure( array $run, string $token, array $failure, array $result ): array {
		$id=absint($failure['article_id']??0);$fresh=$this->runs->get_by_id($run['id']);$terminal=empty($failure['retryable'])||absint($fresh['attempt_count']??1)>=absint($fresh['max_attempts']??3);
		if($id){$this->articles->update_error_code($id,$failure['code']??'wordpress_post_creation_failed',0);if($terminal){$article=$this->articles->get_by_id($id);if($article&&in_array($article['status'],array('approved','draft_created','scheduled','published'),true)){$this->articles->transition_status($id,array($article['status']),'needs_attention',array('updated_by'=>0,'error_code'=>'post_creation_retries_exhausted'));}}}
		return $this->handle_failure($run,$token,$failure,$result);
	}

	private function article_step( array $profile, array $run, string $token, array $result ): array {
		$idea = $this->ideas->get_next_article_idea_for_run( $run['id'], $profile['id'] );
		if ( ! $idea ) { return $this->route_articles( $profile, $run, $token, $result ); }
		if ( 'queued' === $idea['status'] ) {
			$transition = $this->ideas->transition_status( $idea['id'], array( 'queued' ), 'article_generating', array( 'updated_by'=>0 ) );
			if ( ! $transition['success'] ) { return $this->handle_failure( $run, $token, array( 'code'=>'idea_transition_failed','retryable'=>true ), $result ); }
			$idea = $this->ideas->get_by_id( $idea['id'] );
		}
		$placeholder = $this->articles->create_for_idea( $idea['id'], array( 'created_by'=>0, 'featured_image_settings'=>$profile['featured_image_settings']??array() ) );
		if ( ! $placeholder['success'] ) { return $this->handle_failure( $run, $token, array( 'code'=>'article_persistence_failed','retryable'=>true ), $result ); }
		$article = $this->articles->get_by_id( $placeholder['article_id'] );
		if ( ! $article ) { return $this->handle_failure( $run, $token, array( 'code'=>'article_not_found','retryable'=>true,'article_id'=>$placeholder['article_id'] ), $result ); }
		if ( in_array( $article['status'], array( 'queued', 'failed', 'needs_attention' ), true ) ) {
			$transition = $this->articles->transition_status( $article['id'], array( $article['status'] ), 'generating', array( 'updated_by'=>0 ) );
			if ( ! $transition['success'] ) { return $this->handle_failure( $run, $token, array( 'code'=>'article_transition_failed','retryable'=>true,'article_id'=>$article['id'] ), $result ); }
		}
		if ( 'generate_article' !== $run['current_step'] && ! ( $this->runs->update_step( $run['id'], $token, 'generate_article' )['success'] ?? false ) ) { return $this->failed( $result, 'run_step_update_failed' ); }
		if ( ! ( $this->runs->refresh_lock( $run['id'], $token, 1800 )['success'] ?? false ) ) { return $this->failed( $result, 'execution_lock_lost' ); }
		$generated = $this->article_generator->generate( $profile, $run['id'], $idea['id'] );
		if ( ! ( $this->runs->refresh_lock( $run['id'], $token, 1800 )['success'] ?? false ) ) { return $this->failed( $result, 'execution_lock_lost' ); }
		if ( ! $generated['success'] ) { return $this->handle_article_failure( $run, $token, $generated, $result ); }
		$result['articles_generated'] = ! empty( $generated['provider_called'] ) ? 1 : 0;
		if ( $this->ideas->get_next_article_idea_for_run( $run['id'], $profile['id'] ) ) { $this->runs->update_step( $run['id'], $token, 'queue_idea' ); $this->runs->release_lock( $run['id'], $token ); $result['code'] = $generated['code']; return $result; }
		return $this->route_articles( $profile, $run, $token, $result );
	}

	private function route_articles( array $profile, array $run, string $token, array $result ): array {
		$articles = $this->articles->get_articles_for_run( $run['id'], array( 'limit'=>100, 'orderby'=>'created_at', 'order'=>'ASC' ) );
		if ( empty( $articles ) ) { return $this->handle_failure( $run, $token, array( 'code'=>'no_queued_ideas','retryable'=>false ), $result ); }
		foreach ( $articles as $article ) { if ( in_array( $article['status'], array( 'queued','generating','failed','needs_attention','rejected' ), true ) ) { return $this->handle_article_failure( $run, $token, array( 'code'=>'invalid_article_workflow','retryable'=>false,'article_id'=>$article['id'] ), $result ); } }
		$image_service=new AICS_Automation_Featured_Image_Service($this->articles);foreach($articles as $article){if('rejected'===$article['status']){continue;}$prepared=$image_service->prepare_prompt($article,$profile);if(empty($prepared['success'])){return $this->handle_article_failure($run,$token,array('code'=>$prepared['code'],'retryable'=>false,'article_id'=>$article['id']),$result);}}
		$approval = 'approval' === ( $profile['mode'] ?? '' ) && ! empty( $profile['workflow_rules']['require_article_approval'] );
		$target = $approval ? 'pending_approval' : 'approved'; $context = $approval ? array( 'updated_by'=>0 ) : array( 'updated_by'=>0, 'approved_by'=>0 );
		foreach ( $articles as $article ) { if ( 'generated' === $article['status'] ) { $changed = $this->articles->transition_status( $article['id'], array( 'generated' ), $target, $context ); if ( ! $changed['success'] ) { return $this->handle_article_failure( $run, $token, array( 'code'=>'article_transition_failed','retryable'=>true,'article_id'=>$article['id'] ), $result ); } } }
		$articles = $this->articles->get_articles_for_run( $run['id'], array( 'limit'=>100, 'orderby'=>'created_at', 'order'=>'ASC' ) );
		$allowed_routed = $approval ? array( 'pending_approval','approved','draft_created','scheduled','published' ) : array( 'approved','draft_created','scheduled','published' );
		foreach ( $articles as $article ) { if ( ! in_array( $article['status'], $allowed_routed, true ) ) { return $this->handle_article_failure( $run, $token, array( 'code'=>'invalid_article_workflow','retryable'=>false,'article_id'=>$article['id'] ), $result ); } }
		$step = $approval ? 'waiting_article_approval' : 'create_post';
		if ( ! ( $this->runs->update_step( $run['id'], $token, $step )['success'] ?? false ) ) { return $this->failed( $result, 'run_step_update_failed' ); }
		$this->runs->release_lock( $run['id'], $token ); $result['code'] = $approval ? 'articles_generated_waiting_approval' : 'articles_generated_and_approved'; return $result;
	}

	private function evaluate_step( array $profile, array $run, string $token, array $result ): array { $evaluated=$this->evaluator->evaluate($profile,$run['id']);$result['ideas_evaluated']=absint($evaluated['evaluated']??0);if(!($this->runs->refresh_lock($run['id'],$token,900)['success']??false)){return $this->failed($result,'execution_lock_lost');}if($evaluated['success']){$step=$evaluated['next_step'];if(!in_array($step,array('queue_idea','waiting_idea_approval'),true)||!($this->runs->update_step($run['id'],$token,$step)['success']??false)){return $this->failed($result,'run_step_update_failed');}$this->runs->release_lock($run['id'],$token);$result['code']=$evaluated['code'];return $result;}return $this->handle_failure($run,$token,$evaluated,$result); }
	private function handle_article_failure( array $run, string $token, array $failure, array $result ): array { $article_id=absint($failure['article_id']??0);$code=sanitize_key($failure['code']??'article_generation_failed');$fresh=$this->runs->get_by_id($run['id']);$terminal=empty($failure['retryable'])||absint($fresh['attempt_count']??1)>=absint($fresh['max_attempts']??3);if($article_id){$article=$this->articles->get_by_id($article_id);if($terminal){if($article&&in_array($article['status'],array('queued','generating','needs_attention'),true)){$this->articles->transition_status($article_id,array($article['status']),'needs_attention',array('updated_by'=>0,'error_code'=>$code));}}elseif(!empty($failure['retryable'])){$prepared=$this->articles->prepare_generation_retry($article_id,$code,0);if(empty($prepared['success'])){$failure['code']='article_transition_failed';$failure['retryable']=false;}}else{$this->articles->update_error_code($article_id,$code,0);}}return $this->handle_failure($run,$token,$failure,$result); }
	private function handle_failure( array $run, string $token, array $failure, array $result ): array { $result['success']=false;$result['code']=sanitize_key($failure['code']??'worker_step_failed');$fresh=$this->runs->get_by_id($run['id']);$attempt=absint($fresh['attempt_count']??1);$max=absint($fresh['max_attempts']??3);if(!empty($failure['retryable'])&&$attempt<$max){$minutes=array(1=>15,2=>60)[$attempt]??240;$retry=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+'.$minutes.' minutes')->format('Y-m-d H:i:s');if(!($this->runs->schedule_retry($run['id'],$token,$result['code'],$retry)['success']??false)){$this->runs->mark_failed($run['id'],$token,'retry_scheduling_failed');$result['code']='retry_scheduling_failed';}return $result;}$this->runs->mark_failed($run['id'],$token,$result['code']);return $result; }
	private function claimable_steps(): array { return array( 'pending', 'generate_ideas', 'evaluate_ideas', 'queue_idea', 'generate_article', 'create_post', 'generate_featured_image', 'generate_seo', 'apply_seo', 'schedule_post', 'publish_post', 'finalize' ); }
	private function failed( array $result, string $code ): array { $result['success']=false;$result['code']=$code;return $result; }
}
