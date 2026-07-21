<?php
/**
 * OpenAI provider implementation.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tests saved OpenAI credentials through the Responses API.
 */
final class AICS_OpenAI_Provider implements AICS_Provider_Interface {
	private const ENDPOINT = 'https://api.openai.com/v1/responses';

	private AICS_HTTP_Client $http_client;

	/**
	 * Creates the provider.
	 *
	 * @param AICS_HTTP_Client|null $http_client Optional client for testing.
	 */
	public function __construct( ?AICS_HTTP_Client $http_client = null ) {
		$this->http_client = $http_client ?? new AICS_HTTP_Client();
	}

	/**
	 * Returns the provider's display name.
	 *
	 * @return string
	 */
	public function get_provider_name(): string {
		return 'OpenAI';
	}

	/**
	 * Tests the saved API key and model with a minimal Responses API request.
	 *
	 * @return array{success: bool, code: string, message: string}
	 */
	public function test_connection(): array {
		$api_key = AICS_Settings::get( 'openai_api_key', '' );

		if ( ! is_string( $api_key ) || '' === $api_key ) {
			return $this->result( false, 'missing-api-key', __( 'No OpenAI API key is configured.', 'ai-content-studio' ) );
		}

		$response = $this->http_client->post_json(
			self::ENDPOINT,
			array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			array(
				'model'             => AICS_Settings::get_openai_model(),
				'input'             => 'Reply with exactly OK.',
				'max_output_tokens' => 16,
			)
		);

		if ( true === $response['success'] ) {
			return $this->result( true, 'connection-success', __( 'OpenAI connection successful.', 'ai-content-studio' ) );
		}

		return $this->map_failure( $response );
	}

	/**
	 * Generates structured data through the OpenAI Responses API.
	 *
	 * @param AICS_AI_Request $request Provider-independent request.
	 * @return AICS_AI_Response
	 */
	public function generate( AICS_AI_Request $request ): AICS_AI_Response {
		$api_key = AICS_Settings::get( 'openai_api_key', '' );

		if ( ! is_string( $api_key ) || '' === $api_key ) {
			return AICS_AI_Response::failure( 'missing-api-key', __( 'No OpenAI API key is configured.', 'ai-content-studio' ), $this->get_provider_name() );
		}

		if ( ! in_array( $request->get_task_type(), array( 'blog_ideas', 'automation_ideas', 'article_draft' ), true ) ) {
			return AICS_AI_Response::failure( 'unsupported-task', __( 'The requested AI task is not supported.', 'ai-content-studio' ), $this->get_provider_name() );
		}

		$response = $this->http_client->post_json(
			self::ENDPOINT,
			array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			array(
				'model'             => AICS_Settings::get_openai_model(),
				'instructions'      => $request->get_system_instructions(),
				'input'             => $request->get_user_prompt(),
				'max_output_tokens' => $request->get_max_output_tokens(),
				'store'             => false,
				'text'              => array(
					'format' => array(
						'type'   => 'json_schema',
						'name'   => $request->get_task_type(),
						'strict' => true,
						'schema' => $request->get_structured_output_schema(),
					),
				),
			),
			'article_draft' === $request->get_task_type() ? 30 : 15
		);

		if ( ! $response['success'] ) {
			$failure = $this->map_failure( $response );

			return AICS_AI_Response::failure( $failure['code'], $failure['message'], $this->get_provider_name(), $response['status_code'] );
		}

		$structured_data = $this->extract_structured_output( $response['data'] );

		if ( null === $structured_data ) {
			$error_code = 'article_draft' === $request->get_task_type() ? 'invalid-article-format' : 'invalid-idea-format';
			$message    = 'article_draft' === $request->get_task_type() ? __( 'OpenAI returned an invalid article format.', 'ai-content-studio' ) : __( 'OpenAI returned an invalid idea format.', 'ai-content-studio' );

			return AICS_AI_Response::failure( $error_code, $message, $this->get_provider_name(), $response['status_code'] );
		}

		return AICS_AI_Response::success( $structured_data, __( 'OpenAI returned structured content.', 'ai-content-studio' ), $this->get_provider_name(), $response['status_code'] );
	}

	/**
	 * Defensively extracts JSON text from a Responses API response.
	 *
	 * @param array|null $data Decoded Responses API data.
	 * @return array|null
	 */
	private function extract_structured_output( ?array $data ): ?array {
		if ( null === $data || ( isset( $data['status'] ) && 'completed' !== $data['status'] ) ) {
			return null;
		}

		$text_parts = array();

		if ( isset( $data['output_text'] ) && is_string( $data['output_text'] ) ) {
			$text_parts[] = $data['output_text'];
		}

		if ( empty( $text_parts ) && isset( $data['output'] ) && is_array( $data['output'] ) ) {
			foreach ( $data['output'] as $output_item ) {
				if ( ! is_array( $output_item ) || ( isset( $output_item['status'] ) && 'completed' !== $output_item['status'] ) || ! isset( $output_item['content'] ) || ! is_array( $output_item['content'] ) ) {
					continue;
				}

				foreach ( $output_item['content'] as $content_item ) {
					if ( ! is_array( $content_item ) || 'output_text' !== ( $content_item['type'] ?? '' ) || ! isset( $content_item['text'] ) || ! is_string( $content_item['text'] ) ) {
						continue;
					}

					$text_parts[] = $content_item['text'];
				}
			}
		}

		$text = trim( implode( '', $text_parts ) );

		if ( '' === $text ) {
			return null;
		}

		$text = $this->remove_markdown_fence( $text );
		$decoded = json_decode( $text, true );

		return JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Removes one accidental outer Markdown code fence.
	 *
	 * @param string $text Candidate JSON text.
	 * @return string
	 */
	private function remove_markdown_fence( string $text ): string {
		if ( ! str_starts_with( $text, '```' ) || ! str_ends_with( $text, '```' ) ) {
			return $text;
		}

		$first_line_end = strpos( $text, "\n" );

		if ( false === $first_line_end ) {
			return $text;
		}

		return trim( substr( $text, $first_line_end + 1, -3 ) );
	}

	/**
	 * Maps HTTP and OpenAI failures to safe provider results.
	 *
	 * @param array{success: bool, status_code: int, data: array|null, error_code: string, message: string} $response HTTP result.
	 * @return array{success: false, code: string, message: string}
	 */
	private function map_failure( array $response ): array {
		$status_code = $response['status_code'];
		$error_code  = $this->get_openai_error_code( $response['data'] );

		if ( 'network_error' === $response['error_code'] ) {
			return $this->result( false, 'network-error', __( 'The site could not connect to OpenAI.', 'ai-content-studio' ) );
		}

		if ( 401 === $status_code ) {
			return $this->result( false, 'invalid-api-key', __( 'OpenAI rejected the API key.', 'ai-content-studio' ) );
		}

		if ( in_array( $error_code, array( 'insufficient_quota', 'billing_hard_limit_reached' ), true ) ) {
			return $this->result( false, 'quota-error', __( 'OpenAI reported a quota or billing issue.', 'ai-content-studio' ) );
		}

		if ( 429 === $status_code ) {
			return $this->result( false, 'rate-limit', __( 'OpenAI rate limit reached. Please try again later.', 'ai-content-studio' ) );
		}

		if ( in_array( $status_code, array( 403, 404 ), true ) || in_array( $error_code, array( 'model_not_found', 'invalid_model' ), true ) ) {
			return $this->result( false, 'model-unavailable', __( 'The selected model is not available for this account.', 'ai-content-studio' ) );
		}

		if ( in_array( $response['error_code'], array( 'empty_response', 'invalid_json' ), true ) ) {
			return $this->result( false, 'invalid-response', __( 'OpenAI returned an unexpected response.', 'ai-content-studio' ) );
		}

		return $this->result( false, 'api-error', __( 'OpenAI could not complete the connection test.', 'ai-content-studio' ) );
	}

	/**
	 * Reads only the non-secret OpenAI error code from decoded response data.
	 *
	 * @param array|null $data Decoded response data.
	 * @return string
	 */
	private function get_openai_error_code( ?array $data ): string {
		if ( ! isset( $data['error'] ) || ! is_array( $data['error'] ) ) {
			return '';
		}

		$code = $data['error']['code'] ?? $data['error']['type'] ?? '';

		return is_string( $code ) ? sanitize_key( $code ) : '';
	}

	/**
	 * Builds a predictable provider result.
	 *
	 * @param bool   $success Whether the test succeeded.
	 * @param string $code    Controlled result code.
	 * @param string $message Safe user-facing message.
	 * @return array{success: bool, code: string, message: string}
	 */
	private function result( bool $success, string $code, string $message ): array {
		return array(
			'success' => $success,
			'code'    => $code,
			'message' => $message,
		);
	}
}
