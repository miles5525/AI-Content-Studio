<?php
/**
 * Settings administration screen and form handlers.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and processes the plugin settings page.
 */
final class AICS_Settings_Page {
	private const PAGE_SLUG = 'aics-settings';
	private const SAVE_ACTION = 'aics_save_settings';
	private const REMOVE_ACTION = 'aics_remove_api_key';

	/**
	 * Registers settings write handlers.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::REMOVE_ACTION, array( self::class, 'handle_remove_api_key' ) );
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public static function render(): void {
		self::require_permission();

		$model          = AICS_Settings::get_openai_model();
		$key_configured = AICS_Settings::has_openai_api_key();
		?>
		<div class="wrap aics-admin-wrap aics-settings-page">
			<h1><?php esc_html_e( 'AI Content Studio Settings', 'ai-content-studio' ); ?></h1>
			<?php self::render_notice(); ?>

			<div class="aics-settings-section">
				<h2><?php esc_html_e( 'OpenAI', 'ai-content-studio' ); ?></h2>
				<p class="aics-api-key-status <?php echo $key_configured ? 'aics-api-key-status--configured' : 'aics-api-key-status--missing'; ?>">
					<?php echo esc_html( $key_configured ? __( 'API key configured', 'ai-content-studio' ) : __( 'No API key configured', 'ai-content-studio' ) ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
					<?php wp_nonce_field( self::SAVE_ACTION, 'aics_settings_nonce' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="aics-openai-api-key"><?php esc_html_e( 'OpenAI API key', 'ai-content-studio' ); ?></label></th>
							<td>
								<input id="aics-openai-api-key" class="regular-text" type="password" name="openai_api_key" value="" autocomplete="new-password" spellcheck="false">
								<p class="description"><?php esc_html_e( 'Leave this field blank to preserve the existing API key. The key is stored in the WordPress options table for this BYO-key MVP.', 'ai-content-studio' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aics-openai-model"><?php esc_html_e( 'OpenAI model', 'ai-content-studio' ); ?></label></th>
							<td>
								<select id="aics-openai-model" name="openai_model">
									<?php foreach ( AICS_Settings::get_allowed_models() as $allowed_model ) : ?>
										<option value="<?php echo esc_attr( $allowed_model ); ?>" <?php selected( $model, $allowed_model ); ?>><?php echo esc_html( $allowed_model ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Model availability depends on your OpenAI account.', 'ai-content-studio' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'ai-content-studio' ) ); ?>
				</form>

				<?php if ( $key_configured ) : ?>
					<form class="aics-remove-key-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-aics-confirm="<?php echo esc_attr__( 'Are you sure you want to remove the saved API key?', 'ai-content-studio' ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::REMOVE_ACTION ); ?>">
						<?php wp_nonce_field( self::REMOVE_ACTION, 'aics_remove_key_nonce' ); ?>
						<?php submit_button( __( 'Remove API Key', 'ai-content-studio' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves validated settings.
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		self::require_permission();
		check_admin_referer( self::SAVE_ACTION, 'aics_settings_nonce' );

		$api_key = isset( $_POST['openai_api_key'] ) && is_string( $_POST['openai_api_key'] ) ? wp_unslash( $_POST['openai_api_key'] ) : '';
		$model   = isset( $_POST['openai_model'] ) && is_string( $_POST['openai_model'] ) ? wp_unslash( $_POST['openai_model'] ) : '';
		$result  = AICS_Settings::update(
			array(
				'openai_api_key' => $api_key,
				'openai_model'   => $model,
			)
		);

		self::redirect( is_wp_error( $result ) ? 'invalid-model' : 'settings-saved' );
	}

	/**
	 * Removes the configured API key without changing the selected model.
	 *
	 * @return void
	 */
	public static function handle_remove_api_key(): void {
		self::require_permission();
		check_admin_referer( self::REMOVE_ACTION, 'aics_remove_key_nonce' );

		AICS_Settings::remove_openai_api_key();
		self::redirect( 'api-key-removed' );
	}

	/**
	 * Enforces the centralized plugin permission.
	 *
	 * @return void
	 */
	private static function require_permission(): void {
		if ( ! current_user_can( \AIContentStudio\Core\Permissions::manage() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'ai-content-studio' ) );
		}
	}

	/**
	 * Redirects safely to the settings page with a notice code.
	 *
	 * @param string $notice Notice code.
	 * @return void
	 */
	private static function redirect( string $notice ): void {
		$url = add_query_arg(
			'aics_notice',
			$notice,
			admin_url( 'admin.php?page=' . self::PAGE_SLUG )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Renders a notice selected from a fixed allowlist.
	 *
	 * @return void
	 */
	private static function render_notice(): void {
		$notice = isset( $_GET['aics_notice'] ) && is_string( $_GET['aics_notice'] ) ? sanitize_key( wp_unslash( $_GET['aics_notice'] ) ) : '';
		$notices = array(
			'settings-saved'  => array( 'success', __( 'Settings saved.', 'ai-content-studio' ) ),
			'api-key-removed' => array( 'success', __( 'API key removed.', 'ai-content-studio' ) ),
			'invalid-model'   => array( 'error', __( 'The selected model is invalid. No settings were changed.', 'ai-content-studio' ) ),
		);

		if ( ! isset( $notices[ $notice ] ) ) {
			return;
		}
		?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ][1] ); ?></p></div>
		<?php
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
