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

	private function normalize_row( $row ): ?array {
		if ( ! is_array( $row ) ) { return null; }
		foreach ( array( 'id', 'idea_id', 'profile_id', 'run_id', 'word_count', 'generation_attempts', 'approved_by', 'rejected_by', 'created_by', 'updated_by' ) as $field ) { $row[ $field ] = absint( $row[ $field ] ?? 0 ); }
		$row['wordpress_post_id'] = absint( $row['wordpress_post_id'] ?? 0 );
		$row['source_type'] = in_array( $row['source_type'] ?? '', self::SOURCES, true ) ? $row['source_type'] : 'automation';
		$row['status'] = in_array( $row['status'] ?? '', self::STATUSES, true ) ? $row['status'] : 'needs_attention';
		foreach ( array( 'planned_publish_at', 'last_generation_at', 'generated_at', 'approved_at', 'rejected_at', 'post_created_at', 'scheduled_at', 'published_at', 'created_at', 'updated_at' ) as $field ) { $row[ $field ] = self::datetime( $row[ $field ] ?? null ); }
		return $row;
	}

	private function table(): string { global $wpdb; return $wpdb->prefix . 'aics_articles'; }
	private function formats( array $row ): array { $ints = array( 'idea_id', 'profile_id', 'run_id', 'word_count', 'generation_attempts', 'approved_by', 'rejected_by', 'created_by', 'updated_by' ); return array_map( static fn( $field ) => in_array( $field, $ints, true ) ? '%d' : '%s', array_keys( $row ) ); }
	private static function result( bool $success, int $id, string $uuid, string $code ): array { return array( 'success' => $success, 'article_id' => $id, 'uuid' => $uuid, 'code' => $code ); }
	private static function simple( bool $success, int $id, string $code ): array { return array( 'success' => $success, 'article_id' => $id, 'code' => $code ); }
	private static function now(): string { return current_time( 'mysql', true ); }
	private static function nonnegative( $value ): ?int { return is_scalar( $value ) && preg_match( '/^\d+$/', (string) $value ) ? (int) $value : null; }
	private static function statuses( array $values ): ?array { $statuses = array(); foreach ( $values as $value ) { if ( ! is_scalar( $value ) ) { return null; } $status = sanitize_key( (string) $value ); if ( ! in_array( $status, self::STATUSES, true ) ) { return null; } $statuses[] = $status; } return array_values( array_unique( $statuses ) ); }
	private static function error_code( $value ): string { return self::cut( sanitize_key( is_scalar( $value ) ? (string) $value : '' ), 100 ); }
	private static function normalize_hash_part( string $value ): string { return trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' ); }
	private static function length( string $value ): int { return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value ); }
	private static function cut( string $value, int $maximum ): string { return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $maximum, 'UTF-8' ) : substr( $value, 0, $maximum ); }
	private static function datetime( $value ): ?string { if ( null === $value || '' === $value ) { return null; } if ( ! is_scalar( $value ) ) { return null; } $value = (string) $value; $date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) ); $errors = DateTimeImmutable::getLastErrors(); return false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $date->format( 'Y-m-d H:i:s' ) === $value ? $value : null; }
}
