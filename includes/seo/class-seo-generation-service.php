<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_SEO_Generation_Service {
	public function __construct( private ?AICS_Provider_Interface $provider = null, private ?AICS_SEO_Prompt_Builder $prompts = null, private ?AICS_SEO_Quality_Analyzer $analyzer = null ) {
		$this->provider = $provider ?? new AICS_OpenAI_Provider();
		$this->prompts = $prompts ?? new AICS_SEO_Prompt_Builder();
		$this->analyzer = $analyzer ?? new AICS_SEO_Quality_Analyzer();
	}

	public function generate( array $article, array $settings, array $context = array() ): AICS_SEO_Generation_Result {
		$target = ( new AICS_SEO_Plugin_Detector() )->resolve( (string) $settings['target_plugin'] )['key'];
		try {
			$request = $this->prompts->build( new AICS_SEO_Generation_Request( $article, $settings, $target, $context ) );
		} catch ( InvalidArgumentException $e ) {
			return AICS_SEO_Generation_Result::failure( 'seo_article_not_found', __( 'The article could not be prepared for SEO generation.', 'ai-content-studio' ) );
		}
		$response = $this->provider->generate( $request );
		if ( ! $response->is_success() ) { return AICS_SEO_Generation_Result::failure( 'seo_generation_failed', __( 'SEO generation could not be completed.', 'ai-content-studio' ) ); }
		$fallback = is_string( $context['fallback_focus_keyword'] ?? null ) ? $context['fallback_focus_keyword'] : '';
		$normalized = $this->normalize_response( $response->get_data(), $fallback );
		if ( is_wp_error( $normalized ) ) { return AICS_SEO_Generation_Result::failure( $normalized->get_error_code(), __( 'The generated SEO response did not pass validation.', 'ai-content-studio' ) ); }
		$data = new AICS_SEO_Data( $normalized );
		$analysis = $this->analyzer->analyze( $data, $article, $settings, $target );
		return AICS_SEO_Generation_Result::success( $data, $analysis, $response->get_provider_name(), AICS_Settings::get_openai_model() );
	}

	public function normalize_response( $raw, string $fallback_focus_keyword = '' ) {
		if ( ! is_array( $raw ) ) { return new WP_Error( 'invalid_seo_generation_response' ); }
		$required = array( 'focus_keyword', 'seo_title', 'meta_description', 'slug', 'excerpt', 'categories', 'tags', 'image_alt_text' );
		foreach ( $required as $key ) {
			if ( 'focus_keyword' !== $key && ! array_key_exists( $key, $raw ) ) { return new WP_Error( 'seo_generation_response_incomplete' ); }
		}
		$raw['focus_keyword'] = $this->resolve_focus_keyword( $raw );
		if ( '' === trim( $raw['focus_keyword'] ) && '' !== trim( $fallback_focus_keyword ) ) { $raw['focus_keyword'] = $fallback_focus_keyword; }
		if ( $raw['focus_keyword'] !== wp_strip_all_tags( $raw['focus_keyword'] ) ) { return new WP_Error( 'invalid_seo_generation_response' ); }
		foreach ( array( 'seo_title', 'meta_description', 'slug', 'excerpt', 'image_alt_text' ) as $key ) {
			if ( ! is_string( $raw[ $key ] ?? null ) || $raw[ $key ] !== wp_strip_all_tags( $raw[ $key ] ) ) { return new WP_Error( 'invalid_seo_generation_response' ); }
		}
		if ( ! is_array( $raw['categories'] ) || ! is_array( $raw['tags'] ) ) { return new WP_Error( 'invalid_seo_generation_response' ); }
		$focus = $this->plain( $raw['focus_keyword'], 150 );
		if ( '' === $focus || str_contains( $focus, "\n" ) || filter_var( $focus, FILTER_VALIDATE_URL ) ) { return new WP_Error( 'seo_focus_keyword_missing' ); }
		$title = trim( $this->plain( $raw['seo_title'], 250 ), '"' );
		if ( '' === $title ) { return new WP_Error( 'seo_title_missing' ); }
		$description = $this->plain( $raw['meta_description'], 500 );
		if ( '' === $description ) { return new WP_Error( 'seo_meta_description_missing' ); }
		$candidate = trim( $raw['slug'] );
		if ( preg_match( '~(?:https?://|[/?#])~i', $candidate ) ) { $candidate = ''; }
		$slug = substr( sanitize_title( $candidate ), 0, 200 );
		if ( '' === $slug ) { return new WP_Error( 'seo_slug_invalid' ); }
		return array( 'focus_keyword' => $focus, 'seo_title' => $title, 'meta_description' => $description, 'slug' => $slug, 'excerpt' => $this->plain( $raw['excerpt'], 1000 ), 'categories' => $this->names( $raw['categories'], 5 ), 'tags' => $this->names( $raw['tags'], 15 ), 'image_alt_text' => $this->plain( $raw['image_alt_text'], 250 ), 'internal_links' => array(), 'external_links' => array() );
	}

	private function resolve_focus_keyword( array $raw ): string {
		$candidate = $raw['focus_keyword'] ?? '';
		if ( ! is_string( $candidate ) || '' === trim( $candidate ) ) { $candidate = $raw['focus_keyphrase'] ?? ''; }
		if ( ! is_string( $candidate ) || '' === trim( $candidate ) ) { $candidate = $raw['primary_keyword'] ?? ''; }
		return is_string( $candidate ) ? $candidate : '';
	}
	private function plain( string $value, int $max ): string { $value = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $value ) ) ?? '' ); return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max, 'UTF-8' ) : substr( $value, 0, $max ); }
	private function names( array $values, int $max ): array { $out=array();$seen=array();foreach($values as $value){if(!is_string($value)||$value!==wp_strip_all_tags($value)){continue;}$value=$this->plain($value,100);$key=function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);if(''!==$value&&!isset($seen[$key])){$out[]=$value;$seen[$key]=true;}}return array_slice($out,0,$max); }
}
