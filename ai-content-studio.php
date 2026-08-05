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
define( 'AICS_DB_VERSION', '0.13.0' );
define( 'AICS_MINIMUM_PHP_VERSION', '8.0' );
define( 'AICS_MINIMUM_WP_VERSION', '6.4' );
define( 'AICS_PLUGIN_FILE', __FILE__ );
define( 'AICS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AICS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AICS_PLUGIN_DIR . 'includes/core/class-permissions.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-installer.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-usage-log-repository.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-automation-profile-repository.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-automation-run-repository.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-automation-run-action-repository.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-content-idea-repository.php';
require_once AICS_PLUGIN_DIR . 'includes/database/class-article-repository.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-settings.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-seo-state.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-seo-configuration.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-seo-data.php';
require_once AICS_PLUGIN_DIR . 'includes/services/interface-seo-adapter.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-seo-application-result.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-abstract-postmeta-seo-adapter.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-native-wordpress-seo-adapter.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-yoast-seo-adapter.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-rank-math-seo-adapter.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-aioseo-adapter.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-seo-plugin-detector.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-seo-adapter-factory.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-seo-generation-request.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-seo-generation-result.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-seo-recommendation-profile.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-seo-prompt-builder.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-seo-quality-analyzer.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-seo-generation-service.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-internal-link-candidate-service.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-internal-link-recommendation-service.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-external-link-validation-service.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-external-link-recommendation-service.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-seo-link-insertion-service.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-content-image-insertion-service.php';
require_once AICS_PLUGIN_DIR . 'includes/seo/class-native-seo-application-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-seo-quality-gate.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-seo-workflow-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-seo-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-manual-seo-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-manual-seo-application-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-image-generation-request.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-temporary-image-file.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-image-generation-result.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-featured-image-prompt-builder.php';
require_once AICS_PLUGIN_DIR . 'includes/providers/interface-image-provider.php';
require_once AICS_PLUGIN_DIR . 'includes/providers/class-openai-image-provider.php';
require_once AICS_PLUGIN_DIR . 'includes/providers/class-image-provider-factory.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-featured-image-settings.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-featured-image-settings.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-featured-image-generation-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-featured-image-ownership-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-media-library-image-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-featured-image-assignment-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-featured-image-pipeline-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-featured-image-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-usage-logger.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-schedule-calculator.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-profile-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-dispatcher.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-idea-generator.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-idea-evaluator.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-article-generator.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-approval-workflow-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-article-content-validator.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-featured-image-state.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-post-generator.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-manual-article-persistence-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-manual-featured-image-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-post-creator.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-delivery-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-run-inspector.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-run-recovery-planner.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-run-control-service.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-stale-run-detector.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-health-monitor.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-worker.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-automation-scheduler.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-system-check.php';
require_once AICS_PLUGIN_DIR . 'includes/services/class-http-client.php';
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
require_once AICS_PLUGIN_DIR . 'includes/admin/class-automations-page.php';
require_once AICS_PLUGIN_DIR . 'includes/admin/class-automation-runs-page.php';
require_once AICS_PLUGIN_DIR . 'includes/admin/class-approvals-page.php';
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
