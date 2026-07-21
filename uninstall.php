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

// Persistent-data cleanup remains deferred until storage decisions are finalized.
