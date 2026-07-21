<?php
/**
 * Content History admin page.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists native WordPress posts created by AI Content Studio.
 */
final class AICS_Content_History_Page {
	private const PAGE_SLUG = 'aics-content-history';
	private const PER_PAGE = 10;
	private const STATUS_FILTERS = array(
		'all'     => true,
		'draft'   => true,
		'publish' => true,
		'future'  => true,
		'pending' => true,
	);

	/**
	 * Renders the protected history page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( \AIContentStudio\Core\Permissions::manage() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-content-studio' ) );
		}

		$status = self::get_status_filter();
		$paged  = self::get_page_number();
		$query  = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'all' === $status ? array( 'draft', 'pending', 'future', 'publish', 'private' ) : array( $status ),
				'posts_per_page' => self::PER_PAGE,
				'paged'          => $paged,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'perm'           => 'readable',
				'meta_query'     => array(
					array(
						'key'     => '_aics_generated_post',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);
		?>
		<div class="wrap aics-admin-wrap aics-content-history-page">
			<h1><?php esc_html_e( 'Content History', 'ai-content-studio' ); ?></h1>
			<p><?php esc_html_e( 'Native WordPress posts created by AI Content Studio appear here. Temporary articles that were never converted into posts are not included.', 'ai-content-studio' ); ?></p>
			<?php self::render_status_filters( $status ); ?>

			<?php if ( ! $query->have_posts() ) : ?>
				<div class="aics-history-empty">
					<p><?php esc_html_e( 'No AI-generated WordPress posts were found.', 'ai-content-studio' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=aics-create-content' ) ); ?>"><?php esc_html_e( 'Create Content', 'ai-content-studio' ); ?></a>
				</div>
			<?php else : ?>
				<div class="aics-history-table-wrap">
					<table class="widefat fixed striped aics-history-table">
						<thead><?php self::render_table_header(); ?></thead>
						<tbody>
							<?php foreach ( $query->posts as $post ) : ?>
								<?php if ( current_user_can( 'read_post', $post->ID ) || current_user_can( 'edit_post', $post->ID ) ) : ?>
									<?php self::render_row( $post ); ?>
								<?php endif; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php self::render_pagination( $query, $status, $paged ); ?>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	private static function render_status_filters( string $current ): void {
		?>
		<ul class="subsubsub aics-history-filters">
			<?php $position = 0; ?>
			<?php foreach ( array_keys( self::STATUS_FILTERS ) as $value ) : ?>
				<?php
				++$position;
				$url = add_query_arg(
					array(
						'page'        => self::PAGE_SLUG,
						'aics_status' => $value,
					),
					admin_url( 'admin.php' )
				);
				?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>"<?php echo $current === $value ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( self::get_filter_label( $value ) ); ?></a><?php echo $position < count( self::STATUS_FILTERS ) ? ' |' : ''; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="clear"></div>
		<?php
	}

	private static function render_table_header(): void {
		?>
		<tr>
			<th scope="col"><?php esc_html_e( 'Title', 'ai-content-studio' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'ai-content-studio' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Primary Keyword', 'ai-content-studio' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Search Intent', 'ai-content-studio' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Tone', 'ai-content-studio' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Length', 'ai-content-studio' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Author', 'ai-content-studio' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Created', 'ai-content-studio' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Actions', 'ai-content-studio' ); ?></th>
		</tr>
		<?php
	}

	private static function render_row( WP_Post $post ): void {
		$title       = '' !== trim( $post->post_title ) ? $post->post_title : __( '(no title)', 'ai-content-studio' );
		$edit_link   = current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID, '' ) : '';
		$can_view    = current_user_can( 'read_post', $post->ID ) && is_post_publicly_viewable( $post );
		$author      = get_userdata( (int) $post->post_author );
		$timestamp   = get_post_timestamp( $post );
		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<tr>
			<td data-colname="<?php echo esc_attr__( 'Title', 'ai-content-studio' ); ?>">
				<?php if ( is_string( $edit_link ) && '' !== $edit_link ) : ?>
					<a href="<?php echo esc_url( $edit_link ); ?>"><strong><?php echo esc_html( $title ); ?></strong></a>
				<?php else : ?>
					<strong><?php echo esc_html( $title ); ?></strong>
				<?php endif; ?>
			</td>
			<td data-colname="<?php echo esc_attr__( 'Status', 'ai-content-studio' ); ?>"><?php echo esc_html( self::get_status_label( $post->post_status ) ); ?></td>
			<td data-colname="<?php echo esc_attr__( 'Primary Keyword', 'ai-content-studio' ); ?>"><?php echo esc_html( self::get_text_meta( $post->ID, '_aics_primary_keyword' ) ); ?></td>
			<td data-colname="<?php echo esc_attr__( 'Search Intent', 'ai-content-studio' ); ?>"><?php echo esc_html( self::get_mapped_meta( $post->ID, '_aics_search_intent', self::get_intent_labels() ) ); ?></td>
			<td data-colname="<?php echo esc_attr__( 'Tone', 'ai-content-studio' ); ?>"><?php echo esc_html( self::get_mapped_meta( $post->ID, '_aics_requested_tone', self::get_tone_labels() ) ); ?></td>
			<td data-colname="<?php echo esc_attr__( 'Length', 'ai-content-studio' ); ?>"><?php echo esc_html( self::get_mapped_meta( $post->ID, '_aics_requested_length', self::get_length_labels() ) ); ?></td>
			<td data-colname="<?php echo esc_attr__( 'Author', 'ai-content-studio' ); ?>"><?php echo esc_html( $author instanceof WP_User ? $author->display_name : '—' ); ?></td>
			<td data-colname="<?php echo esc_attr__( 'Created', 'ai-content-studio' ); ?>"><?php echo esc_html( $timestamp > 0 ? wp_date( $date_format, $timestamp, wp_timezone() ) : '—' ); ?></td>
			<td data-colname="<?php echo esc_attr__( 'Actions', 'ai-content-studio' ); ?>">
				<?php if ( is_string( $edit_link ) && '' !== $edit_link ) : ?><a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit', 'ai-content-studio' ); ?></a><?php endif; ?>
				<?php if ( $can_view ) : ?><?php echo is_string( $edit_link ) && '' !== $edit_link ? ' | ' : ''; ?><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php esc_html_e( 'View', 'ai-content-studio' ); ?></a><?php endif; ?>
				<?php if ( ( ! is_string( $edit_link ) || '' === $edit_link ) && ! $can_view ) : ?>—<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private static function render_pagination( WP_Query $query, string $status, int $paged ): void {
		if ( $query->max_num_pages < 2 ) {
			return;
		}

		$big  = 999999999;
		$base = str_replace(
			(string) $big,
			'%#%',
			add_query_arg(
				array(
					'page'        => self::PAGE_SLUG,
					'aics_status' => $status,
					'paged'       => $big,
				),
				admin_url( 'admin.php' )
			)
		);
		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => $paged,
				'total'     => (int) $query->max_num_pages,
				'type'      => 'array',
				'prev_text' => __( '&laquo; Previous', 'ai-content-studio' ),
				'next_text' => __( 'Next &raquo;', 'ai-content-studio' ),
			)
		);

		if ( ! is_array( $links ) ) {
			return;
		}
		?>
		<nav class="tablenav-pages aics-history-pagination" aria-label="<?php echo esc_attr__( 'Content History pagination', 'ai-content-studio' ); ?>">
			<?php foreach ( $links as $link ) : ?><?php echo wp_kses_post( $link ); ?><?php endforeach; ?>
		</nav>
		<?php
	}

	private static function get_status_filter(): string {
		$status = isset( $_GET['aics_status'] ) && is_string( $_GET['aics_status'] ) ? sanitize_key( wp_unslash( $_GET['aics_status'] ) ) : 'all';

		return array_key_exists( $status, self::STATUS_FILTERS ) ? $status : 'all';
	}

	private static function get_page_number(): int {
		$requested = isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? (int) wp_unslash( $_GET['paged'] ) : 1;
		$page      = $requested > 0 ? absint( $requested ) : 1;

		return max( 1, $page );
	}

	private static function get_status_label( string $status ): string {
		$labels = array(
			'draft'   => __( 'Draft', 'ai-content-studio' ),
			'publish' => __( 'Published', 'ai-content-studio' ),
			'future'  => __( 'Scheduled', 'ai-content-studio' ),
			'pending' => __( 'Pending', 'ai-content-studio' ),
			'private' => __( 'Private', 'ai-content-studio' ),
		);

		return $labels[ $status ] ?? '—';
	}

	private static function get_filter_label( string $status ): string {
		$labels = array(
			'all'     => __( 'All', 'ai-content-studio' ),
			'draft'   => __( 'Draft', 'ai-content-studio' ),
			'publish' => __( 'Published', 'ai-content-studio' ),
			'future'  => __( 'Scheduled', 'ai-content-studio' ),
			'pending' => __( 'Pending', 'ai-content-studio' ),
		);

		return $labels[ $status ] ?? $labels['all'];
	}

	/** @return array<string,string> */
	private static function get_tone_labels(): array {
		return array(
			'professional'   => __( 'Professional', 'ai-content-studio' ),
			'friendly'       => __( 'Friendly', 'ai-content-studio' ),
			'conversational' => __( 'Conversational', 'ai-content-studio' ),
			'informative'    => __( 'Informative', 'ai-content-studio' ),
			'persuasive'     => __( 'Persuasive', 'ai-content-studio' ),
		);
	}

	/** @return array<string,string> */
	private static function get_length_labels(): array {
		return array(
			'short'  => __( 'Short', 'ai-content-studio' ),
			'medium' => __( 'Medium', 'ai-content-studio' ),
			'long'   => __( 'Long', 'ai-content-studio' ),
		);
	}

	/** @return array<string,string> */
	private static function get_intent_labels(): array {
		return array(
			'informational' => __( 'Informational', 'ai-content-studio' ),
			'commercial'    => __( 'Commercial', 'ai-content-studio' ),
			'transactional' => __( 'Transactional', 'ai-content-studio' ),
			'navigational'  => __( 'Navigational', 'ai-content-studio' ),
		);
	}

	private static function get_text_meta( int $post_id, string $key ): string {
		$value = get_post_meta( $post_id, $key, true );
		$value = is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';

		return '' !== $value ? $value : '—';
	}

	/**
	 * @param array<string,string> $labels Allowlisted labels.
	 */
	private static function get_mapped_meta( int $post_id, string $key, array $labels ): string {
		$value = sanitize_key( (string) get_post_meta( $post_id, $key, true ) );

		return $labels[ $value ] ?? '—';
	}

	private function __construct() {}
}
