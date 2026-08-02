<?php
/** Provider-neutral featured-image generation contract. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

interface AICS_Image_Provider_Interface {
	public function get_provider_key(): string;
	public function get_display_name(): string;
	public function is_configured(): bool;
	/** @return array{success:bool,code:string,message:string} */
	public function validate_configuration(): array;
	/** @return array<string,mixed> */
	public function get_capabilities(): array;
	public function generate( AICS_Image_Generation_Request $request ): AICS_Image_Generation_Result;
}
