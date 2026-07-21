<?php
/**
 * Provider-independent AI request value object.
 *
 * @package AIContentStudio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the minimal immutable data needed for an AI request.
 */
final class AICS_AI_Request {
	private string $task_type;
	private string $system_instructions;
	private string $user_prompt;
	private int $max_output_tokens;
	private array $structured_output_schema;

	/**
	 * Creates a validated request value object.
	 *
	 * @param string              $task_type               Internal task identifier.
	 * @param string              $system_instructions     Provider-independent system instructions.
	 * @param string              $user_prompt             Provider-independent user prompt.
	 * @param int                 $max_output_tokens       Maximum output token budget.
	 * @param array<string,mixed> $structured_output_schema Required structured output schema.
	 * @throws InvalidArgumentException When required values are invalid.
	 */
	public function __construct( string $task_type, string $system_instructions, string $user_prompt, int $max_output_tokens, array $structured_output_schema ) {
		if ( ! in_array( $task_type, array( 'blog_ideas', 'automation_ideas', 'evaluate_content_ideas', 'article_draft', 'automation_article' ), true ) || '' === trim( $system_instructions ) || '' === trim( $user_prompt ) ) {
			throw new InvalidArgumentException( 'Invalid AI request input.' );
		}

		if ( $max_output_tokens < 1 || $max_output_tokens > 10000 || empty( $structured_output_schema ) ) {
			throw new InvalidArgumentException( 'Invalid AI request limits or schema.' );
		}

		$this->task_type                = $task_type;
		$this->system_instructions      = $system_instructions;
		$this->user_prompt              = $user_prompt;
		$this->max_output_tokens        = $max_output_tokens;
		$this->structured_output_schema = $structured_output_schema;
	}

	public function get_task_type(): string {
		return $this->task_type;
	}

	public function get_system_instructions(): string {
		return $this->system_instructions;
	}

	public function get_user_prompt(): string {
		return $this->user_prompt;
	}

	public function get_max_output_tokens(): int {
		return $this->max_output_tokens;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_structured_output_schema(): array {
		return $this->structured_output_schema;
	}
}
