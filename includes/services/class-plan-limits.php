<?php
/**
 * Centralized plan limits for automation profile validation.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Plan_Limits {
	public const FREE = 'free';

	/** Returns the active plan key. Free is the safe default. */
	public static function plan(): string {
		$plan = sanitize_key( (string) apply_filters( 'aics_plan', self::FREE ) );
		return '' !== $plan ? $plan : self::FREE;
	}

	/** Returns the user-facing plan name. Add-ons may override it. */
	public static function label(): string {
		$plan = self::plan();
		$default = self::FREE === $plan ? __( 'Free', 'ai-content-studio' ) : ucfirst( $plan );
		$label = sanitize_text_field( (string) apply_filters( 'aics_plan_label', $default, $plan ) );
		return '' !== $label ? $label : $default;
	}

	/** Returns the reusable upgrade destination placeholder. */
	public static function upgrade_url(): string {
		$default = admin_url( 'admin.php?page=aics-settings&section=general' );
		$url = esc_url_raw( (string) apply_filters( 'aics_upgrade_url', $default, self::plan() ) );
		return '' !== $url ? $url : $default;
	}

	/** Returns normalized native automation capabilities. Extensions may filter them. */
	public static function all(): array {
		$defaults = array(
			'max_active_automation_profiles' => 1,
			'max_ideas_per_cycle'            => 3,
			'max_selected_ideas_per_cycle'   => 1,
			'max_posts_per_period'            => 1,
			'max_weekly_publishing_days'       => 1,
			'allowed_automation_frequencies'  => array( 'weekly' ),
			'minimum_automation_interval'     => 1,
		);
		$limits = apply_filters( 'aics_plan_limits', $defaults );
		$limits = is_array( $limits ) ? array_replace( $defaults, $limits ) : $defaults;
		$frequencies = array_values( array_unique( array_filter( array_map( 'sanitize_key', is_array( $limits['allowed_automation_frequencies'] ) ? $limits['allowed_automation_frequencies'] : array() ) ) ) );
		return array(
			'max_active_automation_profiles' => max( 1, absint( $limits['max_active_automation_profiles'] ) ),
			'max_ideas_per_cycle'            => max( 1, absint( $limits['max_ideas_per_cycle'] ) ),
			'max_selected_ideas_per_cycle'   => max( 1, absint( $limits['max_selected_ideas_per_cycle'] ) ),
			'max_posts_per_period'            => max( 1, absint( $limits['max_posts_per_period'] ) ),
			'max_weekly_publishing_days'       => min( 7, max( 1, absint( $limits['max_weekly_publishing_days'] ) ) ),
			'allowed_automation_frequencies'  => $frequencies ?: array( 'weekly' ),
			'minimum_automation_interval'     => max( 1, absint( $limits['minimum_automation_interval'] ) ),
		);
	}

	public static function max_active_automation_profiles(): int { return self::all()['max_active_automation_profiles']; }
	public static function max_ideas_per_cycle(): int { return self::all()['max_ideas_per_cycle']; }
	public static function max_selected_ideas_per_cycle(): int { return self::all()['max_selected_ideas_per_cycle']; }
	public static function max_posts_per_period(): int { return self::all()['max_posts_per_period']; }
	public static function max_weekly_publishing_days(): int { return self::all()['max_weekly_publishing_days']; }
	public static function allowed_automation_frequencies(): array { return self::all()['allowed_automation_frequencies']; }
	public static function minimum_automation_interval(): int { return self::all()['minimum_automation_interval']; }

	/** Applies effective native capabilities without modifying stored profile data. */
	public static function apply_to_automation_profile( array $profile ): array {
		$content  = is_array( $profile['content_settings'] ?? null ) ? $profile['content_settings'] : array();
		$schedule = is_array( $profile['schedule_settings'] ?? null ) ? $profile['schedule_settings'] : array();

		$content['ideas_per_cycle'] = min(
			max( 1, absint( $content['ideas_per_cycle'] ?? 1 ) ),
			self::max_ideas_per_cycle()
		);
		$content['selected_ideas_per_cycle'] = min(
			max( 1, absint( $content['selected_ideas_per_cycle'] ?? 1 ) ),
			self::max_selected_ideas_per_cycle(),
			$content['ideas_per_cycle']
		);

		$allowed = self::allowed_automation_frequencies();
		$frequency = sanitize_key( (string) ( $schedule['frequency'] ?? '' ) );
		$schedule['frequency'] = in_array( $frequency, $allowed, true ) ? $frequency : $allowed[0];
		$schedule['interval'] = max(
			self::minimum_automation_interval(),
			absint( $schedule['interval'] ?? 1 )
		);
		$schedule['posts_per_period'] = min(
			max( 1, absint( $schedule['posts_per_period'] ?? 1 ) ),
			self::max_posts_per_period()
		);
		$weekdays = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$submitted_days = is_array( $schedule['days_of_week'] ?? null ) ? array_map( 'sanitize_key', array_filter( $schedule['days_of_week'], 'is_scalar' ) ) : array();
		$schedule['days_of_week'] = array_slice( array_values( array_intersect( $weekdays, $submitted_days ) ), 0, self::max_weekly_publishing_days() );
		$content['selected_ideas_per_cycle'] = min(
			$content['selected_ideas_per_cycle'],
			$schedule['posts_per_period']
		);

		$profile['content_settings']  = $content;
		$profile['schedule_settings'] = $schedule;
		return $profile;
	}

	private function __construct() {}
}
