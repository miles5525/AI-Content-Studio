<?php
/** System Status admin page. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_System_Status_Page {
	public static function render(): void {
		if ( ! current_user_can( \AIContentStudio\Core\Permissions::manage() ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-content-studio' ) ); }
		$checks      = AICS_System_Check::run();
		$diagnostics = AICS_System_Check::diagnostics( $checks );
		$labels      = array( 'good' => __( 'Good', 'ai-content-studio' ), 'warning' => __( 'Warning', 'ai-content-studio' ), 'critical' => __( 'Critical', 'ai-content-studio' ) );
		?>
		<div class="wrap aics-admin-wrap aics-system-status-page">
			<header class="aics-page-header">
				<h1 class="aics-page-title"><?php esc_html_e( 'System Status', 'ai-content-studio' ); ?></h1>
				<p class="aics-page-description"><?php esc_html_e( 'Review the local WordPress environment and AI Content Studio dependencies. Opening this page does not contact OpenAI.', 'ai-content-studio' ); ?></p>
			</header>
			<div class="aics-status-table-wrap"><table class="widefat striped aics-system-status-table"><thead><tr><th scope="col"><?php esc_html_e( 'Check', 'ai-content-studio' ); ?></th><th scope="col"><?php esc_html_e( 'Status', 'ai-content-studio' ); ?></th><th scope="col"><?php esc_html_e( 'Value', 'ai-content-studio' ); ?></th><th scope="col"><?php esc_html_e( 'Details', 'ai-content-studio' ); ?></th></tr></thead><tbody>
			<?php foreach ( $checks as $check ) : ?><tr><th scope="row"><?php echo esc_html( $check['label'] ); ?></th><td><span class="aics-check-status aics-check-status--<?php echo esc_attr( $check['status'] ); ?>"><?php echo esc_html( $labels[ $check['status'] ] ); ?></span></td><td><?php echo esc_html( $check['value'] ); ?></td><td><?php echo esc_html( $check['message'] ); ?></td></tr><?php endforeach; ?>
			</tbody></table></div>
			<section class="aics-diagnostics" aria-labelledby="aics-diagnostics-heading"><h2 id="aics-diagnostics-heading"><?php esc_html_e( 'Diagnostic Information', 'ai-content-studio' ); ?></h2><p><?php esc_html_e( 'This report excludes credentials, private content, database details, cookies, nonces, and filesystem paths.', 'ai-content-studio' ); ?></p><label for="aics-diagnostic-text"><strong><?php esc_html_e( 'Safe diagnostic report', 'ai-content-studio' ); ?></strong></label><textarea id="aics-diagnostic-text" class="large-text code" rows="18" readonly><?php echo esc_textarea( $diagnostics ); ?></textarea><p><button type="button" class="button button-secondary" data-aics-copy-diagnostics data-copy-label="<?php echo esc_attr__( 'Copy Diagnostic Information', 'ai-content-studio' ); ?>" data-copied-label="<?php echo esc_attr__( 'Diagnostic information copied.', 'ai-content-studio' ); ?>"><?php esc_html_e( 'Copy Diagnostic Information', 'ai-content-studio' ); ?></button></p><p class="aics-copy-status" data-aics-copy-status aria-live="polite"></p></section>
		</div>
		<?php
	}
	private function __construct() {}
}
