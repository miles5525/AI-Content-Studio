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
 * Manages Content Studio inputs and blog ideas without generating articles.
 */
final class AICS_Content_Studio_Page {
	private const PAGE_SLUG = 'aics-create-content';
	private const ACTION = 'aics_prepare_content_inputs';
	private const NONCE_NAME = 'aics_content_inputs_nonce';
	private const GENERATE_ACTION = 'aics_generate_blog_ideas';
	private const GENERATE_NONCE_NAME = 'aics_generate_ideas_nonce';
	private const SELECT_ACTION = 'aics_select_blog_idea';
	private const SELECT_NONCE_NAME = 'aics_select_idea_nonce';
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
		add_action( 'admin_post_' . self::GENERATE_ACTION, array( self::class, 'handle_generate_ideas' ) );
		add_action( 'admin_post_' . self::SELECT_ACTION, array( self::class, 'handle_select_idea' ) );
	}

	/**
	 * Renders the protected Content Studio form.
	 *
	 * @return void
	 */
	public static function render(): void {
		self::require_permission();

		$state            = self::get_form_state();
		$validated_inputs = self::get_validated_input_state();
		$idea_state       = self::get_idea_state();
		$selected_idea    = self::get_selected_idea();
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

			<?php self::render_blog_ideas( $validated_inputs, $idea_state, $selected_idea ); ?>
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
		delete_transient( self::get_ideas_state_key() );
		delete_transient( self::get_selected_idea_key() );
		self::redirect( 'inputs-validated' );
	}

	/**
	 * Generates ideas from the current user's validated server-side inputs.
	 *
	 * @return void
	 */
	public static function handle_generate_ideas(): void {
		self::require_permission();

		if ( ! self::verify_nonce( self::GENERATE_NONCE_NAME, self::GENERATE_ACTION ) ) {
			self::redirect( 'request-not-verified' );
		}

		if ( ! AICS_Settings::has_openai_api_key() ) {
			self::redirect( 'missing-api-key' );
		}

		$inputs = self::get_validated_input_state();

		if ( null === $inputs ) {
			self::redirect( 'inputs-missing' );
		}

		$engine   = new AICS_AI_Engine();
		$response = $engine->generate_blog_ideas( $inputs );

		if ( ! $response->is_success() ) {
			self::redirect( self::map_generation_notice( $response->get_error_code() ) );
		}

		$data = $response->get_data();

		if ( null === $data || ! isset( $data['ideas'] ) || ! is_array( $data['ideas'] ) ) {
			self::redirect( 'invalid-idea-format' );
		}

		set_transient(
			self::get_ideas_state_key(),
			array(
				'ideas'        => $data['ideas'],
				'generated_at' => current_time( 'timestamp', true ),
				'model'        => AICS_Settings::get_openai_model(),
			),
			self::STATE_TTL
		);
		delete_transient( self::get_selected_idea_key() );
		self::redirect( 'ideas-generated' );
	}

	/**
	 * Selects one idea from the current user's stored generated ideas.
	 *
	 * @return void
	 */
	public static function handle_select_idea(): void {
		self::require_permission();

		if ( ! self::verify_nonce( self::SELECT_NONCE_NAME, self::SELECT_ACTION ) ) {
			self::redirect( 'request-not-verified' );
		}

		$selected_id = isset( $_POST['selected_idea_id'] ) && is_string( $_POST['selected_idea_id'] ) ? sanitize_key( wp_unslash( $_POST['selected_idea_id'] ) ) : '';
		$idea_state  = self::get_idea_state();
		$match       = null;

		if ( null !== $idea_state ) {
			foreach ( $idea_state['ideas'] as $idea ) {
				if ( isset( $idea['id'] ) && $selected_id === $idea['id'] ) {
					$match = $idea;
					break;
				}
			}
		}

		if ( null === $match ) {
			self::redirect( 'invalid-selected-idea' );
		}

		set_transient( self::get_selected_idea_key(), $match, self::STATE_TTL );
		self::redirect( 'idea-selected' );
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
	 * Returns the current user's validated, unexpired input state.
	 *
	 * @return array{business_context:string,topic_keyword:string,tone:string,article_length:string}|null
	 */
	private static function get_validated_input_state(): ?array {
		$state = get_transient( self::get_state_key() );

		if ( ! is_array( $state ) ) {
			return null;
		}

		$normalized = self::normalize_state(
			$state,
			array(
				'business_context' => '',
				'topic_keyword'    => '',
				'tone'             => 'professional',
				'article_length'   => 'medium',
			)
		);

		return '' === self::validate( $normalized ) ? $normalized : null;
	}

	/**
	 * Returns the current user's generated-idea state when structurally usable.
	 *
	 * @return array{ideas:array,generated_at:mixed,model:mixed}|null
	 */
	private static function get_idea_state(): ?array {
		$state = get_transient( self::get_ideas_state_key() );

		if ( ! is_array( $state ) || ! isset( $state['ideas'] ) || ! is_array( $state['ideas'] ) || 5 !== count( $state['ideas'] ) ) {
			return null;
		}

		foreach ( $state['ideas'] as $idea ) {
			if ( ! is_array( $idea ) || ! isset( $idea['id'], $idea['title'], $idea['description'], $idea['primary_keyword'], $idea['search_intent'] ) ) {
				return null;
			}

			foreach ( array( 'id', 'title', 'description', 'primary_keyword', 'search_intent' ) as $field ) {
				if ( ! is_string( $idea[ $field ] ) ) {
					return null;
				}
			}
		}

		return $state;
	}

	/**
	 * Returns the current user's selected idea when available.
	 *
	 * @return array|null
	 */
	private static function get_selected_idea(): ?array {
		$idea = get_transient( self::get_selected_idea_key() );

		return is_array( $idea ) && isset( $idea['id'] ) && is_string( $idea['id'] ) ? $idea : null;
	}

	/**
	 * Renders generation controls and sanitized idea cards.
	 *
	 * @param array|null $validated_inputs Current validated inputs.
	 * @param array|null $idea_state       Current generated ideas.
	 * @param array|null $selected_idea    Current selected idea.
	 * @return void
	 */
	private static function render_blog_ideas( ?array $validated_inputs, ?array $idea_state, ?array $selected_idea ): void {
		if ( null === $validated_inputs ) {
			return;
		}

		$key_configured = AICS_Settings::has_openai_api_key();
		?>
		<section class="aics-blog-ideas-section" aria-labelledby="aics-blog-ideas-heading">
			<h2 id="aics-blog-ideas-heading"><?php esc_html_e( 'Blog Ideas', 'ai-content-studio' ); ?></h2>

			<?php if ( ! $key_configured ) : ?>
				<p>
					<?php esc_html_e( 'Configure an OpenAI API key before generating blog ideas.', 'ai-content-studio' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aics-settings' ) ); ?>"><?php esc_html_e( 'Open Settings', 'ai-content-studio' ); ?></a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Generate five structured blog ideas from your validated Content Studio inputs.', 'ai-content-studio' ); ?></p>
				<form class="aics-generate-ideas-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-aics-generating-label="<?php echo esc_attr__( 'Generating…', 'ai-content-studio' ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::GENERATE_ACTION ); ?>">
					<?php wp_nonce_field( self::GENERATE_ACTION, self::GENERATE_NONCE_NAME ); ?>
					<?php submit_button( null === $idea_state ? __( 'Generate Blog Ideas', 'ai-content-studio' ) : __( 'Generate New Blog Ideas', 'ai-content-studio' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<?php if ( null !== $idea_state ) : ?>
				<div class="aics-idea-grid">
					<?php foreach ( $idea_state['ideas'] as $idea ) : ?>
						<?php $is_selected = null !== $selected_idea && $selected_idea['id'] === $idea['id']; ?>
						<article class="aics-idea-card<?php echo $is_selected ? ' aics-idea-card--selected' : ''; ?>"<?php echo $is_selected ? ' aria-current="true"' : ''; ?>>
							<h3><?php echo esc_html( $idea['title'] ); ?></h3>
							<p><?php echo esc_html( $idea['description'] ); ?></p>
							<dl class="aics-idea-meta">
								<dt><?php esc_html_e( 'Primary keyword', 'ai-content-studio' ); ?></dt>
								<dd><?php echo esc_html( $idea['primary_keyword'] ); ?></dd>
								<dt><?php esc_html_e( 'Search intent', 'ai-content-studio' ); ?></dt>
								<dd><?php echo esc_html( ucfirst( $idea['search_intent'] ) ); ?></dd>
							</dl>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( self::SELECT_ACTION ); ?>">
								<input type="hidden" name="selected_idea_id" value="<?php echo esc_attr( $idea['id'] ); ?>">
								<?php wp_nonce_field( self::SELECT_ACTION, self::SELECT_NONCE_NAME ); ?>
								<?php submit_button( $is_selected ? __( 'Selected', 'ai-content-studio' ) : __( 'Select Idea', 'ai-content-studio' ), $is_selected ? 'primary' : 'secondary', 'submit', false ); ?>
							</form>
						</article>
					<?php endforeach; ?>
				</div>

				<?php if ( null !== $selected_idea ) : ?>
					<p class="aics-selected-idea-message"><?php esc_html_e( 'Blog idea selected. Complete editable article generation will be added in the next task.', 'ai-content-studio' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Verifies a dedicated scalar nonce value.
	 *
	 * @param string $nonce_name Nonce field name.
	 * @param string $action     Nonce action.
	 * @return bool
	 */
	private static function verify_nonce( string $nonce_name, string $action ): bool {
		$nonce = isset( $_POST[ $nonce_name ] ) && is_string( $_POST[ $nonce_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Maps AI errors to fixed Content Studio notice codes.
	 *
	 * @param string $error_code AI response error code.
	 * @return string
	 */
	private static function map_generation_notice( string $error_code ): string {
		$allowed = array( 'missing-api-key', 'invalid-api-key', 'quota-error', 'rate-limit', 'model-unavailable', 'network-error', 'invalid-idea-format' );

		return in_array( $error_code, $allowed, true ) ? $error_code : 'generation-failed';
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
			'inputs-validated'           => array( 'success', __( 'Content inputs validated successfully. You can now generate blog ideas below.', 'ai-content-studio' ) ),
			'request-not-verified'       => array( 'error', __( 'The request could not be verified.', 'ai-content-studio' ) ),
			'ideas-generated'            => array( 'success', __( 'Blog ideas generated successfully.', 'ai-content-studio' ) ),
			'idea-selected'              => array( 'success', __( 'Blog idea selected successfully.', 'ai-content-studio' ) ),
			'inputs-missing'             => array( 'error', __( 'Content inputs are missing or expired. Validate the form again.', 'ai-content-studio' ) ),
			'missing-api-key'            => array( 'error', __( 'No OpenAI API key is configured.', 'ai-content-studio' ) ),
			'generation-failed'          => array( 'error', __( 'The AI service could not generate blog ideas.', 'ai-content-studio' ) ),
			'invalid-api-key'            => array( 'error', __( 'OpenAI authentication failed.', 'ai-content-studio' ) ),
			'quota-error'                => array( 'error', __( 'OpenAI reported a quota or billing issue.', 'ai-content-studio' ) ),
			'rate-limit'                 => array( 'error', __( 'OpenAI rate limit reached. Please try again later.', 'ai-content-studio' ) ),
			'model-unavailable'          => array( 'error', __( 'The selected model is not available for this account.', 'ai-content-studio' ) ),
			'network-error'              => array( 'error', __( 'The site could not connect to OpenAI.', 'ai-content-studio' ) ),
			'invalid-idea-format'        => array( 'error', __( 'OpenAI returned an invalid idea format.', 'ai-content-studio' ) ),
			'invalid-selected-idea'      => array( 'error', __( 'The selected idea is invalid or expired.', 'ai-content-studio' ) ),
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
	 * Returns the current user's generated-idea transient key.
	 *
	 * @return string
	 */
	private static function get_ideas_state_key(): string {
		return 'aics_blog_ideas_' . get_current_user_id();
	}

	/**
	 * Returns the current user's selected-idea transient key.
	 *
	 * @return string
	 */
	private static function get_selected_idea_key(): string {
		return 'aics_selected_blog_idea_' . get_current_user_id();
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
