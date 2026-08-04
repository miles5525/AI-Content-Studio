<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class AICS_SEO_Adapter_Factory {public static function get(string $key='auto'):AICS_SEO_Adapter_Interface{$resolved=(new AICS_SEO_Plugin_Detector())->resolve($key);return new AICS_Native_WordPress_SEO_Adapter();}public static function registered():array{return array('native');}}
