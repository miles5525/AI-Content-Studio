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
	private const TEST_ACTION = 'aics_test_openai_connection';
	private const SAVE_IMAGE_ACTION = 'aics_save_featured_image_settings';
	private const TEST_IMAGE_ACTION = 'aics_test_image_generation';
	private const IMAGE_TEST_TRANSIENT_PREFIX = 'aics_image_test_result_';

	/**
	 * Registers settings write handlers.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::REMOVE_ACTION, array( self::class, 'handle_remove_api_key' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( self::class, 'handle_test_connection' ) );
		add_action( 'admin_post_' . self::SAVE_IMAGE_ACTION, array( self::class, 'handle_save_featured_images' ) );
		add_action( 'admin_post_' . self::TEST_IMAGE_ACTION, array( self::class, 'handle_test_image_generation' ) );
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
		$image_settings = AICS_Featured_Image_Settings::get_effective();
		$image_provider = AICS_Image_Provider_Factory::create( $image_settings['provider'] );
		$image_capabilities = is_wp_error( $image_provider ) ? array() : $image_provider->get_capabilities();
		$image_status = AICS_Featured_Image_Settings::configuration_status();
		?>
		<div class="wrap aics-admin-wrap aics-settings-page">
			<header class="aics-page-header">
				<h1 class="aics-page-title"><?php esc_html_e( 'AI Content Studio Settings', 'ai-content-studio' ); ?></h1>
				<p class="aics-page-description"><?php esc_html_e( 'Configure the AI provider and verify the saved connection securely.', 'ai-content-studio' ); ?></p>
			</header>
			<?php self::render_notice(); ?>

			<div class="aics-settings-section">
				<h2><?php esc_html_e( 'AI Provider Configuration', 'ai-content-studio' ); ?></h2>
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
					<div class="aics-connection-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>">
							<?php wp_nonce_field( self::TEST_ACTION, 'aics_test_connection_nonce' ); ?>
							<p class="description"><?php esc_html_e( 'The connection test uses the currently saved API key and selected model. Save changes before testing.', 'ai-content-studio' ); ?></p>
							<?php submit_button( __( 'Test Connection', 'ai-content-studio' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>

					<form class="aics-remove-key-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-aics-confirm="<?php echo esc_attr__( 'Are you sure you want to remove the saved API key?', 'ai-content-studio' ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::REMOVE_ACTION ); ?>">
						<?php wp_nonce_field( self::REMOVE_ACTION, 'aics_remove_key_nonce' ); ?>
						<?php submit_button( __( 'Remove API Key', 'ai-content-studio' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>

			<div class="aics-settings-section">
				<h2><?php esc_html_e( 'Featured Images', 'ai-content-studio' ); ?></h2>
				<p class="aics-api-key-status <?php echo $image_status['success'] ? 'aics-api-key-status--configured' : 'aics-api-key-status--missing'; ?>"><?php echo esc_html( $image_status['message'] ); ?></p>
				<p><?php esc_html_e( 'Featured images use the configured AI provider credentials. The API key is stored securely and is not displayed here.', 'ai-content-studio' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_IMAGE_ACTION ); ?>">
					<?php wp_nonce_field( self::SAVE_IMAGE_ACTION, 'aics_featured_image_settings_nonce' ); ?>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e( 'Default image generation', 'ai-content-studio' ); ?></th><td><label><input type="checkbox" name="image_enabled" value="1" <?php checked( $image_settings['enabled'] ); ?>> <?php esc_html_e( 'Enable featured-image generation by default', 'ai-content-studio' ); ?></label><p class="description"><?php esc_html_e( 'No images are generated until a later pipeline task connects this setting to generation.', 'ai-content-studio' ); ?></p></td></tr>
						<tr><th scope="row"><label for="aics-image-provider"><?php esc_html_e( 'Image provider', 'ai-content-studio' ); ?></label></th><td><select id="aics-image-provider" name="image_provider"><?php foreach ( AICS_Image_Provider_Factory::provider_options() as $provider_key => $provider_name ) : ?><option value="<?php echo esc_attr( $provider_key ); ?>" <?php selected( $image_settings['provider'], $provider_key ); ?>><?php echo esc_html( $provider_name ); ?></option><?php endforeach; ?></select></td></tr>
						<tr><th scope="row"><label for="aics-image-model"><?php esc_html_e( 'Image model', 'ai-content-studio' ); ?></label></th><td><select id="aics-image-model" name="image_model"><?php foreach ( $image_capabilities['supported_models'] ?? array() as $image_model ) : ?><option value="<?php echo esc_attr( $image_model ); ?>" <?php selected( $image_settings['model'], $image_model ); ?>><?php echo esc_html( $image_model ); ?></option><?php endforeach; ?></select></td></tr>
						<tr><th scope="row"><label for="aics-image-aspect-ratio"><?php esc_html_e( 'Default aspect ratio', 'ai-content-studio' ); ?></label></th><td><select id="aics-image-aspect-ratio" name="image_aspect_ratio"><?php foreach ( array( 'landscape' => __( 'Landscape', 'ai-content-studio' ), 'square' => __( 'Square', 'ai-content-studio' ), 'portrait' => __( 'Portrait', 'ai-content-studio' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $image_settings['aspect_ratio'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
						<tr><th scope="row"><label for="aics-image-quality"><?php esc_html_e( 'Default quality', 'ai-content-studio' ); ?></label></th><td><select id="aics-image-quality" name="image_quality"><?php foreach ( array( 'standard' => __( 'Standard', 'ai-content-studio' ), 'high' => __( 'High', 'ai-content-studio' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $image_settings['quality'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
						<tr><th scope="row"><label for="aics-image-output-format"><?php esc_html_e( 'Default output format', 'ai-content-studio' ); ?></label></th><td><select id="aics-image-output-format" name="image_output_format"><?php foreach ( array( 'png' => 'PNG', 'jpeg' => 'JPEG', 'webp' => 'WebP' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $image_settings['output_format'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
						<tr><th scope="row"><label for="aics-image-visual-style"><?php esc_html_e( 'Default visual style', 'ai-content-studio' ); ?></label></th><td><select id="aics-image-visual-style" name="image_visual_style"><?php foreach ( self::visual_style_labels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $image_settings['visual_style'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
					</table>
					<?php submit_button( __( 'Save Featured Image Settings', 'ai-content-studio' ) ); ?>
				</form>
				<div class="aics-connection-actions">
					<h3><?php esc_html_e( 'Test Image Generation', 'ai-content-studio' ); ?></h3>
					<p><?php esc_html_e( 'This sends one image-generation request using your saved image settings. The result is validated and immediately deleted. It is not added to the Media Library.', 'ai-content-studio' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_IMAGE_ACTION ); ?>">
						<?php wp_nonce_field( self::TEST_IMAGE_ACTION, 'aics_test_image_nonce' ); ?>
						<label for="aics-image-test-topic"><strong><?php esc_html_e( 'Test topic', 'ai-content-studio' ); ?></strong></label><br>
						<input id="aics-image-test-topic" class="regular-text" type="text" name="image_test_topic" maxlength="250" value="<?php echo esc_attr__( 'Modern content strategy for small businesses', 'ai-content-studio' ); ?>">
						<?php submit_button( __( 'Generate and Validate Test Image', 'ai-content-studio' ), 'secondary' ); ?>
					</form>
				</div>
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

	/** Saves validated global featured-image defaults without making a provider request. */
	public static function handle_save_featured_images(): void {
		self::require_permission();
		check_admin_referer( self::SAVE_IMAGE_ACTION, 'aics_featured_image_settings_nonce' );
		$result = AICS_Featured_Image_Settings::update( array(
			'enabled' => isset( $_POST['image_enabled'] ) && '1' === (string) wp_unslash( $_POST['image_enabled'] ),
			'provider' => isset( $_POST['image_provider'] ) && is_string( $_POST['image_provider'] ) ? wp_unslash( $_POST['image_provider'] ) : '',
			'model' => isset( $_POST['image_model'] ) && is_string( $_POST['image_model'] ) ? wp_unslash( $_POST['image_model'] ) : '',
			'aspect_ratio' => isset( $_POST['image_aspect_ratio'] ) && is_string( $_POST['image_aspect_ratio'] ) ? wp_unslash( $_POST['image_aspect_ratio'] ) : '',
			'quality' => isset( $_POST['image_quality'] ) && is_string( $_POST['image_quality'] ) ? wp_unslash( $_POST['image_quality'] ) : '',
			'output_format' => isset( $_POST['image_output_format'] ) && is_string( $_POST['image_output_format'] ) ? wp_unslash( $_POST['image_output_format'] ) : '',
			'visual_style' => isset( $_POST['image_visual_style'] ) && is_string( $_POST['image_visual_style'] ) ? wp_unslash( $_POST['image_visual_style'] ) : '',
		) );
		self::redirect( is_wp_error( $result ) ? $result->get_error_code() : 'image-settings-saved' );
	}

	/** Generates, validates, records safe metadata, and explicitly deletes one test image. */
	public static function handle_test_image_generation(): void {
		self::require_permission();
		check_admin_referer( self::TEST_IMAGE_ACTION, 'aics_test_image_nonce' );
		$topic = isset( $_POST['image_test_topic'] ) && is_string( $_POST['image_test_topic'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['image_test_topic'] ) ) ) : '';
		if ( '' === $topic || self::text_length( $topic ) > 250 ) {
			self::store_image_test_result( array( 'success' => false, 'code' => 'invalid_image_test_topic', 'message' => __( 'Enter a valid test topic of 250 characters or fewer.', 'ai-content-studio' ) ) );
			self::redirect( 'image-test-result' );
		}
		$started_at = microtime( true ); $result = null; $temporary = null; $safe = array();
		try {
			$result = ( new AICS_Featured_Image_Generation_Service() )->generate( array( 'title' => $topic, 'summary' => $topic ), false );
			$temporary = $result->get_temporary_file();
			$safe = array( 'success' => $result->is_success(), 'code' => $result->get_error_code(), 'message' => $result->get_message(), 'provider' => $result->get_provider(), 'model' => $result->get_model(), 'width' => $result->get_width(), 'height' => $result->get_height(), 'format' => $result->get_output_type(), 'http_status' => $result->get_http_status(), 'file_validation' => $result->is_success() ? 'passed' : '' );
		} finally {
			$deleted = ! $temporary instanceof AICS_Temporary_Image_File || $temporary->cleanup();
			$safe['temporary_deleted'] = $deleted;
			if ( ! $deleted ) { $safe = array( 'success' => false, 'code' => 'temporary_image_cleanup_failed', 'message' => __( 'The generated test image could not be deleted safely.', 'ai-content-studio' ), 'temporary_deleted' => false ); }
		}
		AICS_Usage_Logger::log( array( 'event_type' => 'system_test', 'operation' => 'image_generation_test', 'status' => ! empty( $safe['success'] ) ? 'success' : 'failed', 'provider' => $safe['provider'] ?? '', 'model' => $safe['model'] ?? '', 'error_code' => $safe['code'] ?? '', 'duration_ms' => AICS_Usage_Logger::duration_ms( $started_at ), 'metadata' => array( 'test_type' => 'image_generation', 'http_status' => absint( $safe['http_status'] ?? 0 ) ) ) );
		self::store_image_test_result( $safe );
		self::redirect( 'image-test-result' );
	}

	/**
	 * Tests the saved OpenAI configuration without changing settings.
	 *
	 * @return void
	 */
	public static function handle_test_connection(): void {
		self::require_permission();
		check_admin_referer( self::TEST_ACTION, 'aics_test_connection_nonce' );

		$provider   = new AICS_OpenAI_Provider();
		$started_at = microtime( true );
		$result     = $provider->test_connection();
		AICS_Usage_Logger::log(
			array(
				'event_type'  => 'system_test',
				'operation'   => 'openai_connection_test',
				'status'      => $result['success'] ? 'success' : 'failed',
				'provider'    => 'openai',
				'model'       => AICS_Settings::get_openai_model(),
				'error_code'  => $result['success'] ? '' : $result['code'],
				'duration_ms' => AICS_Usage_Logger::duration_ms( $started_at ),
				'metadata'    => array( 'test_type' => 'provider_connection' ),
			)
		);

		self::redirect( $result['code'] );
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
		if ( 'image-test-result' === $notice ) { self::render_image_test_result(); return; }
		$notices = array(
			'settings-saved'    => array( 'success', __( 'Settings saved.', 'ai-content-studio' ) ),
			'api-key-removed'   => array( 'success', __( 'API key removed.', 'ai-content-studio' ) ),
			'invalid-model'     => array( 'error', __( 'The selected model is invalid. No settings were changed.', 'ai-content-studio' ) ),
			'connection-success' => array( 'success', __( 'OpenAI connection successful.', 'ai-content-studio' ) ),
			'missing-api-key'   => array( 'error', __( 'No OpenAI API key is configured.', 'ai-content-studio' ) ),
			'invalid-api-key'   => array( 'error', __( 'OpenAI rejected the API key.', 'ai-content-studio' ) ),
			'quota-error'       => array( 'error', __( 'OpenAI reported a quota or billing issue.', 'ai-content-studio' ) ),
			'rate-limit'        => array( 'error', __( 'OpenAI rate limit reached. Please try again later.', 'ai-content-studio' ) ),
			'model-unavailable' => array( 'error', __( 'The selected model is not available for this account.', 'ai-content-studio' ) ),
			'network-error'     => array( 'error', __( 'The site could not connect to OpenAI.', 'ai-content-studio' ) ),
			'invalid-response'  => array( 'error', __( 'OpenAI returned an unexpected response.', 'ai-content-studio' ) ),
			'api-error'         => array( 'error', __( 'OpenAI could not complete the connection test.', 'ai-content-studio' ) ),
			'image-settings-saved' => array( 'success', __( 'Featured image settings saved.', 'ai-content-studio' ) ),
			'invalid-image-provider' => array( 'error', __( 'The selected image provider is invalid. No image settings were changed.', 'ai-content-studio' ) ),
			'invalid-image-model' => array( 'error', __( 'The selected image model is invalid. No image settings were changed.', 'ai-content-studio' ) ),
			'invalid-image-aspect-ratio' => array( 'error', __( 'The selected image aspect ratio is invalid. No image settings were changed.', 'ai-content-studio' ) ),
			'invalid-image-quality' => array( 'error', __( 'The selected image quality is invalid. No image settings were changed.', 'ai-content-studio' ) ),
			'invalid-image-output-format' => array( 'error', __( 'The selected image output format is invalid. No image settings were changed.', 'ai-content-studio' ) ),
		);

		if ( ! isset( $notices[ $notice ] ) ) {
			return;
		}
		?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ][1] ); ?></p></div>
		<?php
	}

	private static function store_image_test_result( array $result ): void { set_transient( self::IMAGE_TEST_TRANSIENT_PREFIX . get_current_user_id(), $result, 2 * MINUTE_IN_SECONDS ); }
	private static function render_image_test_result(): void { $key=self::IMAGE_TEST_TRANSIENT_PREFIX.get_current_user_id();$result=get_transient($key);delete_transient($key);if(!is_array($result)){return;}$success=!empty($result['success'])&&!empty($result['temporary_deleted']);?><div class="notice notice-<?php echo $success?'success':'error';?> is-dismissible"><p><strong><?php echo esc_html($success?__('Image generation succeeded.','ai-content-studio'):($result['message']??__('Image generation failed.','ai-content-studio')));?></strong></p><?php if($success):?><ul><li><?php echo esc_html__('Provider: ','ai-content-studio').esc_html(AICS_Image_Provider_Factory::provider_options()[$result['provider']]??$result['provider']);?></li><li><?php echo esc_html__('Model: ','ai-content-studio').esc_html($result['model']);?></li><li><?php echo esc_html__('Dimensions: ','ai-content-studio').esc_html(absint($result['width']).' × '.absint($result['height']));?></li><li><?php echo esc_html__('Format: ','ai-content-studio').esc_html(strtoupper($result['format']));?></li><li><?php esc_html_e('File validation: Passed','ai-content-studio');?></li><li><?php esc_html_e('Temporary file: Deleted','ai-content-studio');?></li></ul><?php elseif(!empty($result['code'])):?><p><code><?php echo esc_html(sanitize_key($result['code']));?></code></p><?php endif;?></div><?php }
	private static function visual_style_labels():array{return array('editorial'=>__('Editorial','ai-content-studio'),'photorealistic'=>__('Photorealistic','ai-content-studio'),'modern_illustration'=>__('Modern Illustration','ai-content-studio'),'minimal_3d'=>__('Minimal 3D','ai-content-studio'),'flat_illustration'=>__('Flat Illustration','ai-content-studio'));}
	private static function text_length(string $value):int{return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value);}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
