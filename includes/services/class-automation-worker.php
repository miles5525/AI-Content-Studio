<?php
/** Processes one supported automation workflow unit per cron invocation. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Worker {
	private AICS_Automation_Run_Repository $runs;
	private AICS_Automation_Profile_Repository $profiles;
	private AICS_Content_Idea_Repository $ideas;
	private AICS_Automation_Idea_Generator $generator;
	private AICS_Automation_Idea_Evaluator $evaluator;
	private AICS_Article_Repository $articles;
	private AICS_Automation_Article_Generator $article_generator;

	public function __construct( ?AICS_Automation_Run_Repository $runs = null, ?AICS_Automation_Profile_Repository $profiles = null, ?AICS_Content_Idea_Repository $ideas = null, ?AICS_Automation_Idea_Generator $generator = null, ?AICS_Automation_Idea_Evaluator $evaluator = null, ?AICS_Article_Repository $articles = null, ?AICS_Automation_Article_Generator $article_generator = null ) {
		$this->runs = $runs ?? new AICS_Automation_Run_Repository(); $this->profiles = $profiles ?? new AICS_Automation_Profile_Repository(); $this->ideas = $ideas ?? new AICS_Content_Idea_Repository();
		$this->generator = $generator ?? new AICS_Automation_Idea_Generator( null, $this->ideas ); $this->evaluator = $evaluator ?? new AICS_Automation_Idea_Evaluator( null, $this->ideas );
		$this->articles = $articles ?? new AICS_Article_Repository(); $this->article_generator = $article_generator ?? new AICS_Automation_Article_Generator( null, $this->ideas, $this->articles );
	}

	public function process(): array {
		$result = array( 'success'=>true,'code'=>'no_claimable_runs','runs_checked'=>0,'runs_claimed'=>0,'ideas_created'=>0,'duplicates'=>0,'ideas_evaluated'=>0,'articles_generated'=>0 );
		try {
			$steps = array( 'pending', 'generate_ideas', 'evaluate_ideas', 'queue_idea', 'generate_article' );
			$candidates = $this->runs->get_claimable_runs( current_time( 'mysql', true ), $steps, 1 );
			if ( ! $candidates ) { return $result; }
			$result['runs_checked'] = 1; $ttl = in_array( $candidates[0]['current_step'], array( 'queue_idea', 'generate_article' ), true ) ? 1800 : 900;
			$claim = $this->runs->claim_run( $candidates[0]['id'], $ttl );
			if ( ! ( $claim['success'] ?? false ) ) { return $this->failed( $result, 'run_claim_failed' ); }
			$result['runs_claimed'] = 1; $token = $claim['lock_token']; $run = $this->runs->get_by_id( $candidates[0]['id'] );
			if ( ! $run ) { return $this->failed( $result, 'run_missing_after_claim' ); }
			$profile = $this->profiles->get_by_id( $run['profile_id'] );
			if ( ! $profile ) { $this->runs->mark_failed( $run['id'], $token, 'automation_profile_missing' ); return $this->failed( $result, 'automation_profile_missing' ); }
			if ( 'active' !== $profile['status'] ) { $this->runs->cancel_claimed_run( $run['id'], $token, 'profile_not_active' ); $result['code'] = 'profile_not_active'; return $result; }
			if ( 'evaluate_ideas' === $run['current_step'] ) { return $this->evaluate_step( $profile, $run, $token, $result ); }
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

	private function article_step( array $profile, array $run, string $token, array $result ): array {
		$idea = $this->ideas->get_next_article_idea_for_run( $run['id'], $profile['id'] );
		if ( ! $idea ) { return $this->route_articles( $profile, $run, $token, $result ); }
		if ( 'queued' === $idea['status'] ) {
			$transition = $this->ideas->transition_status( $idea['id'], array( 'queued' ), 'article_generating', array( 'updated_by'=>0 ) );
			if ( ! $transition['success'] ) { return $this->handle_failure( $run, $token, array( 'code'=>'idea_transition_failed','retryable'=>true ), $result ); }
			$idea = $this->ideas->get_by_id( $idea['id'] );
		}
		$placeholder = $this->articles->create_for_idea( $idea['id'], array( 'created_by'=>0 ) );
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
	private function handle_article_failure( array $run, string $token, array $failure, array $result ): array { $article_id=absint($failure['article_id']??0);if($article_id){$this->articles->update_error_code($article_id,$failure['code']??'article_generation_failed',0);}$fresh=$this->runs->get_by_id($run['id']);if(empty($failure['retryable'])||absint($fresh['attempt_count']??1)>=absint($fresh['max_attempts']??3)){if($article_id){$article=$this->articles->get_by_id($article_id);if($article&&in_array($article['status'],array('queued','generating','needs_attention'),true)){$this->articles->transition_status($article_id,array($article['status']),'failed',array('updated_by'=>0,'error_code'=>'retries_exhausted'));}$idea=$article?$this->ideas->get_by_id($article['idea_id']):null;if($idea&&'article_generating'===$idea['status']){$this->ideas->transition_status($idea['id'],array('article_generating'),'failed',array('updated_by'=>0,'error_code'=>'retries_exhausted'));}}}return $this->handle_failure($run,$token,$failure,$result); }
	private function handle_failure( array $run, string $token, array $failure, array $result ): array { $result['success']=false;$result['code']=sanitize_key($failure['code']??'worker_step_failed');$fresh=$this->runs->get_by_id($run['id']);$attempt=absint($fresh['attempt_count']??1);$max=absint($fresh['max_attempts']??3);if(!empty($failure['retryable'])&&$attempt<$max){$minutes=array(1=>15,2=>60)[$attempt]??240;$retry=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+'.$minutes.' minutes')->format('Y-m-d H:i:s');if(!($this->runs->schedule_retry($run['id'],$token,$result['code'],$retry)['success']??false)){$this->runs->mark_failed($run['id'],$token,'retry_scheduling_failed');$result['code']='retry_scheduling_failed';}return $result;}$this->runs->mark_failed($run['id'],$token,$result['code']);return $result; }
	private function failed( array $result, string $code ): array { $result['success']=false;$result['code']=$code;return $result; }
}
