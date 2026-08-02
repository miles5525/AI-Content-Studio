<?php
/**
 * Uninstall cleanup for AI Content Studio.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'aics_automation_dispatcher' );
wp_clear_scheduled_hook( 'aics_automation_worker' );
delete_option( 'aics_automation_dispatcher_lock' );

// Persistent-data cleanup, including administrator run actions, remains deferred
// under the plugin's existing uninstall-data policy until storage decisions are finalized.
