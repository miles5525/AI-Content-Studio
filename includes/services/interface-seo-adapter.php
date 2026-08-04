<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
interface AICS_SEO_Adapter_Interface {public function get_key():string;public function get_label():string;public function is_available():bool;public function validate_data(AICS_SEO_Data $data);public function apply_to_post(int $post_id,AICS_SEO_Data $data);public function read_from_post(int $post_id);public function supports_focus_keyword():bool;public function supports_title():bool;public function supports_meta_description():bool;}
