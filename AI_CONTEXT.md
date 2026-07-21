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

Tasks 1 through 10 are complete. The plugin now includes:

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

Task 10 prepared the plugin for private beta stabilization:

* `includes/services/class-system-check.php` performs 20 controlled, read-only environment, WordPress, provider, database, cron, upload, permission, permalink, and debug checks and creates a safe diagnostic report.
* `includes/admin/class-system-status-page.php` renders a capability-protected status table, accessible textual status labels, a visible diagnostic textarea, clipboard button, and aria-live result.
* `assets/js/system-status.js` provides Clipboard API support plus a selectable-textarea fallback and loads only on System Status.
* `includes/admin/class-assets.php` retains plugin-admin-only asset loading and scopes the clipboard script to `aics-system-status`.
* `includes/admin/class-admin-menu.php` now shows Dashboard, Create Content, Content History, Settings, and System Status in that order. Established Content Ideas and Brand Profile placeholder slugs remain registered as hidden compatibility pages.
* `assets/css/admin.css` adds readable status styling and narrow-screen improvements for cards, tables, workflow controls, and action buttons.
* `ai-content-studio.php`, `README.md`, and `AI_CONTEXT.md` align private-beta version and documentation.

The plugin version is `0.9.0`; the independent database schema version remains `0.2.0`. Plugin headers and `AICS_VERSION` match, and all admin assets use `AICS_VERSION`. The placeholder plugin URI was replaced with the project GitHub repository and foundation-era README statements were removed.

System Status checks WordPress 6.4+, PHP 8.0+ with PHP 8.1 recommended, HTTPS, local REST infrastructure, cURL, JSON, OpenSSL, WordPress HTTP API availability, database connectivity, usage-table presence, installed/code schema versions, WP-Cron configuration, AICS cleanup scheduling and duplicates, upload writability, OpenAI provider/key/model configuration, post-creation permission, permalinks, and debug mode. It makes no OpenAI or external HTTP request and performs no repair or mutation.

Diagnostics include only versions, language, multisite, controlled availability states, provider name, yes/no credential configuration, selected allowlisted model, cron/debug state, PHP memory limit, and execution time. They exclude API-key values and fragments, database credentials/host/name, cookies, nonces, emails, filesystem paths, article data, prompts, provider responses, usage entries, salts, and server-variable dumps.

Security review confirmed state-changing actions retain capability and nonce checks, redirects remain safe and terminating, workflow transients remain user-scoped, generated HTML remains allowlisted, post creation stays draft-only, logging metadata remains allowlisted, edit links remain capability-checked, retention targets only expired AICS rows, and no public REST/AJAX endpoints exist. No functional security correction was required.

## Current Task Boundary

Task 10 adds only private-beta diagnostics, navigation cleanup, accessibility/responsive polish, version consistency, scoped clipboard behavior, and release cleanup. It adds no automated repair, API testing on page load, publishing, scheduling, SEO, images, social integrations, providers, licensing, billing, telemetry, exports, or public endpoints.

Current limitations: System Status reports local configuration but does not repair it or test live OpenAI connectivity. Clipboard behavior depends on browser support, with the visible textarea as fallback. This is a private-beta build and makes no claim of full WCAG compliance or broad hosting-matrix certification.

Current release status: private beta candidate `0.9.0`.

Focused dashboard-count correction after Task 10:

* `includes/admin/class-dashboard-page.php` now derives **WordPress Drafts Created** from current native WordPress posts with `post_type = post`, `post_status = draft`, and `_aics_generated_post = 1`.
* The query retrieves IDs only, requests one row solely for the count query, and disables unnecessary term and metadata cache population.
* Total, successful, and failed AI requests plus Articles Generated remain usage-log metrics.
* Recent Activity remains usage-log based. No historical log rows were created or backfilled.
* Published, scheduled, pending, private, trashed, deleted, unmarked, and non-`post` content is excluded from the draft card.

## SupportCandy-Inspired Admin UI Design System

The focused admin UI task is complete. The installed SupportCandy AI Assistant was inspected only as a visual reference; AI Content Studio does not enqueue its assets, import its PHP, call its hooks, read its settings, or require it at runtime.

The scoped `.aics-admin-wrap` token system adopts the reference plugin's blue-led WordPress admin palette: primary `#2563eb`, primary hover `#1d4ed8`, primary soft `#f0f6fc`, focus accent `#72aee6`, white surfaces, muted surface `#f6f7f7`, standard border `#dcdcde`, strong border `#8c8f94`, text `#1d2327`, muted text `#646970`, success `#0a5c20`, warning `#704d00`, and error `#8a2424`. Shared 4px, 6px, and 8px radii and restrained small and medium shadows are also defined locally.

Shared UI classes now cover page headers, descriptions, cards, sections, actions, empty states, status badges, summary grids, primary/danger buttons, tables, forms, and diagnostics. Dashboard cards have clearer metric hierarchy and restrained semantic accents; Recent Activity, quick links, and the empty state are presented as intentional components. Create Content has consistent stage cards, selected idea treatment, focus-visible idea controls, a contained editor area, separated actions, and clearer draft/result panels. Content History retains its native table, query, filters, pagination, and columns while adding textual status pills, a designed empty state, and locally scrollable narrow-width behavior. Settings groups provider configuration, credential status, connection testing, and removal controls consistently without exposing more of the key. System Status uses the same headers, tables, textual badges, diagnostic panel, and accessible clipboard feedback.

Responsive behavior was reviewed for approximately 1280px, 1024px, 782px, and 480px widths. Summary grids collapse from multiple columns to two and then one, idea cards use a minimum-zero single-column layout on small screens, controls remain within containers, actions wrap, and wide tables scroll only inside their local wrappers. Accessibility decisions include persistent visible labels, strong keyboard focus rings, text plus color for statuses and selection, semantic headings/tables, meaningful empty-state actions, preserved `aria-live` clipboard feedback, and no styling inside the TinyMCE iframe. Full WCAG compliance is not claimed.

Files modified by this UI task: `assets/css/admin.css`, the Dashboard, Content Studio, Content History, Settings, and System Status admin page classes, and `AI_CONTEXT.md`. No provider, prompt, workflow-state, database, permission, nonce, post-generation, query, activation, or uninstall behavior was changed. Current limitations remain: tables use local horizontal scrolling at narrow widths, the workflow stage treatment follows existing conditional sections rather than adding a progress engine, and full browser/manual visual regression testing is still required.

## Automation Blueprint and Foundation Task 1

The master product direction is one persistent content-operations workflow engine supporting three operating modes: `autopilot` for unattended future processing, `approval` for future idea/article/publishing gates, and `manual` for the existing administrator-controlled Content Studio. Automation Foundation Task 1 is complete and adds persistence only; no automation is running yet.

Database schema version `0.3.0` adds `{$wpdb->prefix}aics_automation_profiles` through the existing `dbDelta()` installer. The table contains an unsigned bigint primary ID; unique 191-character `profile_slug`; visible `profile_name`; indexed `mode` and `status`; five LONGTEXT JSON configuration columns; nullable, indexed `next_run_at`; nullable `last_run_at`; controlled `last_error_code`; indexed unsigned `created_by` and `updated_by`; and UTC `created_at` and `updated_at`. It has no foreign keys or native MySQL JSON columns. The allowed modes are `autopilot`, `approval`, and `manual`; statuses are `disabled`, `active`, `paused`, and `error`. No default profile is inserted automatically.

Configuration fields are encoded with `wp_json_encode()` and decoded to PHP arrays; invalid stored JSON safely becomes an empty array. `business_context` accepts only the documented plain-text business fields and deduplicated, length-limited topic/claim arrays. `content_settings` bounds cycle counts and lookback days, constrains tone and article length, and normalizes booleans. `schedule_settings` constrains frequency, interval and volume, orders allowlisted weekdays predictably, validates 24-hour time and ISO dates, but does not calculate schedules. `workflow_rules` contains only three approval booleans. `publishing_settings` constrains future publishing mode/status choices and stores non-negative category/author IDs without executing or validating those choices against WordPress objects.

`AICS_Automation_Profile_Repository` owns normalized `create()`, partial `update()`, `get_by_id()`, `get_by_slug()`, bounded `get_profiles()`, strict UTC `get_due_profiles()`, separated `update_runtime_fields()`, and `table_exists()`. Profile slugs are immutable after creation. Reads cast IDs, normalize mode/status, decode JSON, and preserve UTC database datetime strings. Creates and updates return controlled result codes and never expose database errors. Runtime updates cannot modify configuration or creation audit fields. `next_run_at` and `last_run_at` accept only exact UTC `Y-m-d H:i:s` values or NULL; audit timestamps use `current_time( 'mysql', true )`. Error codes use `sanitize_key()` and are limited to 100 characters.

Fresh activation installs both the established usage-log table and the automation-profile table. Existing installations upgrade on the normal `init` routine without reactivation; the installed version advances only after both required prefixed tables exist and both schema operations report no database error. Matching installations return before `dbDelta()`, existing logs/settings/posts are untouched, and no automation hook or cron event was added. The pre-existing daily usage-log retention event is unchanged.

The established non-destructive lifecycle policy remains: deactivation only clears the usage-retention schedule, and uninstall does not drop tables or delete settings. Automation profiles are therefore preserved on deactivation and uninstall pending final product-wide cleanup decisions.

The profile table stores configuration and scheduling state only. It must never contain provider credentials or headers, passwords, database credentials, cookies, nonces, raw provider output/errors, emails, prompts, generated ideas, article content, or generated articles.

Files created: `includes/database/class-automation-profile-repository.php`. Files modified for this foundation: `ai-content-studio.php`, `includes/database/class-installer.php`, and `AI_CONTEXT.md`. The previously completed admin design-system changes remain preserved and functionally separate.

Current limitations: there is no Automation UI, default profile, scheduler, next-run calculator, lock, queue, run history, approval system, notification, AI call, post generation, or publishing behavior. Cross-field start/end-date rules, referenced author/category validation, and runtime transition policy are deferred to later settings and execution services.

The next planned task is Automation Foundation Task 2 — Automation Settings page and default profile management. It has not begun.

## Important Instruction for Codex

Before making any code changes:

1. Read `AI_CONTEXT.md`.
2. Inspect all current project files.
3. Summarize the current state.
4. Complete only the requested task.
5. Do not continue into another phase automatically.
