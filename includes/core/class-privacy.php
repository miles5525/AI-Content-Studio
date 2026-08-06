<?php
/**
 * Privacy-policy integration.
 *
 * @package AIContentStudio
 */

namespace AIContentStudio\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds suggested privacy-policy text for the plugin's external service use.
 */
final class Privacy {
	/**
	 * Registers the suggested policy text in WordPress.
	 *
	 * @return void
	 */
	public static function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__( 'When an authorized administrator requests AI-generated ideas, articles, SEO metadata, or images—or enables an automation that does so—AI Content Studio sends the instructions and relevant site content needed for that request to OpenAI. The saved API key is sent to authenticate the request. Connection and image tests also contact OpenAI. The plugin does not send this data until an administrator initiates one of these actions or enables the corresponding automation.', 'ai-content-studio' ) . '</p>';
		$content .= '<p>' . wp_kses_post( sprintf(
			/* translators: 1: OpenAI terms link. 2: OpenAI privacy policy link. */
			__( 'OpenAI processes this data under its <a href="%1$s">Terms of Use</a> and <a href="%2$s">Privacy Policy</a>. Site owners should describe their use of AI services and retention practices in their own privacy policy.', 'ai-content-studio' ),
			esc_url( 'https://openai.com/policies/terms-of-use/' ),
			esc_url( 'https://openai.com/policies/privacy-policy/' )
		) ) . '</p>';

		wp_add_privacy_policy_content( __( 'AI Content Studio', 'ai-content-studio' ), wp_kses_post( wpautop( $content, false ) ) );
	}

	private function __construct() {}
}
