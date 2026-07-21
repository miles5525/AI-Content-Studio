<?php
/**
 * Usage dashboard.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AICS_Dashboard_Page {
	public static function render(): void {
		if ( ! current_user_can( \AIContentStudio\Core\Permissions::manage() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-content-studio' ) );
		}

		$repository = new AICS_Usage_Log_Repository();
		$summary    = $repository->get_summary();
		$summary['drafts_created'] = self::count_generated_wordpress_drafts();
		$activity   = $repository->get_recent( 10 );
		$cards      = array(
			'total_ai_requests'      => __( 'Total AI Requests', 'ai-content-studio' ),
			'successful_ai_requests' => __( 'Successful AI Requests', 'ai-content-studio' ),
			'failed_ai_requests'     => __( 'Failed AI Requests', 'ai-content-studio' ),
			'articles_generated'     => __( 'Articles Generated', 'ai-content-studio' ),
			'drafts_created'         => __( 'WordPress Drafts Created', 'ai-content-studio' ),
		);
		?>
		<div class="wrap aics-admin-wrap aics-dashboard-page">
			<header class="aics-page-header">
				<h1 class="aics-page-title"><?php esc_html_e( 'AI Content Studio Dashboard', 'ai-content-studio' ); ?></h1>
				<p class="aics-page-description"><?php esc_html_e( 'A lightweight summary of AI requests and WordPress drafts created by the plugin.', 'ai-content-studio' ); ?></p>
			</header>
			<div class="aics-dashboard-cards">
				<?php foreach ( $cards as $key => $label ) : ?>
					<div class="aics-dashboard-card aics-dashboard-card--<?php echo esc_attr( 'successful_ai_requests' === $key ? 'success' : ( 'failed_ai_requests' === $key ? 'failed' : 'neutral' ) ); ?>"><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary[ $key ] ) ); ?></strong></div>
				<?php endforeach; ?>
			</div>

			<h2><?php esc_html_e( 'Recent Activity', 'ai-content-studio' ); ?></h2>
			<?php if ( empty( $activity ) ) : ?>
				<div class="aics-dashboard-empty aics-empty-state"><h3><?php esc_html_e( 'No activity yet', 'ai-content-studio' ); ?></h3><p><?php esc_html_e( 'Generate blog ideas or an article to begin building your activity history.', 'ai-content-studio' ); ?></p><a class="button button-primary aics-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=aics-create-content' ) ); ?>"><?php esc_html_e( 'Create Content', 'ai-content-studio' ); ?></a></div>
			<?php else : ?>
				<div class="aics-dashboard-table-wrap"><table class="widefat striped aics-dashboard-table"><thead><tr><th><?php esc_html_e( 'Activity', 'ai-content-studio' ); ?></th><th><?php esc_html_e( 'Status', 'ai-content-studio' ); ?></th><th><?php esc_html_e( 'Provider / Model', 'ai-content-studio' ); ?></th><th><?php esc_html_e( 'User', 'ai-content-studio' ); ?></th><th><?php esc_html_e( 'Related Item', 'ai-content-studio' ); ?></th><th><?php esc_html_e( 'Duration', 'ai-content-studio' ); ?></th><th><?php esc_html_e( 'Date', 'ai-content-studio' ); ?></th></tr></thead><tbody>
				<?php foreach ( $activity as $entry ) : ?><?php self::render_activity_row( $entry ); ?><?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
			<nav class="aics-dashboard-links" aria-label="<?php echo esc_attr__( 'Dashboard quick links', 'ai-content-studio' ); ?>"><a class="button button-primary aics-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=aics-create-content' ) ); ?>"><?php esc_html_e( 'Create Content', 'ai-content-studio' ); ?></a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aics-content-history' ) ); ?>"><?php esc_html_e( 'Content History', 'ai-content-studio' ); ?></a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aics-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'ai-content-studio' ); ?></a></nav>
		</div>
		<?php
	}

	private static function render_activity_row( object $entry ): void {
		$operations = array(
			'openai_connection_test'  => __( 'OpenAI Connection Test', 'ai-content-studio' ),
			'generate_blog_ideas'      => __( 'Blog Ideas Generated', 'ai-content-studio' ),
			'generate_article_draft'   => __( 'Article Draft Generated', 'ai-content-studio' ),
			'create_wordpress_draft'   => __( 'WordPress Draft Created', 'ai-content-studio' ),
		);
		$statuses = array( 'success' => __( 'Success', 'ai-content-studio' ), 'failed' => __( 'Failed', 'ai-content-studio' ) );
		$user     = get_userdata( absint( $entry->user_id ?? 0 ) );
		$provider = sanitize_text_field( (string) ( $entry->provider ?? '' ) );
		$model    = sanitize_text_field( (string) ( $entry->model ?? '' ) );
		$operation = sanitize_key( (string) ( $entry->operation ?? '' ) );
		$status    = sanitize_key( (string) ( $entry->status ?? '' ) );
		$post_id  = absint( $entry->object_id ?? 0 );
		$edit     = $post_id > 0 && current_user_can( 'edit_post', $post_id ) ? get_edit_post_link( $post_id, '' ) : '';
		$duration = absint( $entry->duration_ms ?? 0 );
		$timestamp = strtotime( (string) ( $entry->created_at ?? '' ) . ' UTC' );
		?>
		<tr><td><?php echo esc_html( $operations[ $operation ] ?? __( 'Unknown Activity', 'ai-content-studio' ) ); ?></td><td><span class="aics-status aics-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $statuses[ $status ] ?? __( 'Unknown', 'ai-content-studio' ) ); ?></span></td><td><?php echo esc_html( '' !== $provider ? ucfirst( $provider ) . ( '' !== $model ? ' / ' . $model : '' ) : '—' ); ?></td><td><?php echo esc_html( $user instanceof WP_User ? $user->display_name : __( 'Deleted user', 'ai-content-studio' ) ); ?></td><td><?php if ( is_string( $edit ) && '' !== $edit ) : ?><a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit Draft', 'ai-content-studio' ); ?></a><?php else : ?>—<?php endif; ?></td><td><?php echo esc_html( self::format_duration( $duration ) ); ?></td><td><?php echo esc_html( false !== $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp, wp_timezone() ) : '—' ); ?></td></tr>
		<?php
	}

	private static function format_duration( int $milliseconds ): string {
		if ( $milliseconds < 1 ) { return '—'; }
		if ( $milliseconds < 1000 ) { return sprintf( __( '%d ms', 'ai-content-studio' ), $milliseconds ); }
		return sprintf( __( '%.2f s', 'ai-content-studio' ), $milliseconds / 1000 );
	}

	/**
	 * Counts currently existing AI Content Studio WordPress drafts.
	 *
	 * This deliberately uses native post state instead of usage logs so drafts
	 * created before logging was introduced are included and deleted, trashed,
	 * or status-changed posts are excluded.
	 */
	private static function count_generated_wordpress_drafts(): int {
		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'draft',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => '_aics_generated_post',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		return absint( $query->found_posts );
	}

	private function __construct() {}
}
