<?php
/**
 * Server-authoritative state for the Manual Studio wizard.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Manual_Wizard_State_Service {
	public const STEPS = array( 'context', 'ideas', 'article', 'image', 'seo', 'complete' );
	private const TTL = DAY_IN_SECONDS;
	private int $user_id;

	public function __construct( int $user_id ) { $this->user_id = absint( $user_id ); }
	public function key( string $name ): string { return 'aics_' . $name . '_' . $this->user_id; }
	public function get( string $name, $default = null ) { $value = get_transient( $this->key( $name ) ); return false === $value ? $default : $value; }
	public function set( string $name, $value, int $ttl = self::TTL ): bool { return set_transient( $this->key( $name ), $value, $ttl ); }
	public function delete( string $name ): bool { return delete_transient( $this->key( $name ) ); }

	public function context(): array {
		$setup = class_exists('AICS_Setup_Wizard_State_Service') ? AICS_Setup_Wizard_State_Service::defaults() : array();
		$defaults = array( 'business_context'=>'', 'topic_keyword'=>'', 'tone'=>$setup['tone']??'professional', 'article_length'=>$setup['article_length']??'medium', 'content_format'=>$setup['content_format']??'plain', 'idea_instructions'=>'', 'article_instructions'=>'', 'featured_image_instructions'=>'', 'seo_settings'=>AICS_SEO_Configuration::defaults( true ) );
		$stored = $this->get( 'content_inputs', array() );
		return is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;
	}
	public function ideas(): array { $state=$this->get('content_ideas',array()); return is_array($state)&&is_array($state['ideas']??null)?$state:array(); }
	public function selected_idea(): array { $idea=$this->get('selected_idea',array()); return is_array($idea)?$idea:array(); }
	public function draft(): array {
		$draft=$this->get('article_draft',array());
		$id=absint($draft['article_id']??get_user_meta($this->user_id,'aics_manual_wizard_article_id',true));
		if($id){$loaded=(new AICS_Manual_Article_Persistence_Service())->load($id,$this->user_id);if(!empty($loaded['success'])){$a=$loaded['article'];$draft=array_merge($draft,array('article_id'=>$a['id'],'article_uuid'=>$a['article_uuid'],'title'=>$a['title'],'excerpt'=>$a['excerpt'],'content'=>$a['content'],'created_post_id'=>$a['wordpress_post_id'],'word_count'=>$a['word_count']));$this->set('article_draft',$draft);}}
		return is_array($draft)?$draft:array();
	}
	public function article(): ?array { $draft=$this->draft();$id=absint($draft['article_id']??0);if(!$id){return null;}$loaded=(new AICS_Manual_Article_Persistence_Service())->load($id,$this->user_id);return !empty($loaded['success'])?$loaded['article']:null; }
	public function remember_article( int $id ): void { update_user_meta($this->user_id,'aics_manual_wizard_article_id',absint($id)); }

	public function allowed_max(): int {
		$max=0;if(''!==trim((string)$this->context()['business_context'])&&$this->ideas()){$max=1;}if($this->selected_idea()){$max=2;}$article=$this->article();if($article){$max=2;if(absint($article['wordpress_post_id'])){$max=3;$optional=empty($article['featured_image_required']);if('attached'===$article['featured_image_status']||$optional){$max=4;}if('applied'===$article['seo_status']){$max=5;}}}return $max;
	}
	public function current_step(): string { $stored=sanitize_key((string)$this->get('manual_wizard_step',''));$index=array_search($stored,self::STEPS,true);$max=$this->allowed_max();return false!==$index&&$index<=$max?self::STEPS[$index]:self::STEPS[$max]; }
	public function move_to(string $step):bool{$index=array_search($step,self::STEPS,true);if(false===$index||$index>$this->allowed_max()){return false;}$this->set('manual_wizard_step',$step);return true;}
	public function progress(): array {$active=$this->current_step();$active_index=array_search($active,self::STEPS,true);$out=array();foreach(self::STEPS as $i=>$step){$out[$step]=array('status'=>$i<$active_index?'completed':($i===$active_index?'active':'pending'),'accessible'=>$i<=$this->allowed_max());}return $out;}
	public function reset():void{foreach(array('content_inputs','content_input_error','content_ideas','selected_idea','article_draft','manual_wizard_step','manual_wizard_notice') as $name){$this->delete($name);}delete_user_meta($this->user_id,'aics_manual_wizard_article_id');}
	public function acquire(string $operation):bool{$key='aics_manual_wizard_lock_'.$this->user_id.'_'.$operation;$now=time();$lock=(int)get_option($key,0);if($lock&&$lock>$now-300){return false;}if($lock){delete_option($key);}return add_option($key,$now,'','no');}
	public function release(string $operation):void{delete_option('aics_manual_wizard_lock_'.$this->user_id.'_'.$operation);}
}
