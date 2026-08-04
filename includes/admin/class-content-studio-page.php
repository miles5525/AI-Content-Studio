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
 * Manages the administrator's Content Studio workflow.
 */
final class AICS_Content_Studio_Page {
	private const PAGE_SLUG = 'aics-create-content';
	private const ACTION = 'aics_prepare_content_inputs';
	private const NONCE_NAME = 'aics_content_inputs_nonce';
	private const GENERATE_ACTION = 'aics_generate_blog_ideas';
	private const GENERATE_NONCE_NAME = 'aics_generate_ideas_nonce';
	private const SELECT_ACTION = 'aics_select_blog_idea';
	private const SELECT_NONCE_NAME = 'aics_select_idea_nonce';
	private const GENERATE_ARTICLE_ACTION = 'aics_generate_article_draft';
	private const GENERATE_ARTICLE_NONCE_NAME = 'aics_generate_article_nonce';
	private const SAVE_ARTICLE_ACTION = 'aics_save_article_draft';
	private const SAVE_ARTICLE_NONCE_NAME = 'aics_save_article_nonce';
	private const CREATE_DRAFT_ACTION = 'aics_create_wordpress_draft';
	private const CREATE_DRAFT_NONCE_NAME = 'aics_create_wordpress_draft_nonce';
	private const GENERATE_IMAGE_ACTION = 'aics_generate_manual_featured_image';
	private const SAVE_IMAGE_ALT_ACTION = 'aics_save_manual_featured_image_alt';
	private const IMAGE_RESULT_PREFIX = 'aics_manual_image_result_';
	private const RESET_ACTION = 'aics_reset_content_workflow';
	private const RESET_NONCE_NAME = 'aics_reset_content_workflow_nonce';
	private const STATE_TTL = 20 * MINUTE_IN_SECONDS;
	private const ERROR_STATE_TTL = 5 * MINUTE_IN_SECONDS;
	private const ARTICLE_STATE_TTL = 45 * MINUTE_IN_SECONDS;
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
		add_action( 'admin_post_' . self::GENERATE_ARTICLE_ACTION, array( self::class, 'handle_generate_article' ) );
		add_action( 'admin_post_' . self::SAVE_ARTICLE_ACTION, array( self::class, 'handle_save_article' ) );
		add_action( 'admin_post_' . self::CREATE_DRAFT_ACTION, array( self::class, 'handle_create_wordpress_draft' ) );
		add_action( 'admin_post_' . self::GENERATE_IMAGE_ACTION, array( self::class, 'handle_generate_featured_image' ) );
		add_action( 'admin_post_' . self::SAVE_IMAGE_ALT_ACTION, array( self::class, 'handle_save_featured_image_alt' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( self::class, 'handle_reset_workflow' ) );
	}

	/**
	 * Renders the protected Content Studio form.
	 *
	 * @return void
	 */
	public static function render(): void {
		self::require_permission();

		$has_workflow     = self::has_workflow_state();
		$state            = self::get_form_state();
		$validated_inputs = self::get_validated_input_state();
		$idea_state       = self::get_idea_state();
		$selected_idea    = self::get_selected_idea();
		$article_draft    = self::get_article_draft();
		?>
		<div class="wrap aics-admin-wrap aics-content-studio-page">
			<header class="aics-page-header">
				<h1 class="aics-page-title"><?php esc_html_e( 'Create Content', 'ai-content-studio' ); ?></h1>
				<p class="aics-page-description"><?php esc_html_e( 'Turn your business context and topic into blog ideas, an editable article, and a reviewed WordPress draft.', 'ai-content-studio' ); ?></p>
			</header>
			<?php self::render_notice(); ?>
			<?php if ( $has_workflow ) : ?>
				<form class="aics-reset-workflow-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-aics-confirm="<?php echo esc_attr__( 'Start a new content workflow? Your current temporary inputs, generated ideas, and article draft will be cleared. Any WordPress draft already created will remain available.', 'ai-content-studio' ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET_ACTION ); ?>">
					<?php wp_nonce_field( self::RESET_ACTION, self::RESET_NONCE_NAME ); ?>
					<?php submit_button( __( 'Start New Content', 'ai-content-studio' ), 'secondary', 'submit', false ); ?>
					<span class="description"><?php esc_html_e( 'Existing WordPress posts will not be deleted.', 'ai-content-studio' ); ?></span>
				</form>
			<?php endif; ?>

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
			<?php self::render_article_draft( $validated_inputs, $selected_idea, $article_draft ); ?>
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
		delete_transient( self::get_article_draft_key() );
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

		$engine     = new AICS_AI_Engine();
		$started_at = microtime( true );
		$response   = $engine->generate_blog_ideas( $inputs );
		$duration   = AICS_Usage_Logger::duration_ms( $started_at );

		if ( ! $response->is_success() ) {
			self::log_usage( 'ai_request', 'generate_blog_ideas', 'failed', $response->get_error_code(), $duration );
			self::redirect( self::map_generation_notice( $response->get_error_code() ) );
		}

		$data = $response->get_data();

		if ( null === $data || ! isset( $data['ideas'] ) || ! is_array( $data['ideas'] ) ) {
			self::log_usage( 'ai_request', 'generate_blog_ideas', 'failed', 'invalid-idea-format', $duration );
			self::redirect( 'invalid-idea-format' );
		}

		self::log_usage( 'ai_request', 'generate_blog_ideas', 'success', '', $duration, 0, array( 'idea_count' => count( $data['ideas'] ) ) );

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
		delete_transient( self::get_article_draft_key() );
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

		$current_selection = self::get_selected_idea();

		if ( null === $current_selection || $current_selection['id'] !== $match['id'] ) {
			delete_transient( self::get_article_draft_key() );
		}

		set_transient( self::get_selected_idea_key(), $match, self::STATE_TTL );
		self::redirect( 'idea-selected' );
	}

	/**
	 * Generates a sanitized article draft from trusted temporary state.
	 *
	 * @return void
	 */
	public static function handle_generate_article(): void {
		self::require_permission();

		if ( ! self::verify_nonce( self::GENERATE_ARTICLE_NONCE_NAME, self::GENERATE_ARTICLE_ACTION ) ) {
			self::redirect( 'request-not-verified' );
		}

		if ( ! AICS_Settings::has_openai_api_key() ) {
			self::redirect( 'missing-api-key' );
		}

		$inputs        = self::get_validated_input_state();
		$selected_idea = self::get_selected_idea();

		if ( null === $inputs ) {
			self::redirect( 'inputs-missing' );
		}

		if ( null === $selected_idea ) {
			self::redirect( 'selected-idea-missing' );
		}

		$engine     = new AICS_AI_Engine();
		$existing_draft = self::get_article_draft();
		$started_at = microtime( true );
		$response   = $engine->generate_article_draft( $inputs, $selected_idea );
		$duration   = AICS_Usage_Logger::duration_ms( $started_at );
		$metadata   = array( 'requested_length' => $inputs['article_length'], 'tone' => $inputs['tone'] );

		if ( ! $response->is_success() ) {
			self::log_usage( 'ai_request', 'generate_article_draft', 'failed', $response->get_error_code(), $duration, 0, $metadata );
			self::redirect( self::map_article_generation_notice( $response->get_error_code() ) );
		}

		$article = $response->get_data();

		if ( null === $article ) {
			self::log_usage( 'ai_request', 'generate_article_draft', 'failed', 'invalid-article-format', $duration, 0, $metadata );
			self::redirect( 'invalid-article-format' );
		}

		$persistent=(new AICS_Manual_Article_Persistence_Service())->persist_generated($article,get_current_user_id(),absint($existing_draft['article_id']??0));
		if(empty($persistent['success'])){self::log_usage('ai_request','generate_article_draft','failed','manual-article-persistence-failed',$duration,0,$metadata);self::redirect('manual-article-persistence-failed');}

		self::log_usage( 'ai_request', 'generate_article_draft', 'success', '', $duration, 0, $metadata );

		$timestamp = current_time( 'timestamp', true );
		set_transient( self::get_state_key(), $inputs, self::ARTICLE_STATE_TTL );
		set_transient( self::get_selected_idea_key(), $selected_idea, self::ARTICLE_STATE_TTL );
		set_transient(
			self::get_article_draft_key(),
			array_merge(
				$article,
				array(
					'article_id'    => $persistent['article_id'],
					'article_uuid'  => $persistent['uuid'],
					'created_post_id' => absint($persistent['post_id']),
					'idea_id'      => $selected_idea['id'],
					'generated_at' => $timestamp,
					'updated_at'   => $timestamp,
					'model'        => AICS_Settings::get_openai_model(),
					'tone'         => $inputs['tone'],
					'length'       => $inputs['article_length'],
				)
			),
			self::ARTICLE_STATE_TTL
		);
		self::redirect( 'article-generated' );
	}

	/**
	 * Saves a manually edited temporary article draft.
	 *
	 * @return void
	 */
	public static function handle_save_article(): void {
		self::require_permission();

		if ( ! self::verify_nonce( self::SAVE_ARTICLE_NONCE_NAME, self::SAVE_ARTICLE_ACTION ) ) {
			self::redirect( 'request-not-verified' );
		}

		$selected_idea = self::get_selected_idea();
		$current_draft = self::get_article_draft();

		if ( null === $selected_idea || null === $current_draft || $current_draft['idea_id'] !== $selected_idea['id'] ) {
			self::redirect( 'selected-idea-missing' );
		}

		$title   = isset( $_POST['article_title'] ) && is_string( $_POST['article_title'] ) ? wp_unslash( $_POST['article_title'] ) : '';
		$excerpt = isset( $_POST['article_excerpt'] ) && is_string( $_POST['article_excerpt'] ) ? wp_unslash( $_POST['article_excerpt'] ) : '';
		$content = isset( $_POST['article_content'] ) && is_string( $_POST['article_content'] ) ? wp_unslash( $_POST['article_content'] ) : '';
		$article = ( new AICS_Post_Generator() )->prepare_edited_article( $title, $excerpt, $content );

		if ( is_wp_error( $article ) ) {
			self::redirect( self::map_article_validation_notice( $article->get_error_code() ) );
		}
		$persistent=(new AICS_Manual_Article_Persistence_Service())->update(absint($current_draft['article_id']??0),$article,get_current_user_id());
		if(empty($persistent['success'])){self::redirect('manual-article-persistence-failed');}

		$updated_draft               = array_merge( $current_draft, $article );
		$updated_draft['updated_at'] = current_time( 'timestamp', true );
		$inputs = self::get_validated_input_state();

		if ( null !== $inputs ) {
			set_transient( self::get_state_key(), $inputs, self::ARTICLE_STATE_TTL );
		}

		set_transient( self::get_selected_idea_key(), $selected_idea, self::ARTICLE_STATE_TTL );
		set_transient( self::get_article_draft_key(), $updated_draft, self::ARTICLE_STATE_TTL );
		self::redirect( 'article-saved' );
	}

	/**
	 * Creates a native WordPress draft from trusted temporary workflow state.
	 *
	 * @return void
	 */
	public static function handle_create_wordpress_draft(): void {
		self::require_permission();

		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			self::redirect( 'draft-creation-not-allowed' );
		}

		if ( ! self::verify_nonce( self::CREATE_DRAFT_NONCE_NAME, self::CREATE_DRAFT_ACTION ) ) {
			self::redirect( 'request-not-verified' );
		}

		$article_draft = self::get_article_draft();

		if ( null === $article_draft ) {
			self::redirect( 'article-draft-missing' );
		}

		$existing_post   = self::get_associated_post( $article_draft );

		if ( null !== $existing_post ) {
			self::redirect( 'wordpress-draft-already-exists' );
		}

		$started_at = microtime( true );
		$selected_idea = self::get_selected_idea();
		$inputs        = self::get_validated_input_state();
		$context       = array(
			'primary_keyword' => null !== $selected_idea ? $selected_idea['primary_keyword'] : '',
			'search_intent'   => null !== $selected_idea ? $selected_idea['search_intent'] : '',
			'tone'            => null !== $inputs ? $inputs['tone'] : $article_draft['tone'],
			'length'          => null !== $inputs ? $inputs['article_length'] : $article_draft['length'],
			'generated_at'    => $article_draft['generated_at'],
		);
		$result        = (new AICS_Manual_Article_Persistence_Service())->create_or_get_draft(absint($article_draft['article_id']??0),get_current_user_id(),$context);
		$duration      = AICS_Usage_Logger::duration_ms( $started_at );

		if ( empty($result['success']) ) {
			self::log_usage( 'post_creation', 'create_wordpress_draft', 'failed', $result['code'], $duration );
			self::redirect( in_array( $result['code'], array( 'invalid-article-draft', 'draft-creation-not-allowed' ), true ) ? $result['code'] : 'wordpress-draft-creation-failed' );
		}

		if(empty($result['reused'])){self::log_usage( 'post_creation', 'create_wordpress_draft', 'success', '', $duration, absint( $result['post_id'] ), array( 'post_status' => 'draft' ) );}

		$article_draft['created_post_id'] = absint( $result['post_id'] );
		$article_draft['created_post_at'] = current_time( 'timestamp', true );
		set_transient( self::get_article_draft_key(), $article_draft, self::ARTICLE_STATE_TTL );

		self::redirect(!empty($result['reused'])?'wordpress-draft-already-exists':'wordpress-draft-created');
	}

	/** Runs the shared image pipeline for the current persistent Manual Studio article. */
	public static function handle_generate_featured_image():void{self::require_permission();$draft=self::get_article_draft();$id=absint($_POST['article_id']??0);if(!$draft||$id!==absint($draft['article_id'])){self::store_image_result(array('success'=>false,'code'=>'manual_article_not_owned','message'=>__('The Manual Studio article could not be verified.','ai-content-studio')));self::redirect('manual-image-result');}$nonce=isset($_POST['aics_manual_image_nonce'])&&is_string($_POST['aics_manual_image_nonce'])?sanitize_text_field(wp_unslash($_POST['aics_manual_image_nonce'])):'';if(!wp_verify_nonce($nonce,self::GENERATE_IMAGE_ACTION.'_'.$id)){self::store_image_result(array('success'=>false,'code'=>'request_not_verified','message'=>__('The request could not be verified.','ai-content-studio')));self::redirect('manual-image-result');}$result=(new AICS_Manual_Featured_Image_Service())->generate($id,get_current_user_id());self::store_image_result(array('success'=>!empty($result['success']),'code'=>$result['code']??'manual_featured_image_failed','message'=>$result['message']??__('This article featured image requires manual review.','ai-content-studio')));self::redirect('manual-image-result');}

	/** Synchronizes article and attachment alt text without provider work. */
	public static function handle_save_featured_image_alt():void{self::require_permission();$draft=self::get_article_draft();$id=absint($_POST['article_id']??0);if(!$draft||$id!==absint($draft['article_id'])){self::store_image_result(array('success'=>false,'code'=>'manual_article_not_owned','message'=>__('The Manual Studio article could not be verified.','ai-content-studio')));self::redirect('manual-image-result');}$nonce=isset($_POST['aics_manual_alt_nonce'])&&is_string($_POST['aics_manual_alt_nonce'])?sanitize_text_field(wp_unslash($_POST['aics_manual_alt_nonce'])):'';if(!wp_verify_nonce($nonce,self::SAVE_IMAGE_ALT_ACTION.'_'.$id)){self::store_image_result(array('success'=>false,'code'=>'request_not_verified','message'=>__('The request could not be verified.','ai-content-studio')));self::redirect('manual-image-result');}$alt=isset($_POST['featured_image_alt_text'])&&is_string($_POST['featured_image_alt_text'])?wp_unslash($_POST['featured_image_alt_text']):'';$result=(new AICS_Manual_Featured_Image_Service())->save_alt_text($id,get_current_user_id(),$alt);self::store_image_result(array('success'=>!empty($result['success']),'code'=>$result['code']??'attachment_persistence_failed','message'=>$result['message']??__('Image alt text could not be updated.','ai-content-studio')));self::redirect('manual-image-result');}

	/**
	 * Clears only the current user's temporary Content Studio workflow.
	 *
	 * @return void
	 */
	public static function handle_reset_workflow(): void {
		if ( ! is_user_logged_in() || ! current_user_can( \AIContentStudio\Core\Permissions::manage() ) ) {
			self::redirect( 'workflow-reset-not-allowed' );
		}

		if ( ! self::verify_nonce( self::RESET_NONCE_NAME, self::RESET_ACTION ) ) {
			self::redirect( 'request-not-verified' );
		}

		$had_state = self::has_workflow_state();

		delete_transient( self::get_state_key() );
		delete_transient( self::get_error_state_key() );
		delete_transient( self::get_ideas_state_key() );
		delete_transient( self::get_selected_idea_key() );
		delete_transient( self::get_article_draft_key() );

		self::redirect( $had_state ? 'workflow-reset' : 'workflow-already-empty' );
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
	 * Returns the current user's sanitized temporary article draft.
	 *
	 * @return array|null
	 */
	private static function get_article_draft(): ?array {
		$draft = get_transient( self::get_article_draft_key() );
		$required = array( 'article_id', 'article_uuid', 'title', 'content', 'excerpt', 'idea_id', 'generated_at', 'updated_at', 'model', 'tone', 'length' );

		if ( ! is_array( $draft ) ) {
			return null;
		}

		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $draft ) ) {
				return null;
			}
		}

		foreach ( array( 'article_uuid', 'title', 'content', 'excerpt', 'idea_id', 'model', 'tone', 'length' ) as $key ) {
			if ( ! is_string( $draft[ $key ] ) ) {
				return null;
			}
		}
		if(absint($draft['article_id'])<1||!wp_is_uuid($draft['article_uuid'],4)){return null;}

		return $draft;
	}

	/**
	 * Returns a valid post associated with this user's current article workflow.
	 *
	 * Invalid, deleted, trashed, or unmarked associations are cleared without
	 * deleting the WordPress post.
	 *
	 * @param array<string,mixed> $article_draft Current temporary article state.
	 * @return WP_Post|null
	 */
	private static function get_associated_post( array &$article_draft ): ?WP_Post {
		$post_id = absint( $article_draft['created_post_id'] ?? 0 );

		if ( $post_id < 1 ) {
			return null;
		}

		$post            = get_post( $post_id );
		$is_aics_post    = '1' === (string) get_post_meta( $post_id, '_aics_generated_post', true );
		$is_manual       = 'manual' === (string) get_post_meta( $post_id, '_aics_source', true );
		$is_article      = absint($article_draft['article_id']??0)===absint(get_post_meta($post_id,'_aics_article_id',true))&&sanitize_text_field((string)($article_draft['article_uuid']??''))===sanitize_text_field((string)get_post_meta($post_id,'_aics_article_uuid',true));
		$is_same_user    = get_current_user_id() === absint( get_post_meta( $post_id, '_aics_created_by_user', true ) );
		$is_associated   = $is_aics_post&&$is_manual&&$is_article&&$is_same_user;

		if ( ! $post instanceof WP_Post || 'trash' === get_post_status( $post_id ) || ! $is_associated ) {
			unset( $article_draft['created_post_id'], $article_draft['created_post_at'] );
			set_transient( self::get_article_draft_key(), $article_draft, self::ARTICLE_STATE_TTL );
			return null;
		}

		return $post;
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
	 * Renders article-generation controls and the editable temporary draft.
	 *
	 * @param array|null $validated_inputs Current validated inputs.
	 * @param array|null $selected_idea    Current selected idea.
	 * @param array|null $article_draft    Current sanitized draft.
	 * @return void
	 */
	private static function render_article_draft( ?array $validated_inputs, ?array $selected_idea, ?array $article_draft ): void {
		if ( null === $selected_idea ) {
			return;
		}

		if ( null !== $article_draft && $article_draft['idea_id'] !== $selected_idea['id'] ) {
			$article_draft = null;
		}

		$key_configured = AICS_Settings::has_openai_api_key();
		$created_post   = null !== $article_draft ? self::get_associated_post( $article_draft ) : null;
		?>
		<section class="aics-article-section" aria-labelledby="aics-article-heading">
			<h2 id="aics-article-heading"><?php esc_html_e( 'Article Draft', 'ai-content-studio' ); ?></h2>
			<div class="aics-selected-idea-summary">
				<h3><?php esc_html_e( 'Selected Idea', 'ai-content-studio' ); ?></h3>
				<p><strong><?php echo esc_html( $selected_idea['title'] ); ?></strong></p>
				<p><?php echo esc_html( $selected_idea['description'] ); ?></p>
			</div>

			<?php if ( null === $article_draft ) : ?>
				<p><?php esc_html_e( 'Generate an editable temporary draft. This action does not create or publish a WordPress post.', 'ai-content-studio' ); ?></p>
				<?php if ( null === $validated_inputs ) : ?>
					<p><?php esc_html_e( 'Content inputs are missing or expired. Validate the Content Inputs form again before generating an article.', 'ai-content-studio' ); ?></p>
				<?php elseif ( $key_configured ) : ?>
					<form class="aics-generate-article-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-aics-generating-label="<?php echo esc_attr__( 'Generating…', 'ai-content-studio' ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::GENERATE_ARTICLE_ACTION ); ?>">
						<?php wp_nonce_field( self::GENERATE_ARTICLE_ACTION, self::GENERATE_ARTICLE_NONCE_NAME ); ?>
						<?php submit_button( __( 'Generate Article Draft', 'ai-content-studio' ), 'primary', 'submit', false ); ?>
					</form>
				<?php else : ?>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aics-settings' ) ); ?>"><?php esc_html_e( 'Configure an OpenAI API key in Settings.', 'ai-content-studio' ); ?></a></p>
				<?php endif; ?>
			<?php else : ?>
				<form class="aics-article-draft-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-aics-draft-form>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ARTICLE_ACTION ); ?>">
					<?php wp_nonce_field( self::SAVE_ARTICLE_ACTION, self::SAVE_ARTICLE_NONCE_NAME ); ?>
					<p>
						<label for="aics-article-title"><strong><?php esc_html_e( 'Article Title', 'ai-content-studio' ); ?></strong></label><br>
						<input id="aics-article-title" class="large-text" type="text" name="article_title" value="<?php echo esc_attr( $article_draft['title'] ); ?>" maxlength="250" required data-aics-draft-field>
					</p>
					<p>
						<label for="aics-article-excerpt"><strong><?php esc_html_e( 'Article Excerpt', 'ai-content-studio' ); ?></strong></label><br>
						<textarea id="aics-article-excerpt" class="large-text" name="article_excerpt" rows="4" maxlength="500" required data-aics-draft-field><?php echo esc_textarea( $article_draft['excerpt'] ); ?></textarea>
					</p>
					<div class="aics-article-editor">
						<label for="aics_article_content_editor"><strong><?php esc_html_e( 'Article Content', 'ai-content-studio' ); ?></strong></label>
						<?php
						wp_editor(
							$article_draft['content'],
							'aics_article_content_editor',
							array(
								'media_buttons' => false,
								'teeny'         => false,
								'quicktags'     => true,
								'textarea_name' => 'article_content',
								'textarea_rows' => 24,
							)
						);
						?>
					</div>
					<?php submit_button( __( 'Save Edited Draft', 'ai-content-studio' ) ); ?>
				</form>

				<?php if ( null !== $validated_inputs ) : ?>
					<form class="aics-generate-article-form aics-regenerate-article-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-aics-confirm="<?php echo esc_attr__( 'Regeneration will replace the current article only if the new generation succeeds. Continue?', 'ai-content-studio' ); ?>" data-aics-generating-label="<?php echo esc_attr__( 'Generating…', 'ai-content-studio' ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::GENERATE_ARTICLE_ACTION ); ?>">
						<?php wp_nonce_field( self::GENERATE_ARTICLE_ACTION, self::GENERATE_ARTICLE_NONCE_NAME ); ?>
						<?php submit_button( __( 'Regenerate Article', 'ai-content-studio' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'The current draft remains editable, but regeneration requires unexpired validated inputs.', 'ai-content-studio' ); ?></p>
				<?php endif; ?>

				<?php self::render_wordpress_draft_action( $article_draft, $created_post ); ?>
				<?php self::render_featured_image_section($article_draft); ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Renders native WordPress draft creation or the associated-draft result.
	 *
	 * @param array<string,mixed> $article_draft Current temporary article draft.
	 * @param WP_Post|null        $created_post  Valid associated WordPress post.
	 * @return void
	 */
	private static function render_wordpress_draft_action( array $article_draft, ?WP_Post $created_post ): void {
		if ( null !== $created_post ) {
			$edit_link = current_user_can( 'edit_post', $created_post->ID ) ? get_edit_post_link( $created_post->ID, '' ) : '';
			?>
			<div class="aics-wordpress-draft-result">
				<h3><?php esc_html_e( 'WordPress Draft Created', 'ai-content-studio' ); ?></h3>
				<p><strong><?php esc_html_e( 'Post title:', 'ai-content-studio' ); ?></strong> <?php echo esc_html( get_the_title( $created_post ) ); ?></p>
				<p><strong><?php esc_html_e( 'Status:', 'ai-content-studio' ); ?></strong> <?php echo esc_html( ucfirst( get_post_status( $created_post ) ) ); ?></p>
				<?php if ( is_string( $edit_link ) && '' !== $edit_link ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit Draft', 'ai-content-studio' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'View Posts', 'ai-content-studio' ); ?></a>
			</div>
			<?php
			return;
		}
		?>
		<div class="aics-wordpress-draft-action">
			<h3><?php esc_html_e( 'Create WordPress Draft', 'ai-content-studio' ); ?></h3>
			<p><?php esc_html_e( 'The reviewed article will be saved as a WordPress draft. It will not be published automatically and can be reviewed further in the WordPress editor.', 'ai-content-studio' ); ?></p>
			<?php if ( current_user_can( 'edit_posts' ) ) : ?>
				<form class="aics-create-wordpress-draft-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-aics-creating-label="<?php echo esc_attr__( 'Creating Draft...', 'ai-content-studio' ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE_DRAFT_ACTION ); ?>">
					<?php wp_nonce_field( self::CREATE_DRAFT_ACTION, self::CREATE_DRAFT_NONCE_NAME ); ?>
					<?php submit_button( __( 'Create WordPress Draft', 'ai-content-studio' ), 'primary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'You are not allowed to create posts.', 'ai-content-studio' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Renders preview-safe Manual Studio image state and protected actions. */
	private static function render_featured_image_section(array $draft):void{$id=absint($draft['article_id']??0);if(!$id){return;}$view=(new AICS_Manual_Featured_Image_Service())->view($id,get_current_user_id());if(empty($view['success'])){return;}$article=$view['article'];$status=$article['featured_image_status'];$attachment=$view['attachment'];?><section class="aics-content-section aics-featured-image-review" aria-labelledby="aics-featured-image-heading"><h3 id="aics-featured-image-heading"><?php esc_html_e('Featured Image','ai-content-studio');?></h3><p><strong><?php esc_html_e('Status:','ai-content-studio');?></strong> <?php echo esc_html(AICS_Featured_Image_State::status_label($status));?></p><?php if(empty($view['post_valid'])):?><p><?php esc_html_e('Create a WordPress draft before generating its featured image.','ai-content-studio');?></p><?php elseif('attached'===$status&&is_array($attachment)):?><div class="aics-featured-image-preview"><img src="<?php echo esc_url($attachment['url']);?>" alt="<?php echo esc_attr($attachment['alt']);?>"><p><?php echo esc_html(sprintf(__('Attachment %1$d · %2$d × %3$d · %4$s','ai-content-studio'),$attachment['id'],$attachment['width'],$attachment['height'],strtoupper(str_replace('image/','',$attachment['mime']))));?></p></div><?php self::render_alt_form($id,$attachment);self::render_image_links($article,$attachment['id']);?><?php elseif('uploaded'===$status):?><p><?php esc_html_e('The image was uploaded but has not yet been assigned to the post.','ai-content-studio');?></p><?php self::render_image_action($id,__('Retry Assignment','ai-content-studio'));if(is_array($attachment)){self::render_alt_form($id,$attachment);}?><?php elseif(in_array($status,array('pending','generating'),true)):?><p><?php echo esc_html(sprintf(__('Image generation is currently %s.','ai-content-studio'),strtolower(AICS_Featured_Image_State::status_label($status))));?></p><?php elseif('skipped'===$status):?><p><?php esc_html_e('Featured-image generation was skipped for this article.','ai-content-studio');?></p><?php elseif(in_array($status,array('retrying','failed','needs_attention'),true)):?><p><?php echo esc_html(AICS_Featured_Image_State::error_label($article['featured_image_last_error_code']??''));?></p><?php if(!empty($article['featured_image_last_error_code'])):?><p><code><?php echo esc_html(sanitize_key($article['featured_image_last_error_code']));?></code></p><?php endif;?><?php if('featured_image_ownership_conflict'!==($article['featured_image_last_error_code']??'')&&(!empty($view['provider_ready'])||is_array($attachment))):self::render_image_action($id,__('Retry Featured Image','ai-content-studio'));endif;if(is_array($attachment)){self::render_alt_form($id,$attachment);}?><?php else:?><p><?php esc_html_e('No featured image has been generated.','ai-content-studio');?></p><?php if(!empty($view['provider_ready'])):self::render_image_action($id,__('Generate Featured Image','ai-content-studio'));else:?><p><?php esc_html_e('Configure the image provider in Settings before generating an image.','ai-content-studio');?></p><?php endif;?><?php endif;?></section><?php }
	private static function render_image_action(int $id,string $label):void{?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" data-aics-generating-label="<?php echo esc_attr__('Generating…','ai-content-studio');?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::GENERATE_IMAGE_ACTION);?>"><input type="hidden" name="article_id" value="<?php echo esc_attr((string)$id);?>"><?php wp_nonce_field(self::GENERATE_IMAGE_ACTION.'_'.$id,'aics_manual_image_nonce');?><?php submit_button($label,'secondary','submit',false);?></form><?php }
	private static function render_alt_form(int $id,array $attachment):void{?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_IMAGE_ALT_ACTION);?>"><input type="hidden" name="article_id" value="<?php echo esc_attr((string)$id);?>"><?php wp_nonce_field(self::SAVE_IMAGE_ALT_ACTION.'_'.$id,'aics_manual_alt_nonce');?><p><label for="aics-featured-alt-<?php echo esc_attr((string)$id);?>"><strong><?php esc_html_e('Alt text','ai-content-studio');?></strong></label><br><input id="aics-featured-alt-<?php echo esc_attr((string)$id);?>" class="regular-text" type="text" maxlength="250" name="featured_image_alt_text" value="<?php echo esc_attr($attachment['alt']);?>"></p><?php submit_button(__('Save Alt Text','ai-content-studio'),'secondary','submit',false);?></form><?php }
	private static function render_image_links(array $article,int $attachment):void{$post=absint($article['wordpress_post_id']);$post_link=current_user_can('edit_post',$post)?get_edit_post_link($post,''):'';$media_link=current_user_can('edit_post',$attachment)?get_edit_post_link($attachment,''):'';if(!$post_link&&!$media_link){return;}?><p><?php if($post_link):?><a href="<?php echo esc_url($post_link);?>"><?php esc_html_e('Edit Post','ai-content-studio');?></a><?php endif;?><?php if($post_link&&$media_link):?> | <?php endif;?><?php if($media_link):?><a href="<?php echo esc_url($media_link);?>"><?php esc_html_e('Edit Media','ai-content-studio');?></a><?php endif;?></p><?php }

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
	 * Maps article-generation errors to fixed notice codes.
	 *
	 * @param string $error_code AI response error code.
	 * @return string
	 */
	private static function map_article_generation_notice( string $error_code ): string {
		$allowed = array( 'missing-api-key', 'invalid-api-key', 'quota-error', 'rate-limit', 'model-unavailable', 'network-error', 'article-title-required', 'article-title-too-long', 'article-excerpt-required', 'article-excerpt-too-long', 'article-content-required', 'article-content-too-large' );

		if ( in_array( $error_code, $allowed, true ) ) {
			return $error_code;
		}

		return 'invalid-article-format' === $error_code ? $error_code : 'article-generation-failed';
	}

	/**
	 * Maps manual-edit validation errors to fixed notice codes.
	 *
	 * @param string $error_code Validation error code.
	 * @return string
	 */
	private static function map_article_validation_notice( string $error_code ): string {
		$allowed = array( 'article-title-required', 'article-title-too-long', 'article-excerpt-required', 'article-excerpt-too-long', 'article-content-required', 'article-content-too-large' );

		return in_array( $error_code, $allowed, true ) ? $error_code : 'invalid-article-format';
	}

	/**
	 * Renders a controlled success or validation notice.
	 *
	 * @return void
	 */
	private static function render_notice(): void {
		$notice = isset( $_GET['aics_notice'] ) && is_string( $_GET['aics_notice'] ) ? sanitize_key( wp_unslash( $_GET['aics_notice'] ) ) : '';
		if('manual-image-result'===$notice){self::render_image_result_notice();return;}
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
			'article-generated'          => array( 'success', __( 'Article draft generated successfully.', 'ai-content-studio' ) ),
			'article-saved'              => array( 'success', __( 'Article draft saved successfully.', 'ai-content-studio' ) ),
			'selected-idea-missing'      => array( 'error', __( 'The selected blog idea is missing or expired.', 'ai-content-studio' ) ),
			'article-generation-failed'  => array( 'error', __( 'Article generation failed. The previous draft was preserved.', 'ai-content-studio' ) ),
			'invalid-article-format'     => array( 'error', __( 'OpenAI returned an invalid article format.', 'ai-content-studio' ) ),
			'article-title-required'     => array( 'error', __( 'Article title is required.', 'ai-content-studio' ) ),
			'article-title-too-long'     => array( 'error', __( 'Article title is too long.', 'ai-content-studio' ) ),
			'article-excerpt-required'   => array( 'error', __( 'Article excerpt is required.', 'ai-content-studio' ) ),
			'article-excerpt-too-long'   => array( 'error', __( 'Article excerpt is too long.', 'ai-content-studio' ) ),
			'article-content-required'   => array( 'error', __( 'Article content is required.', 'ai-content-studio' ) ),
			'article-content-too-large'  => array( 'error', __( 'Article content is too large.', 'ai-content-studio' ) ),
			'manual-article-persistence-failed' => array( 'error', __( 'The generated article could not be saved to shared article storage. The previous article was preserved.', 'ai-content-studio' ) ),
			'wordpress-draft-created'    => array( 'success', __( 'WordPress draft created successfully.', 'ai-content-studio' ) ),
			'wordpress-draft-created-meta-warning' => array( 'warning', __( 'The WordPress draft was created, but some association metadata could not be saved.', 'ai-content-studio' ) ),
			'wordpress-draft-already-exists' => array( 'warning', __( 'A WordPress draft already exists for this article.', 'ai-content-studio' ) ),
			'article-draft-missing'       => array( 'error', __( 'The article draft is missing or expired.', 'ai-content-studio' ) ),
			'invalid-article-draft'       => array( 'error', __( 'The article draft is invalid.', 'ai-content-studio' ) ),
			'wordpress-draft-creation-failed' => array( 'error', __( 'WordPress could not create the draft.', 'ai-content-studio' ) ),
			'draft-creation-not-allowed'  => array( 'error', __( 'You are not allowed to create posts.', 'ai-content-studio' ) ),
			'associated-draft-missing'    => array( 'warning', __( 'The previously associated draft no longer exists. You can create a new WordPress draft.', 'ai-content-studio' ) ),
			'workflow-reset'              => array( 'success', __( 'New content workflow started successfully.', 'ai-content-studio' ) ),
			'workflow-already-empty'      => array( 'info', __( 'The content workflow was already empty.', 'ai-content-studio' ) ),
			'workflow-reset-not-allowed'  => array( 'error', __( 'You are not allowed to perform this action.', 'ai-content-studio' ) ),
		);

		if ( ! isset( $notices[ $notice ] ) ) {
			return;
		}
		?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ][1] ); ?></p></div>
		<?php
	}
	private static function store_image_result(array $result):void{set_transient(self::IMAGE_RESULT_PREFIX.get_current_user_id(),array('success'=>!empty($result['success']),'code'=>sanitize_key((string)($result['code']??'')),'message'=>sanitize_text_field((string)($result['message']??''))),2*MINUTE_IN_SECONDS);}
	private static function render_image_result_notice():void{$key=self::IMAGE_RESULT_PREFIX.get_current_user_id();$result=get_transient($key);delete_transient($key);if(!is_array($result)){return;}?><div class="notice notice-<?php echo esc_attr(!empty($result['success'])?'success':'error');?> is-dismissible"><p><?php echo esc_html($result['message']??__('Featured-image operation completed.','ai-content-studio'));?></p><?php if(empty($result['success'])&&!empty($result['code'])):?><p><code><?php echo esc_html($result['code']);?></code></p><?php endif;?></div><?php }

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
	 * Returns the current user's temporary article-draft transient key.
	 *
	 * @return string
	 */
	private static function get_article_draft_key(): string {
		return 'aics_article_draft_' . get_current_user_id();
	}

	/**
	 * Reports whether the current user has meaningful temporary workflow state.
	 *
	 * @return bool
	 */
	private static function has_workflow_state(): bool {
		$keys = array(
			self::get_state_key(),
			self::get_error_state_key(),
			self::get_ideas_state_key(),
			self::get_selected_idea_key(),
			self::get_article_draft_key(),
		);

		foreach ( $keys as $key ) {
			if ( false !== get_transient( $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Writes one non-sensitive usage record without affecting the main action.
	 */
	private static function log_usage( string $event, string $operation, string $status, string $error_code, int $duration, int $object_id = 0, array $metadata = array() ): void {
		AICS_Usage_Logger::log(
			array(
				'event_type'  => $event,
				'operation'   => $operation,
				'status'      => $status,
				'provider'    => 'ai_request' === $event ? 'openai' : '',
				'model'       => 'ai_request' === $event ? AICS_Settings::get_openai_model() : '',
				'error_code'  => $error_code,
				'object_id'   => $object_id,
				'duration_ms' => $duration,
				'metadata'    => $metadata,
			)
		);
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
