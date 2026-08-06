<?php
/**
 * SEO quality gate.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates generated SEO data before native application.
 */
final class AICS_SEO_Quality_Gate {
	private const BLOCKING_ANALYSIS_IDS = array(
		'focus_keyword_exists',
		'seo_title_exists',
		'meta_description_exists',
		'slug_structure',
		'word_count',
		'excerpt',
	);

	/**
	 * Evaluates the configured gate.
	 *
	 * @param array<string,mixed> $article  Article data.
	 * @param array<string,mixed> $settings SEO settings.
	 * @param bool                $applied   Whether metadata was applied.
	 * @param bool                $verified  Whether the adapter verified it.
	 * @return array<string,mixed>
	 */
	public function evaluate( array $article, array $settings, bool $applied = false, bool $verified = false ): array {
		$quality_gate = AICS_SEO_Configuration::quality_gate( $settings );
		$analysis     = is_array( $article['seo_analysis'] ?? null ) ? $article['seo_analysis'] : array();
		$raw_score    = $analysis['score'] ?? $analysis['readiness_score'] ?? 0;
		$score        = is_numeric( $raw_score ) ? (float) $raw_score : 0.0;
		$score        = max( 0.0, min( 100.0, $score ) );
		$minimum      = (float) $quality_gate['minimum_readiness_score'];

		$missing = array();
		foreach ( array( 'seo_focus_keyword', 'seo_title', 'seo_meta_description', 'seo_slug', 'excerpt' ) as $field ) {
			$value = $article[ $field ] ?? '';
			if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
				$missing[] = $field;
			}
		}
		if ( empty( $analysis ) || ! isset( $analysis['score'] ) ) {
			$missing[] = 'analysis';
		}

		$analysis_blockers = array();
		$warnings          = is_array( $analysis['warnings'] ?? null ) ? $analysis['warnings'] : array();
		$blocking_issues   = is_array( $analysis['blocking_issues'] ?? null ) ? $analysis['blocking_issues'] : array();
		foreach ( $blocking_issues as $issue ) {
			$raw_id = is_array( $issue ) ? ( $issue['id'] ?? '' ) : $issue;
			$id     = is_scalar( $raw_id ) ? sanitize_key( (string) $raw_id ) : '';
			if ( in_array( $id, self::BLOCKING_ANALYSIS_IDS, true ) ) {
				$analysis_blockers[] = $id;
			} elseif ( '' !== $id ) {
				$warnings[] = is_array( $issue ) ? $issue : array( 'id' => $id, 'message' => $id );
			}
		}

		$reasons = array();
		if ( $quality_gate['require_core_metadata'] && $missing ) {
			$reasons[] = 'seo_core_metadata_missing';
		}
		if ( $quality_gate['enabled'] && $score < $minimum ) {
			$reasons[] = 'seo_readiness_below_minimum';
		}
		if ( $analysis_blockers ) {
			$reasons[] = 'seo_blocking_analysis_issue';
		}
		if ( $quality_gate['require_successful_application'] && ! $applied ) {
			$reasons[] = 'seo_native_application_failed';
		}
		if ( $quality_gate['require_adapter_verification'] && ! $verified ) {
			$reasons[] = 'seo_adapter_verification_failed';
		}

		$environment = array();
		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			$environment[] = array(
				'id'      => 'site_noindex',
				'message' => __( 'This site discourages search engines from indexing it. This environment notice does not affect article SEO Readiness.', 'ai-content-studio' ),
			);
		}

		return array(
			'passed'                 => empty( $reasons ),
			'score'                  => (int) round( $score ),
			'minimum'                => (int) round( $minimum ),
			'missing_fields'         => array_values( array_unique( $missing ) ),
			'failed_conditions'      => array_values( array_unique( $reasons ) ),
			'blocking_analysis_ids'  => array_values( array_unique( $analysis_blockers ) ),
			'warnings'               => array_values( $warnings ),
			'environment_notices'    => $environment,
			'configuration'          => $quality_gate,
		);
	}
}
