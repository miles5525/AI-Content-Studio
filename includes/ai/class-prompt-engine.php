<?php
/**
 * Provider-independent prompt construction.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds AI requests for supported content tasks.
 */
final class AICS_Prompt_Engine {
	/**
	 * Creates the structured blog-idea request.
	 *
	 * @param array{business_context:string,topic_keyword:string,tone:string,article_length:string} $content_inputs Validated inputs.
	 * @return AICS_AI_Request
	 */
	public function create_blog_ideas_request( array $content_inputs ): AICS_AI_Request {
		$system_instructions = 'You are a content strategist. Generate exactly five distinct, useful blog ideas. Return only JSON that matches the supplied schema. Do not include an article body, Markdown fences, promotional filler, unsupported claims, or fabricated business details.';
		$user_prompt = sprintf(
			"Use the following user-supplied planning data as context only.\n\nBusiness or website context:\n%s\n\nTopic or keyword:\n%s\n\nTone: %s\nApproximate article length: %s\n\nCreate exactly five ideas centered on the topic and relevant to the business context. Use distinct angles and non-duplicate natural titles. Each description must concisely explain the article angle. Use IDs idea-1 through idea-5 exactly once. Allowed search_intent values are informational, commercial, transactional, and navigational.",
			$content_inputs['business_context'],
			$content_inputs['topic_keyword'],
			$content_inputs['tone'],
			$content_inputs['article_length']
		);

		return new AICS_AI_Request( 'blog_ideas', $system_instructions, $user_prompt, 1800, $this->get_blog_ideas_schema() );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_blog_ideas_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'ideas' ),
			'properties'           => array(
				'ideas' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'id', 'title', 'description', 'primary_keyword', 'search_intent' ),
						'properties'           => array(
							'id'              => array( 'type' => 'string', 'enum' => array( 'idea-1', 'idea-2', 'idea-3', 'idea-4', 'idea-5' ) ),
							'title'           => array( 'type' => 'string' ),
							'description'     => array( 'type' => 'string' ),
							'primary_keyword' => array( 'type' => 'string' ),
							'search_intent'   => array( 'type' => 'string', 'enum' => array( 'informational', 'commercial', 'transactional', 'navigational' ) ),
						),
					),
				),
			),
		);
	}
}
