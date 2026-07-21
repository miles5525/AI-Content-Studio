<?php
/**
 * Plugin Name:       AI Content Studio
 * Plugin URI:        https://github.com/miles5525/AI-Content-Studio
 * Description:       Generate, review, and manage AI-assisted WordPress content.
 * Version:           0.9.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            AI Content Studio
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-content-studio
 * Domain Path:       /languages
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AICS_VERSION', '0.9.0' );
define( 'AICS_DB_VERSION', '0.3.0' );
define( 'AICS_MINIMUM_PHP_VERSION', '8.0' );
define( 'AICS_MINIMUM_WP_VERSION', '6.4' );
define( 'AICS_PLUGIN_FILE', __FILE__ );
define( 'AICS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AICS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AICS_PLUGIN_DIR . 'includes/core/class-permissions.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-installer.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-usage-log-repository.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-automation-profile-repository.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-settings.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-usage-logger.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-system-check.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-http-client.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-post-generator.php';
require_once AICS_PLUGIN_DIR . 'includes/ai/class-ai-request.php';
require_once AICS_PLUGIN_DIR . 'includes/ai/class-ai-response.php';
require_once AICS_PLUGIN_DIR . 'includes/ai/class-prompt-engine.php';
require_once AICS_PLUGIN_DIR . 'includes/providers/interface-provider.php';
require_once AICS_PLUGIN_DIR . 'includes/providers/class-openai-provider.php';
require_once AICS_PLUGIN_DIR . 'includes/ai/class-ai-engine.php';
require_once AICS_PLUGIN_DIR . 'includes/core/class-activator.php';
require_once AICS_PLUGIN_DIR . 'includes/core/class-deactivator.php';
require_once AICS_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
require_once AICS_PLUGIN_DIR . 'includes/admin/class-assets.php';
require_once AICS_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
require_once AICS_PLUGIN_DIR . 'includes/admin/class-content-studio-page.php';
require_once AICS_PLUGIN_DIR . 'includes/admin/class-content-history-page.php';
require_once AICS_PLUGIN_DIR . 'includes/admin/class-dashboard-page.php';
require_once AICS_PLUGIN_DIR . 'includes/admin/class-system-status-page.php';
require_once AICS_PLUGIN_DIR . 'includes/core/class-plugin.php';

register_activation_hook( __FILE__, array( 'AIContentStudio\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AIContentStudio\\Core\\Deactivator', 'deactivate' ) );

/**
 * Starts the plugin after all active plugins have loaded.
 *
 * @return void
 */
function AICS_run_plugin(): void {
	AIContentStudio\Core\Plugin::instance()->run();
}
add_action( 'plugins_loaded', 'AICS_run_plugin' );
