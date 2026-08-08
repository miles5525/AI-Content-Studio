<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Manual_SEO_Service {
	public function __construct( private ?AICS_Article_Repository $articles = null, private ?AICS_SEO_Generation_Service $generator = null, private ?AICS_SEO_Quality_Analyzer $analyzer = null ) {
		$this->articles = $articles ?? new AICS_Article_Repository();
		$this->generator = $generator ?? new AICS_SEO_Generation_Service();
		$this->analyzer = $analyzer ?? new AICS_SEO_Quality_Analyzer();
	}

	public function view( int $id, int $user ): array {
		$article = $this->load( $id, $user );
		if ( is_wp_error( $article ) ) { return array( 'success' => false, 'code' => $article->get_error_code() ); }
		$settings = $this->settings( $user );
		$target = $article['seo_target_plugin'] ?: ( new AICS_SEO_Plugin_Detector() )->resolve( $settings['target_plugin'] )['key'];
		return array( 'success' => true, 'article' => $article, 'settings' => $settings, 'target' => $target, 'profile' => AICS_SEO_Recommendation_Profile::get( $target ) );
	}

	public function generate( int $id, int $user ): array {
		$article = $this->load( $id, $user );
		if ( is_wp_error( $article ) ) { return $this->failure( $article->get_error_code() ); }
		if ( 'applied' === $article['seo_status'] ) { return $this->failure( 'seo_regeneration_not_allowed' ); }
		if ( in_array( $article['seo_status'], array( 'generating', 'retrying' ), true ) ) { return $this->failure( 'seo_generation_already_running' ); }
		if ( ! AICS_Settings::has_openai_api_key() ) { return $this->failure( 'seo_provider_not_configured' ); }

		$settings = $this->settings( $user );
		$started = microtime( true );
		$current = $article['seo_status'];
		if ( 'pending' !== $current ) {
			$pending = $this->articles->transition_seo_status( $id, $current, 'pending', '' );
			if ( empty( $pending['success'] ) ) { return $this->failure( 'seo_generation_already_running' ); }
		}
		$transition = $this->articles->transition_seo_status( $id, 'pending', 'generating', '' );
		if ( empty( $transition['success'] ) ) { return $this->failure( 'seo_generation_already_running' ); }

		$result = $this->generator->generate( $article, $settings, array(
			'target_audience' => '',
			'fallback_focus_keyword' => $this->resolve_fallback_focus_keyword( $article, $user ),
		) );
		$duration = AICS_Usage_Logger::duration_ms( $started );
		if ( ! $result->is_success() ) {
			$code = $result->get_code();
			if ( $this->preserve_usable_review( $article, $settings, $user ) ) {
				$this->log( $id, false, $code, $duration );
				return array( 'success' => true, 'code' => 'seo_regeneration_failed_saved_data_preserved', 'message' => __( 'The new SEO generation attempt failed, so the current reviewed SEO data was preserved.', 'ai-content-studio' ) );
			}
			$this->articles->set_seo_failure( $id, 'generating', $code );
			$this->log( $id, false, $code, $duration );
			return $this->failure( $code );
		}

		$analysis = $result->get_analysis();
		$status = ! empty( $analysis['blocking_issues'] ) ? 'review_required' : 'generated';
		$stored = $this->articles->persist_seo_result( $id, $result->get_data(), $analysis, $this->target( $settings ), $status, $user );
		if ( empty( $stored['success'] ) ) {
			$this->articles->set_seo_failure( $id, 'generating', 'seo_persistence_failed' );
			$this->log( $id, false, 'seo_persistence_failed', $duration );
			return $this->failure( 'seo_persistence_failed' );
		}
		$this->log( $id, true, '', $duration );
		return array( 'success' => true, 'code' => 'seo_generated', 'message' => __( 'SEO data generated and analyzed.', 'ai-content-studio' ) );
	}

	public function save( int $id, int $user, array $input ): array {
		$article = $this->load( $id, $user );
		if ( is_wp_error( $article ) ) { return $this->failure( $article->get_error_code() ); }
		$settings = $this->settings( $user );
		$normalized = ( new AICS_SEO_Generation_Service() )->normalize_response( $input );
		if ( is_wp_error( $normalized ) ) { return $this->failure( $normalized->get_error_code() ); }
		$data = new AICS_SEO_Data( $normalized );
		$target = $article['seo_target_plugin'] ?: $this->target( $settings );
		$analysis = $this->analyzer->analyze( $data, $article, $settings, $target );
		$status = ! empty( $analysis['blocking_issues'] ) ? 'review_required' : 'generated';
		$saved = $this->articles->persist_seo_result( $id, $data, $analysis, $target, $status, $user );
		return empty( $saved['success'] ) ? $this->failure( 'seo_persistence_failed' ) : array( 'success' => true, 'code' => 'seo_saved', 'message' => __( 'SEO data saved and reanalyzed without an AI request.', 'ai-content-studio' ) );
	}

	private function load( int $id, int $user ) {
		$article = $this->articles->get_by_id( $id );
		if ( ! $article ) { return new WP_Error( 'seo_article_not_found' ); }
		if ( 'manual' !== $article['source_type'] || $article['created_by'] !== $user ) { return new WP_Error( 'seo_article_source_invalid' ); }
		return $article;
	}

	private function resolve_fallback_focus_keyword( array $article, int $user ): string {
		$candidate = trim( (string) ( $article['seo_focus_keyword'] ?? '' ) );
		if ( '' !== $candidate ) { return $candidate; }
		$post_id = absint( $article['wordpress_post_id'] ?? 0 );
		$post_keyword = $post_id ? trim( (string) get_post_meta( $post_id, '_aics_primary_keyword', true ) ) : '';
		if ( '' !== $post_keyword ) { return $post_keyword; }
		$state = new AICS_Manual_Wizard_State_Service( $user );
		$selected_idea = $state->selected_idea();
		if ( absint( $state->draft()['article_id'] ?? 0 ) === absint( $article['id'] ) && is_string( $selected_idea['primary_keyword'] ?? null ) && '' !== trim( $selected_idea['primary_keyword'] ) ) {
			return trim( $selected_idea['primary_keyword'] );
		}
		return trim( (string) ( $state->context()['topic_keyword'] ?? '' ) );
	}

	private function preserve_usable_review( array $article, array $settings, int $user ): bool {
		$normalized = ( new AICS_SEO_Generation_Service() )->normalize_response( array(
			'focus_keyword' => $article['seo_focus_keyword'] ?? '',
			'seo_title' => $article['seo_title'] ?? '',
			'meta_description' => $article['seo_meta_description'] ?? '',
			'slug' => $article['seo_slug'] ?? '',
			'excerpt' => $article['excerpt'] ?? '',
			'categories' => $article['seo_categories'] ?? array(),
			'tags' => $article['seo_tags'] ?? array(),
			'image_alt_text' => $article['featured_image_alt_text'] ?? '',
		) );
		if ( is_wp_error( $normalized ) ) { return false; }
		$data = new AICS_SEO_Data( $normalized );
		$target = $article['seo_target_plugin'] ?: $this->target( $settings );
		$analysis = $this->analyzer->analyze( $data, $article, $settings, $target );
		if ( ! empty( $analysis['blocking_issues'] ) ) { return false; }
		$saved = $this->articles->persist_seo_result( $article['id'], $data, $analysis, $target, 'generated', $user );
		return ! empty( $saved['success'] );
	}

	private function settings( int $user ): array {
		$state = get_transient( 'aics_content_inputs_' . $user );
		return AICS_SEO_Configuration::normalize_stored( is_array( $state ) ? ( $state['seo_settings'] ?? array() ) : array(), true );
	}
	private function target( array $settings ): string { return ( new AICS_SEO_Plugin_Detector() )->resolve( $settings['target_plugin'] )['key']; }
	private function failure( string $code ): array {
		$messages = array(
			'seo_article_not_found' => __( 'The SEO article was not found.', 'ai-content-studio' ), 'seo_article_source_invalid' => __( 'The Manual Studio article could not be verified.', 'ai-content-studio' ),
			'seo_provider_not_configured' => __( 'Configure the text provider before generating SEO data.', 'ai-content-studio' ), 'seo_generation_already_running' => __( 'SEO generation is already running.', 'ai-content-studio' ),
			'seo_regeneration_not_allowed' => __( 'SEO has already been applied. Use Reapply SEO to verify the current integration without regenerating it.', 'ai-content-studio' ), 'seo_persistence_failed' => __( 'SEO data could not be saved.', 'ai-content-studio' ),
			'seo_generation_failed' => __( 'The AI provider could not generate SEO data. Please retry the SEO step.', 'ai-content-studio' ), 'invalid_seo_generation_response' => __( 'The AI provider returned SEO data in an unexpected format. Please retry.', 'ai-content-studio' ),
			'seo_generation_response_incomplete' => __( 'The AI provider omitted one or more required SEO fields. Please retry.', 'ai-content-studio' ), 'seo_focus_keyword_missing' => __( 'The generated focus keyword was empty or invalid. Please retry.', 'ai-content-studio' ),
			'seo_title_missing' => __( 'The generated SEO title was empty. Please retry.', 'ai-content-studio' ), 'seo_meta_description_missing' => __( 'The generated meta description was empty. Please retry.', 'ai-content-studio' ), 'seo_slug_invalid' => __( 'The generated slug was invalid. Please retry.', 'ai-content-studio' ),
		);
		return array( 'success' => false, 'code' => $code, 'message' => $messages[ $code ] ?? __( 'SEO data could not be validated. Please retry or review the saved fields.', 'ai-content-studio' ) );
	}
	private function log( int $id, bool $success, string $code, int $duration ): void { AICS_Usage_Logger::log( array( 'event_type' => 'ai_request', 'operation' => 'seo_metadata_generation', 'status' => $success ? 'success' : 'failed', 'provider' => 'openai', 'model' => AICS_Settings::get_openai_model(), 'error_code' => $code, 'object_id' => $id, 'duration_ms' => $duration ) ); }
}
