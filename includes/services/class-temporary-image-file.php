<?php
/**
 * Validated, explicitly owned temporary image file.
 *
 * @package AI_Content_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AICS_Temporary_Image_File {
	public const MAX_BYTES = 26214400;

	private const MIME_TYPES = array(
		'png'  => 'image/png',
		'jpeg' => 'image/jpeg',
		'webp' => 'image/webp',
	);

	private string $path;
	private string $mime_type;
	private int $width;
	private int $height;
	private int $file_size;
	private string $output_format;

	private function __construct( string $path, string $mime_type, int $width, int $height, int $file_size, string $output_format ) {
		$this->path          = $path;
		$this->mime_type     = $mime_type;
		$this->width         = $width;
		$this->height        = $height;
		$this->file_size     = $file_size;
		$this->output_format = $output_format;
	}

	/**
	 * Writes and validates decoded image bytes.
	 *
	 * @return self|WP_Error
	 */
	public static function create_from_bytes( string $bytes, string $output_format ) {
		$output_format = strtolower( $output_format );
		if ( ! isset( self::MIME_TYPES[ $output_format ] ) ) {
			return new WP_Error( 'unsupported_image_format', __( 'The generated image format is not supported.', 'ai-content-studio' ) );
		}

		$byte_length = strlen( $bytes );
		if ( 0 === $byte_length ) {
			return new WP_Error( 'image_file_validation_failed', __( 'The generated image file was empty.', 'ai-content-studio' ) );
		}
		if ( $byte_length > self::MAX_BYTES ) {
			return new WP_Error( 'image_file_too_large', __( 'The generated image exceeded the maximum allowed file size.', 'ai-content-studio' ) );
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$seed = wp_tempnam( 'aics-image-' . $output_format );
		if ( ! is_string( $seed ) || '' === $seed || ! self::is_owned_path( $seed ) ) {
			return new WP_Error( 'temporary_image_creation_failed', __( 'A secure temporary image file could not be created.', 'ai-content-studio' ) );
		}

		$directory = dirname( $seed );
		$filename  = wp_unique_filename( $directory, basename( $seed ) . '.' . $output_format );
		$path      = trailingslashit( $directory ) . $filename;
		if ( ! self::is_owned_path( $path ) || ! @rename( $seed, $path ) ) {
			self::delete_owned_path( $seed );
			return new WP_Error( 'temporary_image_creation_failed', __( 'A secure temporary image file could not be created.', 'ai-content-studio' ) );
		}

		$written = @file_put_contents( $path, $bytes, LOCK_EX );
		if ( $written !== $byte_length ) {
			self::delete_owned_path( $path );
			return new WP_Error( 'temporary_image_creation_failed', __( 'The temporary image file could not be written completely.', 'ai-content-studio' ) );
		}

		$validated = self::validate_file( $path, $output_format );
		if ( is_wp_error( $validated ) ) {
			self::delete_owned_path( $path );
			return $validated;
		}

		return new self( $path, $validated['mime_type'], $validated['width'], $validated['height'], $validated['file_size'], $output_format );
	}

	/** @return array<string,int|string>|WP_Error */
	private static function validate_file( string $path, string $output_format ) {
		if ( ! is_file( $path ) ) {
			return new WP_Error( 'image_file_validation_failed', __( 'The temporary image file could not be found.', 'ai-content-studio' ) );
		}

		$file_size = filesize( $path );
		if ( false === $file_size || $file_size < 1 ) {
			return new WP_Error( 'image_file_validation_failed', __( 'The generated image file was empty.', 'ai-content-studio' ) );
		}
		if ( $file_size > self::MAX_BYTES ) {
			return new WP_Error( 'image_file_too_large', __( 'The generated image exceeded the maximum allowed file size.', 'ai-content-studio' ) );
		}

		$image_info = @getimagesize( $path );
		if ( false === $image_info || empty( $image_info[0] ) || empty( $image_info[1] ) || empty( $image_info['mime'] ) ) {
			return new WP_Error( 'image_file_validation_failed', __( 'The generated file is not a valid image.', 'ai-content-studio' ) );
		}

		$mime_type = strtolower( (string) $image_info['mime'] );
		if ( ! in_array( $mime_type, self::MIME_TYPES, true ) ) {
			return new WP_Error( 'unsupported_image_format', __( 'The generated image MIME type is not supported.', 'ai-content-studio' ) );
		}
		if ( self::MIME_TYPES[ $output_format ] !== $mime_type ) {
			return new WP_Error( 'image_file_validation_failed', __( 'The generated image did not match the requested format.', 'ai-content-studio' ) );
		}

		return array(
			'mime_type' => $mime_type,
			'width'     => (int) $image_info[0],
			'height'    => (int) $image_info[1],
			'file_size' => (int) $file_size,
		);
	}

	private static function is_owned_path( string $path ): bool {
		$temp_root = wp_normalize_path( untrailingslashit( get_temp_dir() ) ) . '/';
		$candidate = wp_normalize_path( $path );
		return 0 === strpos( $candidate, $temp_root ) && 0 === strpos( basename( $candidate ), 'aics-image-' );
	}

	private static function delete_owned_path( string $path ): bool {
		if ( ! self::is_owned_path( $path ) || ! file_exists( $path ) ) {
			return ! file_exists( $path );
		}
		wp_delete_file( $path );
		return ! file_exists( $path );
	}

	public function exists(): bool {
		return '' !== $this->path && is_file( $this->path );
	}

	public function cleanup(): bool {
		if ( '' === $this->path ) {
			return true;
		}
		$deleted = self::delete_owned_path( $this->path );
		if ( $deleted ) {
			$this->path = '';
		}
		return $deleted;
	}

	public function get_path(): string { return $this->path; }
	public function get_mime_type(): string { return $this->mime_type; }
	public function get_width(): int { return $this->width; }
	public function get_height(): int { return $this->height; }
	public function get_file_size(): int { return $this->file_size; }
	public function get_output_format(): string { return $this->output_format; }

	public function __destruct() {
		$this->cleanup();
	}
}
