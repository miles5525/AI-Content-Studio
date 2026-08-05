<?php
/**
 * Persistent article storage and normalization.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns database persistence for one current article per persistent idea.
 */
final class AICS_Article_Repository {
	private const SOURCES = array( 'automation', 'manual' );
	private const STATUSES = array( 'queued', 'generating', 'generated', 'pending_approval', 'approved', 'rejected', 'draft_created', 'scheduled', 'published', 'failed', 'paused', 'needs_attention' );
	private const CONTENT_SOURCE_STATUSES = array( 'queued', 'generating', 'failed', 'needs_attention' );
	private const POST_STATUSES = array( 'draft_created', 'scheduled', 'published' );
	private const ORDERBY = array( 'id', 'status', 'word_count', 'planned_publish_at', 'generated_at', 'approved_at', 'scheduled_at', 'published_at', 'created_at', 'updated_at' );

	/** Creates one persistent Manual Studio article without automation parents. */
	public function create_manual_article( array $article_data, $created_by ): array {
		global $wpdb;$user=self::nonnegative($created_by);if(null===$user||0===$user){return self::result(false,0,'','invalid_created_by');}
		$title=trim(sanitize_text_field((string)($article_data['title']??'')));$excerpt=trim(sanitize_textarea_field((string)($article_data['excerpt']??'')));$content=is_string($article_data['content']??null)?trim((string)$article_data['content']):'';
		if(''===$title||''===$excerpt||''===trim(wp_strip_all_tags($content))||self::length($title)>250||self::length($excerpt)>500||self::length($content)>100000){return self::result(false,0,'','invalid_manual_article');}
		$uuid=wp_generate_uuid4();if(!is_string($uuid)||!wp_is_uuid($uuid,4)){return self::result(false,0,'','uuid_generation_failed');}
		$now=self::now();$text=trim(preg_replace('/\s+/u',' ',html_entity_decode(wp_strip_all_tags($content),ENT_QUOTES|ENT_HTML5,get_bloginfo('charset')?:'UTF-8'))??'');$words=''===$text?0:count(preg_split('/\s+/u',$text,-1,PREG_SPLIT_NO_EMPTY)?:array());$hash=hash('sha256',self::normalize_hash_part($title).'|'.self::normalize_hash_part($excerpt).'|'.$content);
		$row=array('article_uuid'=>$uuid,'idea_id'=>null,'profile_id'=>null,'run_id'=>null,'source_type'=>'manual','title'=>$title,'excerpt'=>$excerpt,'content'=>$content,'content_hash'=>$hash,'word_count'=>$words,'status'=>'generated','wordpress_post_id'=>null,'generation_attempts'=>1,'last_generation_at'=>$now,'generated_at'=>$now,'featured_image_required'=>0,'featured_image_status'=>'not_requested','featured_image_attachment_id'=>null,'featured_image_attempts'=>0,'created_by'=>$user,'updated_by'=>$user,'created_at'=>$now,'updated_at'=>$now);
		$inserted=$wpdb->insert($this->table(),$row,$this->formats($row));return false===$inserted?self::result(false,0,'','database_insert_failed'):self::result(true,(int)$wpdb->insert_id,$uuid,'manual_article_created');
	}

	/** Updates editable fields on the same owned Manual Studio article. */
	public function update_manual_article( $article_id, array $article_data, $updated_by ): array {
		global $wpdb;$id=absint($article_id);$user=self::nonnegative($updated_by);$article=$this->get_by_id($id);if(!$article){return self::simple(false,0,'article_not_found');}if('manual'!==$article['source_type']||null===$user||0===$user||$article['created_by']!==$user){return self::simple(false,$id,'manual_article_not_owned');}if(!in_array($article['status'],array('generated','draft_created'),true)){return self::simple(false,$id,'manual_article_update_conflict');}
		$title=trim(sanitize_text_field((string)($article_data['title']??'')));$excerpt=trim(sanitize_textarea_field((string)($article_data['excerpt']??'')));$content=is_string($article_data['content']??null)?trim((string)$article_data['content']):'';if(''===$title||''===$excerpt||''===trim(wp_strip_all_tags($content))||self::length($title)>250||self::length($excerpt)>500||self::length($content)>100000){return self::simple(false,$id,'invalid_manual_article');}
		$text=trim(preg_replace('/\s+/u',' ',html_entity_decode(wp_strip_all_tags($content),ENT_QUOTES|ENT_HTML5,get_bloginfo('charset')?:'UTF-8'))??'');$words=''===$text?0:count(preg_split('/\s+/u',$text,-1,PREG_SPLIT_NO_EMPTY)?:array());$hash=hash('sha256',self::normalize_hash_part($title).'|'.self::normalize_hash_part($excerpt).'|'.$content);$now=self::now();
		$changed=$wpdb->query($wpdb->prepare("UPDATE {$this->table()} SET title=%s,excerpt=%s,content=%s,content_hash=%s,word_count=%d,updated_by=%d,updated_at=%s WHERE id=%d AND source_type='manual' AND created_by=%d AND status IN ('generated','draft_created')",$title,$excerpt,$content,$hash,$words,$user,$now,$id,$user));
		return false===$changed?self::simple(false,$id,'database_update_failed'):(0===$changed&&!$this->get_by_id($id)?self::simple(false,$id,'article_not_found'):self::simple(true,$id,'manual_article_updated'));
	}

	/** Creates or safely returns the one article belonging to an idea. */
	public function create_for_idea( $idea_id, array $context = array() ): array {
		global $wpdb;
		$id = absint( $idea_id );
		if ( 0 === $id ) {
			return self::result( false, 0, '', 'invalid_idea_id' );
		}

		$existing = $this->get_by_idea_id( $id );
		if ( $existing ) {
			return self::result( true, $existing['id'], $existing['article_uuid'], 'article_already_exists' );
		}

		$ideas = new AICS_Content_Idea_Repository();
		$idea  = $ideas->get_by_id( $id );
		if ( ! $idea ) {
			return self::result( false, 0, '', 'idea_not_found' );
		}
		if ( ! in_array( $idea['status'], array( 'approved', 'queued', 'article_generating' ), true ) ) {
			return self::result( false, 0, '', 'idea_not_ready' );
		}

		$uuid = wp_generate_uuid4();
		if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid, 4 ) ) {
			return self::result( false, 0, '', 'uuid_generation_failed' );
		}

		$created_by = self::nonnegative( $context['created_by'] ?? 0 );
		if ( null === $created_by ) {
			return self::result( false, 0, '', 'invalid_created_by' );
		}
		$source = in_array( $idea['source_type'], self::SOURCES, true ) ? $idea['source_type'] : 'automation';
		$image = AICS_Automation_Featured_Image_Settings::validate( is_array( $context['featured_image_settings'] ?? null ) ? $context['featured_image_settings'] : array() );
		$image = is_wp_error( $image ) ? AICS_Automation_Featured_Image_Settings::defaults() : $image;
		$now    = self::now();
		$row    = array(
			'article_uuid'        => $uuid,
			'idea_id'             => $id,
			'profile_id'          => absint( $idea['profile_id'] ),
			'run_id'              => absint( $idea['run_id'] ),
			'source_type'         => $source,
			'title'               => '',
			'excerpt'             => '',
			'content'             => '',
			'content_hash'        => '',
			'word_count'          => 0,
			'status'              => 'queued',
			'planned_publish_at'  => $idea['planned_publish_at'],
			'wordpress_post_id'   => null,
			'generation_attempts' => 0,
			'approved_by'         => 0,
			'rejected_by'         => 0,
			'rejection_code'      => '',
			'last_error_code'     => '',
			'featured_image_required' => $image['enabled'] && $image['required'] ? 1 : 0,
			'featured_image_status' => $image['enabled'] ? 'pending' : 'not_requested',
			'featured_image_attachment_id' => null,
			'featured_image_attempts' => 0,
			'created_by'          => $created_by,
			'updated_by'          => $created_by,
			'created_at'          => $now,
			'updated_at'          => $now,
		);
		$inserted = $wpdb->insert( $this->table(), $row, $this->formats( $row ) );
		if ( false !== $inserted ) {
			return self::result( true, (int) $wpdb->insert_id, $uuid, 'article_created' );
		}

		// A concurrent request may have won the unique idea_id insert race.
		$existing = $this->get_by_idea_id( $id );
		return $existing
			? self::result( true, $existing['id'], $existing['article_uuid'], 'article_already_exists' )
			: self::result( false, 0, '', 'database_insert_failed' );
	}

	public function get_by_id( $article_id ): ?array {
		return $this->get_one( 'id', absint( $article_id ), '%d' );
	}

	public function get_by_uuid( $article_uuid ): ?array {
		$uuid = is_scalar( $article_uuid ) ? sanitize_text_field( (string) $article_uuid ) : '';
		return wp_is_uuid( $uuid ) ? $this->get_one( 'article_uuid', $uuid, '%s' ) : null;
	}

	public function get_by_idea_id( $idea_id ): ?array {
		$id = absint( $idea_id );
		return $id > 0 ? $this->get_one( 'idea_id', $id, '%d' ) : null;
	}

	public function get_by_wordpress_post_id( $post_id ): ?array {
		$id = absint( $post_id );
		return $id > 0 ? $this->get_one( 'wordpress_post_id', $id, '%d' ) : null;
	}

	public function get_articles( array $args = array() ): array {
		global $wpdb;
		list( $where, $values ) = $this->filters( $args );
		$orderby = in_array( $args['orderby'] ?? '', self::ORDERBY, true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		$limit   = max( 1, min( 100, absint( $args['limit'] ?? 20 ) ) );
		$offset  = absint( $args['offset'] ?? 0 );
		$values[] = $limit;
		$values[] = $offset;
		$sql = $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order}, id {$order} LIMIT %d OFFSET %d", $values );
		return array_values( array_filter( array_map( array( $this, 'normalize_row' ), $wpdb->get_results( $sql, ARRAY_A ) ) ) );
	}

	public function count_articles( array $args = array() ): int {
		global $wpdb;
		list( $where, $values ) = $this->filters( $args );
		$sql = "SELECT COUNT(*) FROM {$this->table()} WHERE " . implode( ' AND ', $where );
		return (int) $wpdb->get_var( $values ? $wpdb->prepare( $sql, $values ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function get_articles_for_run( $run_id, array $args = array() ): array {
		$id = absint( $run_id );
		if ( 0 === $id ) {
			return array();
		}
		$args['run_id'] = $id;
		return $this->get_articles( $args );
	}
	public function aggregate_for_run_ids(array $run_ids):array{global $wpdb;$ids=array_values(array_unique(array_filter(array_map('absint',$run_ids))));if(!$ids){return array();}$marks=implode(',',array_fill(0,count($ids),'%d'));$sql=$wpdb->prepare("SELECT run_id,COUNT(*) articles,SUM(wordpress_post_id IS NOT NULL AND wordpress_post_id>0) posts FROM {$this->table()} WHERE run_id IN ({$marks}) GROUP BY run_id",$ids);$out=array();foreach($wpdb->get_results($sql,ARRAY_A) as $row){$out[absint($row['run_id'])]=array('articles'=>absint($row['articles']),'posts'=>absint($row['posts']));}return $out;}
	public function get_post_associations_for_health(array $run_ids,$limit=100):array{global $wpdb;$ids=array_slice(array_values(array_unique(array_filter(array_map('absint',$run_ids)))),0,100);if(!$ids){return array();}$limit=max(1,min(100,absint($limit)));$marks=implode(',',array_fill(0,count($ids),'%d'));$values=array_merge($ids,array($limit+1));$sql=$wpdb->prepare("SELECT id,article_uuid,run_id,profile_id,status,wordpress_post_id FROM {$this->table()} WHERE run_id IN ({$marks}) AND wordpress_post_id IS NOT NULL AND wordpress_post_id>0 ORDER BY run_id,id LIMIT %d",$values);return $wpdb->get_results($sql,ARRAY_A)?:array();}

	/** Counts downstream articles with a native post association for one run/profile. */
	public function count_associated_posts_for_run( $run_id, $profile_id ): int {
		global $wpdb; $run=absint($run_id); $profile=absint($profile_id); if(0===$run||0===$profile){return 0;}
		return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table()} WHERE run_id=%d AND profile_id=%d AND source_type='automation' AND status IN ('draft_created','scheduled','published') AND wordpress_post_id IS NOT NULL AND wordpress_post_id>0",$run,$profile));
	}

	/** Requeues only empty terminal article placeholders during an audited manual retry. */
	public function prepare_for_administrator_retry( $run_id, array $error_codes ): bool {
		global $wpdb; $run=absint($run_id); $codes=array_values(array_unique(array_filter(array_map('sanitize_key',$error_codes))));
		if(0===$run||!$codes){return false;}$marks=implode(',',array_fill(0,count($codes),'%s'));$values=array_merge(array(current_time('mysql',true),$run),$codes);
		$sql=$wpdb->prepare("UPDATE {$this->table()} SET status='queued',updated_at=%s WHERE run_id=%d AND status IN ('needs_attention','failed') AND title='' AND excerpt='' AND content='' AND last_error_code IN ({$marks})",$values);
		$changed=$wpdb->query($sql);return false!==$changed;
	}

	/** Returns one deterministically ordered approved automation article. */
	public function get_next_approved_for_post_creation( $run_id, $profile_id ): ?array {
		global $wpdb;
		$run = absint( $run_id ); $profile = absint( $profile_id );
		if ( 0 === $run || 0 === $profile ) { return null; }
		$sql = $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE run_id=%d AND profile_id=%d AND source_type='automation' AND status='approved' ORDER BY CASE WHEN planned_publish_at IS NULL THEN 1 ELSE 0 END ASC, planned_publish_at ASC, created_at ASC, id ASC LIMIT 1", $run, $profile );
		return $this->normalize_row( $wpdb->get_row( $sql, ARRAY_A ) );
	}

	/** Stores content only after the existing article validator has sanitized it. */
	public function store_generated_content( $article_id, array $article_data, $updated_by = 0 ): array {
		global $wpdb;
		$id      = absint( $article_id );
		$article = $this->get_by_id( $id );
		if ( ! $article ) {
			return self::simple( false, 0, 'article_not_found' );
		}
		if ( ! in_array( $article['status'], self::CONTENT_SOURCE_STATUSES, true ) ) {
			return self::simple( false, $id, 'article_content_not_storable' );
		}
		if ( ! is_string( $article_data['title'] ?? null ) || ! is_string( $article_data['excerpt'] ?? null ) || ! is_string( $article_data['content'] ?? null ) ) {
			return self::simple( false, $id, 'invalid_article_content' );
		}
		$title   = trim( sanitize_text_field( $article_data['title'] ) );
		$excerpt = trim( sanitize_textarea_field( $article_data['excerpt'] ) );
		$content = trim( $article_data['content'] );
		$user    = self::nonnegative( $updated_by );
		if ( '' === $title || '' === $excerpt || '' === trim( wp_strip_all_tags( $content ) ) ) {
			return self::simple( false, $id, 'article_content_required' );
		}
		if ( self::length( $title ) > 250 || self::length( $excerpt ) > 500 || self::length( $content ) > 100000 ) {
			return self::simple( false, $id, 'article_content_too_large' );
		}
		if ( null === $user ) {
			return self::simple( false, $id, 'invalid_updated_by' );
		}
		$now  = self::now();
		$text = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
		$word_count = '' === $text ? 0 : count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: array() );
		$hash_source = self::normalize_hash_part( $title ) . '|' . self::normalize_hash_part( $excerpt ) . '|' . $content;
		$row = array( 'title' => $title, 'excerpt' => $excerpt, 'content' => $content, 'content_hash' => hash( 'sha256', $hash_source ), 'word_count' => $word_count, 'status' => 'generated', 'generated_at' => $now, 'last_generation_at' => $now, 'last_error_code' => '', 'updated_by' => $user, 'updated_at' => $now );
		$placeholders = implode( ',', array_fill( 0, count( self::CONTENT_SOURCE_STATUSES ), '%s' ) );
		$values = array_values( $row );
		$values[] = $id;
		array_push( $values, ...self::CONTENT_SOURCE_STATUSES );
		$set = array();
		foreach ( $row as $field => $value ) {
			$set[] = $field . ( in_array( $field, array( 'word_count', 'updated_by' ), true ) ? '=%d' : '=%s' );
		}
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$this->table()} SET " . implode( ',', $set ) . " WHERE id=%d AND status IN ({$placeholders})", $values ) );
		return 1 === $changed ? self::simple( true, $id, 'article_content_stored' ) : self::simple( false, $id, 'article_content_store_conflict' );
	}

	public function increment_generation_attempt( $article_id, $updated_by = 0 ): array {
		global $wpdb;
		$id = absint( $article_id );
		$user = self::nonnegative( $updated_by );
		if ( 0 === $id || null === $user ) {
			return array( 'success' => false, 'article_id' => $id, 'attempt_count' => 0, 'code' => 0 === $id ? 'invalid_article_id' : 'invalid_updated_by' );
		}
		$now = self::now();
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$this->table()} SET generation_attempts=generation_attempts+1,last_generation_at=%s,updated_by=%d,updated_at=%s WHERE id=%d AND generation_attempts<65535", $now, $user, $now, $id ) );
		if ( 1 !== $changed ) {
			return array( 'success' => false, 'article_id' => $id, 'attempt_count' => $this->get_by_id( $id )['generation_attempts'] ?? 0, 'code' => $this->get_by_id( $id ) ? 'generation_attempt_limit_reached' : 'article_not_found' );
		}
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT generation_attempts FROM {$this->table()} WHERE id=%d", $id ) );
		return array( 'success' => true, 'article_id' => $id, 'attempt_count' => $count, 'code' => 'generation_attempt_incremented' );
	}

	/** Atomically returns an empty in-progress placeholder to its retry queue. */
	public function prepare_generation_retry( $article_id, $error_code, $updated_by = 0 ): array {
		global $wpdb;
		$id=absint($article_id);$code=self::error_code($error_code);$user=self::nonnegative($updated_by);$article=$this->get_by_id($id);
		if(!$article){return self::simple(false,0,'article_not_found');}if(''===$code||null===$user){return self::simple(false,$id,'invalid_error_code');}
		if('queued'===$article['status']&&''===$article['title']&&''===$article['excerpt']&&''===$article['content']){return self::simple(true,$id,'article_ready_for_retry');}
		if('generating'!==$article['status']||''!==$article['title']||''!==$article['excerpt']||''!==$article['content']){return self::simple(false,$id,'article_retry_conflict');}
		$now=self::now();$sql=$wpdb->prepare("UPDATE {$this->table()} SET status='queued',last_error_code=%s,updated_by=%d,updated_at=%s WHERE id=%d AND status='generating' AND title='' AND excerpt='' AND content=''",$code,$user,$now,$id);$changed=$wpdb->query($sql);
		return 1===$changed?self::simple(true,$id,'article_ready_for_retry'):self::simple(false,$id,'article_retry_conflict');
	}

	public function transition_status( $article_id, array $from_statuses, $to_status, array $context = array() ): array {
		global $wpdb;
		$id = absint( $article_id );
		$from = self::statuses( $from_statuses );
		$to = sanitize_key( is_scalar( $to_status ) ? (string) $to_status : '' );
		$allowed_context = array( 'updated_by', 'approved_by', 'rejected_by', 'rejection_code', 'error_code', 'planned_publish_at' );
		if ( array_diff( array_keys( $context ), $allowed_context ) ) {
			return self::simple( false, $id, 'invalid_transition_context' );
		}
		if ( 0 === $id || null === $from || empty( $from ) || ! in_array( $to, self::STATUSES, true ) ) {
			return self::simple( false, $id, 'invalid_article_transition' );
		}
		$user = self::nonnegative( $context['updated_by'] ?? 0 );
		if ( null === $user ) {
			return self::simple( false, $id, 'invalid_updated_by' );
		}
		$now = self::now();
		$set = array( 'status=%s', 'updated_by=%d', 'updated_at=%s' );
		$values = array( $to, $user, $now );
		if ( 'approved' === $to ) {
			$actor = self::nonnegative( $context['approved_by'] ?? 0 );
			if ( null === $actor ) { return self::simple( false, $id, 'invalid_approved_by' ); }
			$set = array_merge( $set, array( 'approved_by=%d', 'approved_at=%s', 'rejected_by=0', 'rejected_at=NULL', "rejection_code=''" ) );
			$values[] = $actor; $values[] = $now;
		} elseif ( 'rejected' === $to ) {
			$actor = self::nonnegative( $context['rejected_by'] ?? 0 );
			$code = self::error_code( $context['rejection_code'] ?? 'article_rejected' );
			if ( null === $actor || '' === $code ) { return self::simple( false, $id, 'invalid_rejection' ); }
			$set = array_merge( $set, array( 'rejected_by=%d', 'rejected_at=%s', 'rejection_code=%s', 'approved_by=0', 'approved_at=NULL' ) );
			$values[] = $actor; $values[] = $now; $values[] = $code;
		}
		if ( 'failed' === $to || 'needs_attention' === $to ) {
			$code = self::error_code( $context['error_code'] ?? 'article_processing_failed' );
			if ( '' === $code ) { return self::simple( false, $id, 'invalid_error_code' ); }
			$set[] = 'last_error_code=%s'; $values[] = $code;
		}
		if ( array_key_exists( 'planned_publish_at', $context ) ) {
			$planned = self::datetime( $context['planned_publish_at'] );
			if ( null === $planned && null !== $context['planned_publish_at'] && '' !== $context['planned_publish_at'] ) { return self::simple( false, $id, 'invalid_planned_publish_at' ); }
			$set[] = null === $planned ? 'planned_publish_at=NULL' : 'planned_publish_at=%s';
			if ( null !== $planned ) { $values[] = $planned; }
		}
		$holders = implode( ',', array_fill( 0, count( $from ), '%s' ) );
		$values[] = $id; array_push( $values, ...$from );
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$this->table()} SET " . implode( ',', $set ) . " WHERE id=%d AND status IN ({$holders})", $values ) );
		return 1 === $changed ? self::simple( true, $id, 'article_status_transitioned' ) : self::simple( false, $id, $this->get_by_id( $id ) ? 'article_transition_conflict' : 'article_not_found' );
	}

	public function associate_wordpress_post( $article_id, $post_id, $target_status, $updated_by = 0 ): array {
		global $wpdb;
		$id = absint( $article_id ); $post = absint( $post_id );
		$status = sanitize_key( is_scalar( $target_status ) ? (string) $target_status : '' );
		$user = self::nonnegative( $updated_by );
		$article = $this->get_by_id( $id );
		if ( ! $article ) { return self::simple( false, 0, 'article_not_found' ); }
		if ( 0 === $post || ! get_post( $post ) ) { return self::simple( false, $id, 'wordpress_post_not_found' ); }
		if ( ! in_array( $status, self::POST_STATUSES, true ) || null === $user ) { return self::simple( false, $id, 'invalid_post_association' ); }
		if ( $article['wordpress_post_id'] > 0 && $article['wordpress_post_id'] !== $post ) { return self::simple( false, $id, 'article_post_reassociation_rejected' ); }
		$other = $this->get_by_wordpress_post_id( $post );
		if ( $other && $other['id'] !== $id ) { return self::simple( false, $id, 'wordpress_post_already_associated' ); }
		$now = self::now();
		$set = array( 'wordpress_post_id=%d', 'status=%s', 'updated_by=%d', 'updated_at=%s' );
		$values = array( $post, $status, $user, $now );
		if ( 0 === $article['wordpress_post_id'] ) { $set[] = 'post_created_at=%s'; $values[] = $now; }
		if ( 'scheduled' === $status ) { $set[] = 'scheduled_at=%s'; $values[] = $now; }
		if ( 'published' === $status ) { $set[] = 'published_at=%s'; $values[] = $now; }
		$values[] = $id; $values[] = $post;
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$this->table()} SET " . implode( ',', $set ) . ' WHERE id=%d AND (wordpress_post_id IS NULL OR wordpress_post_id=%d)', $values ) );
		if ( false === $changed ) { return self::simple( false, $id, 'database_update_failed' ); }
		if ( 1 === $changed ) { return self::simple( true, $id, 'wordpress_post_associated' ); }
		$fresh = $this->get_by_id( $id );
		return $fresh && $fresh['wordpress_post_id'] === $post
			? self::simple( true, $id, 'wordpress_post_associated' )
			: self::simple( false, $id, 'article_post_reassociation_rejected' );
	}

	public function update_planned_publish_at( $article_id, $utc_datetime, $updated_by = 0 ): array {
		global $wpdb;
		$id = absint( $article_id ); $user = self::nonnegative( $updated_by ); $date = self::datetime( $utc_datetime );
		if ( 0 === $id || ! $this->get_by_id( $id ) ) { return self::simple( false, 0, 'article_not_found' ); }
		if ( null === $user ) { return self::simple( false, $id, 'invalid_updated_by' ); }
		if ( null === $date && null !== $utc_datetime && '' !== $utc_datetime ) { return self::simple( false, $id, 'invalid_planned_publish_at' ); }
		$row = array( 'planned_publish_at' => $date, 'updated_by' => $user, 'updated_at' => self::now() );
		$changed = $wpdb->update( $this->table(), $row, array( 'id' => $id ), array( '%s', '%d', '%s' ), array( '%d' ) );
		return false === $changed ? self::simple( false, $id, 'database_update_failed' ) : self::simple( true, $id, 'planned_publish_at_updated' );
	}

	/** Synchronizes an existing associated post to its scheduled article state. */
	public function mark_scheduled( $article_id, $post_id, $planned_publish_at, $updated_by = 0 ): array {
		global $wpdb; $id=absint($article_id);$post=absint($post_id);$user=self::nonnegative($updated_by);$planned=self::datetime($planned_publish_at);$article=$this->get_by_id($id);
		if(!$article){return self::simple(false,0,'article_not_found');}if(0===$post||$article['wordpress_post_id']!==$post||null===$planned||null===$user){return self::simple(false,$id,'invalid_post_association');}if(!in_array($article['status'],array('draft_created','scheduled'),true)){return self::simple(false,$id,'article_schedule_conflict');}
		$now=self::now();$sql=$wpdb->prepare("UPDATE {$this->table()} SET status='scheduled',planned_publish_at=%s,scheduled_at=COALESCE(scheduled_at,%s),last_error_code='',updated_by=%d,updated_at=%s WHERE id=%d AND wordpress_post_id=%d AND status IN ('draft_created','scheduled')",$planned,$now,$user,$now,$id,$post);$changed=$wpdb->query($sql);return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'article_marked_scheduled');
	}

	/** Synchronizes an existing associated post to its published article state. */
	public function mark_published( $article_id, $post_id, $published_at_utc, $updated_by = 0 ): array {
		global $wpdb;$id=absint($article_id);$post=absint($post_id);$user=self::nonnegative($updated_by);$published=self::datetime($published_at_utc);$article=$this->get_by_id($id);
		if(!$article){return self::simple(false,0,'article_not_found');}if(0===$post||$article['wordpress_post_id']!==$post||null===$published||null===$user){return self::simple(false,$id,'invalid_post_association');}if(!in_array($article['status'],array('draft_created','scheduled','published'),true)){return self::simple(false,$id,'article_publish_conflict');}
		$now=self::now();$sql=$wpdb->prepare("UPDATE {$this->table()} SET status='published',published_at=COALESCE(published_at,%s),last_error_code='',updated_by=%d,updated_at=%s WHERE id=%d AND wordpress_post_id=%d AND status IN ('draft_created','scheduled','published')",$published,$user,$now,$id,$post);$changed=$wpdb->query($sql);return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'article_marked_published');
	}

	public function update_error_code( $article_id, $error_code, $updated_by = 0 ): array {
		global $wpdb;
		$id = absint( $article_id ); $user = self::nonnegative( $updated_by ); $code = self::error_code( $error_code );
		if ( 0 === $id || ! $this->get_by_id( $id ) ) { return self::simple( false, 0, 'article_not_found' ); }
		if ( null === $user || '' === $code ) { return self::simple( false, $id, 'invalid_error_code' ); }
		$changed = $wpdb->update( $this->table(), array( 'last_error_code' => $code, 'updated_by' => $user, 'updated_at' => self::now() ), array( 'id' => $id ), array( '%s', '%d', '%s' ), array( '%d' ) );
		return false === $changed ? self::simple( false, $id, 'database_update_failed' ) : self::simple( true, $id, 'article_error_updated' );
	}

	/** Returns only the shared featured-image persistence fields. */
	public function get_featured_image_data( $article_id ): ?array {
		$article=$this->get_by_id(absint($article_id));if(!$article){return null;}
		$fields=array('featured_image_required','featured_image_status','featured_image_attachment_id','featured_image_prompt','featured_image_alt_text','featured_image_provider','featured_image_model','featured_image_attempts','featured_image_generated_at','featured_image_uploaded_at','featured_image_attached_at','featured_image_last_error_code');return array_intersect_key($article,array_fill_keys($fields,true));
	}

	/** Initializes the image lifecycle once without generating or uploading an image. */
	public function initialize_featured_image( $article_id, array $image_data ): array {
		global $wpdb;
		$id = absint( $article_id ); $article = $this->get_by_id( $id );
		if ( ! $article ) { return self::simple( false, 0, 'article_not_found' ); }
		$required = filter_var( $image_data['required'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( null === $required ) { return self::simple( false, $id, 'invalid_featured_image_required' ); }
		$target = $required ? 'pending' : 'skipped';
		if ( ! AICS_Featured_Image_State::can_transition( $article['featured_image_status'], $target ) ) { return self::simple( false, $id, 'featured_image_transition_rejected' ); }
		$prompt = self::nullable_text( $image_data['prompt'] ?? null, 5000 ); $alt = self::nullable_text( $image_data['alt_text'] ?? null, 1000 );
		if ( false === $prompt || false === $alt ) { return self::simple( false, $id, 'invalid_featured_image_metadata' ); }
		$sets = array( 'featured_image_required=%d', 'featured_image_status=%s', 'featured_image_prompt=' . ( null === $prompt ? 'NULL' : '%s' ), 'featured_image_alt_text=' . ( null === $alt ? 'NULL' : '%s' ), 'featured_image_last_error_code=NULL', 'updated_at=%s' );
		$values = array( $required ? 1 : 0, $target ); if ( null !== $prompt ) { $values[] = $prompt; } if ( null !== $alt ) { $values[] = $alt; } $values[] = self::now(); $values[] = $id;
		$sql = $wpdb->prepare( "UPDATE {$this->table()} SET " . implode( ',', $sets ) . " WHERE id=%d AND featured_image_status='not_requested'", $values );
		$changed = $wpdb->query( $sql );
		return 1 === $changed ? self::simple( true, $id, 'featured_image_initialized' ) : self::simple( false, $id, false === $changed ? 'database_update_failed' : 'featured_image_state_changed' );
	}

	/** Performs a strict compare-and-swap image-state transition. */
	public function atomic_transition_featured_image_status( $article_id, $expected_status, $resulting_status, array $changes=array() ): array {
		global $wpdb;$id=absint($article_id);if(0===$id||!AICS_Featured_Image_State::is_supported($expected_status)||!AICS_Featured_Image_State::is_supported($resulting_status)){return self::simple(false,$id,'invalid_featured_image_status');}$expected=sanitize_key((string)$expected_status);$result=sanitize_key((string)$resulting_status);if(!AICS_Featured_Image_State::can_transition($expected,$result)){return self::simple(false,$id,'featured_image_transition_rejected');}
		$allowed=array('featured_image_prompt'=>5000,'featured_image_alt_text'=>1000,'featured_image_provider'=>64,'featured_image_model'=>100);$sets=array('featured_image_status=%s');$values=array($result);
		foreach($changes as $field=>$value){if(isset($allowed[$field])){$clean=self::nullable_text($value,$allowed[$field]);if(false===$clean){return self::simple(false,$id,'invalid_featured_image_metadata');}$sets[]=$field.'='.(null===$clean?'NULL':'%s');if(null!==$clean){$values[]=$clean;}}elseif('featured_image_attempts'===$field){$attempts=self::nonnegative($value);if(null===$attempts){return self::simple(false,$id,'invalid_featured_image_attempts');}$sets[]='featured_image_attempts=%d';$values[]=$attempts;}elseif(in_array($field,array('featured_image_generated_at','featured_image_uploaded_at'),true)){$date=self::datetime($value);if(null===$date){return self::simple(false,$id,'invalid_featured_image_timestamp');}$sets[]="{$field}=%s";$values[]=$date;}elseif('featured_image_last_error_code'===$field){$code=is_scalar($value)?sanitize_key((string)$value):'';if(''!==$code&&!AICS_Featured_Image_State::is_error_supported($code)){return self::simple(false,$id,'invalid_featured_image_error_code');}$sets[]='featured_image_last_error_code='.(''===$code?'NULL':'%s');if(''!==$code){$values[]=$code;}}else{return self::simple(false,$id,'invalid_featured_image_change');}}
		$now=self::now();$sets[]='updated_at=%s';$values[]=$now;$values[]=$id;$values[]=$expected;$sql=$wpdb->prepare("UPDATE {$this->table()} SET ".implode(',',$sets)." WHERE id=%d AND featured_image_status=%s",$values);$changed=$wpdb->query($sql);return 1===$changed?self::simple(true,$id,'featured_image_status_updated'):self::simple(false,$id,false===$changed?'database_update_failed':(null===$this->get_by_id($id)?'article_not_found':'featured_image_state_changed'));
	}

	/** Atomically persists a validated upload before featured-image assignment. */
	public function mark_featured_image_uploaded( $article_id, $expected_status, $attachment_id, array $metadata=array() ): array {
		global $wpdb;
		$id=absint($article_id);$attachment=absint($attachment_id);$expected=AICS_Featured_Image_State::is_supported($expected_status)?sanitize_key((string)$expected_status):'';$article=$this->get_by_id($id);
		if(!$article){return self::simple(false,0,'article_not_found');}if(!$attachment||!get_post($attachment)||!AICS_Featured_Image_State::can_transition($expected,'uploaded')){return self::simple(false,$id,'invalid_featured_image_attachment');}
		$current=absint($article['featured_image_attachment_id']);if($current&&$current!==$attachment){return self::simple(false,$id,'featured_image_attachment_conflict');}
		$alt=self::nullable_text($metadata['alt_text']??$article['featured_image_alt_text'],1000);$provider=self::nullable_text($metadata['provider']??$article['featured_image_provider'],64);$model=self::nullable_text($metadata['model']??$article['featured_image_model'],100);
		if(false===$alt||false===$provider||false===$model){return self::simple(false,$id,'invalid_featured_image_metadata');}
		$now=self::now();$sets=array("featured_image_status='uploaded'",'featured_image_attachment_id=%d','featured_image_generated_at=COALESCE(featured_image_generated_at,%s)','featured_image_uploaded_at=COALESCE(featured_image_uploaded_at,%s)','featured_image_last_error_code=NULL');$values=array($attachment,$now,$now);
		foreach(array('featured_image_alt_text'=>$alt,'featured_image_provider'=>$provider,'featured_image_model'=>$model) as $field=>$value){$sets[]=$field.'='.(null===$value?'NULL':'%s');if(null!==$value){$values[]=$value;}}
		$sets[]='updated_at=%s';$values[]=$now;$values[]=$id;$values[]=$expected;$values[]=$attachment;
		$changed=$wpdb->query($wpdb->prepare("UPDATE {$this->table()} SET ".implode(',',$sets)." WHERE id=%d AND featured_image_status=%s AND (featured_image_attachment_id IS NULL OR featured_image_attachment_id=%d)",$values));
		if(1===$changed){return self::simple(true,$id,'featured_image_upload_persisted');}if(false===$changed){return self::simple(false,$id,'database_update_failed');}$fresh=$this->get_by_id($id);return $fresh&&'uploaded'===$fresh['featured_image_status']&&$attachment===$fresh['featured_image_attachment_id']?self::simple(true,$id,'featured_image_upload_already_persisted'):self::simple(false,$id,'featured_image_state_changed');
	}

	/** Idempotently associates one attachment and advances uploaded to attached. */
	public function associate_featured_image_attachment( $article_id, $expected_status, $attachment_id, array $metadata=array() ): array {
		global $wpdb;$id=absint($article_id);$attachment=absint($attachment_id);if(0===$id||0===$attachment){return self::simple(false,$id,'invalid_featured_image_attachment');}$article=$this->get_by_id($id);if(!$article){return self::simple(false,0,'article_not_found');}$current=absint($article['featured_image_attachment_id']);if('attached'===$article['featured_image_status']&&$current===$attachment){return self::simple(true,$id,'featured_image_attachment_already_associated');}if($current&&$current!==$attachment){return self::simple(false,$id,'featured_image_attachment_conflict');}
		$expected=AICS_Featured_Image_State::is_supported($expected_status)?sanitize_key((string)$expected_status):'';if('uploaded'!==$expected||!AICS_Featured_Image_State::can_transition($expected,'attached')){return self::simple(false,$id,'featured_image_transition_rejected');}
		$alt=self::nullable_text($metadata['alt_text']??$article['featured_image_alt_text'],1000);if(false===$alt){return self::simple(false,$id,'invalid_featured_image_metadata');}$now=self::now();$sets=array("featured_image_status='attached'",'featured_image_attachment_id=%d','featured_image_alt_text='.(null===$alt?'NULL':'%s'),'featured_image_attached_at=COALESCE(featured_image_attached_at,%s)','featured_image_last_error_code=NULL','updated_at=%s');$values=array($attachment);if(null!==$alt){$values[]=$alt;}$values[]=$now;$values[]=$now;$values[]=$id;$values[]=$expected;$values[]=$attachment;$sql=$wpdb->prepare("UPDATE {$this->table()} SET ".implode(',',$sets)." WHERE id=%d AND featured_image_status=%s AND (featured_image_attachment_id IS NULL OR featured_image_attachment_id=%d)",$values);$changed=$wpdb->query($sql);if(1===$changed){return self::simple(true,$id,'featured_image_attachment_associated');}if(false===$changed){return self::simple(false,$id,'database_update_failed');}$fresh=$this->get_by_id($id);return $fresh&&'attached'===$fresh['featured_image_status']&&$attachment===$fresh['featured_image_attachment_id']?self::simple(true,$id,'featured_image_attachment_already_associated'):self::simple(false,$id,'featured_image_state_changed');
	}

	public function record_featured_image_error( $article_id, $expected_status, $resulting_status, $controlled_error_code ): array {$code=is_scalar($controlled_error_code)?sanitize_key((string)$controlled_error_code):'';if(!AICS_Featured_Image_State::is_error_supported($code)){return self::simple(false,absint($article_id),'invalid_featured_image_error_code');}return $this->atomic_transition_featured_image_status($article_id,$expected_status,$resulting_status,array('featured_image_last_error_code'=>$code));}

	/** Persists normalized SEO values without accepting raw provider output. */
	public function update_seo_data($article_id,AICS_SEO_Data $data,string $status='generated',string $target='native'):array{global $wpdb;$id=absint($article_id);if(!$id||!AICS_SEO_State::is_valid($status)||!in_array($target,array('native','yoast','rank_math','aioseo'),true)){return self::simple(false,$id,'invalid_seo_data');}$p=$data->to_persistence_data();$analysis=wp_json_encode(array('checks'=>json_decode($p['analysis_checks'],true)?:array(),'warnings'=>json_decode($p['warnings'],true)?:array(),'title_uses_number'=>$p['title_uses_number'],'title_uses_power_word'=>$p['title_uses_power_word'],'title_uses_sentiment_word'=>$p['title_uses_sentiment_word'],'keyword_density'=>$p['keyword_density'],'keyword_occurrences'=>$p['keyword_occurrences'],'word_count'=>$p['word_count']));$now=self::now();$changed=$wpdb->update($this->table(),array('seo_status'=>$status,'seo_title'=>$p['seo_title'],'seo_meta_description'=>$p['meta_description'],'seo_focus_keyword'=>$p['focus_keyword'],'seo_slug'=>$p['slug'],'seo_categories'=>$p['categories'],'seo_tags'=>$p['tags'],'seo_internal_links'=>$p['internal_links'],'seo_external_links'=>$p['external_links'],'seo_analysis'=>$analysis,'seo_target_plugin'=>$target,'seo_generated_at'=>$status==='generated'?$now:null,'seo_updated_at'=>$now,'seo_last_error_code'=>null),array('id'=>$id),null,array('%d'));return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'seo_data_updated');}
	public function transition_seo_status($article_id,string $expected,string $result,string $error=''):array{global $wpdb;$id=absint($article_id);if(!$id||!AICS_SEO_State::can_transition($expected,$result)){return self::simple(false,$id,'seo_transition_rejected');}$error=substr(sanitize_key($error),0,100);$changed=$wpdb->query($wpdb->prepare("UPDATE {$this->table()} SET seo_status=%s,seo_last_error_code=".(''===$error?'NULL':'%s').",seo_updated_at=%s,updated_at=%s WHERE id=%d AND seo_status=%s",array_values(array_filter(array($result,$error,self::now(),self::now(),$id,$expected),static fn($v)=>''!==$v))));return 1===$changed?self::simple(true,$id,'seo_status_updated'):self::simple(false,$id,'seo_state_changed');}
	public function set_seo_failure($article_id,string $expected,string $code):array{return $this->transition_seo_status($article_id,$expected,'failed',$code);}
	public function persist_seo_result($article_id,AICS_SEO_Data $data,array $analysis,string $target,string $status,int $user):array{global $wpdb;$id=absint($article_id);$article=$this->get_by_id($id);if(!$article||'manual'!==$article['source_type']||$article['created_by']!==$user||!AICS_SEO_State::is_valid($status)||!in_array($target,array('native','yoast','rank_math','aioseo'),true)){return self::simple(false,$id,'invalid_seo_data');}$p=$data->to_persistence_data();$encoded=wp_json_encode($analysis);if(!is_string($encoded)){return self::simple(false,$id,'seo_analysis_failed');}$now=self::now();$row=array('seo_status'=>$status,'seo_title'=>$p['seo_title'],'seo_meta_description'=>$p['meta_description'],'seo_focus_keyword'=>$p['focus_keyword'],'seo_slug'=>$p['slug'],'excerpt'=>$p['excerpt'],'seo_categories'=>$p['categories'],'seo_tags'=>$p['tags'],'seo_internal_links'=>$p['internal_links'],'seo_external_links'=>$p['external_links'],'seo_analysis'=>$encoded,'seo_target_plugin'=>$target,'seo_generated_at'=>$article['seo_generated_at']??$now,'seo_updated_at'=>$now,'seo_last_error_code'=>null,'updated_by'=>$user,'updated_at'=>$now);if(''!==$p['image_alt_text']){$row['featured_image_alt_text']=$p['image_alt_text'];}$changed=$wpdb->update($this->table(),$row,array('id'=>$id),$this->formats($row),array('%d'));return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'seo_data_updated');}

	public function mark_seo_applied($article_id,string $content,array $internal,array $external,array $analysis,string $alt,int $user):array{global $wpdb;$id=absint($article_id);$a=$this->get_by_id($id);if(!$a||'manual'!==$a['source_type']||$a['created_by']!==$user){return self::simple(false,$id,'seo_article_source_invalid');}$content=wp_kses_post($content);$ij=wp_json_encode($internal);$ej=wp_json_encode($external);$aj=wp_json_encode($analysis);if(!is_string($ij)||!is_string($ej)||!is_string($aj)){return self::simple(false,$id,'seo_application_failed');}$text=trim(preg_replace('/\s+/u',' ',html_entity_decode(wp_strip_all_tags($content),ENT_QUOTES|ENT_HTML5,get_bloginfo('charset')?:'UTF-8'))??'');$words=''===$text?0:count(preg_split('/\s+/u',$text,-1,PREG_SPLIT_NO_EMPTY)?:array());$hash=hash('sha256',self::normalize_hash_part($a['title']).'|'.self::normalize_hash_part($a['excerpt']).'|'.$content);$now=self::now();$changed=$wpdb->update($this->table(),array('content'=>$content,'content_hash'=>$hash,'word_count'=>$words,'seo_internal_links'=>$ij,'seo_external_links'=>$ej,'seo_analysis'=>$aj,'featured_image_alt_text'=>substr(sanitize_text_field($alt),0,250),'seo_status'=>'applied','seo_applied_at'=>$now,'seo_updated_at'=>$now,'seo_last_error_code'=>null,'updated_by'=>$user,'updated_at'=>$now),array('id'=>$id),null,array('%d'));return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'seo_applied');}

	public function increment_seo_attempt($article_id,string $type):array{global $wpdb;$id=absint($article_id);$column='generation'===$type?'seo_generation_attempts':('application'===$type?'seo_application_attempts':'');if(!$id||!$column){return self::simple(false,$id,'invalid_seo_attempt');}$changed=$wpdb->query($wpdb->prepare("UPDATE {$this->table()} SET {$column}={$column}+1,seo_updated_at=%s,updated_at=%s WHERE id=%d",self::now(),self::now(),$id));return 1===$changed?self::simple(true,$id,'seo_attempt_incremented'):self::simple(false,$id,'database_update_failed');}
	public function persist_automation_seo($article_id,AICS_SEO_Data $data,array $analysis,string $target,string $provider,string $model):array{global $wpdb;$id=absint($article_id);$a=$this->get_by_id($id);if(!$a||'automation'!==$a['source_type']){return self::simple(false,$id,'invalid_seo_data');}$p=$data->to_persistence_data();$now=self::now();$row=array('seo_status'=>empty($analysis['blocking_issues'])?'generated':'review_required','seo_title'=>$p['seo_title'],'seo_meta_description'=>$p['meta_description'],'seo_focus_keyword'=>$p['focus_keyword'],'seo_slug'=>$p['slug'],'excerpt'=>$p['excerpt'],'seo_categories'=>$p['categories'],'seo_tags'=>$p['tags'],'seo_analysis'=>wp_json_encode($analysis),'seo_target_plugin'=>$target,'seo_provider'=>substr(sanitize_key($provider),0,64),'seo_model'=>substr(sanitize_text_field($model),0,100),'seo_generated_at'=>$now,'seo_analyzed_at'=>$now,'seo_updated_at'=>$now,'seo_last_error_code'=>null,'updated_at'=>$now);if(''!==$p['image_alt_text']){$row['featured_image_alt_text']=$p['image_alt_text'];}$changed=$wpdb->update($this->table(),$row,array('id'=>$id),$this->formats($row),array('%d'));return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'seo_data_updated');}
	public function persist_seo_application($article_id,array $report,array $analysis,string $adapter,string $version,string $content,array $internal,array $external,string $alt,int $user=0):array{global $wpdb;$id=absint($article_id);$a=$this->get_by_id($id);if(!$a){return self::simple(false,$id,'article_not_found');}$safe=array_intersect_key($report,array_flip(array('adapter_key','adapter_label','post_id','applied_fields','skipped_fields','verified_fields','warnings','error_code','native_applied','plugin_applied','verification_passed','refresh_requested','applied_at')));$now=self::now();$content=wp_kses_post($content);$row=array('content'=>$content,'content_hash'=>hash('sha256',self::normalize_hash_part($a['title']).'|'.self::normalize_hash_part($a['excerpt']).'|'.$content),'seo_internal_links'=>wp_json_encode($internal),'seo_external_links'=>wp_json_encode($external),'seo_analysis'=>wp_json_encode($analysis),'featured_image_alt_text'=>substr(sanitize_text_field($alt),0,250),'seo_status'=>'applied','seo_adapter'=>sanitize_key($adapter),'seo_adapter_version'=>substr(sanitize_text_field($version),0,64),'seo_application_report'=>wp_json_encode($safe),'seo_analyzed_at'=>$now,'seo_applied_at'=>$now,'seo_updated_at'=>$now,'seo_last_error_code'=>null,'updated_by'=>absint($user),'updated_at'=>$now);$changed=$wpdb->update($this->table(),$row,array('id'=>$id),$this->formats($row),array('%d'));return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'seo_applied');}

	/** Synchronizes bounded alt text without changing either article lifecycle. */
	public function update_featured_image_alt_text( $article_id, $attachment_id, $alt_text, $updated_by ): array {
		global $wpdb;$id=absint($article_id);$attachment=absint($attachment_id);$user=self::nonnegative($updated_by);$alt=self::nullable_text($alt_text,250);if(!$id||!$attachment||null===$user||0===$user||null===$alt||''===$alt){return self::simple(false,$id,'invalid_featured_image_metadata');}
		$changed=$wpdb->query($wpdb->prepare("UPDATE {$this->table()} SET featured_image_alt_text=%s,updated_by=%d,updated_at=%s WHERE id=%d AND source_type='manual' AND created_by=%d AND featured_image_attachment_id=%d AND featured_image_status IN ('uploaded','attached','retrying')",$alt,$user,self::now(),$id,$user,$attachment));
		if(false===$changed){return self::simple(false,$id,'database_update_failed');}$fresh=$this->get_by_id($id);return $fresh&&$fresh['featured_image_attachment_id']===$attachment&&$fresh['featured_image_alt_text']===$alt?self::simple(true,$id,'featured_image_alt_text_updated'):self::simple(false,$id,'featured_image_state_changed');
	}

	public function table_exists(): bool {
		global $wpdb;
		$table = $this->table();
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	private function get_one( string $field, $value, string $format ): ?array {
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE {$field}={$format} LIMIT 1", $value );
		return $this->normalize_row( $wpdb->get_row( $sql, ARRAY_A ) );
	}

	private function filters( array $args ): array {
		$where = array( '1=1' ); $values = array();
		foreach ( array( 'idea_id', 'profile_id', 'run_id', 'wordpress_post_id' ) as $field ) {
			if ( array_key_exists( $field, $args ) ) {
				$value = self::nonnegative( $args[ $field ] );
				if ( null === $value || ( in_array( $field, array( 'idea_id', 'wordpress_post_id' ), true ) && 0 === $value ) ) { $where[] = '1=0'; } else { $where[] = "{$field}=%d"; $values[] = $value; }
			}
		}
		foreach ( array( 'source_type' => self::SOURCES, 'status' => self::STATUSES ) as $field => $allowed ) {
			if ( isset( $args[ $field ] ) ) {
				$value = is_scalar( $args[ $field ] ) ? sanitize_key( (string) $args[ $field ] ) : '';
				if ( in_array( $value, $allowed, true ) ) { $where[] = "{$field}=%s"; $values[] = $value; } else { $where[] = '1=0'; }
			}
		}
		if ( isset( $args['statuses'] ) ) {
			$statuses = self::statuses( is_array( $args['statuses'] ) ? $args['statuses'] : array() );
			if ( empty( $statuses ) ) { $where[] = '1=0'; } else { $where[] = 'status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')'; array_push( $values, ...$statuses ); }
		}
		foreach ( array( 'planned_before' => array( 'planned_publish_at', '<=' ), 'planned_after' => array( 'planned_publish_at', '>=' ), 'created_before' => array( 'created_at', '<=' ), 'created_after' => array( 'created_at', '>=' ) ) as $key => $definition ) {
			if ( isset( $args[ $key ] ) ) {
				$date = self::datetime( $args[ $key ] );
				if ( null === $date ) { $where[] = '1=0'; } else { $where[] = $definition[0] . $definition[1] . '%s'; $values[] = $date; }
			}
		}
		return array( $where, $values );
	}

	/** Atomically persists final SEO application state, report, analysis, and controlled error. */
	public function persist_seo_application_error($article_id,string $code,int $user=0):array{global $wpdb;$id=absint($article_id);$code=substr(sanitize_key($code),0,100);if(!$id||''===$code||!$this->get_by_id($id)){return self::simple(false,$id,'invalid_seo_application_error');}$now=self::now();$changed=$wpdb->update($this->table(),array('seo_status'=>'needs_attention','seo_applied_at'=>null,'seo_last_error_code'=>$code,'seo_updated_at'=>$now,'updated_by'=>absint($user),'updated_at'=>$now),array('id'=>$id),array('%s','%s','%s','%s','%d','%s'),array('%d'));return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'seo_error_recorded');}

	/** Atomically persists final SEO application state, report, analysis, and controlled error. */
	public function persist_seo_application_outcome($article_id,array $report,array $analysis,string $adapter,string $version,string $content,array $internal,array $external,string $alt,string $status,string $error='',int $user=0):array{
		global $wpdb;$id=absint($article_id);$a=$this->get_by_id($id);if(!$a||!in_array($status,array('applied','review_required','needs_attention','failed'),true)){return self::simple(false,$id,'invalid_seo_application_outcome');}$error=substr(sanitize_key($error),0,100);if('applied'===$status&&''!==$error){return self::simple(false,$id,'invalid_seo_application_outcome');}if('applied'!==$status&&''===$error){return self::simple(false,$id,'invalid_seo_application_outcome');}
		$safe=array_intersect_key($report,array_flip(array('adapter_key','adapter_label','post_id','applied_fields','skipped_fields','verified_fields','warnings','error_code','native_applied','plugin_applied','verification_passed','refresh_requested','applied_at','quality_gate')));$encoded_report=wp_json_encode($safe);$encoded_analysis=wp_json_encode($analysis);$encoded_internal=wp_json_encode($internal);$encoded_external=wp_json_encode($external);if(!is_string($encoded_report)||!is_string($encoded_analysis)||!is_string($encoded_internal)||!is_string($encoded_external)){return self::simple(false,$id,'seo_application_failed');}
		$now=self::now();$content=wp_kses_post($content);$row=array('content'=>$content,'content_hash'=>hash('sha256',self::normalize_hash_part($a['title']).'|'.self::normalize_hash_part($a['excerpt']).'|'.$content),'seo_internal_links'=>$encoded_internal,'seo_external_links'=>$encoded_external,'seo_analysis'=>$encoded_analysis,'featured_image_alt_text'=>substr(sanitize_text_field($alt),0,250),'seo_status'=>$status,'seo_adapter'=>sanitize_key($adapter),'seo_adapter_version'=>substr(sanitize_text_field($version),0,64),'seo_application_report'=>$encoded_report,'seo_analyzed_at'=>$now,'seo_applied_at'=>'applied'===$status?$now:null,'seo_updated_at'=>$now,'seo_last_error_code'=>'applied'===$status?null:$error,'updated_by'=>absint($user),'updated_at'=>$now);$changed=$wpdb->update($this->table(),$row,array('id'=>$id),$this->formats($row),array('%d'));return false===$changed?self::simple(false,$id,'database_update_failed'):self::simple(true,$id,'applied'===$status?'seo_applied':'seo_application_requires_attention');
	}

	private function normalize_row( $row ): ?array {
		if ( ! is_array( $row ) ) { return null; }
		foreach ( array( 'id', 'word_count', 'generation_attempts', 'approved_by', 'rejected_by', 'created_by', 'updated_by', 'featured_image_required', 'featured_image_attempts' ) as $field ) { $row[ $field ] = absint( $row[ $field ] ?? 0 ); }
		foreach(array('idea_id','profile_id','run_id') as $field){$row[$field]=null===$row[$field]?null:absint($row[$field]);}
		$row['wordpress_post_id'] = absint( $row['wordpress_post_id'] ?? 0 );
		$row['featured_image_attachment_id'] = absint( $row['featured_image_attachment_id'] ?? 0 );
		$row['featured_image_status'] = AICS_Featured_Image_State::normalize( $row['featured_image_status'] ?? '' );
		$row['seo_status'] = AICS_SEO_State::is_valid($row['seo_status']??'')?$row['seo_status']:AICS_SEO_State::NOT_REQUESTED;
		foreach(array('seo_categories','seo_tags','seo_internal_links','seo_external_links','seo_analysis','seo_application_report') as $field){$decoded=json_decode(is_string($row[$field]??null)?$row[$field]:'',true);$row[$field]=is_array($decoded)?$decoded:array();}
		$row['source_type'] = in_array( $row['source_type'] ?? '', self::SOURCES, true ) ? $row['source_type'] : 'automation';
		$row['status'] = in_array( $row['status'] ?? '', self::STATUSES, true ) ? $row['status'] : 'needs_attention';
		foreach ( array( 'planned_publish_at', 'last_generation_at', 'generated_at', 'approved_at', 'rejected_at', 'post_created_at', 'scheduled_at', 'published_at', 'featured_image_generated_at', 'featured_image_uploaded_at', 'featured_image_attached_at', 'seo_generated_at','seo_analyzed_at','seo_approved_at','seo_applied_at','seo_updated_at','created_at', 'updated_at' ) as $field ) { $row[ $field ] = self::datetime( $row[ $field ] ?? null ); }
		return $row;
	}

	private function table(): string { global $wpdb; return $wpdb->prefix . 'aics_articles'; }
	private function formats( array $row ): array { $ints = array( 'idea_id', 'profile_id', 'run_id', 'word_count', 'generation_attempts', 'approved_by', 'rejected_by', 'created_by', 'updated_by', 'featured_image_required', 'featured_image_attachment_id', 'featured_image_attempts' ); return array_map( static fn( $field ) => in_array( $field, $ints, true ) ? '%d' : '%s', array_keys( $row ) ); }
	private static function result( bool $success, int $id, string $uuid, string $code ): array { return array( 'success' => $success, 'article_id' => $id, 'uuid' => $uuid, 'code' => $code ); }
	private static function simple( bool $success, int $id, string $code ): array { return array( 'success' => $success, 'article_id' => $id, 'code' => $code ); }
	private static function now(): string { return current_time( 'mysql', true ); }
	private static function nonnegative( $value ): ?int { return is_scalar( $value ) && preg_match( '/^\d+$/', (string) $value ) ? (int) $value : null; }
	private static function statuses( array $values ): ?array { $statuses = array(); foreach ( $values as $value ) { if ( ! is_scalar( $value ) ) { return null; } $status = sanitize_key( (string) $value ); if ( ! in_array( $status, self::STATUSES, true ) ) { return null; } $statuses[] = $status; } return array_values( array_unique( $statuses ) ); }
	private static function error_code( $value ): string { return self::cut( sanitize_key( is_scalar( $value ) ? (string) $value : '' ), 100 ); }
	private static function normalize_hash_part( string $value ): string { return trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' ); }
	private static function length( string $value ): int { return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value ); }
	private static function cut( string $value, int $maximum ): string { return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $maximum, 'UTF-8' ) : substr( $value, 0, $maximum ); }
	private static function nullable_text($value,int $maximum){if(null===$value||''===$value){return null;}if(!is_scalar($value)){return false;}return self::cut(sanitize_textarea_field((string)$value),$maximum);}
	private static function datetime( $value ): ?string { if ( null === $value || '' === $value ) { return null; } if ( ! is_scalar( $value ) ) { return null; } $value = (string) $value; $date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) ); $errors = DateTimeImmutable::getLastErrors(); return false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $date->format( 'Y-m-d H:i:s' ) === $value ? $value : null; }
}
