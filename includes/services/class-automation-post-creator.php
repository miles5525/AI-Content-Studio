<?php
/** Automated persistent-article to WordPress-draft delivery. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Automation_Post_Creator {
	private AICS_Article_Repository $articles;
	private AICS_Content_Idea_Repository $ideas;
	private AICS_Post_Generator $generator;

	public function __construct( ?AICS_Article_Repository $articles = null, ?AICS_Content_Idea_Repository $ideas = null, ?AICS_Post_Generator $generator = null ) {
		$this->articles = $articles ?? new AICS_Article_Repository();
		$this->ideas = $ideas ?? new AICS_Content_Idea_Repository();
		$this->generator = $generator ?? new AICS_Post_Generator();
	}

	public function create_post_for_article( $article_id, array $profile = array() ): array {
		$id = absint( $article_id ); $article = $this->articles->get_by_id( $id );
		if ( ! $article ) { return $this->result( false, 'article_not_found', $id, 0, false, false ); }
		if ( absint( $profile['id'] ?? 0 ) !== $article['profile_id'] ) { return $this->result( false, 'automation_profile_missing', $id, 0, false, false ); }
		if ( 'automation' !== $article['source_type'] || ! in_array( $article['status'], array( 'approved','draft_created','scheduled','published' ), true ) ) { return $this->result( false, 'article_not_ready', $id, 0, false, false ); }
		$idea = $this->ideas->get_by_id( $article['idea_id'] );
		if ( ! $idea || $idea['run_id'] !== $article['run_id'] || $idea['profile_id'] !== $article['profile_id'] ) { return $this->result( false, 'related_idea_not_found', $id, 0, false, false ); }
		$existing = $this->associated_post( $article );
		if ( $existing > 0 ) {
			if ( in_array( $article['status'], array( 'draft_created','scheduled','published' ), true ) ) { return $this->result( true, 'wordpress_post_already_exists', $id, $existing, false, false ); }
			return $this->associate( $article, $existing, 'wordpress_post_already_exists', false );
		}
		$found = get_posts( array( 'post_type'=>'post', 'post_status'=>'any', 'fields'=>'ids', 'posts_per_page'=>1, 'no_found_rows'=>true, 'meta_key'=>'_aics_article_id', 'meta_value'=>(string)$id ) );
		if ( $found ) {
			$post_id = absint( $found[0] );
			if ( $this->post_belongs_to_article( $post_id, $article ) ) { return $this->associate( $article, $post_id, 'duplicate_post_recovered', false ); }
		}
		if ( 'approved' !== $article['status'] ) { return $this->result( false, 'article_not_ready', $id, 0, false, false ); }
		if ( '' === trim( $article['title'] ) || '' === trim( $article['excerpt'] ) || '' === trim( wp_strip_all_tags( $article['content'] ) ) || ! preg_match( '/^[a-f0-9]{64}$/', $article['content_hash'] ) || null === $article['generated_at'] ) { return $this->result( false, 'invalid_article_content', $id, 0, false, false ); }
		$author = $this->author_id( $profile );
		if ( 0 === $author ) { return $this->result( false, 'invalid_post_author', $id, 0, false, false ); }
		$settings = is_array( $profile['publishing_settings'] ?? null ) ? $profile['publishing_settings'] : array();
		$category = absint( $settings['category_id'] ?? 0 );
		$term = $category ? get_term( $category, 'category' ) : null;
		if ( ! $term || is_wp_error( $term ) ) { $category = absint( get_option( 'default_category', 0 ) ); $term = $category ? get_term( $category, 'category' ) : null; if ( ! $term || is_wp_error( $term ) ) { $category = 0; } }
		$metadata = array( '_aics_generated_post'=>1, '_aics_source'=>sanitize_text_field('automation'), '_aics_article_id'=>absint($id), '_aics_article_uuid'=>sanitize_text_field( $article['article_uuid'] ), '_aics_idea_id'=>absint($article['idea_id']), '_aics_run_id'=>absint($article['run_id']), '_aics_profile_id'=>absint($article['profile_id']) );
		$started = microtime( true );
		$created = $this->generator->create_draft_from_article( $article, array( 'author_id'=>$author, 'category_id'=>$category ), $metadata );
		if ( empty( $created['success'] ) ) { $this->log( false, 'wordpress_post_creation_failed', 0, $started ); return $this->result( false, 'wordpress_post_creation_failed', $id, 0, false, true ); }
		$post_id = absint( $created['post_id'] ?? 0 );
		if ( ! $this->post_belongs_to_article( $post_id, $article ) ) { wp_trash_post( $post_id ); $this->log( false, 'article_post_association_failed', 0, $started ); return $this->result( false, 'article_post_association_failed', $id, 0, false, true ); }
		$associated = $this->articles->associate_wordpress_post( $id, $post_id, 'draft_created', 0 );
		if ( ! empty( $associated['success'] ) ) { $this->log( true, '', $post_id, $started ); return $this->result( true, 'wordpress_draft_created', $id, $post_id, true, false ); }
		$fresh = $this->articles->get_by_id( $id ); $winner = $fresh ? $this->associated_post( $fresh ) : 0;
		if ( $winner > 0 && $winner !== $post_id ) { wp_trash_post( $post_id ); $this->log( true, '', $winner, $started ); return $this->result( true, 'duplicate_post_recovered', $id, $winner, false, false ); }
		$this->log( false, 'article_post_association_failed', 0, $started );
		return $this->result( false, 'article_post_association_failed', $id, 0, false, true );
	}

	private function author_id( array $profile ): int {
		$settings = is_array( $profile['publishing_settings'] ?? null ) ? $profile['publishing_settings'] : array();
		foreach ( array( absint( $settings['author_id'] ?? 0 ), absint( $profile['created_by'] ?? 0 ) ) as $id ) { $user = $id ? get_user_by( 'id', $id ) : false; if ( $user && user_can( $user, 'edit_posts' ) ) { return $id; } }
		return 0;
	}

	private function associated_post( array $article ): int { $post_id=absint($article['wordpress_post_id']??0); return $post_id && $this->post_belongs_to_article($post_id,$article)?$post_id:0; }
	private function post_belongs_to_article( int $post_id, array $article ): bool { $post=get_post($post_id);return $post instanceof WP_Post&&'post'===$post->post_type&&'trash'!==$post->post_status&&absint(get_post_meta($post_id,'_aics_article_id',true))===$article['id']&&sanitize_text_field((string)get_post_meta($post_id,'_aics_article_uuid',true))===$article['article_uuid']; }
	private function associate( array $article, int $post_id, string $code, bool $created ): array { $saved=$this->articles->associate_wordpress_post($article['id'],$post_id,'draft_created',0);$retryable='database_update_failed'===($saved['code']??'');return empty($saved['success'])?$this->result(false,'article_post_association_failed',$article['id'],0,false,$retryable):$this->result(true,$code,$article['id'],$post_id,$created,false); }
	private function log( bool $success, string $code, int $post_id, float $started ): void { AICS_Usage_Logger::log( array( 'user_id'=>0, 'event_type'=>'post_creation', 'operation'=>'create_wordpress_draft', 'status'=>$success?'success':'failed', 'error_code'=>$code, 'object_id'=>$post_id, 'duration_ms'=>AICS_Usage_Logger::duration_ms($started), 'metadata'=>array('post_status'=>'draft','source'=>'automation') ) ); }
	private function result( bool $success, string $code, int $article_id, int $post_id, bool $created, bool $retryable ): array { return array( 'success'=>$success, 'code'=>$code, 'article_id'=>$article_id, 'post_id'=>$post_id, 'created'=>$created, 'retryable'=>$retryable ); }
}
