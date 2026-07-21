<?php
/**
 * Plugin permission management.
 *
 * @package AIContentStudio
 */

namespace AIContentStudio\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes plugin permission requirements.
 */
final class Permissions {
	private const MANAGE = 'manage_options';

	/**
	 * Returns the capability required to manage the plugin.
	 *
	 * @return string
	 */
	public static function manage(): string {
		return self::MANAGE;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
