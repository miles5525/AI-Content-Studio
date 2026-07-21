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
