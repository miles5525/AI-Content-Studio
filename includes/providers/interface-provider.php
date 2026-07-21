<?php
/**
 * Common AI provider contract.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines the minimal behavior shared by AI providers.
 */
interface AICS_Provider_Interface {
	/**
	 * Returns the provider's display name.
	 *
	 * @return string
	 */
	public function get_provider_name(): string;

	/**
	 * Tests the provider using its saved configuration.
	 *
	 * @return array{success: bool, code: string, message: string}
	 */
	public function test_connection(): array;
}
