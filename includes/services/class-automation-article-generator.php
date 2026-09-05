<?php
/** Automated persistent article generation through the existing AI engine. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Article_Generator {
	private AICS_AI_Engine $engine;
	private AICS_Content_Idea_Repository $ideas;
	private AICS_Article_Repository $articles;

	public function __construct( ?AICS_AI_Engine $engine = null, ?AICS_Content_Idea_Repository $ideas = null, ?AICS_Article_Repository $articles = null ) {
		$this->engine   = $engine ?? new AICS_AI_Engine();
		$this->ideas    = $ideas ?? new AICS_Content_Idea_Repository();
		$this->articles = $articles ?? new AICS_Article_Repository();
	}

	/** Generates or idempotently recovers one persistent article. */
	public function generate( array $profile, $run_id, $idea_id ): array {
		$result = array( 'success' => false, 'code' => 'article_generation_not_started', 'retryable' => false, 'article_id' => 0, 'idea_id' => absint( $idea_id ), 'provider_called' => false );
		$run_id = absint( $run_id ); $profile_id = absint( $profile['id'] ?? 0 );
		$idea = $this->ideas->get_by_id( $result['idea_id'] );
		if ( 0 === $run_id || 0 === $profile_id || ! $idea || $idea['run_id'] !== $run_id || $idea['profile_id'] !== $profile_id || 'automation' !== $idea['source_type'] ) {
			$result['code'] = $idea ? 'invalid_article_workflow' : 'idea_not_found'; return $result;
		}

		$created = $this->articles->create_for_idea( $idea['id'], array( 'created_by' => 0 ) );
		if ( ! $created['success'] ) { $result['code'] = $created['code']; $result['retryable'] = 'database_insert_failed' === $created['code']; return $result; }
		$result['article_id'] = absint( $created['article_id'] );
		$article = $this->articles->get_by_id( $result['article_id'] );
		if ( ! $article || $article['idea_id'] !== $idea['id'] || $article['run_id'] !== $run_id || $article['profile_id'] !== $profile_id ) { $result['code'] = 'invalid_article_workflow'; return $result; }

		if ( $this->has_generated_content( $article ) ) {
			return $this->finish_idea( $idea, $result, 'article_already_generated' );
		}
		if ( ! in_array( $article['status'], array( 'queued', 'generating', 'failed', 'needs_attention' ), true ) || ! in_array( $idea['status'], array( 'queued', 'article_generating' ), true ) ) {
			$result['code'] = 'invalid_article_workflow'; return $result;
		}

		$business = is_array( $profile['business_context'] ?? null ) ? $profile['business_context'] : array();
		$settings = is_array( $profile['content_settings'] ?? null ) ? $profile['content_settings'] : array();
		$tone = sanitize_key( (string) ( $settings['default_tone'] ?? '' ) );
		$length = sanitize_key( (string) ( $settings['article_length'] ?? '' ) );
		if ( ! in_array( $tone, array( 'professional', 'friendly', 'conversational', 'informative', 'persuasive' ), true ) || ! in_array( $length, array( 'short', 'medium', 'long' ), true ) ) { $result['code'] = 'invalid_automation_profile'; return $result; }
		if ( ! AICS_Settings::has_openai_api_key() ) { $result['code'] = 'missing_api_configuration'; return $result; }

		$attempt = $this->articles->increment_generation_attempt( $article['id'], 0 );
		if ( ! $attempt['success'] ) { $result['code'] = 'article_attempt_increment_failed'; $result['retryable'] = true; return $result; }
		$started = microtime( true );
		$result['provider_called'] = true;
		$response = $this->engine->generate_automation_article( $business, $settings, $idea );
		$duration = AICS_Usage_Logger::duration_ms( $started );
		if ( ! $response->is_success() ) {
			$code = $this->provider_code( $response->get_error_code() );
			$this->articles->update_error_code( $article['id'], $code, 0 );
			$this->log( $article['id'], false, $code, $duration, $length, $tone );
			$result['code'] = $code; $result['retryable'] = $this->retryable( $response->get_error_code() ); return $result;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! isset( $data['article'] ) || ! is_array( $data['article'] ) ) {
			$this->articles->update_error_code( $article['id'], 'invalid_article_structure', 0 );
			$this->log( $article['id'], false, 'invalid_article_structure', $duration, $length, $tone );
			$result['code'] = 'invalid_article_structure'; $result['retryable'] = true; return $result;
		}
		$stored = $this->articles->store_generated_content( $article['id'], $data['article'], 0 );
		if ( ! $stored['success'] ) {
			$this->articles->update_error_code( $article['id'], 'article_persistence_failed', 0 );
			$this->log( $article['id'], false, 'article_persistence_failed', $duration, $length, $tone );
			$result['code'] = 'article_persistence_failed'; $result['retryable'] = true; return $result;
		}
		$fresh = $this->articles->get_by_id( $article['id'] );
		if ( ! $fresh || ! $this->has_generated_content( $fresh ) ) {
			$this->log( $article['id'], false, 'article_persistence_failed', $duration, $length, $tone );
			$result['code'] = 'article_persistence_failed'; $result['retryable'] = true; return $result;
		}
		$this->log( $article['id'], true, '', $duration, $length, $tone );
		return $this->finish_idea( $idea, $result, 'article_generation_completed' );
	}

	private function finish_idea( array $idea, array $result, string $code ): array {
		if ( 'article_generating' === $idea['status'] ) {
			$transition = $this->ideas->transition_status( $idea['id'], array( 'article_generating' ), 'article_generated', array( 'updated_by' => 0 ) );
			if ( ! $transition['success'] ) { $fresh = $this->ideas->get_by_id( $idea['id'] ); if ( ! $fresh || ! in_array( $fresh['status'], array( 'article_generated', 'completed' ), true ) ) { $result['code'] = 'idea_transition_failed'; $result['retryable'] = true; return $result; } }
		} elseif ( ! in_array( $idea['status'], array( 'article_generated', 'completed' ), true ) ) { $result['code'] = 'invalid_article_workflow'; return $result; }
		$result['success'] = true; $result['code'] = $code; return $result;
	}

	private function has_generated_content( array $article ): bool { return in_array( $article['status'], array( 'generated', 'pending_approval', 'approved', 'draft_created', 'scheduled', 'published' ), true ) && '' !== trim( (string) $article['title'] ) && '' !== trim( (string) $article['excerpt'] ) && '' !== trim( (string) $article['content'] ) && 64 === strlen( (string) $article['content_hash'] ) && null !== $article['generated_at']; }
	private function provider_code( string $code ): string { $controlled=array('missing_article_title','missing_article_excerpt','missing_article_content','invalid_article_field_type','article_title_too_long','article_excerpt_too_long','article_content_too_long','article_content_too_short','article_content_empty_after_sanitization','article_contains_placeholder_text','article_contains_raw_json_wrapper','article_contains_disallowed_table','article_markup_normalization_failed','article_content_structure_invalid','unsafe_script_element','unsafe_style_element','unsafe_embedded_content','unsafe_form_element','unsafe_event_handler','unsafe_url_scheme','unsafe_svg_content','unsafe_active_markup');if(in_array($code,$controlled,true)){return $code;}$map = array( 'missing-api-key'=>'missing_api_configuration','invalid-api-key'=>'invalid_api_configuration','rate-limit'=>'provider_rate_limit','network-error'=>'provider_network_error','invalid-response'=>'invalid_article_json','invalid-article-format'=>'invalid_article_json','missing-article-object'=>'missing_article_object','invalid-article-structure'=>'invalid_article_structure','unsafe_article_content'=>'unsafe_article_content','article-title-required'=>'missing_article_title','article-excerpt-required'=>'missing_article_excerpt','article-content-required'=>'missing_article_content','article-title-too-long'=>'article_title_too_long','article-excerpt-too-long'=>'article_excerpt_too_long','article-content-too-large'=>'article_content_too_long','quota-error'=>'provider_quota_error','model-unavailable'=>'provider_model_unavailable','invalid-automation-article-input'=>'invalid_automation_profile' ); return $map[ $code ] ?? 'provider_request_failed'; }
	private function retryable( string $code ): bool { return ! in_array( $code, array( 'missing-api-key', 'invalid-api-key', 'quota-error', 'model-unavailable', 'unsupported-task', 'invalid-automation-article-input' ), true ); }
	private function log( int $article_id, bool $success, string $code, int $duration, string $length, string $tone ): void { $category='';if(0===strpos($code,'unsafe_')){$category=in_array($code,array('unsafe_event_handler'),true)?'dangerous_attribute':('unsafe_url_scheme'===$code?'dangerous_url':'dangerous_markup');}elseif(in_array($code,array('article_content_too_short'),true)){$category='length';}elseif(in_array($code,array('article_content_empty_after_sanitization'),true)){$category='sanitization';}elseif(in_array($code,array('missing_article_title','missing_article_excerpt','missing_article_content','invalid_article_field_type','article_contains_placeholder_text','article_contains_raw_json_wrapper','article_contains_disallowed_table','article_markup_normalization_failed','article_content_structure_invalid'),true)){$category='structure';}$metadata=array('requested_length'=>$length,'tone'=>$tone,'source'=>'automation');if(''!==$category){$metadata['validation_category']=$category;}AICS_Usage_Logger::log( array( 'user_id'=>0,'event_type'=>'ai_request','operation'=>'generate_article_draft','status'=>$success?'success':'failed','provider'=>'openai','model'=>AICS_Settings::get_openai_model(),'error_code'=>$code,'object_id'=>$article_id,'duration_ms'=>$duration,'metadata'=>$metadata ) ); }
}
