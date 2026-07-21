<?php
/**
 * Generic admin placeholder template.
 *
 * @var string $page_title Translated page title.
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap aics-admin-wrap">
	<h1><?php echo esc_html( $page_title ); ?></h1>
	<div class="aics-placeholder-card">
		<h2><?php esc_html_e( 'AI Content Studio', 'ai-content-studio' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: Current admin page name. */
				esc_html__( 'The %s feature will be added in a future development task.', 'ai-content-studio' ),
				'<strong>' . esc_html( $page_title ) . '</strong>'
			);
			?>
		</p>
	</div>
</div>
