# AI Content Studio — AI Development Context

## Project Overview

AI Content Studio is a WordPress plugin that helps website owners generate, optimize, manage, and publish content using AI directly inside WordPress.

The long-term goal is to build a complete AI-powered content marketing system for WordPress.

The plugin should support:

* Blog idea generation
* Complete article generation
* SEO metadata generation
* Brand-aware content
* AI image generation
* WordPress draft, scheduling, and publishing
* Content history and usage tracking
* Content rewriting and improvement
* Social media content repurposing
* Social media publishing
* Multiple AI providers
* Bring Your Own API Key

## Project Location

Development folder:

```text
C:\xampp\htdocs\supportcandy-ai-test\wp-content\plugins\AI Content Studio
```

WordPress plugin slug:

```text
ai-content-studio
```

Preferred WordPress test folder:

```text
C:\xampp\htdocs\supportcandy-ai-test\wp-content\plugins\AI Content Studio
```

## Product Principles

1. WordPress-first experience.
2. Simple for non-technical users.
3. Bring Your Own API Key.
4. No forced AI credit system.
5. Modular provider architecture.
6. Secure WordPress coding practices.
7. Features must be developed and tested incrementally.
8. Do not build multiple major features in one task.
9. Avoid unnecessary external dependencies.
10. Keep the code suitable for future commercial distribution.

## Technical Requirements

Plugin name:

```text
AI Content Studio
```

Plugin slug:

```text
ai-content-studio
```

Text domain:

```text
ai-content-studio
```

PHP prefix:

```text
AICS_
```

PHP namespace:

```text
AIContentStudio
```

Minimum PHP version:

```text
8.0
```

Minimum WordPress version:

```text
6.4
```

## Coding Standards

All code must follow:

* WordPress coding standards
* Proper capability checks
* Nonce verification
* Input sanitization
* Output escaping
* Prepared SQL queries
* Translation-ready strings
* Clear class responsibilities
* Modular service-based architecture
* No direct file access
* No business logic inside templates
* No unnecessary global variables
* No silent error handling
* No hardcoded API keys
* No provider-specific logic inside general content services

## Planned Architecture

Preferred directories:

```text
assets/
assets/css/
assets/js/

includes/
includes/admin/
includes/ajax/
includes/core/
includes/database/
includes/helpers/
includes/providers/
includes/repositories/
includes/rest/
includes/services/

templates/
languages/
```

Main architecture areas:

* Core plugin bootstrap
* Admin UI
* Permissions
* Settings
* AI provider abstraction
* Prompt management
* Brand profile
* Content generation
* SEO generation
* Image generation
* WordPress publishing
* Content history
* Usage tracking
* Background processing
* Social content generation

## Initial Admin Pages

The plugin should eventually contain:

* Dashboard
* Content Ideas
* Create Content
* Content History
* Brand Profile
* Settings
* System Status

## MVP Scope

The first working version should support:

1. Plugin foundation
2. Admin interface
3. Settings system
4. OpenAI provider connection
5. Brand Profile
6. Blog idea generation
7. Complete article generation
8. SEO title and meta description generation
9. WordPress draft creation
10. Content history
11. Basic usage and error logging

## Future Scope

Later versions may include:

* Gemini
* Claude
* OpenRouter
* DeepSeek
* Grok
* Local AI providers
* AI image generation
* Featured image generation
* Inline article images
* Content calendar
* Automatic scheduling
* Existing post rewriting
* Translation
* Internal linking
* Content audit
* Topic clusters
* Competitor analysis
* Social media content generation
* Social media publishing
* SaaS integration

## AI Provider Architecture

The system must use a provider-independent design.

General content services must not call OpenAI directly.

Use an interface or contract for providers.

Example responsibilities:

```text
AI_Provider_Interface
OpenAI_Provider
Provider_Manager
Prompt_Service
Content_Generation_Service
```

Provider-specific API requests, authentication, response parsing, and error handling must remain inside the provider implementation.

## Development Workflow

For every task:

1. Read this file first.
2. Review the current project structure.
3. Review existing implementation before editing.
4. Make only the requested change.
5. Do not remove working functionality.
6. Avoid broad refactoring unless necessary.
7. Run syntax checks after changes.
8. Review security implications.
9. Explain changed files.
10. Provide exact manual testing steps.
11. Stop after the requested task is complete.
12. Wait for confirmation before beginning the next task.

## Testing Rules

Each development task should include:

* Activation test
* Admin page test
* Permission test
* Nonce test where applicable
* Invalid input test
* Error handling test
* PHP syntax validation
* WordPress debug log review

Use:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

during development where appropriate.

## Database Rules

Do not create database tables until a feature requires them.

When tables are introduced:

* Use `dbDelta()`
* Include schema versioning
* Use the WordPress database prefix
* Add suitable indexes
* Avoid storing secrets unnecessarily
* Use prepared queries
* Support future migrations
* Define uninstall behavior clearly

## Security Rules

Never:

* Expose API keys in rendered HTML
* Log complete API keys
* Trust request parameters
* Skip capability checks
* Skip nonce validation for write actions
* Output unescaped database content
* Allow arbitrary URLs without validation
* Allow unrestricted file uploads
* Execute generated AI content as code

## Current Development Status

Tasks 1 through 9 are complete. The plugin now includes:

* A namespaced plugin bootstrap and lifecycle handlers
* Minimum PHP and WordPress version checks during activation
* Installation metadata stored in WordPress options
* A centralized `Permissions` class based on `manage_options`
* An AI Content Studio admin menu with six placeholder pages and a functional Settings page
* Admin CSS and JavaScript scoped to the plugin's admin pages
* Direct-access protection, uninstall safety, README documentation, and Git ignore rules
* A functional Settings page for saving an OpenAI API key and selecting an allowed model
* Explicit, nonce-protected actions for saving settings and removing only the API key

Task 2 added or changed:

* `includes/services/class-settings.php`
* `includes/admin/class-settings-page.php`
* `ai-content-studio.php`
* `includes/core/class-plugin.php`
* `includes/admin/class-admin-menu.php`
* `assets/css/admin.css`
* `assets/js/admin.js`
* `AI_CONTEXT.md`

Settings use one non-autoloaded WordPress option array named `aics_settings`. Supported keys are `openai_api_key` and `openai_model`; the default model is `gpt-4.1-mini`. API keys are trimmed, conservatively sanitized, never rendered back into HTML, and preserved when the save field is blank. The key can be removed only through its dedicated action. Models are validated against the Task 2 allowlist. WordPress option storage is intentionally used without encryption for the BYO-key MVP.

Task 3 added a minimal provider architecture:

* `includes/providers/interface-provider.php` defines the provider name and connection-test contract.
* `includes/providers/class-openai-provider.php` implements the OpenAI connection test.
* `includes/services/class-http-client.php` wraps JSON POST requests through the WordPress HTTP API.
* `includes/admin/class-settings-page.php` now registers and renders the dedicated Test Connection workflow.
* `ai-content-studio.php` loads the provider contract, HTTP client, and OpenAI provider.
* `assets/css/admin.css` includes minimal connection-action spacing.
* `AI_CONTEXT.md` documents the completed task.

The HTTP client uses `wp_remote_post()`, a bounded timeout, `wp_json_encode()`, normalized result arrays, JSON validation, and safe handling for network, HTTP, empty-body, API-error, and malformed-response failures. It never logs or returns request headers or request bodies in messages.

The OpenAI provider uses `https://api.openai.com/v1/responses` with the saved API key and allowlisted model from `AICS_Settings`. The Settings page submits a dedicated nonce-protected action, the server performs a small Responses API request, and the user is redirected to a controlled success or failure notice. No credential, raw API response, or arbitrary remote message is rendered or placed in a URL.

Task 4 replaced the Create Content placeholder with the Content Studio form foundation. The form collects:

* Business or website context, required with a 3000-character maximum
* Topic or keyword, required with a 250-character maximum
* An allowlisted tone, defaulting to `professional`
* An allowlisted approximate article length, defaulting to `medium`

Task 4 added or changed:

* `includes/admin/class-content-studio-page.php`
* `ai-content-studio.php`
* `includes/core/class-plugin.php`
* `includes/admin/class-admin-menu.php`
* `assets/css/admin.css`
* `AI_CONTEXT.md`

The form uses a dedicated `admin-post.php` action with centralized capability enforcement and nonce verification. Text values are unslashed, sanitized, trimmed, checked without silent truncation, and safely escaped when redisplayed. Select values are checked against fixed allowlists. Redirects contain only controlled notice codes and never contain form data.

Each user's latest valid form state is stored for 20 minutes in an `aics_content_inputs_{user_id}` transient. A separate five-minute user-scoped validation transient preserves sanitized text after a failed submission without overwriting the last valid state. No sessions, API keys, secrets, or permanent plugin settings are used for Content Studio state.

Task 5 added secure AI-powered blog-idea generation and selection:

* `includes/ai/class-ai-request.php` defines an immutable provider-independent request containing the task, instructions, user prompt, output-token limit, and structured schema.
* `includes/ai/class-ai-response.php` defines a predictable success or failure result with validated data, a safe public message, a controlled error code, provider name, and optional HTTP status.
* `includes/ai/class-prompt-engine.php` builds the `blog_ideas` request and exact five-idea JSON schema from validated Content Studio inputs.
* `includes/ai/class-ai-engine.php` orchestrates prompt creation, provider generation, and generated-output validation.
* `includes/providers/interface-provider.php` now requires `generate( AICS_AI_Request $request ): AICS_AI_Response` while preserving connection testing.
* `includes/providers/class-openai-provider.php` converts provider-independent requests to Responses API requests and defensively extracts structured output.
* `includes/admin/class-content-studio-page.php` adds protected idea-generation and idea-selection actions plus five escaped idea cards.
* `ai-content-studio.php` loads the new AI classes in dependency order.
* `assets/css/admin.css` and `assets/js/admin.js` add scoped idea-card styling and double-submission prevention.
* `AI_CONTEXT.md` documents the completed task.

The OpenAI request uses the existing Responses API endpoint, saved allowlisted model, reusable HTTP client, `store: false`, and strict structured output through `text.format`. The prompt requires exactly five objects, and the schema requires each object to contain `id`, `title`, `description`, `primary_keyword`, and `search_intent`; exact cardinality is enforced again by the AI engine before storage. The provider handles nested output text, incomplete output, missing output, invalid JSON, and accidental outer Markdown fences without exposing raw responses.

The AI engine requires IDs `idea-1` through `idea-5`, exactly five items, unique IDs and titles, plain non-empty text, allowlisted search intent, and length limits of 200 characters for title and primary keyword and 600 characters for description. It sanitizes every generated field and rejects the entire result rather than storing partial output.

Generated ideas are stored for 20 minutes in `aics_blog_ideas_{user_id}` only after complete validation. New generation replaces old ideas only after success and clears the prior selection. Selection submits only a stored idea ID, reloads the server-side idea list, and copies the matching idea into `aics_selected_blog_idea_{user_id}` for 20 minutes. No API key, authorization header, raw response, or complete prompt is stored in idea state.

Task 6 added complete editable article-draft generation without WordPress post creation:

* `includes/services/class-post-generator.php` sanitizes and validates generated and manually edited article fields for future post creation.
* `includes/ai/class-ai-request.php` now accepts the `article_draft` task and its larger bounded output-token budget.
* `includes/ai/class-prompt-engine.php` builds the article instructions, approximate length guidance, and strict title/content/excerpt schema.
* `includes/ai/class-ai-engine.php` orchestrates article requests and requires `AICS_Post_Generator` validation before returning success.
* `includes/providers/class-openai-provider.php` supports `article_draft` through the existing generation method and Responses API path, with a bounded 30-second article timeout.
* `includes/admin/class-content-studio-page.php` adds protected generate and save actions, temporary draft state, the selected-idea summary, editable fields, `wp_editor()`, regeneration, and controlled notices.
* `ai-content-studio.php` loads the post-generator service before the AI engine.
* `assets/css/admin.css` adds scoped article-editor styling.
* `assets/js/admin.js` adds article generation double-submit prevention and a best-effort unsaved-edit warning for title, excerpt, Text mode, and TinyMCE Visual mode.
* `AI_CONTEXT.md` documents the completed task.

The article structured response requires separate `title`, `content`, and `excerpt` strings. Title is plain text with a 250-character limit; excerpt is plain text with a 500-character limit. Content must contain meaningful text, is limited to 100,000 characters, and is filtered through an article-specific `wp_kses()` allowlist containing only `p`, `h2`, `h3`, `h4`, `ul`, `ol`, `li`, `strong`, `em`, `blockquote`, and `a`. Links may retain only safe `href` and `title` attributes. Scripts, styles, iframes, forms, event handlers, inline CSS, arbitrary attributes, and unsafe URL protocols are removed.

Sanitized article drafts are stored for 45 minutes in `aics_article_draft_{user_id}` with the selected idea ID, generation and update timestamps, model, tone, and requested length. Successful article generation refreshes the trusted input and selection lifetimes. Manual saves preserve generation metadata and update only sanitized editable fields plus `updated_at`. Selecting a different idea, validating new inputs, or successfully generating new ideas clears an incompatible draft. Failed generation never replaces the previous draft.

The editor and regeneration forms are separate and never nested. Regeneration uses trusted server-side inputs and the selected idea, sends no current draft back to OpenAI, asks for confirmation, and replaces state only after full validation. No `wp_insert_post()` call or permanent article storage exists.

Task 7 added native WordPress draft creation from the reviewed temporary article:

* `includes/services/class-post-generator.php` now revalidates reviewed article data and exclusively owns native post creation through `wp_insert_post()`.
* `includes/admin/class-content-studio-page.php` registers the protected `aics_create_wordpress_draft` action, loads only trusted user-scoped state, prevents duplicates, stores the created post association, and renders the result panel.
* `assets/css/admin.css` adds scoped styling for the draft-creation action and result panel.
* `assets/js/admin.js` disables the creation button after submission as a usability safeguard; server-side duplicate checks remain authoritative.
* `AI_CONTEXT.md` documents the completed task.

The service hardcodes `post_type` to `post` and `post_status` to `draft`, uses the current authenticated user as `post_author`, and passes the temporary article through the existing title, excerpt, content-length, meaningful-content, and strict `wp_kses()` validation again before insertion. The browser submits only an action and dedicated nonce; it does not submit article fields in the creation form. Automatic publishing is impossible through this action.

After successful creation, the article transient stores `created_post_id` and `created_post_at`. Duplicate prevention checks that user-scoped association and confirms either the private plugin marker and creating-user metadata or, as a metadata-failure fallback, the trusted workflow timestamp and matching post author. Existing non-trashed associated posts are reused and no second post is inserted. Deleted, trashed, or invalid associations are cleared without deleting any WordPress post, allowing a new draft to be created.

Created posts receive these sanitized private metadata fields:

* `_aics_generated_post`
* `_aics_source_idea_id`
* `_aics_primary_keyword`
* `_aics_search_intent`
* `_aics_requested_tone`
* `_aics_requested_length`
* `_aics_created_by_user`
* `_aics_generation_timestamp`

No API key, authorization header, business context, full prompt, or raw provider response is stored in post meta. Optional metadata failure does not delete a successfully created post; the post ID remains in workflow state and a controlled warning is shown. Edit links are rendered only after `current_user_can( 'edit_post', $post_id )` succeeds.

Task 8 added a secure workflow reset and native WordPress Content History:

* `includes/admin/class-content-history-page.php` provides the protected Content History query, filters, table, metadata normalization, actions, empty state, and pagination.
* `includes/admin/class-content-studio-page.php` registers `aics_reset_content_workflow`, renders Start New Content when temporary state exists, and deletes only the current user's five workflow transients after capability, login, and nonce checks.
* `includes/admin/class-admin-menu.php` replaces the Content History placeholder with the dedicated page renderer.
* `ai-content-studio.php` loads the new history page class.
* `assets/css/admin.css` adds scoped reset and responsive history-table styling.
* `AI_CONTEXT.md` documents the completed task.

No `AICS_Workflow_State` service was introduced. All temporary state keys and their existing expiration behavior remain centralized in `AICS_Content_Studio_Page`, so extracting a service would have added indirection without reducing cross-class duplication.

Start New Content clears only these current-user transients:

* `aics_content_inputs_{user_id}`
* `aics_content_input_errors_{user_id}`
* `aics_blog_ideas_{user_id}`
* `aics_selected_blog_idea_{user_id}`
* `aics_article_draft_{user_id}`, including its created-post association

Reset never deletes or modifies WordPress posts or post metadata, never changes `aics_settings`, never affects another user's transient keys, and never calls an AI provider. It uses the existing generic confirmation JavaScript, while the server remains authoritative.

Content History queries native `post` records through `WP_Query`, requiring `_aics_generated_post = 1`, ordering newest first, and displaying 10 items per page. The default `all` filter includes draft, pending, future, publish, and private posts but excludes Trash. Allowlisted filters are `all`, `draft`, `publish`, `future`, and `pending`; invalid values fall back to `all`, and page numbers below 1 fall back to 1. Pagination preserves only the controlled page and status values.

The history page requires the centralized plugin capability. The native query requests readable posts, each row is also checked with `read_post` or `edit_post`, edit links require `edit_post`, and View appears only for publicly viewable posts the user can read. Private metadata is normalized and escaped; known tone, length, search-intent, and status values use translated allowlisted labels, while missing or unknown values display an em dash.

Task 9 added lightweight usage logging and a functional dashboard:

* `includes/database/class-usage-log-repository.php` owns inserts, aggregate summary queries, bounded recent-activity reads, and retention deletion.
* `includes/services/class-usage-logger.php` validates strict event/operation/status allowlists, sanitizes scalar fields, accepts only five safe metadata keys, measures durations, and performs cleanup without affecting primary operations.
* `includes/admin/class-dashboard-page.php` renders five summary cards, the latest 10 activity records, capability-checked related-post links, safe user labels, duration/date formatting, an empty state, and quick links.
* `includes/database/class-installer.php` creates or upgrades the usage table through `dbDelta()` and updates the installed schema version only after a successful schema operation.
* `includes/core/class-plugin.php`, `class-activator.php`, and `class-deactivator.php` provide runtime schema upgrade and daily retention-cron lifecycle handling.
* `includes/admin/class-settings-page.php` logs one final OpenAI connection-test outcome.
* `includes/admin/class-content-studio-page.php` logs one final outcome for blog-idea generation, article generation, and WordPress draft creation.
* `includes/admin/class-admin-menu.php`, `ai-content-studio.php`, and `assets/css/admin.css` load and display the dashboard at the existing `ai-content-studio` top-level URL.

The schema version is `0.2.0`. The table is `{$wpdb->prefix}aics_usage_logs` with `id`, `user_id`, `event_type`, `operation`, `status`, `provider`, `model`, `error_code`, `object_id`, `duration_ms`, `metadata`, and UTC `created_at` columns. It has indexes for user, event type, operation, status, creation time, and object ID. Fresh activation installs it; existing installations run the narrow version comparison on `init`, and matching installations skip `dbDelta()`.

Logged operations are `openai_connection_test`, `generate_blog_ideas`, `generate_article_draft`, and `create_wordpress_draft`. Allowed event types are `system_test`, `ai_request`, and `post_creation`; statuses are `success` and `failed`. Safe metadata is limited to `idea_count`, `requested_length`, `tone`, `post_status`, and `test_type`. API keys, authorization headers, prompts, business context, topics, titles, article content, excerpts, provider responses, raw error messages, emails, and IP addresses are never accepted as metadata.

Dashboard totals are calculated with aggregate SQL rather than loading logs: AI requests count idea and article operations; successful/failed AI requests apply their corresponding status; articles count successful article generation; WordPress drafts count successful draft creation. Recent activity is limited to 10 newest rows and maps every displayed operation and status through controlled labels.

Logs are retained for 30 days through `aics_cleanup_usage_logs`. Activation schedules one daily event, runtime startup restores a missing schedule without duplicating it, deactivation clears the event without deleting logs, and cleanup deletes only rows older than the UTC cutoff. Uninstall remains non-destructive because the established uninstall policy defers cleanup until final data-storage decisions are made.

## Current Task Boundary

Task 9 adds only operation-level usage records, aggregate dashboard summaries, recent activity, and 30-day retention. It adds no token accounting, cost calculation, billing, limits, charts, exports, log filters, log deletion UI, content/prompt logging, external analytics, REST/AJAX endpoints, or additional providers.

Current limitations: logging is best effort and deliberately contains no exact token or cost data. Dashboard summaries are lifetime totals within the retention window, and there is no reporting filter or chart. Deactivation preserves the table and logs; uninstall also preserves them under the current policy.

The next planned task is system status, final UX polish, and release-readiness checks. It has not begun.

## Important Instruction for Codex

Before making any code changes:

1. Read `AI_CONTEXT.md`.
2. Inspect all current project files.
3. Summarize the current state.
4. Complete only the requested task.
5. Do not continue into another phase automatically.
