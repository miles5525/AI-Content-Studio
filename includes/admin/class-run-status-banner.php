<?php
/**
 * Shared admin automation run status.
 *
 * @package AIContentStudio
 */

namespace AIContentStudio\Admin;

use AIContentStudio\Core\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies friendly, read-only run status data to plugin admin screens.
 */
final class Run_Status_Banner {
	private const ACTIVE_STATUSES = array( 'queued', 'running', 'retrying' );

	/** Registers the read-only AJAX endpoint. */
	public function register(): void {
		add_action( 'wp_ajax_aics_admin_run_status', array( $this, 'handle_ajax' ) );
	}

	/** Returns the current banner payload. */
	public function handle_ajax(): void {
		check_ajax_referer( 'aics_admin_run_status', 'nonce' );

		if ( ! current_user_can( Permissions::manage() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to view automation runs.', 'ai-content-studio' ) ), 403 );
		}

		$status                    = $this->get_status();
		$status['published_posts'] = $this->claim_published_post_notifications( get_current_user_id() );
		wp_send_json_success( $status );
	}

	/** Builds a presentation-safe payload without exposing internal step names. */
	private function get_status(): array {
		$repository = new \AICS_Automation_Run_Repository();
		$processing = array();

		foreach ( self::ACTIVE_STATUSES as $status ) {
			$runs = $repository->get_runs(
				array(
					'status'  => $status,
					'orderby' => 'updated_at',
					'order'   => 'DESC',
					'limit'   => 100,
				)
			);
			foreach ( $runs as $run ) {
				if ( $this->is_processing_run( $run ) ) {
					$processing[] = $run;
				}
			}
		}

		if ( $processing ) {
			usort( $processing, static fn( array $left, array $right ): int => strcmp( $right['updated_at'], $left['updated_at'] ) );
			$latest       = $processing[0];
			$active_count = count( $processing );
			$stage = $this->friendly_stage( $latest );

			if ( $active_count > 1 ) {
				return array(
					'visible'      => true,
					'tone'         => 'active',
					'animated'     => true,
					'title'        => sprintf( _n( '%s post is being prepared', '%s posts are being prepared', $active_count, 'ai-content-studio' ), number_format_i18n( $active_count ) ),
					'description'  => sprintf( __( 'Latest: %1$s. %2$s', 'ai-content-studio' ), $stage['label'], $stage['description'] ),
					'action_label' => __( 'View Runs', 'ai-content-studio' ),
					'action_url'   => $this->runs_url(),
				);
			}

			return array(
				'visible'      => true,
				'tone'         => 'active',
				'animated'     => true,
				'title'        => sprintf( __( 'Preparing your post — %s', 'ai-content-studio' ), $stage['label'] ),
				'description'  => $stage['description'],
				'action_label' => __( 'View Run', 'ai-content-studio' ),
				'action_url'   => $this->run_url( $latest['id'] ),
			);
		}

		return array( 'visible' => false );
	}

	/** Maps every technical workflow step to a user-facing stage. */
	private function friendly_stage( array $run ): array {
		$step = $run['current_step'];
		$map  = array(
			'pending'                  => array( __( 'Preparing Ideas', 'ai-content-studio' ), __( 'AI Content Studio is preparing topic ideas for your post.', 'ai-content-studio' ) ),
			'generate_ideas'           => array( __( 'Preparing Ideas', 'ai-content-studio' ), __( 'AI Content Studio is preparing topic ideas for your post.', 'ai-content-studio' ) ),
			'evaluate_ideas'           => array( __( 'Evaluating Ideas', 'ai-content-studio' ), __( 'The strongest ideas are being reviewed for quality.', 'ai-content-studio' ) ),
			'select_ideas'             => array( __( 'Evaluating Ideas', 'ai-content-studio' ), __( 'The strongest ideas are being selected for this publishing cycle.', 'ai-content-studio' ) ),
			'queue_idea'               => array( __( 'Writing Article', 'ai-content-studio' ), __( 'The selected idea is being prepared for article writing.', 'ai-content-studio' ) ),
			'generate_article'         => array( __( 'Writing Article', 'ai-content-studio' ), __( 'AI Content Studio is writing your article.', 'ai-content-studio' ) ),
			'validate_article'         => array( __( 'Writing Article', 'ai-content-studio' ), __( 'The article is being checked before it is added to WordPress.', 'ai-content-studio' ) ),
			'create_post'              => array( __( 'Creating WordPress Post', 'ai-content-studio' ), __( 'Your generated article is being saved as a WordPress post.', 'ai-content-studio' ) ),
			'generate_featured_image'  => array( __( 'Generating Featured Image', 'ai-content-studio' ), __( 'A featured image is being generated and attached.', 'ai-content-studio' ) ),
			'generate_seo'             => array( __( 'Optimizing SEO', 'ai-content-studio' ), __( 'SEO details are being generated for your post.', 'ai-content-studio' ) ),
			'apply_seo'                => array( __( 'Optimizing SEO', 'ai-content-studio' ), __( 'The generated SEO details are being applied to your post.', 'ai-content-studio' ) ),
			'schedule_post'            => array( __( 'Scheduled for Publishing', 'ai-content-studio' ), __( 'Your post is being prepared for its scheduled publishing time.', 'ai-content-studio' ) ),
			'publish_post'             => array( __( 'Publishing', 'ai-content-studio' ), __( 'Your post is being published to WordPress.', 'ai-content-studio' ) ),
			'waiting_idea_approval'    => array( __( 'Waiting for Approval', 'ai-content-studio' ), __( 'An idea needs your approval before preparation can continue.', 'ai-content-studio' ) ),
			'waiting_article_approval' => array( __( 'Waiting for Approval', 'ai-content-studio' ), __( 'An article needs your approval before preparation can continue.', 'ai-content-studio' ) ),
			'waiting_publish_approval' => array( __( 'Waiting for Approval', 'ai-content-studio' ), __( 'Publishing needs your approval before the post can continue.', 'ai-content-studio' ) ),
			'finalize'                 => array( __( 'Preparing for Publishing', 'ai-content-studio' ), __( 'Final checks are being completed for your post.', 'ai-content-studio' ) ),
			'complete'                 => array( __( 'Completed', 'ai-content-studio' ), __( 'Preparation has completed successfully.', 'ai-content-studio' ) ),
		);

		if ( 'failed' === $run['status'] || ( 'retrying' !== $run['status'] && '' !== $run['last_error_code'] ) ) {
			return array( 'label' => __( 'Needs Attention', 'ai-content-studio' ), 'description' => __( 'This automation run needs review before it can continue.', 'ai-content-studio' ) );
		}

		$stage = $map[ $step ] ?? $map['pending'];
		return array( 'label' => $stage[0], 'description' => $stage[1] );
	}

	private function is_processing_run( array $run ): bool {
		return in_array( $run['status'], self::ACTIVE_STATUSES, true )
			&& ( 'retrying' === $run['status'] || '' === $run['last_error_code'] )
			&& in_array( $run['current_step'], array( 'pending', 'generate_ideas', 'evaluate_ideas', 'select_ideas', 'queue_idea', 'generate_article', 'validate_article', 'create_post', 'generate_featured_image', 'generate_seo', 'apply_seo', 'publish_post', 'finalize' ), true );
	}

	private function run_url( int $run_id ): string {
		return add_query_arg( array( 'page' => 'aics-settings', 'section' => 'automation-runs', 'view' => 'details', 'run_id' => $run_id ), admin_url( 'admin.php' ) );
	}

	private function runs_url(): string {
		return add_query_arg( array( 'page' => 'aics-settings', 'section' => 'automation-runs' ), admin_url( 'admin.php' ) );
	}

	/** Claims newly published automation posts once for the current administrator. */
	private function claim_published_post_notifications( int $user_id ): array {
		$baseline_key = '_aics_published_notice_started_at';
		$baseline     = get_user_meta( $user_id, $baseline_key, true );
		if ( ! is_string( $baseline ) || '' === $baseline ) {
			add_user_meta( $user_id, $baseline_key, current_time( 'mysql', true ), true );
			return array();
		}

		$articles = ( new \AICS_Article_Repository() )->get_articles(
			array(
				'source_type' => 'automation',
				'status'      => 'published',
				'orderby'     => 'published_at',
				'order'       => 'DESC',
				'limit'       => 100,
			)
		);
		$notifications = array();
		foreach ( array_reverse( $articles ) as $article ) {
			$post_id      = absint( $article['wordpress_post_id'] ?? 0 );
			$published_at = $article['published_at'] ?? null;
			$run_id       = absint( $article['run_id'] ?? 0 );
			if ( ! $post_id || ! $run_id || ! is_string( $published_at ) || $published_at < $baseline ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
				continue;
			}
			$url = get_permalink( $post );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$claim_key = '_aics_published_notice_' . absint( $article['id'] ) . '_' . $post_id;
			if ( ! add_user_meta( $user_id, $claim_key, $published_at, true ) ) {
				continue;
			}
			$notifications[] = array(
				'key'   => absint( $article['id'] ) . ':' . $post_id,
				'title' => get_the_title( $post ),
				'url'   => esc_url_raw( $url ),
			);
			break;
		}
		return $notifications;
	}
}
