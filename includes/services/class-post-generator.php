<?php
/**
 * Article draft preparation service.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes and validates generated or manually edited article data.
 */
final class AICS_Post_Generator {
	private const TITLE_MAX_LENGTH = 250;
	private const EXCERPT_MAX_LENGTH = 500;
	private const CONTENT_MAX_LENGTH = 100000;
	private const ALLOWED_HTML = array(
		'p'          => array(),
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'strong'     => array(),
		'em'         => array(),
		'blockquote' => array(),
		'a'          => array(
			'href'  => true,
			'title' => true,
		),
	);

	/**
	 * Validates an AI-generated article.
	 *
	 * @param array<string,mixed> $article Generated structured data.
	 * @return array{title:string,content:string,excerpt:string}|WP_Error
	 */
	public function prepare_generated_article( array $article ) {
		return $this->prepare(
			$article['title'] ?? '',
			$article['excerpt'] ?? '',
			$article['content'] ?? ''
		);
	}

	/**
	 * Strictly validates automation output before persistent storage.
	 *
	 * @param array<string,mixed> $article Generated structured data.
	 * @return array{title:string,content:string,excerpt:string}|WP_Error
	 */
	public function validate_and_prepare_article( array $article ) {
		$content = $article['content'] ?? null;
		if ( ! is_string( $content ) ) {
			return new WP_Error( 'invalid_article_structure', __( 'The generated article structure is invalid.', 'ai-content-studio' ) );
		}

		$unsafe_patterns = array(
			'/<(script|style|iframe|form|input|embed|svg)\b/i',
			'/\son[a-z]+\s*=/i',
			'/\sstyle\s*=/i',
			'/\b(?:javascript|data)\s*:/i',
			'/\[(?:insert|add|replace|write)[^\]]*\]/i',
			'/\bTODO\b/i',
			'/\blorem ipsum\b/i',
			'/\b(?:model|provider) error\b/i',
			'/^\s*\{\s*"article"\s*:/i',
		);
		foreach ( $unsafe_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return new WP_Error( 'unsafe_article_content', __( 'The generated article contains unsafe or incomplete content.', 'ai-content-studio' ) );
			}
		}

		return $this->prepare_generated_article( $article );
	}

	/**
	 * Validates manually edited article fields.
	 *
	 * @param mixed $title   Edited title.
	 * @param mixed $excerpt Edited excerpt.
	 * @param mixed $content Edited article HTML.
	 * @return array{title:string,content:string,excerpt:string}|WP_Error
	 */
	public function prepare_edited_article( $title, $excerpt, $content ) {
		return $this->prepare( $title, $excerpt, $content );
	}

	/**
	 * Creates a native WordPress draft from reviewed article data.
	 *
	 * @param array<string,mixed> $article_data Reviewed temporary article data.
	 * @param array<string,mixed> $context      Sanitized workflow association data.
	 * @return array{success:bool,post_id:int,code:string,message:string}
	 */
	public function create_wordpress_draft( array $article_data, array $context = array() ): array {
		$article = $this->prepare_generated_article( $article_data );

		if ( is_wp_error( $article ) ) {
			return array(
				'success' => false,
				'post_id' => 0,
				'code'    => 'invalid-article-draft',
				'message' => __( 'The reviewed article draft is invalid.', 'ai-content-studio' ),
			);
		}

		$user_id = get_current_user_id();

		if ( $user_id < 1 ) {
			return array(
				'success' => false,
				'post_id' => 0,
				'code'    => 'draft-creation-not-allowed',
				'message' => __( 'You are not allowed to create posts.', 'ai-content-studio' ),
			);
		}

		$metadata = array(
			'_aics_generated_post'       => 1,
			'_aics_source_idea_id'       => sanitize_key( (string) ( $context['idea_id'] ?? '' ) ),
			'_aics_primary_keyword'      => sanitize_text_field( (string) ( $context['primary_keyword'] ?? '' ) ),
			'_aics_search_intent'        => sanitize_key( (string) ( $context['search_intent'] ?? '' ) ),
			'_aics_requested_tone'       => sanitize_key( (string) ( $context['tone'] ?? '' ) ),
			'_aics_requested_length'     => sanitize_key( (string) ( $context['length'] ?? '' ) ),
			'_aics_created_by_user'      => $user_id,
			'_aics_generation_timestamp' => absint( $context['generated_at'] ?? current_time( 'timestamp', true ) ),
		);
		return $this->create_draft_from_article( $article, array( 'author_id' => $user_id ), $metadata );
	}

	/** Creates a native draft from controlled article data and settings. */
	public function create_draft_from_article( array $article_data, array $post_settings, array $metadata = array() ): array {
		$article = $this->prepare_generated_article( $article_data );
		if ( is_wp_error( $article ) ) { return array( 'success'=>false, 'post_id'=>0, 'code'=>'invalid-article-draft', 'message'=>__( 'The reviewed article draft is invalid.', 'ai-content-studio' ) ); }
		$args = array( 'post_type'=>'post', 'post_status'=>'draft', 'post_title'=>$article['title'], 'post_content'=>$article['content'], 'post_excerpt'=>$article['excerpt'], 'post_author'=>absint( $post_settings['author_id'] ?? 0 ) );
		$category_id = absint( $post_settings['category_id'] ?? 0 );
		if ( $category_id > 0 ) { $args['post_category'] = array( $category_id ); }
		$post_id = wp_insert_post( $args, true );
		if ( is_wp_error( $post_id ) || $post_id < 1 ) { return array( 'success'=>false, 'post_id'=>0, 'code'=>'wordpress-draft-creation-failed', 'message'=>__( 'WordPress could not create the draft.', 'ai-content-studio' ) ); }
		$metadata_saved = true;

		foreach ( $metadata as $key => $value ) {
			if ( false === update_post_meta( $post_id, $key, $value ) ) {
				$metadata_saved = false;
			}
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'code'    => $metadata_saved ? 'wordpress-draft-created' : 'wordpress-draft-created-meta-warning',
			'message' => $metadata_saved
				? __( 'WordPress draft created successfully.', 'ai-content-studio' )
				: __( 'The WordPress draft was created, but some association metadata could not be saved.', 'ai-content-studio' ),
		);
	}

	/**
	 * Sanitizes and validates all article fields.
	 *
	 * @param mixed $title   Candidate title.
	 * @param mixed $excerpt Candidate excerpt.
	 * @param mixed $content Candidate article HTML.
	 * @return array{title:string,content:string,excerpt:string}|WP_Error
	 */
	private function prepare( $title, $excerpt, $content ) {
		$title   = is_string( $title ) ? trim( sanitize_text_field( $title ) ) : '';
		$excerpt = is_string( $excerpt ) ? trim( sanitize_textarea_field( $excerpt ) ) : '';
		$content = is_string( $content ) ? trim( wp_kses( $content, self::ALLOWED_HTML ) ) : '';

		if ( '' === $title ) {
			return new WP_Error( 'article-title-required', __( 'Article title is required.', 'ai-content-studio' ) );
		}

		if ( $this->string_length( $title ) > self::TITLE_MAX_LENGTH ) {
			return new WP_Error( 'article-title-too-long', __( 'Article title is too long.', 'ai-content-studio' ) );
		}

		if ( '' === $excerpt ) {
			return new WP_Error( 'article-excerpt-required', __( 'Article excerpt is required.', 'ai-content-studio' ) );
		}

		if ( $this->string_length( $excerpt ) > self::EXCERPT_MAX_LENGTH ) {
			return new WP_Error( 'article-excerpt-too-long', __( 'Article excerpt is too long.', 'ai-content-studio' ) );
		}

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return new WP_Error( 'article-content-required', __( 'Article content is required.', 'ai-content-studio' ) );
		}

		if ( $this->string_length( $content ) > self::CONTENT_MAX_LENGTH ) {
			return new WP_Error( 'article-content-too-large', __( 'Article content is too large.', 'ai-content-studio' ) );
		}

		return array(
			'title'   => $title,
			'content' => $content,
			'excerpt' => $excerpt,
		);
	}

	private function string_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}
}
