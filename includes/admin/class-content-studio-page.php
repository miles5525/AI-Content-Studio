<?php
/**
 * Content Studio form foundation.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and validates Content Studio inputs without generating content.
 */
final class AICS_Content_Studio_Page {
	private const PAGE_SLUG = 'aics-create-content';
	private const ACTION = 'aics_prepare_content_inputs';
	private const NONCE_NAME = 'aics_content_inputs_nonce';
	private const STATE_TTL = 20 * MINUTE_IN_SECONDS;
	private const ERROR_STATE_TTL = 5 * MINUTE_IN_SECONDS;
	private const BUSINESS_CONTEXT_MAX_LENGTH = 3000;
	private const TOPIC_MAX_LENGTH = 250;
	private const TONES = array(
		'professional'   => 'Professional',
		'friendly'       => 'Friendly',
		'conversational' => 'Conversational',
		'informative'    => 'Informative',
		'persuasive'     => 'Persuasive',
	);
	private const ARTICLE_LENGTHS = array(
		'short'  => 'Short — approximately 600–800 words',
		'medium' => 'Medium — approximately 1000–1400 words',
		'long'   => 'Long — approximately 1800–2400 words',
	);

	/**
	 * Registers the Content Studio form handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_submission' ) );
	}

	/**
	 * Renders the protected Content Studio form.
	 *
	 * @return void
	 */
	public static function render(): void {
		self::require_permission();

		$state = self::get_form_state();
		?>
		<div class="wrap aics-admin-wrap aics-content-studio-page">
			<h1><?php esc_html_e( 'AI Content Studio', 'ai-content-studio' ); ?></h1>
			<p><?php esc_html_e( 'Prepare the business and writing inputs that will be used for AI-assisted blog generation in a future development task.', 'ai-content-studio' ); ?></p>
			<?php self::render_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION, self::NONCE_NAME ); ?>

				<section class="aics-content-section" aria-labelledby="aics-content-context-heading">
					<h2 id="aics-content-context-heading"><?php esc_html_e( 'Content Context', 'ai-content-studio' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="aics-business-context"><?php esc_html_e( 'Business or Website Context', 'ai-content-studio' ); ?> <span class="aics-required" aria-hidden="true">*</span></label>
							</th>
							<td>
								<textarea id="aics-business-context" class="large-text" name="business_context" rows="8" maxlength="<?php echo esc_attr( (string) self::BUSINESS_CONTEXT_MAX_LENGTH ); ?>" required aria-required="true"><?php echo esc_textarea( $state['business_context'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Describe the business or website, services, audience, location, and content goals.', 'ai-content-studio' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="aics-topic-keyword"><?php esc_html_e( 'Topic or Keyword', 'ai-content-studio' ); ?> <span class="aics-required" aria-hidden="true">*</span></label>
							</th>
							<td>
								<input id="aics-topic-keyword" class="regular-text" type="text" name="topic_keyword" value="<?php echo esc_attr( $state['topic_keyword'] ); ?>" maxlength="<?php echo esc_attr( (string) self::TOPIC_MAX_LENGTH ); ?>" required aria-required="true">
								<p class="description"><?php esc_html_e( 'Enter the primary subject, phrase, or keyword for the planned content.', 'ai-content-studio' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="aics-content-section" aria-labelledby="aics-writing-preferences-heading">
					<h2 id="aics-writing-preferences-heading"><?php esc_html_e( 'Writing Preferences', 'ai-content-studio' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="aics-tone"><?php esc_html_e( 'Tone', 'ai-content-studio' ); ?></label></th>
							<td>
								<select id="aics-tone" name="tone">
									<?php foreach ( self::TONES as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $state['tone'], $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aics-article-length"><?php esc_html_e( 'Article Length', 'ai-content-studio' ); ?></label></th>
							<td>
								<select id="aics-article-length" name="article_length">
									<?php foreach ( self::ARTICLE_LENGTHS as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $state['article_length'], $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Word counts are approximate because future AI output may vary.', 'ai-content-studio' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<?php submit_button( __( 'Continue', 'ai-content-studio' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Validates and temporarily stores submitted Content Studio inputs.
	 *
	 * @return void
	 */
	public static function handle_submission(): void {
		self::require_permission();

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) && is_string( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			self::redirect( 'request-not-verified' );
		}

		$state = self::sanitize_submission();
		$error = self::validate( $state );

		if ( '' !== $error ) {
			set_transient(
				self::get_error_state_key(),
				array(
					'state' => $state,
				),
				self::ERROR_STATE_TTL
			);
			self::redirect( $error );
		}

		set_transient( self::get_state_key(), $state, self::STATE_TTL );
		delete_transient( self::get_error_state_key() );
		self::redirect( 'inputs-validated' );
	}

	/**
	 * Sanitizes request values into a predictable form-state array.
	 *
	 * @return array{business_context: string, topic_keyword: string, tone: string, article_length: string}
	 */
	private static function sanitize_submission(): array {
		$business_context = isset( $_POST['business_context'] ) && is_string( $_POST['business_context'] ) ? wp_unslash( $_POST['business_context'] ) : '';
		$topic_keyword    = isset( $_POST['topic_keyword'] ) && is_string( $_POST['topic_keyword'] ) ? wp_unslash( $_POST['topic_keyword'] ) : '';
		$tone             = isset( $_POST['tone'] ) && is_string( $_POST['tone'] ) ? wp_unslash( $_POST['tone'] ) : '';
		$article_length   = isset( $_POST['article_length'] ) && is_string( $_POST['article_length'] ) ? wp_unslash( $_POST['article_length'] ) : '';

		return array(
			'business_context' => trim( sanitize_textarea_field( $business_context ) ),
			'topic_keyword'    => trim( sanitize_text_field( $topic_keyword ) ),
			'tone'             => sanitize_key( $tone ),
			'article_length'   => sanitize_key( $article_length ),
		);
	}

	/**
	 * Returns the first controlled validation error code.
	 *
	 * @param array{business_context: string, topic_keyword: string, tone: string, article_length: string} $state Sanitized state.
	 * @return string
	 */
	private static function validate( array $state ): string {
		if ( '' === $state['business_context'] ) {
			return 'business-context-required';
		}

		if ( self::string_length( $state['business_context'] ) > self::BUSINESS_CONTEXT_MAX_LENGTH ) {
			return 'business-context-too-long';
		}

		if ( '' === $state['topic_keyword'] ) {
			return 'topic-required';
		}

		if ( self::string_length( $state['topic_keyword'] ) > self::TOPIC_MAX_LENGTH ) {
			return 'topic-too-long';
		}

		if ( ! array_key_exists( $state['tone'], self::TONES ) ) {
			return 'invalid-tone';
		}

		if ( ! array_key_exists( $state['article_length'], self::ARTICLE_LENGTHS ) ) {
			return 'invalid-article-length';
		}

		return '';
	}

	/**
	 * Returns multibyte-safe string length when available.
	 *
	 * @param string $value Sanitized value.
	 * @return int
	 */
	private static function string_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Returns state for the current user, preferring a validation flash.
	 *
	 * @return array{business_context: string, topic_keyword: string, tone: string, article_length: string}
	 */
	private static function get_form_state(): array {
		$defaults = array(
			'business_context' => '',
			'topic_keyword'    => '',
			'tone'             => 'professional',
			'article_length'   => 'medium',
		);
		$error_state = get_transient( self::get_error_state_key() );

		if ( is_array( $error_state ) && isset( $error_state['state'] ) && is_array( $error_state['state'] ) ) {
			delete_transient( self::get_error_state_key() );
			return self::normalize_state( $error_state['state'], $defaults );
		}

		$state = get_transient( self::get_state_key() );

		return is_array( $state ) ? self::normalize_state( $state, $defaults ) : $defaults;
	}

	/**
	 * Normalizes stored state before rendering it.
	 *
	 * @param array<string,mixed>  $state Stored state.
	 * @param array<string,string> $defaults Default state.
	 * @return array{business_context: string, topic_keyword: string, tone: string, article_length: string}
	 */
	private static function normalize_state( array $state, array $defaults ): array {
		$normalized = array(
			'business_context' => isset( $state['business_context'] ) && is_string( $state['business_context'] ) ? $state['business_context'] : '',
			'topic_keyword'    => isset( $state['topic_keyword'] ) && is_string( $state['topic_keyword'] ) ? $state['topic_keyword'] : '',
			'tone'             => isset( $state['tone'] ) && is_string( $state['tone'] ) && array_key_exists( $state['tone'], self::TONES ) ? $state['tone'] : $defaults['tone'],
			'article_length'   => isset( $state['article_length'] ) && is_string( $state['article_length'] ) && array_key_exists( $state['article_length'], self::ARTICLE_LENGTHS ) ? $state['article_length'] : $defaults['article_length'],
		);

		return array_merge( $defaults, $normalized );
	}

	/**
	 * Renders a controlled success or validation notice.
	 *
	 * @return void
	 */
	private static function render_notice(): void {
		$notice = isset( $_GET['aics_notice'] ) && is_string( $_GET['aics_notice'] ) ? sanitize_key( wp_unslash( $_GET['aics_notice'] ) ) : '';
		$notices = array(
			'business-context-required' => array( 'error', __( 'Business context is required.', 'ai-content-studio' ) ),
			'business-context-too-long' => array( 'error', __( 'Business context is too long.', 'ai-content-studio' ) ),
			'topic-required'            => array( 'error', __( 'Topic or keyword is required.', 'ai-content-studio' ) ),
			'topic-too-long'            => array( 'error', __( 'Topic or keyword is too long.', 'ai-content-studio' ) ),
			'invalid-tone'              => array( 'error', __( 'Invalid tone selected.', 'ai-content-studio' ) ),
			'invalid-article-length'     => array( 'error', __( 'Invalid article length selected.', 'ai-content-studio' ) ),
			'inputs-validated'           => array( 'success', __( 'Content inputs validated successfully. Blog-idea generation will be added in the next development task.', 'ai-content-studio' ) ),
			'request-not-verified'       => array( 'error', __( 'The request could not be verified.', 'ai-content-studio' ) ),
		);

		if ( ! isset( $notices[ $notice ] ) ) {
			return;
		}
		?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ][1] ); ?></p></div>
		<?php
	}

	/**
	 * Enforces the centralized plugin permission.
	 *
	 * @return void
	 */
	private static function require_permission(): void {
		if ( ! current_user_can( \AIContentStudio\Core\Permissions::manage() ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'ai-content-studio' ) );
		}
	}

	/**
	 * Returns the current user's valid-state transient key.
	 *
	 * @return string
	 */
	private static function get_state_key(): string {
		return 'aics_content_inputs_' . get_current_user_id();
	}

	/**
	 * Returns the current user's validation-flash transient key.
	 *
	 * @return string
	 */
	private static function get_error_state_key(): string {
		return 'aics_content_input_errors_' . get_current_user_id();
	}

	/**
	 * Redirects safely without putting form data in the URL.
	 *
	 * @param string $notice Controlled notice code.
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
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
