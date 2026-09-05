<?php
/** Structured article safety validation and sanitization. @package AIContentStudio */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AICS_Article_Content_Validator {
	private const TITLE_MAX = 250;
	private const EXCERPT_MAX = 500;
	private const CONTENT_MAX = 100000;
	private const MINIMUM_WORDS = array( 'short'=>200, 'medium'=>350, 'long'=>600 );
	private const ALLOWED_HTML = array(
		'p'=>array(), 'h2'=>array(), 'h3'=>array(), 'h4'=>array(), 'ul'=>array(), 'ol'=>array(), 'li'=>array(),
		'strong'=>array(), 'em'=>array(), 'blockquote'=>array(), 'a'=>array( 'href'=>true, 'title'=>true ),
		'table'=>array(), 'caption'=>array(), 'thead'=>array(), 'tbody'=>array(), 'tr'=>array(), 'th'=>array(), 'td'=>array(),
	);

	public function validate( array $article, string $requested_length = '', bool $allow_tables = true ): array {
		foreach ( array( 'title', 'excerpt', 'content' ) as $field ) {
			if ( ! array_key_exists( $field, $article ) ) { return $this->failure( 'missing_article_' . $field, 'structure' ); }
			if ( ! is_string( $article[ $field ] ) ) { return $this->failure( 'invalid_article_field_type', 'structure' ); }
		}
		$title=trim(sanitize_text_field($article['title']));$excerpt=trim(sanitize_textarea_field($article['excerpt']));$raw=trim($article['content']);
		if(''===$title){return $this->failure('missing_article_title','structure');}if($this->length($title)>self::TITLE_MAX){return $this->failure('article_title_too_long','structure');}
		if(''===$excerpt){return $this->failure('missing_article_excerpt','structure');}if($this->length($excerpt)>self::EXCERPT_MAX){return $this->failure('article_excerpt_too_long','structure');}
		if(''===$raw){return $this->failure('missing_article_content','structure');}if($this->length($raw)>self::CONTENT_MAX){return $this->failure('article_content_too_long','structure');}
		$danger=$this->dangerous_code($raw);if(null!==$danger){return $this->failure($danger,$this->category($danger));}
		if ( ! $allow_tables && preg_match( '/<\s*\/?(?:table|caption|thead|tbody|tr|th|td)\b/i', $raw ) ) { return $this->failure( 'article_contains_disallowed_table', 'structure' ); }
		if(preg_match('/^\s*[{\[]\s*"(?:article|title|content|excerpt)"\s*:/i',$raw)){return $this->failure('article_contains_raw_json_wrapper','structure');}
		$wrappers=array();$normalized=$raw;
		if(preg_match('/^\s*```(?:html)?\s*/i',$normalized)||preg_match('/\s*```\s*$/',$normalized)){$normalized=preg_replace('/^\s*```(?:html)?\s*/i','',$normalized)??$normalized;$normalized=preg_replace('/\s*```\s*$/','',$normalized)??$normalized;$wrappers[]='markdown_wrapper_removed';}
		$document=preg_match('/<!doctype\s+html|<\/?(?:html|head|body)\b/i',$normalized);if($document){$normalized=preg_replace('/<!doctype\s+html[^>]*>/i','',$normalized)??$normalized;$normalized=preg_replace('/<\/?(?:html|head|body)\b[^>]*>/i','',$normalized)??$normalized;$wrappers[]='html_document_wrapper_removed';}
		$markup_normalized=$this->needs_markup_normalization($normalized);if($markup_normalized){$normalized=$this->normalize_markup($normalized);}
		$before=$this->text($normalized);$sanitized=trim(wp_kses($normalized,self::ALLOWED_HTML));$after=$this->text($sanitized);$changed=$sanitized!==trim($normalized);
		if(''===$after){return $this->failure('article_content_empty_after_sanitization','sanitization',array('sanitization_changed_content'=>$changed,'wrappers_removed'=>!empty($wrappers)));}
		if($this->has_residual_markdown($sanitized)){return $this->failure('article_markup_normalization_failed','structure',array('markup_normalized'=>$markup_normalized));}
		$body_without_headings=preg_replace('/<h[2-4]\b[^>]*>.*?<\/h[2-4]>/is','',$sanitized)??$sanitized;if(''===$this->text($body_without_headings)){return $this->failure('article_content_too_short','length');}
		$paragraphs=array();if(preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is',$sanitized,$matches)){foreach($matches[1] as $paragraph){$text=$this->text($paragraph);if(''!==$text){$paragraphs[]=$text;}}}if(count($paragraphs)>=3&&1===count(array_unique($paragraphs))){return $this->failure('article_contains_placeholder_text','structure');}
		$before_words=$this->words($before);$after_words=$this->words($after);if($before_words>0&&$after_words<max(1,(int)floor($before_words*0.7))){return $this->failure('unsafe_active_markup','sanitization',array('word_count_before'=>$before_words,'word_count_after'=>$after_words));}
		if($this->placeholder($after)){return $this->failure('article_contains_placeholder_text','structure');}
		$length=sanitize_key($requested_length);if(isset(self::MINIMUM_WORDS[$length])&&$after_words<self::MINIMUM_WORDS[$length]){return $this->failure('article_content_too_short','length',array('word_count_after'=>$after_words));}
		$blocks=preg_match_all('/<(?:p|h[2-4]|ul|ol|blockquote|table)\b/i',$sanitized);if($after_words>=self::MINIMUM_WORDS['short']&&$blocks<2){return $this->failure('article_content_structure_invalid','structure',array('block_count'=>$blocks));}if(in_array($length,array('medium','long'),true)&&!preg_match('/<h2\b/i',$sanitized)){return $this->failure('article_content_structure_invalid','structure',array('block_count'=>$blocks));}
		$removed=array();if($wrappers){$removed[]='wrapper_removed';}if($changed){$removed[]='unsupported_markup_removed';}
		$code=$markup_normalized?'article_markup_normalized':($wrappers?(in_array('markdown_wrapper_removed',$wrappers,true)?'markdown_wrapper_removed':'html_document_wrapper_removed'):($changed?'article_content_sanitized':'article_content_valid'));
		return array('success'=>true,'code'=>$code,'article'=>array('title'=>$title,'excerpt'=>$excerpt,'content'=>$sanitized),'retryable'=>false,'diagnostics'=>array('markup_normalized'=>$markup_normalized,'sanitization_changed_content'=>$changed,'removed_markup_categories'=>$removed,'wrappers_removed'=>!empty($wrappers),'word_count_before'=>$before_words,'word_count_after'=>$after_words,'block_count'=>$blocks));
	}

	private function needs_markup_normalization(string $content):bool{if(preg_match('/(?:^|\R)\s*(?:#{1,4}\s+|[-+]\s+\S|\d+\.\s+\S)/m',$content)||preg_match('/\*\*\S(?:.|\R)*?\S\*\*/U',$content)){return true;}return !preg_match('/<(?:p|h[2-4]|ul|ol|blockquote|table)\b/i',$content);}
	private function normalize_markup(string $content):string{if(preg_match('/<(?:p|h[2-4]|ul|ol|blockquote|table)\b/i',$content)&&!preg_match('/(?:^|\R)\s*(?:#{1,4}\s+|[-+]\s+\S|\d+[.)]\s+\S)/m',$content)){return $this->normalize_html_text($content);}$blocks=array();$content=preg_replace_callback('/<(p|h[2-4]|ul|ol|blockquote|table)\b[^>]*>.*?<\/\1>/is',function(array $match)use(&$blocks):string{$token='AICSHTMLBLOCK'.count($blocks).'TOKEN';$blocks[$token]=$this->normalize_html_text($match[0]);return $token;},$content)??$content;$lines=preg_split('/\R/',str_replace(array("\r\n","\r"),"\n",$content))?:array();$out=array();$paragraph=array();$list='';$flush_paragraph=static function()use(&$out,&$paragraph):void{if($paragraph){$out[]='<p>'.implode(' ',array_map('trim',$paragraph)).'</p>';$paragraph=array();}};$close_list=static function()use(&$out,&$list):void{if(''!==$list){$out[]='</'.$list.'>';$list='';}};foreach($lines as $line){$trim=trim($line);if(''===$trim){$flush_paragraph();$close_list();continue;}if(isset($blocks[$trim])){$flush_paragraph();$close_list();$out[]=$blocks[$trim];continue;}if(preg_match('/^\s*(#{1,4})\s+(.+?)\s*#*\s*$/',$line,$m)){$flush_paragraph();$close_list();$level=max(2,min(4,strlen($m[1])));$out[]='<h'.$level.'>'.$this->inline_markup($m[2],true).'</h'.$level.'>';continue;}if(preg_match('/^\s*[-+]\s+(.+)$/',$line,$m)){$flush_paragraph();if('ul'!==$list){$close_list();$list='ul';$out[]='<ul>';}$out[]='<li>'.$this->inline_markup($m[1],true).'</li>';continue;}if(preg_match('/^\s*\d+[.)]\s+(.+)$/',$line,$m)){$flush_paragraph();if('ol'!==$list){$close_list();$list='ol';$out[]='<ol>';}$out[]='<li>'.$this->inline_markup($m[1],true).'</li>';continue;}$close_list();if(preg_match('/^\s*(?:<!--\s*\/?wp:|<\/?(?:p|h[2-4]|ul|ol|li|strong|em|blockquote|a|table|caption|thead|tbody|tr|th|td)\b)/i',$line)){$flush_paragraph();$out[]=$this->normalize_html_text($line);continue;}$paragraph[]=$this->inline_markup($trim,true);}$flush_paragraph();$close_list();return trim(implode("\n",$out));}
	private function normalize_html_text(string $html):string{$parts=preg_split('/(<[^>]+>)/',$html,-1,PREG_SPLIT_DELIM_CAPTURE)?:array();foreach($parts as $index=>$part){if(''!==$part&&'<'!==$part[0]){$parts[$index]=$this->inline_markup($part,false);}}return implode('',$parts);}
	private function inline_markup(string $text,bool $escape):string{$value=$escape?esc_html($text):$text;$value=preg_replace_callback('/\[([^\]\r\n]+)\]\(([^\s)]+)\)/',static function(array $match):string{$url=esc_url(html_entity_decode($match[2],ENT_QUOTES|ENT_HTML5),array('http','https'));return ''===$url?$match[1]:'<a href="'.esc_attr($url).'">'.$match[1].'</a>';},$value)??$value;$value=preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s','<strong>$1</strong>',$value)??$value;return preg_replace('/(?<!\*)\*(?=\S)([^*\r\n]+?)(?<=\S)\*(?!\*)/','<em>$1</em>',$value)??$value;}
	private function has_residual_markdown(string $html):bool{return 1===preg_match('/(?:^|\R|>)\s*(?:#{1,4}\s+|[-+]\s+\S|\d+[.)]\s+\S)|\*\*\S.+?\S\*\*/s',$html);}

	private function dangerous_code(string $raw):?string{$patterns=array('unsafe_script_element'=>'/<\s*script\b/i','unsafe_style_element'=>'/<\s*style\b/i','unsafe_embedded_content'=>'/<\s*(?:iframe|object|embed)\b/i','unsafe_form_element'=>'/<\s*(?:form|input|button)\b/i','unsafe_active_markup'=>'/<\s*(?:meta|link|base)\b/i','unsafe_event_handler'=>'/\s+on[a-z][a-z0-9_-]*\s*=\s*(?:["\']|[^\s>]+)/i','unsafe_url_scheme'=>'/(?:javascript|vbscript)\s*:|data\s*:\s*(?:text\/html|image\/svg\+xml|application\/(?:javascript|x-javascript|xml))/i','unsafe_svg_content'=>'/<\s*\/?svg\b|\s+xlink:href\s*=|<\s*(?:animate|set|foreignObject)\b/i');foreach($patterns as $code=>$pattern){if(preg_match($pattern,$raw)){return $code;}}return null;}
	private function placeholder(string $text):bool{return 1===preg_match('/(?:\bTODO\b|\blorem\s+ipsum\b|\[(?:insert\s+(?:content|example)|add\s+details)\]|\bas an AI language model\b|\bunable to generate\b|\berror generating response\b)/i',$text);}
	private function failure(string $code,string $category,array $diagnostics=array()):array{return array('success'=>false,'code'=>$code?:'unsafe_article_content','article'=>array(),'retryable'=>true,'diagnostics'=>array_merge(array('category'=>$category),$diagnostics));}
	private function category(string $code):string{return false!==strpos($code,'element')||'unsafe_embedded_content'===$code?'dangerous_element':('unsafe_event_handler'===$code?'dangerous_attribute':('unsafe_url_scheme'===$code?'dangerous_url':'dangerous_markup'));}
	private function text(string $html):string{$text=html_entity_decode(wp_strip_all_tags($html),ENT_QUOTES|ENT_HTML5,get_bloginfo('charset')?:'UTF-8');return trim(preg_replace('/\s+/u',' ',$text)??'');}
	private function words(string $text):int{return ''===$text?0:count(preg_split('/\s+/u',$text,-1,PREG_SPLIT_NO_EMPTY)?:array());}
	private function length(string $value):int{return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value);}
}
