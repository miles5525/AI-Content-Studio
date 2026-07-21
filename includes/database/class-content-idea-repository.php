<?php
/** Persistent content-idea storage and normalization. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Content_Idea_Repository {
	private const SOURCES = array( 'automation', 'manual' );
	private const STATUSES = array( 'generated', 'pending_approval', 'approved', 'rejected', 'queued', 'article_generating', 'article_generated', 'completed', 'failed', 'paused' );
	private const INTENTS = array( 'informational', 'commercial', 'transactional', 'navigational' );
	private const ORDERBY = array( 'id', 'score', 'priority', 'planned_publish_at', 'approved_at', 'created_at', 'updated_at' );

	public function create( array $data ): array {
		global $wpdb;
		$normalized = $this->normalize_create( $data );
		if ( ! $normalized['success'] ) { return self::result( false, 0, '', $normalized['code'] ); }
		$row = $normalized['row'];
		$uuid = wp_generate_uuid4();
		if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid, 4 ) ) { return self::result( false, 0, '', 'uuid_generation_failed' ); }
		$row = array_merge( array( 'idea_uuid' => $uuid ), $row );
		$ok = $wpdb->insert( $this->table(), $row, $this->formats( $row ) );
		return false === $ok ? self::result( false, 0, '', 'database_insert_failed' ) : self::result( true, (int) $wpdb->insert_id, $uuid, 'idea_created' );
	}

	public function create_many( array $ideas, array $context = array() ): array {
		if ( empty( $ideas ) || count( $ideas ) > 20 ) { return array( 'success'=>false, 'created'=>0, 'failed'=>count($ideas), 'idea_ids'=>array(), 'item_results'=>array(), 'code'=>empty($ideas)?'empty_idea_batch':'idea_batch_too_large' ); }
		$allowed = array( 'profile_id', 'run_id', 'source_type', 'status', 'created_by' );
		$shared = array_intersect_key( $context, array_flip( $allowed ) );
		$ids = array(); $items = array(); $failed = 0;
		foreach ( array_values( $ideas ) as $index => $idea ) {
			$result = is_array( $idea ) ? $this->create( array_merge( $idea, $shared ) ) : self::result( false, 0, '', 'invalid_idea_data' );
			$items[] = array( 'index'=>$index, 'success'=>$result['success'], 'idea_id'=>$result['idea_id'], 'code'=>$result['code'] );
			if ( $result['success'] ) { $ids[] = $result['idea_id']; } else { ++$failed; }
		}
		return array( 'success'=>0===$failed, 'created'=>count($ids), 'failed'=>$failed, 'idea_ids'=>$ids, 'item_results'=>$items, 'code'=>0===$failed?'idea_batch_created':(empty($ids)?'idea_batch_failed':'idea_batch_partially_created') );
	}

	public function update( $idea_id, array $data ): array {
		global $wpdb; $id = absint( $idea_id ); $current = $this->get_by_id( $id );
		if ( 0 === $id || ! $current ) { return self::simple( false, 0, 'idea_not_found' ); }
		$allowed = array( 'title','summary','primary_keyword','secondary_keywords','search_intent','suggested_category','outline','score','priority','planned_publish_at' );
		$row = array();
		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $data ) ) { continue; }
			$value = $this->normalize_mutable( $field, $data[ $field ] );
			if ( is_array( $value ) && isset( $value['error'] ) ) { return self::simple( false, $id, $value['error'] ); }
			$row[ $field ] = $value;
		}
		if ( array_key_exists( 'title', $row ) || array_key_exists( 'primary_keyword', $row ) ) {
			$title = $row['title'] ?? $current['title']; $keyword = $row['primary_keyword'] ?? $current['primary_keyword'];
			$row['normalized_title'] = self::normalize_phrase( $title, 250 );
			$row['normalized_keyword'] = self::normalize_phrase( $keyword, 191 );
			$row['content_fingerprint'] = hash( 'sha256', $row['normalized_title'] . '|' . $row['normalized_keyword'] );
		}
		if ( empty( $row ) ) { return self::simple( false, $id, 'no_idea_changes' ); }
		$updated_by = array_key_exists( 'updated_by', $data ) ? self::nonnegative( $data['updated_by'] ) : get_current_user_id();
		if ( null === $updated_by ) { return self::simple( false, $id, 'invalid_updated_by' ); }
		$row['updated_by'] = $updated_by; $row['updated_at'] = self::now();
		$changed = $wpdb->update( $this->table(), $row, array( 'id'=>$id ), $this->formats( $row ), array( '%d' ) );
		return false === $changed ? self::simple( false, $id, 'database_update_failed' ) : self::simple( true, $id, 'idea_updated' );
	}

	public function get_by_id( $idea_id ): ?array { global $wpdb; $sql=$wpdb->prepare("SELECT * FROM {$this->table()} WHERE id=%d LIMIT 1",absint($idea_id)); return $this->normalize_row($wpdb->get_row($sql,ARRAY_A)); }
	public function get_by_uuid( $uuid ): ?array { global $wpdb; $uuid=is_scalar($uuid)?sanitize_text_field((string)$uuid):''; if(!wp_is_uuid($uuid)){return null;} $sql=$wpdb->prepare("SELECT * FROM {$this->table()} WHERE idea_uuid=%s LIMIT 1",$uuid); return $this->normalize_row($wpdb->get_row($sql,ARRAY_A)); }

	public function get_ideas( array $args = array() ): array {
		global $wpdb; [ $where, $values ] = $this->filters( $args );
		$order = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		$orderby = in_array( $args['orderby'] ?? '', self::ORDERBY, true ) ? $args['orderby'] : 'created_at';
		$limit=max(1,min(100,absint($args['limit']??20))); $offset=absint($args['offset']??0); $values[]=$limit; $values[]=$offset;
		$sql=$wpdb->prepare("SELECT * FROM {$this->table()} WHERE ".implode(' AND ',$where)." ORDER BY {$orderby} {$order}, id {$order} LIMIT %d OFFSET %d",$values);
		return array_values(array_filter(array_map(array($this,'normalize_row'),$wpdb->get_results($sql,ARRAY_A))));
	}

	public function count_ideas( array $args = array() ): int { global $wpdb; [ $where,$values ]=$this->filters($args); $sql="SELECT COUNT(*) FROM {$this->table()} WHERE ".implode(' AND ',$where); return (int)$wpdb->get_var($values?$wpdb->prepare($sql,$values):$sql); }
	public function get_ideas_for_run( $run_id, array $args = array() ): array { $run=self::nonnegative($run_id); if(null===$run||0===$run){return array();} $args['run_id']=$run; return $this->get_ideas($args); }

	public function find_recent_duplicate( $profile_id, $fingerprint, $since_utc, $exclude_idea_id = 0 ): ?array {
		global $wpdb; $profile=self::nonnegative($profile_id); $fingerprint=is_scalar($fingerprint)?strtolower((string)$fingerprint):''; $since=self::datetime($since_utc); $exclude=absint($exclude_idea_id);
		if(null===$profile||0===$profile||!preg_match('/^[a-f0-9]{64}$/',$fingerprint)||null===$since){return null;}
		$sql=$wpdb->prepare("SELECT * FROM {$this->table()} WHERE profile_id=%d AND content_fingerprint=%s AND created_at>=%s AND id<>%d ORDER BY created_at DESC,id DESC LIMIT 1",$profile,$fingerprint,$since,$exclude);
		return $this->normalize_row($wpdb->get_row($sql,ARRAY_A));
	}

	/** Returns the same deterministic fingerprint used for persistence. */
	public function build_fingerprint( $title, $primary_keyword = '' ): ?string { $title=self::plain($title,250,false);$keyword=self::plain($primary_keyword,191,false);if(null===$title||''===$title||null===$keyword){return null;}return hash('sha256',self::normalize_phrase($title,250).'|'.self::normalize_phrase($keyword,191)); }

	public function transition_status( $idea_id, array $from_statuses, $to_status, array $context = array() ): array {
		global $wpdb; $id=absint($idea_id); $from=array(); foreach($from_statuses as $candidate){if(is_scalar($candidate)){$from[]=sanitize_key((string)$candidate);}} $from=array_values(array_unique($from)); $to=sanitize_key(is_scalar($to_status)?(string)$to_status:'');
		if(0===$id){return self::simple(false,0,'idea_not_found');} if(empty($from)||array_diff($from,self::STATUSES)){return self::simple(false,$id,'invalid_source_status');} if(!in_array($to,self::STATUSES,true)){return self::simple(false,$id,'invalid_idea_status');}
		$updated_by=self::nonnegative($context['updated_by']??0); if(null===$updated_by){return self::simple(false,$id,'invalid_updated_by');}
		$now=self::now(); $set=array('status=%s','updated_by=%d','updated_at=%s'); $values=array($to,$updated_by,$now);
		if('approved'===$to){$approved=self::nonnegative($context['approved_by']??0);if(null===$approved){return self::simple(false,$id,'invalid_approved_by');}$set=array_merge($set,array('approved_by=%d','approved_at=%s','rejected_by=0','rejected_at=NULL',"rejection_code=''","last_error_code=''"));$values[]=$approved;$values[]=$now;}
		if('rejected'===$to){$rejected=self::nonnegative($context['rejected_by']??0);$code=self::error_code($context['rejection_code']??'idea_rejected');if(null===$rejected||''===$code){return self::simple(false,$id,'invalid_rejection');}$set=array_merge($set,array('rejected_by=%d','rejected_at=%s','rejection_code=%s','approved_by=0','approved_at=NULL'));$values[]=$rejected;$values[]=$now;$values[]=$code;}
		if('failed'===$to){$code=self::error_code($context['error_code']??'idea_processing_failed');if(''===$code){return self::simple(false,$id,'invalid_error_code');}$set[]='last_error_code=%s';$values[]=$code;}
		if(array_key_exists('planned_publish_at',$context)){$planned=self::datetime($context['planned_publish_at']);if(null===$planned&&null!==$context['planned_publish_at']&&''!==$context['planned_publish_at']){return self::simple(false,$id,'invalid_planned_publish_at');}$set[]='planned_publish_at='. (null===$planned?'NULL':'%s');if(null!==$planned){$values[]=$planned;}}
		$placeholders=implode(',',array_fill(0,count($from),'%s')); $values[]=$id; array_push($values,...$from);
		$sql=$wpdb->prepare("UPDATE {$this->table()} SET ".implode(',',$set)." WHERE id=%d AND status IN ({$placeholders})",$values); $changed=$wpdb->query($sql);
		if(1===$changed){return self::simple(true,$id,'idea_status_transitioned');} return self::simple(false,$id,null===$this->get_by_id($id)?'idea_not_found':'idea_transition_conflict');
	}

	public function update_error_code( $idea_id, $error_code, $updated_by = 0 ): array { global $wpdb; $id=absint($idea_id);$code=self::error_code($error_code);$user=self::nonnegative($updated_by);if(0===$id||null===$this->get_by_id($id)){return self::simple(false,0,'idea_not_found');}if(''===$code){return self::simple(false,$id,'invalid_error_code');}if(null===$user){return self::simple(false,$id,'invalid_updated_by');}$changed=$wpdb->update($this->table(),array('last_error_code'=>$code,'updated_by'=>$user,'updated_at'=>self::now()),array('id'=>$id),array('%s','%d','%s'),array('%d'));return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'idea_error_updated'); }
	public function table_exists(): bool { global $wpdb;$table=$this->table();return $table===$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))); }

	private function normalize_create( array $data ): array {
		$source=sanitize_key(is_scalar($data['source_type']??'automation')?(string)($data['source_type']??'automation'):'');if(!in_array($source,self::SOURCES,true)){return array('success'=>false,'code'=>'invalid_source_type');}
		$profile=self::nonnegative($data['profile_id']??0);if(null===$profile){return array('success'=>false,'code'=>'invalid_profile_id');}$run=self::nonnegative($data['run_id']??0);if(null===$run){return array('success'=>false,'code'=>'invalid_run_id');}
		$title=self::plain($data['title']??'',250,false);if(null===$title||''===$title){return array('success'=>false,'code'=>'invalid_idea_title');}
		$intent=sanitize_key(is_scalar($data['search_intent']??'informational')?(string)($data['search_intent']??'informational'):'');if(!in_array($intent,self::INTENTS,true)){return array('success'=>false,'code'=>'invalid_search_intent');}
		$status=sanitize_key(is_scalar($data['status']??'generated')?(string)($data['status']??'generated'):'');if(!in_array($status,self::STATUSES,true)){return array('success'=>false,'code'=>'invalid_idea_status');}
		$score=self::decimal($data['score']??0);if(null===$score){return array('success'=>false,'code'=>'invalid_score');}$priority=self::range_int($data['priority']??0,0,1000);if(null===$priority){return array('success'=>false,'code'=>'invalid_priority');}
		$planned=self::datetime($data['planned_publish_at']??null);if(null===$planned&&isset($data['planned_publish_at'])&&''!==$data['planned_publish_at']){return array('success'=>false,'code'=>'invalid_planned_publish_at');}
		$created=self::nonnegative($data['created_by']??0);if(null===$created){return array('success'=>false,'code'=>'invalid_created_by');}$now=self::now();$keyword=self::plain($data['primary_keyword']??'',191,false);if(null===$keyword){return array('success'=>false,'code'=>'invalid_primary_keyword');}
		$normalized_title=self::normalize_phrase($title,250);$normalized_keyword=self::normalize_phrase($keyword,191);
		$row=array('profile_id'=>$profile,'run_id'=>$run,'source_type'=>$source,'title'=>$title,'normalized_title'=>$normalized_title,'summary'=>self::plain($data['summary']??'',2000,true),'primary_keyword'=>$keyword,'normalized_keyword'=>$normalized_keyword,'secondary_keywords'=>self::encode(self::text_list($data['secondary_keywords']??array(),20,191,true)),'search_intent'=>$intent,'suggested_category'=>self::plain($data['suggested_category']??'',191,false)??'','outline'=>self::encode(self::text_list($data['outline']??array(),30,300,false)),'content_fingerprint'=>hash('sha256',$normalized_title.'|'.$normalized_keyword),'score'=>number_format($score,2,'.',''),'status'=>$status,'priority'=>$priority,'planned_publish_at'=>$planned,'approved_by'=>0,'approved_at'=>null,'rejected_by'=>0,'rejected_at'=>null,'rejection_code'=>'','last_error_code'=>'','created_by'=>$created,'updated_by'=>$created,'created_at'=>$now,'updated_at'=>$now);
		return array('success'=>true,'row'=>$row);
	}

	private function normalize_mutable(string $field,$value){if('title'===$field){$v=self::plain($value,250,false);return null===$v||''===$v?array('error'=>'invalid_idea_title'):$v;}if('summary'===$field){$v=self::plain($value,2000,true);return null===$v?array('error'=>'invalid_idea_summary'):$v;}if('primary_keyword'===$field){$v=self::plain($value,191,false);return null===$v?array('error'=>'invalid_primary_keyword'):$v;}if('suggested_category'===$field){$v=self::plain($value,191,false);return null===$v?array('error'=>'invalid_suggested_category'):$v;}if('secondary_keywords'===$field){return self::encode(self::text_list($value,20,191,true));}if('outline'===$field){return self::encode(self::text_list($value,30,300,false));}if('search_intent'===$field){$v=sanitize_key(is_scalar($value)?(string)$value:'');return in_array($v,self::INTENTS,true)?$v:array('error'=>'invalid_search_intent');}if('score'===$field){$v=self::decimal($value);return null===$v?array('error'=>'invalid_score'):number_format($v,2,'.','');}if('priority'===$field){$v=self::range_int($value,0,1000);return null===$v?array('error'=>'invalid_priority'):$v;}if('planned_publish_at'===$field){$v=self::datetime($value);return null===$v&&null!==$value&&''!==$value?array('error'=>'invalid_planned_publish_at'):$v;}return array('error'=>'invalid_idea_field');}
	private function filters(array $args):array{$where=array('1=1');$values=array();foreach(array('profile_id','run_id') as $f){if(isset($args[$f])){$v=self::nonnegative($args[$f]);if(null===$v){$where[]='1=0';}else{$where[]="{$f}=%d";$values[]=$v;}}}foreach(array('source_type'=>self::SOURCES,'status'=>self::STATUSES,'search_intent'=>self::INTENTS) as $f=>$allowed){if(isset($args[$f])){if(in_array($args[$f],$allowed,true)){$where[]="{$f}=%s";$values[]=$args[$f];}else{$where[]='1=0';}}}if(isset($args['statuses'])){$submitted=array();foreach(is_array($args['statuses'])?$args['statuses']:array() as $status){if(is_scalar($status)){$submitted[]=sanitize_key((string)$status);}}$s=array_values(array_intersect(array_unique($submitted),self::STATUSES));if($s){$where[]='status IN ('.implode(',',array_fill(0,count($s),'%s')).')';array_push($values,...$s);}else{$where[]='1=0';}}if(isset($args['content_fingerprint'])){if(is_string($args['content_fingerprint'])&&preg_match('/^[a-f0-9]{64}$/',$args['content_fingerprint'])){$where[]='content_fingerprint=%s';$values[]=$args['content_fingerprint'];}else{$where[]='1=0';}}foreach(array('planned_before'=>array('planned_publish_at','<='),'planned_after'=>array('planned_publish_at','>='),'created_before'=>array('created_at','<='),'created_after'=>array('created_at','>=')) as $key=>$spec){if(isset($args[$key])){$d=self::datetime($args[$key]);if(null===$d){$where[]='1=0';}else{$where[]=$spec[0].$spec[1].'%s';$values[]=$d;}}}return array($where,$values);}
	private function normalize_row($row):?array{if(!is_array($row)){return null;}foreach(array('id','profile_id','run_id','priority','approved_by','rejected_by','created_by','updated_by') as $f){$row[$f]=absint($row[$f]??0);}$row['score']=(float)($row['score']??0);$row['source_type']=in_array($row['source_type']??'',self::SOURCES,true)?$row['source_type']:'automation';$row['status']=in_array($row['status']??'',self::STATUSES,true)?$row['status']:'failed';$row['search_intent']=in_array($row['search_intent']??'',self::INTENTS,true)?$row['search_intent']:'informational';$row['secondary_keywords']=self::decode($row['secondary_keywords']??'');$row['outline']=self::decode($row['outline']??'');foreach(array('planned_publish_at','approved_at','rejected_at') as $f){$row[$f]=self::datetime($row[$f]??null);}return $row;}
	private function table():string{global $wpdb;return $wpdb->prefix.'aics_content_ideas';}
	private function formats(array $row):array{$ints=array('profile_id','run_id','priority','approved_by','rejected_by','created_by','updated_by');return array_map(static fn($f)=>in_array($f,$ints,true)?'%d':'%s',array_keys($row));}
	private static function result(bool $success,int $id,string $uuid,string $code):array{return array('success'=>$success,'idea_id'=>$id,'uuid'=>$uuid,'code'=>$code);}
	private static function simple(bool $success,int $id,string $code):array{return array('success'=>$success,'idea_id'=>$id,'code'=>$code);}
	private static function now():string{return current_time('mysql',true);}
	private static function nonnegative($v):?int{return is_scalar($v)&&preg_match('/^\d+$/',(string)$v)?(int)$v:null;}
	private static function range_int($v,int $min,int $max):?int{$v=self::nonnegative($v);return null!==$v&&$v>=$min&&$v<=$max?$v:null;}
	private static function decimal($v):?float{return is_numeric($v)&&is_finite((float)$v)&&(float)$v>=0&&(float)$v<=100?round((float)$v,2):null;}
	private static function plain($v,int $max,bool $textarea):?string{if(!is_scalar($v)){return null;}$v=$textarea?sanitize_textarea_field((string)$v):sanitize_text_field((string)$v);return self::length($v)>$max?null:$v;}
	private static function normalize_phrase(string $v,int $max):string{$v=html_entity_decode(wp_strip_all_tags($v),ENT_QUOTES|ENT_HTML5,get_bloginfo('charset')?:'UTF-8');$v=remove_accents($v);$v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);$v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??'';$v=trim(preg_replace('/\s+/u',' ',$v)??'');return self::cut($v,$max);}
	private static function text_list($v,int $max_items,int $max_length,bool $dedupe):array{if(!is_array($v)){return array();}$out=array();$seen=array();foreach($v as $item){if(!is_scalar($item)){continue;}$item=sanitize_text_field((string)$item);if(''===$item){continue;}$item=self::cut($item,$max_length);$key=function_exists('mb_strtolower')?mb_strtolower($item,'UTF-8'):strtolower($item);if($dedupe&&isset($seen[$key])){continue;}$seen[$key]=true;$out[]=$item;if(count($out)>=$max_items){break;}}return array_values($out);}
	private static function encode(array $v):string{$json=wp_json_encode($v);return false===$json?'[]':$json;}
	private static function decode($v):array{$d=json_decode(is_string($v)?$v:'',true);return is_array($d)&&array_is_list($d)?array_values(array_filter($d,'is_string')):array();}
	private static function datetime($v):?string{if(null===$v||''===$v){return null;}if(!is_scalar($v)){return null;}$v=(string)$v;$d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v,new DateTimeZone('UTC'));$e=DateTimeImmutable::getLastErrors();return false!==$d&&(false===$e||(0===$e['warning_count']&&0===$e['error_count']))&&$d->format('Y-m-d H:i:s')===$v?$v:null;}
	private static function error_code($v):string{return self::cut(sanitize_key(is_scalar($v)?(string)$v:''),100);}
	private static function length(string $v):int{return function_exists('mb_strlen')?mb_strlen($v):strlen($v);}
	private static function cut(string $v,int $max):string{return function_exists('mb_substr')?mb_substr($v,0,$max):substr($v,0,$max);}
}
