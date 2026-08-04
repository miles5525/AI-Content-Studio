<?php
/** Automation featured-image profile normalization and run resolution. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Featured_Image_Settings {
	private const SOURCES = array( 'global', 'override' );
	private const STYLES = array( 'editorial', 'photorealistic', 'modern_illustration', 'minimal_3d', 'flat_illustration' );

	public static function defaults(): array {
		return array( 'enabled'=>false, 'required'=>false, 'settings_source'=>'global', 'provider'=>'', 'model'=>'', 'visual_style'=>'editorial', 'aspect_ratio'=>'landscape', 'quality'=>'standard', 'output_format'=>'png' );
	}

	/** Strictly validates submitted or stored profile values. @return array|WP_Error */
	public static function validate( array $input ) {
		$out = self::defaults();
		$out['enabled'] = self::boolean( $input['enabled'] ?? false );
		$out['required'] = $out['enabled'] && self::boolean( $input['required'] ?? false );
		$out['settings_source'] = self::key( $input['settings_source'] ?? 'global' );
		if ( ! in_array( $out['settings_source'], self::SOURCES, true ) ) {
			return self::error( 'invalid_featured_image_settings_source' );
		}

		$out['provider'] = self::key( $input['provider'] ?? '' );
		$out['model'] = self::identifier( $input['model'] ?? '' );
		foreach ( array( 'visual_style', 'aspect_ratio', 'quality', 'output_format' ) as $key ) {
			$out[ $key ] = self::key( $input[ $key ] ?? $out[ $key ] );
		}
		if ( ! in_array( $out['visual_style'], self::STYLES, true ) ) { return self::error( 'invalid_featured_image_visual_style' ); }

		/* Preserve valid overrides even while global settings are selected or images are disabled. */
		if ( '' !== $out['provider'] || '' !== $out['model'] || 'override' === $out['settings_source'] ) {
			$provider = AICS_Image_Provider_Factory::create( $out['provider'] );
			if ( is_wp_error( $provider ) ) { return self::error( 'invalid_featured_image_provider' ); }
			$capabilities = $provider->get_capabilities();
			if ( ! in_array( $out['model'], $capabilities['supported_models'] ?? array(), true ) ) { return self::error( 'invalid_featured_image_model' ); }
			if ( ! in_array( $out['aspect_ratio'], $capabilities['supported_aspect_ratios'] ?? array(), true ) ) { return self::error( 'invalid_featured_image_aspect_ratio' ); }
			if ( ! in_array( $out['quality'], $capabilities['supported_quality_levels'] ?? array(), true ) ) { return self::error( 'invalid_featured_image_quality' ); }
			if ( ! in_array( $out['output_format'], $capabilities['supported_output_formats'] ?? array(), true ) ) { return self::error( 'invalid_featured_image_output_format' ); }
		}
		return $out;
	}

	/** Lenient read normalization gives old profiles safe disabled defaults. */
	public static function normalize_stored( $value ): array {
		if ( ! is_array( $value ) || ! array_key_exists( 'enabled', $value ) ) { return self::defaults(); }
		$validated = self::validate( $value );
		return is_wp_error( $validated ) ? self::defaults() : $validated;
	}

	/** Resolves a fully non-sensitive configuration for a newly dispatched run. @return array|WP_Error */
	public static function resolve_for_run( array $profile_settings ) {
		$settings = self::validate( $profile_settings );
		if ( is_wp_error( $settings ) ) { return self::error( 'featured_image_configuration_invalid' ); }
		if ( ! $settings['enabled'] ) { return self::defaults(); }

		if ( 'global' === $settings['settings_source'] ) {
			$stored = get_option( 'aics_settings', array() );
			$stored_image = is_array( $stored ) && is_array( $stored['featured_images'] ?? null ) ? $stored['featured_images'] : array();
			if ( is_wp_error( AICS_Featured_Image_Settings::validate( $stored_image ) ) ) { return self::error( 'featured_image_configuration_invalid' ); }
			$global = AICS_Featured_Image_Settings::get_effective();
			$effective = array_merge( $settings, array_intersect_key( $global, array_flip( array( 'provider','model','visual_style','aspect_ratio','quality','output_format' ) ) ) );
		} else {
			$effective = $settings;
		}
		$effective['enabled'] = true;
		$effective['required'] = (bool) $settings['required'];
		$effective['settings_source'] = $settings['settings_source'];
		$checked = self::validate( $effective );
		if ( is_wp_error( $checked ) ) { return self::error( 'featured_image_configuration_invalid' ); }
		$provider = AICS_Image_Provider_Factory::create( $checked['provider'] );
		$status = is_wp_error( $provider ) ? array( 'success'=>false ) : $provider->validate_configuration();
		return empty( $status['success'] ) ? self::error( 'featured_image_configuration_invalid' ) : $checked;
	}

	public static function provider_label( string $key ): string {
		$options = AICS_Image_Provider_Factory::provider_options();
		return isset( $options[ $key ] ) ? $options[ $key ] : __( 'Unavailable', 'ai-content-studio' );
	}

	private static function boolean( $value ): bool { return true === filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
	private static function key( $value ): string { return sanitize_key( is_scalar( $value ) ? (string) $value : '' ); }
	private static function identifier( $value ): string { $value=is_scalar($value)?trim(sanitize_text_field((string)$value)):''; return strlen($value)<=100 && 1===preg_match('/^[a-zA-Z0-9._:-]+$/',$value)?$value:''; }
	private static function error( string $code ): WP_Error { return new WP_Error( $code, __( 'The featured image configuration is invalid. Review the selected settings.', 'ai-content-studio' ) ); }
	private function __construct() {}
}
