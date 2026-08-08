<?php
/**
 * Deterministic automation schedule calculations.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Schedule_Calculator {
	private const DATABASE_FORMAT = 'Y-m-d H:i:s';
	private const WEEKDAYS = array( 'monday'=>1, 'tuesday'=>2, 'wednesday'=>3, 'thursday'=>4, 'friday'=>5, 'saturday'=>6, 'sunday'=>7 );

	/** Calculates the first eligible run strictly after a UTC reference. */
	public function calculate_next_run( array $settings, $from_utc = null ): array {
		$frequency = sanitize_key( (string) ( $settings['frequency'] ?? '' ) );
		if ( 'weekly' !== $frequency ) {
			$extended = apply_filters( 'aics_calculate_extended_automation_schedule', null, $settings, $from_utc );
			return $this->extended_result( $extended );
		}
		$normalized = $this->normalize( $settings );
		if ( ! $normalized['success'] ) { return $this->failure( $normalized['code'] ); }
		$reference = $this->utc_reference( $from_utc );
		if ( null === $reference ) { return $this->failure( 'invalid_reference_datetime' ); }

		$s = $normalized['settings'];
		$timezone = wp_timezone();
		$local_reference = $reference->setTimezone( $timezone );
		$start = '' === $s['start_date'] ? null : new DateTimeImmutable( $s['start_date'] . ' 00:00:00', $timezone );
		$end = '' === $s['end_date'] ? null : new DateTimeImmutable( $s['end_date'] . ' 23:59:59', $timezone );

		$candidate = $this->weekly( $s, $local_reference, $start, $end );

		if ( null === $candidate ) { return $this->failure( 'schedule_has_no_future_run' ); }
		$utc = $candidate->setTimezone( new DateTimeZone( 'UTC' ) );
		return array( 'success'=>true, 'code'=>'next_run_calculated', 'next_run_utc'=>$utc->format(self::DATABASE_FORMAT), 'next_run_local'=>$candidate->format(self::DATABASE_FORMAT), 'runs'=>array(array('utc'=>$utc->format(self::DATABASE_FORMAT),'local'=>$candidate->format(self::DATABASE_FORMAT))) );
	}

	/** Calculates up to ten unique future runs without persisting them. */
	public function calculate_upcoming_runs( array $settings, $count = 5, $from_utc = null ): array {
		if ( ! is_numeric( $count ) || (int)$count < 1 ) { return $this->failure( 'invalid_preview_count' ); }
		$count = min( 10, (int)$count ); $runs=array(); $reference=$from_utc;
		for ( $i=0; $i<$count; $i++ ) {
			$result=$this->calculate_next_run($settings,$reference);
			if(!$result['success']){ if(empty($runs)){return $result;} break; }
			$run=$result['runs'][0];
			if(isset($runs[$run['utc']])){break;}
			$runs[$run['utc']]=$run; $reference=$run['utc'];
		}
		$values=array_values($runs);
		return array('success'=>!empty($values),'code'=>empty($values)?'schedule_has_no_future_run':'upcoming_runs_calculated','next_run_utc'=>$values[0]['utc']??null,'next_run_local'=>$values[0]['local']??null,'runs'=>$values);
	}

	/** Formats a strict UTC database value using WordPress site settings. */
	public function format_utc_for_site( $utc_datetime ): string {
		$utc=$this->strict_datetime($utc_datetime,new DateTimeZone('UTC'));
		return null===$utc?'':wp_date(get_option('date_format').' '.get_option('time_format'),$utc->getTimestamp(),wp_timezone());
	}

	private function weekly(array $s,DateTimeImmutable $reference,?DateTimeImmutable $start,?DateTimeImmutable $end):?DateTimeImmutable{
		$anchor_date=$start??$reference; $anchor_week=$anchor_date->modify('monday this week')->setTime(0,0);
		for($block=0;$block<1000;$block++){
			$week=$anchor_week->modify('+'.($block*$s['interval']).' weeks');
			foreach(self::WEEKDAYS as $name=>$offset){ if(!in_array($name,$s['days_of_week'],true)){continue;} $candidate=$this->at_time($week->modify('+'.($offset-1).' days'),$s['publish_time']); if($start&&$candidate<$start){continue;} if($end&&$candidate>$end){return null;} if($candidate>$reference){return $candidate;} }
		} return null;
	}

	private function at_time(DateTimeImmutable $date,string $time):DateTimeImmutable{[$hour,$minute]=array_map('intval',explode(':',$time));return $date->setTime($hour,$minute,0);}
	private function utc_reference($value):?DateTimeImmutable{if(null===$value){return current_datetime()->setTimezone(new DateTimeZone('UTC'));}return $this->strict_datetime($value,new DateTimeZone('UTC'));}
	private function strict_datetime($value,DateTimeZone $timezone):?DateTimeImmutable{if(!is_scalar($value)){return null;}$value=(string)$value;$date=DateTimeImmutable::createFromFormat('!'.self::DATABASE_FORMAT,$value,$timezone);$errors=DateTimeImmutable::getLastErrors();return false!==$date&&(false===$errors||(0===$errors['warning_count']&&0===$errors['error_count']))&&$date->format(self::DATABASE_FORMAT)===$value?$date:null;}
	private function strict_date($value):bool{if(''===$value){return true;}if(!is_scalar($value)){return false;}$date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$value,new DateTimeZone('UTC'));$errors=DateTimeImmutable::getLastErrors();return false!==$date&&(false===$errors||(0===$errors['warning_count']&&0===$errors['error_count']))&&$date->format('Y-m-d')===(string)$value;}
	private function normalize(array $s):array{
		$frequency=sanitize_key((string)($s['frequency']??'')); $interval=absint($s['interval']??0); $time=is_scalar($s['publish_time']??null)?(string)$s['publish_time']:''; $start=is_scalar($s['start_date']??null)?(string)$s['start_date']:''; $end=is_scalar($s['end_date']??null)?(string)$s['end_date']:'';
		if('weekly'!==$frequency){return array('success'=>false,'code'=>'unsupported_automation_frequency');}
		if($interval<1||$interval>31){return array('success'=>false,'code'=>'invalid_interval');}
		if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time)){return array('success'=>false,'code'=>'invalid_publish_time');}
		if(!$this->strict_date($start)||!$this->strict_date($end)||(''!==$start&&''!==$end&&$end<$start)){return array('success'=>false,'code'=>'invalid_schedule_date');}
		$days=array_values(array_intersect(array_keys(self::WEEKDAYS),is_array($s['days_of_week']??null)?array_map('sanitize_key',array_filter($s['days_of_week'],'is_scalar')):array()));
		if('weekly'===$frequency&&empty($days)){return array('success'=>false,'code'=>'weekly_days_required');}
		return array('success'=>true,'settings'=>array('frequency'=>$frequency,'interval'=>$interval,'days_of_week'=>$days,'publish_time'=>$time,'start_date'=>$start,'end_date'=>$end));
	}
	private function extended_result($result):array{if(!is_array($result)||empty($result['success'])||!is_string($result['next_run_utc']??null)){return $this->failure('unsupported_automation_frequency');}$utc=$this->strict_datetime($result['next_run_utc'],new DateTimeZone('UTC'));if(null===$utc){return $this->failure('invalid_extended_schedule_result');}$value=$utc->format(self::DATABASE_FORMAT);return array('success'=>true,'code'=>'next_run_calculated','next_run_utc'=>$value,'next_run_local'=>is_string($result['next_run_local']??null)?$result['next_run_local']:$value,'runs'=>array(array('utc'=>$value,'local'=>is_string($result['next_run_local']??null)?$result['next_run_local']:$value)));}
	private function failure(string $code):array{return array('success'=>false,'code'=>$code,'next_run_utc'=>null,'next_run_local'=>null,'runs'=>array());}
}
