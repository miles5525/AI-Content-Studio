<?php
/**
 * Global WordPress Cron scheduler for automation dispatching.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AICS_Automation_Scheduler {
	public const HOOK = 'aics_automation_dispatcher';
	public const WORKER_HOOK = 'aics_automation_worker';
	public const SCHEDULE = 'aics_every_five_minutes';

	public static function register(): void {
		self::register_schedule();
		add_action( self::HOOK, array( self::class, 'run_dispatcher' ) );
		add_action( self::WORKER_HOOK, array( self::class, 'run_worker' ) );
		AICS_Automation_Health_Monitor::register();
		add_action( 'init', array( self::class, 'ensure_scheduled' ) );
	}

	public static function register_schedule(): void {
		add_filter( 'cron_schedules', array( self::class, 'add_schedule' ) );
	}

	public static function add_schedule( array $schedules ): array {
		$schedules[ self::SCHEDULE ] = array( 'interval' => 300, 'display' => __( 'Every Five Minutes — AI Content Studio', 'ai-content-studio' ) );
		return $schedules;
	}

	public static function ensure_scheduled(): bool {
		$dispatcher = false !== wp_next_scheduled( self::HOOK ) || false !== wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::HOOK );
		$worker = false !== wp_next_scheduled( self::WORKER_HOOK ) || false !== wp_schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), self::SCHEDULE, self::WORKER_HOOK );
		$health = false !== wp_next_scheduled( AICS_Automation_Health_Monitor::HOOK ) || false !== wp_schedule_event( time() + ( 10 * MINUTE_IN_SECONDS ), 'hourly', AICS_Automation_Health_Monitor::HOOK );
		return $dispatcher && $worker && $health;
	}

	public static function clear(): void {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::WORKER_HOOK );
		wp_clear_scheduled_hook( AICS_Automation_Health_Monitor::HOOK );
	}

	public static function next_scheduled() {
		return wp_next_scheduled( self::HOOK );
	}
	public static function next_worker_scheduled() { return wp_next_scheduled( self::WORKER_HOOK ); }
	public static function run_worker(): void { try { (new AICS_Automation_Worker())->process(); } catch(Throwable $exception){if(defined('WP_DEBUG')&&WP_DEBUG){error_log('AI Content Studio scheduler stopped with controlled code: worker_callback_failed');}} }

	public static function run_dispatcher(): void {
		try {
			( new AICS_Automation_Dispatcher() )->dispatch();
		} catch ( Throwable $exception ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'AI Content Studio scheduler stopped with controlled code: scheduler_callback_failed' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	private function __construct() {}
}
