<?php
/**
 * Provider-independent AI response value object.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds a safe, predictable AI operation result.
 */
final class AICS_AI_Response {
	private bool $success;
	private ?array $data;
	private string $message;
	private string $error_code;
	private string $provider_name;
	private int $http_status_code;

	/**
	 * @param bool       $success          Whether the operation succeeded.
	 * @param array|null $data             Validated structured data only.
	 * @param string     $message          Safe public message.
	 * @param string     $error_code       Controlled internal error code.
	 * @param string     $provider_name    Provider display name.
	 * @param int        $http_status_code Non-sensitive HTTP status code.
	 */
	private function __construct( bool $success, ?array $data, string $message, string $error_code, string $provider_name, int $http_status_code ) {
		$this->success          = $success;
		$this->data             = $data;
		$this->message          = $message;
		$this->error_code       = $error_code;
		$this->provider_name    = $provider_name;
		$this->http_status_code = $http_status_code;
	}

	/**
	 * @param array  $data          Validated structured data.
	 * @param string $message       Safe public message.
	 * @param string $provider_name Provider display name.
	 * @param int    $http_status_code HTTP status code.
	 */
	public static function success( array $data, string $message, string $provider_name = '', int $http_status_code = 0 ): self {
		return new self( true, $data, $message, '', $provider_name, $http_status_code );
	}

	/**
	 * @param string $error_code      Controlled error code.
	 * @param string $message         Safe public message.
	 * @param string $provider_name   Provider display name.
	 * @param int    $http_status_code HTTP status code.
	 */
	public static function failure( string $error_code, string $message, string $provider_name = '', int $http_status_code = 0 ): self {
		return new self( false, null, $message, $error_code, $provider_name, $http_status_code );
	}

	public function is_success(): bool {
		return $this->success;
	}	

	public function get_data(): ?array {
		return $this->data;
	}

	public function get_message(): string {
		return $this->message;
	}

	public function get_error_code(): string {
		return $this->error_code;
	}

	public function get_provider_name(): string {
		return $this->provider_name;
	}

	public function get_http_status_code(): int {
		return $this->http_status_code;
	}
}
