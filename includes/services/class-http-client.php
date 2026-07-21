<?php
/**
 * WordPress HTTP API wrapper.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends JSON requests and normalizes remote responses.
 */
final class AICS_HTTP_Client {
	private const DEFAULT_TIMEOUT = 15;

	/**
	 * Sends a JSON POST request.
	 *
	 * Request headers and bodies are deliberately never logged or included in
	 * returned messages because they may contain credentials or prompt data.
	 *
	 * @param string               $url     HTTPS endpoint.
	 * @param array<string,string> $headers Request headers.
	 * @param array<string,mixed>  $body    Request body.
	 * @param int                  $timeout Timeout in seconds.
	 * @return array{success: bool, status_code: int, data: array|null, error_code: string, message: string}
	 */
	public function post_json( string $url, array $headers, array $body, int $timeout = self::DEFAULT_TIMEOUT ): array {
		$encoded_body = wp_json_encode( $body );

		if ( false === $encoded_body ) {
			return $this->failure( 0, null, 'json_encode_error', __( 'The request could not be prepared.', 'ai-content-studio' ) );
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers'            => $headers,
				'body'               => $encoded_body,
				'timeout'            => max( 5, min( 30, $timeout ) ),
				'data_format'        => 'body',
				'reject_unsafe_urls' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->failure( 0, null, 'network_error', __( 'The remote service could not be reached.', 'ai-content-studio' ) );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( '' === trim( $response_body ) ) {
			return $this->failure( $status_code, null, 'empty_response', __( 'The remote service returned an empty response.', 'ai-content-studio' ) );
		}

		$data = json_decode( $response_body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return $this->failure( $status_code, null, 'invalid_json', __( 'The remote service returned an invalid response.', 'ai-content-studio' ) );
		}

		if ( $status_code < 200 || $status_code >= 300 || isset( $data['error'] ) ) {
			return $this->failure( $status_code, $data, 'api_error', __( 'The remote service rejected the request.', 'ai-content-studio' ) );
		}

		return array(
			'success'     => true,
			'status_code' => $status_code,
			'data'        => $data,
			'error_code'  => '',
			'message'     => __( 'The remote request succeeded.', 'ai-content-studio' ),
		);
	}

	/**
	 * Builds a normalized failure result.
	 *
	 * @param int        $status_code HTTP status code, or zero for local failures.
	 * @param array|null $data        Decoded response data when safely available to the provider.
	 * @param string     $error_code  Internal normalized error code.
	 * @param string     $message     Safe generic message.
	 * @return array{success: false, status_code: int, data: array|null, error_code: string, message: string}
	 */
	private function failure( int $status_code, ?array $data, string $error_code, string $message ): array {
		return array(
			'success'     => false,
			'status_code' => $status_code,
			'data'        => $data,
			'error_code'  => $error_code,
			'message'     => $message,
		);
	}
}
