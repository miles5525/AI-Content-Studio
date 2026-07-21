<?php
/**
 * Provider-independent AI orchestration.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates prompt creation, provider generation, and output validation.
 */
final class AICS_AI_Engine {
	private const SEARCH_INTENTS = array( 'informational', 'commercial', 'transactional', 'navigational' );
	private AICS_Provider_Interface $provider;
	private AICS_Prompt_Engine $prompt_engine;
	private AICS_Post_Generator $post_generator;

	public function __construct( ?AICS_Provider_Interface $provider = null, ?AICS_Prompt_Engine $prompt_engine = null, ?AICS_Post_Generator $post_generator = null ) {
		$this->provider      = $provider ?? new AICS_OpenAI_Provider();
		$this->prompt_engine = $prompt_engine ?? new AICS_Prompt_Engine();
		$this->post_generator = $post_generator ?? new AICS_Post_Generator();
	}

	/**
	 * Generates and validates exactly five structured blog ideas.
	 *
	 * @param array<string,mixed> $content_inputs Validated Content Studio state.
	 * @return AICS_AI_Response
	 */
	public function generate_blog_ideas( array $content_inputs ): AICS_AI_Response {
		$inputs = $this->validate_inputs( $content_inputs );

		if ( null === $inputs ) {
			return AICS_AI_Response::failure( 'invalid-content-inputs', __( 'Content inputs are missing or invalid.', 'ai-content-studio' ) );
		}

		try {
			$request = $this->prompt_engine->create_blog_ideas_request( $inputs );
		} catch ( InvalidArgumentException $exception ) {
			return AICS_AI_Response::failure( 'invalid-content-inputs', __( 'Content inputs are missing or invalid.', 'ai-content-studio' ) );
		}

		$response = $this->provider->generate( $request );

		if ( ! $response->is_success() ) {
			return $response;
		}

		$ideas = $this->validate_ideas( $response->get_data() );

		if ( null === $ideas ) {
			return AICS_AI_Response::failure( 'invalid-idea-format', __( 'OpenAI returned an invalid idea format.', 'ai-content-studio' ), $response->get_provider_name(), $response->get_http_status_code() );
		}

		return AICS_AI_Response::success( array( 'ideas' => $ideas ), __( 'Blog ideas generated successfully.', 'ai-content-studio' ), $response->get_provider_name(), $response->get_http_status_code() );
	}

	/**
	 * Generates and validates a complete structured article draft.
	 *
	 * @param array<string,mixed> $content_inputs Validated Content Studio state.
	 * @param array<string,mixed> $selected_idea Selected server-side idea.
	 * @return AICS_AI_Response
	 */
	public function generate_article_draft( array $content_inputs, array $selected_idea ): AICS_AI_Response {
		$inputs = $this->validate_inputs( $content_inputs );
		$idea   = $this->validate_selected_idea( $selected_idea );

		if ( null === $inputs ) {
			return AICS_AI_Response::failure( 'invalid-content-inputs', __( 'Content inputs are missing or invalid.', 'ai-content-studio' ) );
		}

		if ( null === $idea ) {
			return AICS_AI_Response::failure( 'selected-idea-missing', __( 'The selected blog idea is missing or invalid.', 'ai-content-studio' ) );
		}

		try {
			$request = $this->prompt_engine->create_article_draft_request( $inputs, $idea );
		} catch ( InvalidArgumentException $exception ) {
			return AICS_AI_Response::failure( 'invalid-article-request', __( 'The article request could not be prepared.', 'ai-content-studio' ) );
		}

		$response = $this->provider->generate( $request );

		if ( ! $response->is_success() ) {
			return $response;
		}

		$data    = $response->get_data();
		$article = $this->post_generator->prepare_generated_article( is_array( $data ) ? $data : array() );

		if ( is_wp_error( $article ) ) {
			return AICS_AI_Response::failure( $article->get_error_code(), __( 'OpenAI returned an invalid article format.', 'ai-content-studio' ), $response->get_provider_name(), $response->get_http_status_code() );
		}

		return AICS_AI_Response::success( $article, __( 'Article draft generated successfully.', 'ai-content-studio' ), $response->get_provider_name(), $response->get_http_status_code() );
	}

	/**
	 * @param array<string,mixed> $inputs Candidate inputs.
	 * @return array{business_context:string,topic_keyword:string,tone:string,article_length:string}|null
	 */
	private function validate_inputs( array $inputs ): ?array {
		$required = array( 'business_context', 'topic_keyword', 'tone', 'article_length' );

		foreach ( $required as $key ) {
			if ( ! isset( $inputs[ $key ] ) || ! is_string( $inputs[ $key ] ) || '' === trim( $inputs[ $key ] ) ) {
				return null;
			}
		}

		if ( ! in_array( $inputs['tone'], array( 'professional', 'friendly', 'conversational', 'informative', 'persuasive' ), true ) || ! in_array( $inputs['article_length'], array( 'short', 'medium', 'long' ), true ) ) {
			return null;
		}

		return array(
			'business_context' => $inputs['business_context'],
			'topic_keyword'    => $inputs['topic_keyword'],
			'tone'             => $inputs['tone'],
			'article_length'   => $inputs['article_length'],
		);
	}

	/**
	 * @param array<string,mixed> $idea Candidate selected idea.
	 * @return array{id:string,title:string,description:string,primary_keyword:string,search_intent:string}|null
	 */
	private function validate_selected_idea( array $idea ): ?array {
		$required = array( 'id', 'title', 'description', 'primary_keyword', 'search_intent' );

		foreach ( $required as $key ) {
			if ( ! isset( $idea[ $key ] ) || ! is_string( $idea[ $key ] ) || '' === trim( $idea[ $key ] ) ) {
				return null;
			}
		}

		if ( ! in_array( $idea['id'], array( 'idea-1', 'idea-2', 'idea-3', 'idea-4', 'idea-5' ), true ) || ! in_array( $idea['search_intent'], self::SEARCH_INTENTS, true ) ) {
			return null;
		}

		return array(
			'id'              => $idea['id'],
			'title'           => $idea['title'],
			'description'     => $idea['description'],
			'primary_keyword' => $idea['primary_keyword'],
			'search_intent'   => $idea['search_intent'],
		);
	}

	/**
	 * @param array|null $data Provider structured data.
	 * @return array<int,array{id:string,title:string,description:string,primary_keyword:string,search_intent:string}>|null
	 */
	private function validate_ideas( ?array $data ): ?array {
		if ( ! isset( $data['ideas'] ) || ! is_array( $data['ideas'] ) || 5 !== count( $data['ideas'] ) ) {
			return null;
		}

		$ideas       = array();
		$ids         = array();
		$title_keys  = array();
		$allowed_ids = array( 'idea-1', 'idea-2', 'idea-3', 'idea-4', 'idea-5' );

		foreach ( $data['ideas'] as $idea ) {
			if ( ! is_array( $idea ) ) {
				return null;
			}

			$id              = isset( $idea['id'] ) && is_string( $idea['id'] ) ? sanitize_key( $idea['id'] ) : '';
			$title           = isset( $idea['title'] ) && is_string( $idea['title'] ) ? trim( sanitize_text_field( $idea['title'] ) ) : '';
			$description     = isset( $idea['description'] ) && is_string( $idea['description'] ) ? trim( sanitize_text_field( $idea['description'] ) ) : '';
			$primary_keyword = isset( $idea['primary_keyword'] ) && is_string( $idea['primary_keyword'] ) ? trim( sanitize_text_field( $idea['primary_keyword'] ) ) : '';
			$search_intent   = isset( $idea['search_intent'] ) && is_string( $idea['search_intent'] ) ? sanitize_key( $idea['search_intent'] ) : '';
			$title_key       = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );

			if ( ! in_array( $id, $allowed_ids, true ) || isset( $ids[ $id ] ) || '' === $title || '' === $description || '' === $primary_keyword || isset( $title_keys[ $title_key ] ) ) {
				return null;
			}

			if ( $this->string_length( $title ) > 200 || $this->string_length( $description ) > 600 || $this->string_length( $primary_keyword ) > 200 || ! in_array( $search_intent, self::SEARCH_INTENTS, true ) ) {
				return null;
			}

			$ids[ $id ]              = true;
			$title_keys[ $title_key ] = true;
			$ideas[] = compact( 'id', 'title', 'description', 'primary_keyword', 'search_intent' );
		}

		if ( array_diff( $allowed_ids, array_keys( $ids ) ) ) {
			return null;
		}

		usort( $ideas, static fn( array $first, array $second ): int => strcmp( $first['id'], $second['id'] ) );

		return $ideas;
	}

	private function string_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}
}
