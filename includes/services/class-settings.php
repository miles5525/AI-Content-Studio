<?php
/**
 * Centralized plugin settings access.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the AI Content Studio settings option.
 */
final class AICS_Settings {
	private const OPTION_NAME = 'aics_settings';
	private const DEFAULT_MODEL = 'gpt-4.1-mini';
	private const ALLOWED_MODELS = array(
		'gpt-4.1-mini',
		'gpt-4.1',
		'gpt-4o-mini',
		'gpt-4o',
	);

	/**
	 * Returns all supported settings with defaults applied.
	 *
	 * @return array<string, string>
	 */
	public static function get_all(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return array(
			'openai_api_key' => isset( $settings['openai_api_key'] ) && is_string( $settings['openai_api_key'] ) ? $settings['openai_api_key'] : '',
			'openai_model'   => self::is_allowed_model( $settings['openai_model'] ?? '' ) ? $settings['openai_model'] : self::DEFAULT_MODEL,
		);
	}

	/**
	 * Returns a supported setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Value returned when the key is unsupported.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$settings = self::get_all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Updates supported settings while preserving a stored key when blank.
	 *
	 * @param array<string, mixed> $settings Submitted settings.
	 * @return true|WP_Error
	 */
	public static function update( array $settings ) {
		$current = self::get_all();

		if ( isset( $settings['openai_model'] ) ) {
			$model = sanitize_text_field( (string) $settings['openai_model'] );

			if ( ! self::is_allowed_model( $model ) ) {
				return new WP_Error( 'aics_invalid_model', __( 'The selected model is not valid.', 'ai-content-studio' ) );
			}

			$current['openai_model'] = $model;
		}

		if ( array_key_exists( 'openai_api_key', $settings ) ) {
			$api_key = self::sanitize_api_key( (string) $settings['openai_api_key'] );

			if ( '' !== $api_key ) {
				$current['openai_api_key'] = $api_key;
			}
		}

		self::persist( $current );

		return true;
	}

	/**
	 * Reports whether an OpenAI API key is configured.
	 *
	 * @return bool
	 */
	public static function has_openai_api_key(): bool {
		return '' !== self::get( 'openai_api_key', '' );
	}

	/**
	 * Removes only the saved OpenAI API key.
	 *
	 * @return void
	 */
	public static function remove_openai_api_key(): void {
		$settings                   = self::get_all();
		$settings['openai_api_key'] = '';

		self::persist( $settings );
	}

	/**
	 * Returns the selected, validated OpenAI model.
	 *
	 * @return string
	 */
	public static function get_openai_model(): string {
		return (string) self::get( 'openai_model', self::DEFAULT_MODEL );
	}

	/**
	 * Returns the model allowlist.
	 *
	 * @return string[]
	 */
	public static function get_allowed_models(): array {
		return self::ALLOWED_MODELS;
	}

	/**
	 * Validates a model against the supported allowlist.
	 *
	 * @param mixed $model Model value.
	 * @return bool
	 */
	private static function is_allowed_model( $model ): bool {
		return is_string( $model ) && in_array( $model, self::ALLOWED_MODELS, true );
	}

	/**
	 * Conservatively sanitizes an API key without changing valid punctuation.
	 *
	 * @param string $api_key API key submitted by an administrator.
	 * @return string
	 */
	private static function sanitize_api_key( string $api_key ): string {
		return trim( sanitize_text_field( $api_key ) );
	}

	/**
	 * Persists the single settings array with autoload disabled on creation.
	 *
	 * WordPress option storage is intentionally used for the BYO-key MVP.
	 * Encryption is outside the scope of Task 2.
	 *
	 * @param array<string, string> $settings Validated settings.
	 * @return void
	 */
	private static function persist( array $settings ): void {
		$stored = get_option( self::OPTION_NAME, false );
		if ( is_array( $stored ) ) {
			$settings = array_merge( $stored, $settings );
		}

		if ( false === $stored ) {
			add_option( self::OPTION_NAME, $settings, '', false );
			return;
		}

		update_option( self::OPTION_NAME, $settings, false );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
