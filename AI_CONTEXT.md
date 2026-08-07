# AI Content Studio — AI Development Context

## Manual SEO Rank Math and AIOSEO Reliability Fix (2026-08-06)

Manual Studio SEO reapplication no longer attempts to save/regenerate an already-applied SEO record before invoking the idempotent application workflow. This removes the misleading `seo_regeneration_not_allowed` path that previously surfaced as the generic “SEO generation or validation failed” message.

AIOSEO 5 integration now writes and reads its dedicated `focus_keyword` model field while retaining the supported legacy `keyphrases` structure. Read-back accepts decoded objects, arrays, or JSON strings and normalizes slashes, entities, and whitespace consistently before verification. Manual generation/validation failures now map controlled error codes to actionable messages instead of collapsing provider, missing-field, and invalid-field failures into one generic notice. Rank Math continues using normalized post-meta verification and its comma-separated focus-keyword compatibility rule.

## First-Run Setup Wizard (2026-08-06)

AI Content Studio includes a hidden, focused `admin.php?page=aics-setup` onboarding route and a visible **Getting Started** section inside Settings. The six server-authoritative stages are Welcome, AI Provider, Featured Images, SEO Integration, Content Defaults, and Finish. The implementation reuses existing OpenAI credential/model storage and connection testing, featured-image settings/provider capabilities, SEO detection/target labels, shared admin wizard styling, skeletons, accessibility rules, and authenticated AJAX patterns.

`AICS_Setup_Wizard_State_Service` stores only controlled progress, skip state, non-sensitive successful-test evidence, defaults, completion version, and completion time. API keys remain exclusively in `AICS_Settings` and are never returned to JavaScript. Future steps are server-blocked, images may be skipped without deleting their configuration, completion requires a successful test matching the current model, and reopening reads current settings without resetting them.

Fresh-install detection runs before schema installation. A site is treated as legacy when `aics_db_version` or `aics_settings` already exists; it receives `legacy_configured` and no redirect. Only a genuine non-network fresh activation receives a one-use redirect option. The next qualified administrator request deletes it immediately and refuses redirects during bulk activation, network admin, AJAX, REST, cron, or WP-CLI. Incomplete fresh setup has a dismissible Dashboard banner. The setup wizard never generates an article, post, automation run, or SEO metadata.

## Automations AJAX wizard (2026-08-06)

- Automations presents the existing default profile as eight single-page stages: Basics, Content, Workflow, Schedule, Image, SEO, Publishing, and Review.
- `assets/js/automation-wizard.js` loads only on Automations and provides accessible navigation, loading feedback, browser history, double-submit prevention, and authenticated AJAX persistence.
- AJAX saves continue through `AICS_Automation_Profile_Service`; the wizard never dispatches a run or calls an AI/image provider. New profiles remain disabled until explicit activation. Editing an active profile preserves its status unless Deactivate is selected.
- The admin-post handler remains as a non-JavaScript fallback. Existing profiles and automation runs are unchanged.

## User-focused Dashboard redesign (2026-08-06)

- The main Dashboard now uses persistent articles, AICS-associated WordPress posts, approval queues, automation profiles, and automation runs rather than raw AI usage logs.
- It contains a welcome header, two primary actions, four product metrics, compact actionable attention items, an automation overview, six bounded recent-content items, and small helpful links.
- Raw provider/model/request-duration activity and the live Automation Health snapshot were removed only from the Dashboard; their repositories and diagnostic screens remain intact.
- Dashboard rendering performs no provider calls, health scan, dispatcher, worker, SEO analysis, or schedule calculation. WordPress post caches are primed for recent associated content.

## Content History AJAX search and filtering (2026-08-06)

- Content History remains based on native AICS-associated WordPress posts; temporary articles without a WordPress post remain excluded.
- `AICS_Content_History_Query_Service` owns normalization and bounded prepared queries for search, WordPress status, site-local creation dates, current post author, reliable article `source_type`, and pagination. Search covers post/article content and stored SEO fields plus exact numeric post/article IDs, without loading full bodies into PHP.
- Manual Studio and Automation source filtering is enabled because current article records reliably store `source_type` and their WordPress post association. AICS posts without an associated article remain visible as Legacy under All Sources.
- `assets/js/content-history.js` loads only on Content History and provides debounced authenticated AJAX results, stale-request cancellation, skeleton rows, URL state, pagination, reset, announcements, and browser history restoration. The native GET form and status links remain usable without JavaScript.
- Filters are read-only: they do not modify articles, posts, SEO, images, runs, or approvals and invoke no providers or automation work.

## Settings navigation consolidation (2026-08-06)

- The visible AI Content Studio submenu is now Dashboard, Create Content, Automations, Approvals, and Settings. Automation Runs, Content History, and System Status are Settings workspace sections rather than visible submenu pages.
- `AICS_Settings_Section_Registry` is the centralized allowlist for labels, descriptions, capabilities, and controlled render callbacks. Unknown sections fall back to General; inaccessible definitions are neither rendered nor linked.
- Settings uses a responsive two-column workspace with persistent internal navigation and a bounded content panel. General preserves the existing provider and featured-image forms. The three moved sections reuse their original controllers, repositories, action handlers, AJAX endpoints, diagnostics, and permission checks without copying logic.
- Old page slugs remain hidden WordPress admin routes and safely redirect to their Settings section while preserving allowlisted run-detail, run-filter, Content History filter, and pagination parameters. Nonces are not forwarded.
- Content History and System Status JavaScript load on Settings only for their matching section. System diagnostics are therefore not evaluated for unrelated Settings sections.

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

## Automation Foundation Task 2

Automation Foundation Task 2 is complete. A capability-protected **Automations** submenu at `admin.php?page=aics-automations` now appears between Create Content and Content History. Manual Studio remains at Create Content. The page uses the existing scoped SupportCandy-inspired AICS design tokens, shared headers/cards/forms/badges, responsive two-to-one-column layout, visible labels, and native WordPress controls.

The first UI release manages one immutable profile slug, `default`, named **Default Automation** by default. Viewing the page reads stored values or renders service defaults and never inserts a row. The first valid, authenticated, capability-checked, nonce-verified save creates the row through `AICS_Automation_Profile_Repository`; later saves update the same row. The unique database key and repository checks prevent duplicates.

`AICS_Automation_Profile_Service` contains defaults, cross-field validation, normalization, author/category checks, active/disabled mapping, default-profile create/update orchestration, and safe result codes. It does not render, read request globals, register hooks, redirect, schedule events, call providers, or create content. The admin page owns the dedicated `admin_post_aics_save_automation_profile` handler, explicit request-field extraction, nonce/capability enforcement, controlled notices, user-scoped five-minute validation-state transient, rendering, and safe redirects.

Administrator fields cover automation name, enable control, Autopilot/Approval mode, the allowlisted business profile, content cycle counts and defaults, duplicate lookback, FAQ/table/list flags, frequency/interval/volume, ordered weekdays, publish time and date range, three approval gates, derived publishing behavior, and validated post author/category defaults. Business text and line arrays have explicit limits, no HTML, at most 50 unique list items of 250 characters, and no emails or credentials. Content defaults are 5 ideas, 1 selected idea, professional tone, medium length, and 180-day lookback. Schedule defaults are weekly, interval 1, one post, Monday, and 10:00; weekly mode requires a day, times and dates are strict, and end date cannot precede start date.

The master checkbox maps only to `active` or `disabled` and preserves configuration and runtime fields. An active profile explicitly states that processing is not connected. Autopilot forces all approval flags false; Approval Workflow requires at least one gate. Publishing mode is limited to `draft`, `schedule`, or `publish` and derives post status `draft`, `future`, or `publish`; the page warns about automatic publishing. Authors must exist and be able to edit posts, categories must exist in the category taxonomy, and no email address is displayed.

Minimal progressive-enhancement JavaScript toggles weekly days, approval controls, and the automatic-publishing warning and prevents double submission. The full form remains usable without JavaScript and server validation remains authoritative. Validation failures preserve only sanitized, expected configuration for the current user; nonces, raw request arrays, unexpected fields, credentials, and runtime state are never preserved, and the transient is deleted after display.

Files created: `includes/services/class-automation-profile-service.php` and `includes/admin/class-automations-page.php`. Files modified: `ai-content-studio.php`, `includes/core/class-plugin.php`, `includes/admin/class-admin-menu.php`, `includes/admin/class-assets.php`, `includes/database/class-automation-profile-repository.php`, `assets/css/admin.css`, `assets/js/admin.js`, and `AI_CONTEXT.md`.

No automation scheduler, next-run calculation, automation execution, AI request, generated idea/article persistence, post creation, scheduling, publishing, approval queue, AJAX endpoint, or REST endpoint was added. Existing runtime timestamps and controlled error code are preserved during settings saves. Current limitations are the absence of all execution behavior and previews; active means configuration-enabled only.

## Automation Foundation Task 3

Automation Foundation Task 3 is complete. `AICS_Schedule_Calculator` is the authoritative, repository-independent calculation layer with `calculate_next_run()`, bounded `calculate_upcoming_runs()`, and `format_utc_for_site()`. It reads no request data, renders nothing, persists nothing, registers no hook, and never accesses providers or content services.

Schedule configuration is interpreted in `wp_timezone()` using `DateTimeImmutable` and converted to exact UTC `Y-m-d H:i:s` for database storage. Optional reference values are strict UTC database datetimes, and every result is strictly later than its reference. Calendar days, Monday-based calendar weeks, and calendar months are advanced locally rather than by fixed seconds, so the WordPress site timezone controls daylight-saving normalization. No PHP default timezone is changed.

Daily intervals are calendar-day intervals. Weekly schedules evaluate allowlisted weekdays Monday through Sunday and use Monday as the fixed week boundary; `interval` selects active weeks. Monthly schedules now use the visible, validated `monthly_day` setting (1–31), preventing drift. A missing day in a short month clamps to that month’s last date without changing the stored intended day. Start dates are inclusive lower bounds, end dates are inclusive through the configured local publish time, and exhausted ranges return `schedule_has_no_future_run` without changing profile status. `posts_per_period` remains configuration only: one automation cycle has one `next_run_at`; future queue/slot work will distribute multiple posts.

After a valid profile save, `AICS_Automation_Profile_Service` coordinates calculation and runtime persistence. Active profiles store the calculated UTC `next_run_at` through `update_runtime_fields()`. Disabled profiles store NULL. A valid profile with no remaining date stores NULL, remains active, and returns a controlled warning. `last_run_at`, `last_error_code`, creation audit fields, and unrelated configuration remain untouched. There is still no automation cron event.

The Automations page now includes an Upcoming Schedule card showing the site timezone, controlled localized summary, start/end bounds, scheduler-disconnected status, and five localized preview runs with secondary UTC values. Disabled profiles still receive a calculated preview but never persist a next run. Unsaved profiles show a save-first message. The status summary distinguishes active calculated schedules, active exhausted schedules, and disabled schedules. A visible **Publishing Day of Month** field is shown for monthly frequency; shorter-month behavior is explained.

Files created: `includes/services/class-schedule-calculator.php`. Files modified for Task 3: `ai-content-studio.php`, `includes/services/class-automation-profile-service.php`, `includes/database/class-automation-profile-repository.php`, `includes/admin/class-automations-page.php`, `assets/css/admin.css`, `assets/js/admin.js`, and `AI_CONTEXT.md`. Task 2 files remain part of the current uncommitted worktree.

Controlled failures cover invalid UTC references, frequency, interval, publish time, dates, monthly day, weekly days, exhausted ranges, and runtime persistence. Raw exceptions and date errors are never returned or rendered. Preview lists are limited to 10, stop at the inclusive end date, prevent duplicates, and are never stored.

No cron registration, run engine, run table, lock, retry, queue, AI call, idea/article generation, post creation, WordPress post scheduling, publishing, or approval execution exists. The existing usage-retention cron is unchanged.

## Automation Foundation Task 4

Automation Foundation Task 4 is complete. Database schema version `0.4.0` adds `{$wpdb->prefix}aics_automation_runs` through the existing activation/runtime `dbDelta()` path. Existing profile, usage-log, settings, post, and schedule data remain untouched, and matching schema versions still return before schema work.

The runs table contains a server-generated unique UUID, profile ID, nullable unique `active_profile_key`, controlled trigger/status/step fields, private 64-character lock token, UTC lock and retry timestamps, bounded attempt counters, controlled error code, lifecycle timestamps, and indexed operational fields. It has no foreign keys and stores no content or provider data. The unique active key equals the profile ID for `queued`, `running`, and `retrying`, then becomes NULL for `completed`, `failed`, or `cancelled`, allowing history while preventing concurrent active runs for one profile at database level.

`AICS_Automation_Run_Repository` provides `create_run()`, ID/UUID/active-profile reads, bounded `get_runs()`, `claim_run()`, `refresh_lock()`, `release_lock()`, `update_step()`, `schedule_retry()`, `mark_completed()`, `mark_failed()`, `mark_cancelled()`, `recover_expired_locks()`, and `table_exists()`. Trigger types are `scheduled`, `manual`, `retry`, and `system`. Statuses are `queued`, `running`, `retrying`, `completed`, `failed`, and `cancelled`. Workflow steps are limited to pending, idea generation/evaluation/selection/queueing, article generation/validation, post creation/scheduling/publishing, three approval waits, finalize, and complete.

UUIDs are generated only with `wp_generate_uuid4()`. Claims generate a fresh 64-character server-side token and use one conditional SQL UPDATE covering status, retry readiness, lock expiry, and remaining attempts. Attempts increment exactly once after a successful claim and never after failed claims. TTL is restricted to 60–3600 seconds, defaulting to 300. Normal reads omit `lock_token`; only `claim_run()` returns it to its internal caller, and it is never placed in HTML, URLs, notices, diagnostics, or logs.

Worker-owned step, retry, completion, failure, refresh, and release mutations require the exact current token, running status, and an unexpired lock. Releasing unfinished work returns it to `queued` so no unlocked run remains `running`. Completion clears locks/retry/error state, sets step complete, and releases the active key. Failure clears operational state and releases the active key. Cancellation is limited to queued/retrying runs.

Retries reuse the same run. A successful claim increments `attempt_count`; retry scheduling requires a future strict UTC datetime. Exhausted attempts become terminal `failed` with `retries_exhausted`. Bounded stale-lock recovery returns recoverable rows to queued with `stale_lock_recovered`, or fails exhausted rows and releases their active keys. All operational times use UTC `Y-m-d H:i:s` and never depend on browser/site display time.

The existing non-destructive lifecycle policy remains: deactivation and uninstall preserve run history and the table. System Status now performs one read-only Automation Run Table availability check without reading rows, UUIDs, locks, errors, or SQL details.

Files created: `includes/database/class-automation-run-repository.php`. Files modified for Task 4: `ai-content-studio.php`, `includes/database/class-installer.php`, `includes/services/class-system-check.php`, and `AI_CONTEXT.md`. Earlier uncommitted Tasks 2–3 files remain in the worktree.

The run table never stores API credentials or headers, prompts, business context, ideas, article text/titles, provider responses/errors, cookies, nonces, emails, IP addresses, stack traces, or SQL errors. Repository results expose only controlled codes. No run-management UI, cron registration, due-profile dispatcher, worker, scheduler, AI operation, content record, post mutation, retry cron, REST endpoint, or AJAX endpoint exists.

## Automation Foundation Task 5

Automation Foundation Task 5 is complete. One global WordPress Cron event now uses hook `aics_automation_dispatcher`, schedule key `aics_every_five_minutes`, and a 300-second interval. `AICS_Automation_Scheduler` registers the custom interval and callback, schedules the first event approximately one minute ahead, reports the next event, and clears all dispatcher events. It delegates all database orchestration to `AICS_Automation_Dispatcher` and never queries automation tables itself.

Fresh activation registers the custom schedule before ensuring the event. Existing active installations receive the event through a lightweight `init` existence check. `wp_next_scheduled()` prevents duplicates and an existing event is never moved on later requests. Deactivation clears the global dispatcher event along with the pre-existing usage cleanup event while preserving all profile, run, usage, setting, and content data. Uninstall clears the dispatcher event and deletes only the dispatcher lock option; the established persistent-data retention policy is otherwise unchanged.

The dispatcher uses atomic `add_option()` acquisition of the non-autoloaded `aics_automation_dispatcher_lock`. Its private value contains only a random 64-character token and UTC expiration time, expires after 240 seconds, supports stale-lock removal and reacquisition, and can be released only by the matching owner. Tokens are never returned in dispatch results, rendered, placed in URLs, or logged. A valid overlapping invocation exits with controlled code `dispatcher_already_running`.

Each invocation processes at most 10 due profiles by default and never more than 50. It first recovers up to 20 expired running locks through `AICS_Automation_Run_Repository`, then asks `AICS_Automation_Profile_Repository::get_due_profiles()` for active profiles whose non-null `next_run_at` is at or before the current UTC time, ordered oldest first. Each row is re-read and rechecked before dispatch. Disabled profiles, profiles without a next run, and profiles no longer due are not processed.

An existing queued, running, or retrying run is a safe skip and leaves `next_run_at` unchanged. Otherwise the schedule calculator finds the first cycle strictly after the dispatcher time, one queued `scheduled`/`pending` run is created through the run repository, and only then is `next_run_at` advanced through the profile runtime-field method with system user ID 0. `last_run_at` and all configuration/error fields remain unchanged. Database uniqueness is the final concurrent duplicate guard, and `active_run_exists` is handled as a safe skip.

Missed cycles use a collapse policy: however many intervals were missed, one run is queued and the following schedule is calculated from the current dispatcher time. This prevents backlog and future provider-usage storms. If an already stored due cycle belongs to a now-ended inclusive date range, it is still queued once and the successfully advanced `next_run_at` becomes NULL without changing the active profile status. Invalid schedules do not create runs or advance the profile.

Run creation and schedule advancement remain separate operations. If run creation fails, the profile is untouched. If advancement fails after creation, the dispatcher asks the run repository to cancel that newly created queued run, leaves the original due time intact, counts a controlled profile failure, and allows a later dispatcher invocation to retry. One profile failure does not stop the bounded batch.

The Automations page now reports **Scheduler Connected**, the next site-local dispatcher check, the profile's next content cycle, and enabled/disabled state, or a controlled missing-event warning. System Status adds an **Automation Scheduler** check: good when scheduled with normal WP-Cron, warning when scheduled while `DISABLE_WP_CRON` is enabled, and critical when missing. The existing daily usage-log cleanup check remains unchanged.

Files created: `includes/services/class-automation-scheduler.php` and `includes/services/class-automation-dispatcher.php`. Files modified for Task 5: `ai-content-studio.php`, `includes/core/class-plugin.php`, `includes/core/class-activator.php`, `includes/core/class-deactivator.php`, `includes/admin/class-automations-page.php`, `includes/services/class-system-check.php`, `uninstall.php`, and `AI_CONTEXT.md`.

Runs are queued but not processed. No worker, workflow-step execution, AI request, idea/article record, content generation, post creation/scheduling/publishing, approval action, notification, public endpoint, Action Scheduler integration, or external scheduler was added. Current usage metrics remain AI-request based and receive no dispatcher activity.

## Persistent Content Task 1

Persistent Content Task 1 is complete. Database schema version `0.5.0` adds `{$wpdb->prefix}aics_content_ideas` through the existing activation and runtime `dbDelta()` upgrade path. The installed version is advanced only after the usage, automation-profile, automation-run, and content-ideas tables all exist and each schema operation reports no database error. Existing active installations upgrade on `init` without reactivation; matching versions return before `dbDelta()`. Existing profiles, runs, next-run values, usage logs, settings, and WordPress posts remain intact.

The table stores a unique server-generated UUID; profile/run associations; controlled source, status, and search-intent values; sanitized planning content; derived normalized title/keyword and SHA-256 fingerprint; bounded score and priority; optional UTC publishing plan; approval/rejection/error audit state; and UTC creation/update audit fields. Secondary keywords and outlines use JSON arrays in LONGTEXT rather than PHP serialization or native MySQL JSON. The schema uses the WordPress prefix, charset, and collation, has no foreign keys, and includes the required operational indexes plus a compatible 191-character prefix index for normalized title.

`AICS_Content_Idea_Repository` owns `create()`, bounded `create_many()`, allowlisted `update()`, ID/UUID reads, bounded filtered `get_ideas()`, direct `count_ideas()`, run-scoped reads, recent duplicate lookup, conditional `transition_status()`, focused `update_error_code()`, and table availability checks. It renders nothing, reads no browser globals, registers no hooks, and performs no AI, article, post, approval-action, or worker behavior.

Source types are limited to `automation` and `manual`. Statuses are limited to `generated`, `pending_approval`, `approved`, `rejected`, `queued`, `article_generating`, `article_generated`, `completed`, `failed`, and `paused`. Search intent is limited to informational, commercial, transactional, or navigational. General updates cannot change workflow status, UUID, associations, decision/error state, or creation audit fields.

Titles are required plain text up to 250 characters; summaries are optional plain text up to 2,000; keywords/categories are bounded plain text; secondary keywords allow at most 20 items with case-insensitive deduplication; outlines preserve at most 30 ordered plain-text items. Invalid stored JSON safely decodes to an empty array. Score is validated from 0.00 through 100.00 and stored to two decimals. Priority is an integer from 0 through 1000. `planned_publish_at` accepts only an exact UTC `Y-m-d H:i:s` value or NULL; the repository never interprets browser-local time.

Normalized title and keyword are derived server-side by stripping markup, decoding entities, removing accents, lowercasing, converting punctuation/separators to spaces, collapsing whitespace, and enforcing database lengths. The deterministic fingerprint is `hash( 'sha256', normalized_title . '|' . normalized_keyword )`, is recalculated after title/keyword updates, is indexed but deliberately not unique, and supports reuse outside future lookback periods. Recent duplicate lookup requires a positive profile ID, a strict 64-character hexadecimal fingerprint, a caller-calculated UTC lower bound, and returns the newest match across all statuses, including rejected ideas.

Status changes use one conditional UPDATE whose WHERE clause contains both the idea ID and allowlisted expected statuses. Concurrent or stale transitions return `idea_transition_conflict`. The repository validates the target and relevant context but deliberately does not encode the full product transition graph; the future workflow service owns that policy. Approval records UTC approval time, allows system user 0, clears rejection audit/error state, and rejection records UTC rejection time plus a controlled code while clearing approval fields. Later non-decision states preserve historical approval/rejection audit fields unless a new explicit approval or rejection decision replaces them. Failed transitions preserve content and store only a controlled error code. `update_error_code()` does not change status.

System Status now includes a read-only **Content Ideas Table** availability check without reading ideas, fingerprints, or SQL details. The established retention policy remains unchanged: deactivation and uninstall preserve the content-ideas table and its rows. The table must never contain API keys/headers, full prompts, raw provider responses/errors, business-profile JSON, article content, credentials, cookies, nonces, lock tokens, stack traces, or filesystem paths.

Files created: `includes/database/class-content-idea-repository.php`. Files modified for Persistent Content Task 1: `ai-content-studio.php`, `includes/database/class-installer.php`, `includes/services/class-system-check.php`, and `AI_CONTEXT.md`.

No content ideas are generated automatically yet. There is no idea-generation service, evaluation/scoring algorithm, worker step, Idea Library UI, approval/rejection action, article record, article generation, post mutation, notification, REST/AJAX endpoint, billing, or licensing behavior.

## Persistent Content Task 2

Persistent Content Task 2 is complete. A second global five-minute WordPress Cron event, `aics_automation_worker`, uses the existing `aics_every_five_minutes` schedule and begins approximately two minutes after registration, offset from the dispatcher. The lightweight runtime ensure creates it for already-active installations, `wp_next_scheduled()` prevents duplicates, and the event is never moved on later requests. Activation ensures both events; deactivation and uninstall clear both while preserving usage cleanup and all persistent data.

`AICS_Automation_Worker` processes at most one run per invocation. `AICS_Automation_Run_Repository::get_claimable_runs()` performs bounded candidate discovery for only `pending` and `generate_ideas` queued/ready-retrying runs with no active lock and remaining attempts; `claim_run()` remains the atomic ownership boundary. The worker claims with a 900-second TTL, rechecks the run/profile, advances pending to generate_ideas, invokes the generator, refreshes ownership before final transitions, then advances successful work to evaluate_ideas and safely releases it back to queued. It never processes evaluate_ideas.

Missing profiles terminate with `automation_profile_missing`. Profiles no longer active are token-safely cancelled with `profile_not_active`, releasing the active-profile key without changing the profile or existing content. Existing ideas for a run are the idempotency proof: the worker skips AI, advances directly to evaluate_ideas, and releases the lock. This protects retries, stale-lock recovery, timeouts after persistence, and repeated cron requests.

`AICS_Automation_Idea_Generator` builds controlled prompt input from allowlisted business/content fields, calls the existing `AICS_AI_Engine`, validates the structured ideas array independently, limits processing to normalized `ideas_per_cycle` (1–20), removes deterministic within-batch duplicates, checks recent same-profile fingerprints for the configured UTC lookback, and persists through `AICS_Content_Idea_Repository::create_many()`. It never manages run locks or calls provider/HTTP code directly. The prompt requests JSON-only article-planning objects containing title, summary, primary/secondary keywords, controlled search intent, suggested category, and ordered outline. Provider response bodies and prompts are never stored or logged.

All stored automation ideas use the current profile/run IDs, `source_type=automation`, `status=generated`, score `0.00`, priority 0, and system audit user 0. At least one created idea is success; partial validation, duplicate removal, and persistence are reported by safe counts. Zero valid ideas, zero unique ideas, or persistence failure returns a controlled failure. `AICS_Content_Idea_Repository::build_fingerprint()` exposes the exact centralized normalization/fingerprint algorithm without duplicating it in the generator. A zero-day lookback disables historical lookup but not within-batch deduplication.

Retryable provider/network/rate-limit/format/persistence failures reuse the same run. Attempt 1 waits 15 minutes, attempt 2 waits 60 minutes, and later remaining attempts wait 240 minutes. Retry times are UTC, the step stays generate_ideas, and `schedule_retry()` clears the lock. Terminal configuration/profile failures and exhausted attempts use the existing token-guarded failure path and release the active key. No sleeping or one-off retry cron events are used.

Each actual automated provider attempt writes one existing `ai_request/generate_blog_ideas` usage row with success/failure, provider/model, controlled error code, run ID, duration, and only `idea_count` plus `source=automation` metadata. Prompts, business context, titles, keywords, response data, credentials, and tokens are excluded. These requests intentionally contribute to the existing AI-request metrics.

The Automations page now reports **Automation Engine Connected** and both next dispatcher and worker checks when both events exist. System Status adds an **Automation Worker** health check that requires the event and both run/idea tables, warns when WP-Cron is disabled, and is critical when the event or a table is missing.

Files created: `includes/services/class-automation-worker.php` and `includes/services/class-automation-idea-generator.php`. Files modified: `ai-content-studio.php`, `includes/ai/class-ai-request.php`, `includes/ai/class-ai-engine.php`, `includes/ai/class-prompt-engine.php`, `includes/providers/class-openai-provider.php`, `includes/database/class-automation-run-repository.php`, `includes/database/class-content-idea-repository.php`, `includes/services/class-automation-scheduler.php`, `includes/services/class-usage-logger.php`, `includes/services/class-system-check.php`, `includes/admin/class-automations-page.php`, `uninstall.php`, and `AI_CONTEXT.md`.

Idea evaluation, scoring, selection, approval routing, article persistence/generation, WordPress post creation/scheduling/publishing, notifications, public execution endpoints, and persistent Manual Studio ideas are not implemented. Runs stop queued at evaluate_ideas.

## Persistent Content Task 3

Persistent Content Task 3 is complete. Schema version `0.6.0` adds nullable UTC `evaluated_at` and an operational index to the existing content-ideas table through the activation/runtime `dbDelta()` path. Existing ideas, profiles, runs, logs, settings, next-run values, and WordPress posts remain intact, and matching versions continue to skip schema work.

`AICS_Content_Idea_Repository` now includes normalized `evaluated_at` output, conditional `store_evaluation()`, bounded evaluated/unevaluated run reads, and grouped run-status counts. Evaluation storage accepts only scores from 0.00–100.00, updates only generated ideas whose marker is NULL, records UTC evaluation/update timestamps and system audit user, and preserves content, status, and approval/rejection history.

`AICS_Automation_Idea_Evaluator` evaluates at most 20 generated, unevaluated automation ideas through the existing AI engine/provider. Candidate references are temporary `candidate_N` keys mapped to stored IDs server-side. The focused JSON-only prompt supplies allowlisted business context and candidate planning fields, requests every candidate once, prohibits new/rewritten ideas and reasoning, and uses five bounded dimensions: business relevance 0–30, audience value 0–25, content depth 0–20, originality 0–15, and search-intent fit 0–10. The stored total is calculated server-side and normalized to two decimals; provider totals are never trusted.

Valid partial scores are persisted, while invalid, unknown, repeated, or missing candidate evaluations leave their ideas unevaluated. The run is not routed until every generated idea has `evaluated_at`; a partial result schedules the established retry and the next attempt sends only remaining ideas. If everything is already evaluated or routed, no provider call or duplicate usage log occurs.

The internal first-version eligibility safeguard is `60.00`. Autopilot—or Approval mode without idea approval—sorts eligible ideas by score descending, priority descending, created time ascending, then idea ID ascending. It selects up to `selected_ideas_per_cycle`, system-approves and queues selected ideas, rejects eligible unselected ideas with `not_selected_for_cycle`, and rejects weaker ideas with `below_quality_threshold`. The run advances to `queue_idea`. Existing approved ideas are completed to queued and already routed ideas remain untouched, allowing deterministic recovery.

When mode is Approval and `require_idea_approval` is true, every eligible idea becomes `pending_approval` without applying the selected-count limit; weak ideas are rejected with `below_quality_threshold`, and the run advances to `waiting_idea_approval`. If no eligible idea exists, the run becomes failed with `no_eligible_ideas`. No approval interface is included.

The existing worker now discovers and claims `evaluate_ideas` in addition to generation steps, still processes one run per invocation, uses the 900-second bounded lock, refreshes ownership before final run transitions, reuses 15/60/240-minute retries, and never claims `queue_idea` or `waiting_idea_approval`. Missing/inactive profile policies remain unchanged. Runs remain queued after successful routing and are not completed.

Actual evaluation requests log one `ai_request/evaluate_content_ideas` usage row containing only provider/model, controlled status/error, run ID, duration, and bounded evaluated/eligible/source metadata. Skipped evaluation logs nothing. Dashboard total/success/failed AI metrics and Recent Activity include this operation under **Idea Evaluation**; article and WordPress-draft definitions are unchanged.

Files created: `includes/services/class-automation-idea-evaluator.php`. Files modified: `ai-content-studio.php`, `includes/database/class-installer.php`, `includes/database/class-content-idea-repository.php`, `includes/database/class-automation-run-repository.php`, `includes/services/class-automation-worker.php`, `includes/services/class-usage-logger.php`, `includes/database/class-usage-log-repository.php`, `includes/ai/class-ai-request.php`, `includes/ai/class-ai-engine.php`, `includes/ai/class-prompt-engine.php`, `includes/providers/class-openai-provider.php`, `includes/admin/class-dashboard-page.php`, `includes/admin/class-automations-page.php`, and `AI_CONTEXT.md`.

Idea approval UI, article persistence/generation, WordPress post creation/scheduling/publishing, notifications, public execution endpoints, and persistent Manual Studio ideas remain unimplemented. Runs stop at `queue_idea` or `waiting_idea_approval`.

## Persistent Content Task 4

Persistent Content Task 4 is complete. Database schema version `0.7.0` adds `{$wpdb->prefix}aics_articles` through the existing activation and runtime `dbDelta()` upgrade path. Fresh and already-active installations use the same schema, the installed version advances only after every required table exists without a reported schema error, and matching versions skip schema work. Existing profiles, runs, ideas, usage logs, settings, WordPress posts, and scheduling state remain intact.

The table stores a unique server-generated article UUID; a unique persistent idea association; derived profile, run, and source associations; title, excerpt, sanitized WordPress-safe HTML, SHA-256 content hash, and server-calculated word count; controlled workflow status; optional planned publishing time; nullable unique WordPress post association; bounded generation attempts; lifecycle/audit timestamps; controlled rejection/error codes; and audit users. Operational datetimes use exact UTC `Y-m-d H:i:s`. It uses the WordPress prefix, charset, and collation, permits multiple NULL post IDs, uses no foreign keys or native JSON, and contains the required unique and operational indexes.

`AICS_Article_Repository` owns idempotent `create_for_idea()`, normalized ID/UUID/idea/post reads, bounded filtered reads, direct counts, run-scoped reads, generated-content persistence preparation, atomic generation-attempt increments, expected-state transitions, focused WordPress post association, planned-publish updates, controlled error updates, and table checks. It renders nothing, registers no hooks, calls no AI provider, creates no post, and contains no publishing workflow.

The first persistent model deliberately supports one current article per idea. `idea_id` must be positive and unique. Placeholder creation accepts only approved, queued, or article-generating ideas; derives profile, run, source, and planned publication from the idea; generates a stable UUID using `wp_generate_uuid4()`; and starts queued with empty content/hash, zero words, and zero attempts. Repeated or concurrent creation returns the existing resource with successful code `article_already_exists`. Regeneration will update this row; revisions require a future versioning design.

Sources are limited to `automation` and reserved future `manual`. Statuses are queued, generating, generated, pending approval, approved, rejected, draft created, scheduled, published, failed, paused, and needs attention. Transitions validate source and target states and perform one expected-state conditional update. A future workflow service—not the repository—will enforce the complete product transition graph.

`store_generated_content()` exists for Persistent Content Task 5 but is not called here. It accepts only queued, generating, failed, or needs-attention records; requires structurally valid title, excerpt, and meaningful content within 250/500/100,000-character limits; calculates SHA-256 hash and approximate multibyte-safe word count server-side; and records generated timestamps/status. The hash uses normalized title, normalized excerpt, and already-sanitized content, is recalculated on replacement, and is not unique. Strict AI article validation and HTML sanitization must occur through `AICS_Post_Generator` before this method; no second sanitizer was introduced.

Generation attempts increment atomically up to the SMALLINT UNSIGNED limit and placeholders do not increment them. Approval records a controlled actor (system user 0 is valid) and UTC time while clearing rejection fields. Rejection records a controlled actor, UTC time, and sanitized internal code while clearing approval fields. Error fields accept only bounded `sanitize_key()` codes and never provider messages, prompts, content, SQL errors, paths, or prose.

Future post association is prepared but unused. It verifies the article and post, rejects cross-article duplicate assignment, preserves the same association, rejects reassociation, and can set only draft-created, scheduled, or published state plus relevant UTC timestamps. It never calls `wp_insert_post()` or updates a post. No broad generic update method can overwrite content and workflow fields together.

System Status includes a read-only **Content Articles Table** check without reading rows, hashes, content, or SQL details. Retention remains unchanged: deactivation, disabling automation, and uninstall preserve article rows/table. Storage excludes API keys, headers, full prompts, raw provider responses/errors, locks, credentials, cookies, nonces, stack traces, paths, and complete business-profile JSON; repository failures do not log article fields.

Files created: `includes/database/class-article-repository.php`. Files modified for Persistent Content Task 4: `ai-content-studio.php`, `includes/database/class-installer.php`, `includes/services/class-system-check.php`, and `AI_CONTEXT.md`. The preceding Task 3 changes remain preserved in the working tree.

No automated article is generated yet. No `queue_idea` run is processed, no article/idea approval interface exists, and automation creates, schedules, updates, or publishes no WordPress post. Persistent Manual Studio articles, revisions, synchronization, notifications, execution endpoints, billing, and licensing remain unimplemented.

## Persistent Content Task 5

Persistent Content Task 5 is complete. `AICS_Automation_Article_Generator` loads one selected persistent idea and its existing article placeholder, validates profile/idea/run associations, prepares only controlled business/content/idea fields, invokes the existing provider-independent AI engine, persists only validated article data, synchronizes the idea state, writes one safe usage record per real request, and returns controlled results. It does not own run locks, retries, run steps, rendering, browser input, SQL, post creation, scheduling, or publishing.

The worker now claims `queue_idea` and `generate_article` in addition to pending, idea generation, and idea evaluation. It still claims only one run and processes at most one queued/recoverable idea and one article provider request per invocation. Article work uses a 1,800-second execution lock, refreshed immediately before generation and after the provider returns. Waiting-approval, post-creation, scheduling, publishing, and completion steps are not claimable.

Queued ideas are selected within the current run/profile using priority descending, score descending, dated `planned_publish_at` values before NULL values, planned date ascending, creation time ascending, and idea ID ascending. Selection is bounded to one row. Pending-approval, rejected, completed, failed, paused, or unrelated ideas are never selected. Recoverable `article_generating` ideas use the same deterministic boundary.

Before generation, the idea moves atomically from queued to article-generating, `create_for_idea()` creates or reuses the unique article placeholder, the article moves from queued/failed/needs-attention to generating, and the run moves to `generate_article`. The database unique idea index remains the concurrency guard. Each real provider request increments the article generation-attempt counter exactly once; placeholder creation and idempotent recovery do not increment it.

`AICS_Prompt_Engine::create_automation_article_request()` builds the automation-specific request. It includes only allowlisted business context, content options, and persistent idea planning fields. The prompt requires one approved idea, configured tone/length, natural keywords, matching search intent, useful outline adherence, prohibited-topic/claim avoidance, configured CTA, conditional FAQs/lists, JSON-only output, and WordPress-safe HTML. The current strict safe-HTML format does not support tables, so table markup is explicitly prohibited even when a stored profile has the future-facing table preference enabled.

The expected response is `{ "article": { "title": "...", "excerpt": "...", "content": "..." } }` with no additional properties. `AICS_AI_Engine::generate_automation_article()` requires the article object and string fields, then calls the newly exposed `AICS_Post_Generator::validate_and_prepare_article()`. This reuses the existing sanitizer/limits and adds persistent-automation rejection for scripts, styles, iframes, forms, inputs, embeds, SVG, event handlers, inline CSS, unsafe URL schemes, incomplete placeholders, TODO/lorem ipsum, model/provider error text, and raw JSON-wrapper content. Manual Studio remains transient and its existing generation/edit/draft flow is unchanged.

Only sanitized title, excerpt, and HTML reach `store_generated_content()`. The repository calculates the SHA-256 content hash, approximate word count, generated/last-generation UTC timestamps, and generated status. After persistence, the generator verifies the hash, timestamp, status, and meaningful fields before atomically moving the idea from article-generating to article-generated. Ideas are not marked completed.

When another queued or recoverable idea remains, the worker returns the run to queued/`queue_idea` and releases the lock; the next invocation processes the next article. After all selected ideas have valid persistent articles, Approval Workflow with `require_article_approval=true` moves generated articles to pending approval and the run to `waiting_article_approval`. Autopilot, or Approval Workflow without article approval, system-approves generated articles with `approved_by=0` and UTC `approved_at`, then moves the run to `create_post`. Neither destination is processed in this task, and rejected or inconsistent articles stop automatic routing with a controlled failure.

Persisted valid article content is the idempotency proof. Existing generated/pending/approved/draft/scheduled/published content is reused, missing idea-state transitions are completed, partial deterministic routing resumes, and no new request, attempt, article row, content replacement, or usage row is produced. Interrupted generating placeholders reuse the same UUID and row.

Retryable provider, response, validation, persistence, and recoverable-transition failures keep the current placeholder, store only controlled error codes, retain `generate_article`, and use the existing 15/60/240-minute UTC retry schedule. No sleep or new run is used. Exhaustion marks the article and idea failed where applicable, marks the run failed, clears its active key/lock, and preserves records. Lock tokens, prompts, content, provider messages, and database errors are never logged or returned.

Each real automated article request logs the existing `ai_request/generate_article_draft` operation with success/failure, configured provider/model, controlled error code, article ID, duration, and only requested length, tone, and `source=automation`. Title, excerpt, body, idea fields, keywords, business context, prompt, raw response, credentials, and locks are excluded. Existing Dashboard queries therefore include automation in Total/Successful/Failed AI Requests and Articles Generated without changing the native WordPress Drafts Created correction.

The Automations page now states that selected ideas can be converted to persistent articles while WordPress post creation and publishing remain disconnected. No article rows or content are displayed and no article action controls were added.

Files created: `includes/services/class-automation-article-generator.php`. Files modified for Persistent Content Task 5: `ai-content-studio.php`, `includes/services/class-automation-worker.php`, `includes/database/class-automation-run-repository.php`, `includes/database/class-content-idea-repository.php`, `includes/ai/class-ai-request.php`, `includes/ai/class-ai-engine.php`, `includes/ai/class-prompt-engine.php`, `includes/providers/class-openai-provider.php`, `includes/services/class-post-generator.php`, `includes/admin/class-automations-page.php`, and `AI_CONTEXT.md`.

WordPress post creation from persistent articles is not connected yet. Article approval UI, article editing/queue interfaces, regeneration controls, revisions, scheduling, publishing, notifications, and persistent Manual Studio migration are not implemented.

## Persistent Content Task 6

Persistent Content Task 6 is complete. The product now has one unified **Approval Center** for the independent idea and article approval gates. The protected submenu uses slug `aics-approvals` and appears after Automations and before Content History. It uses the centralized management capability, existing scoped assets, controlled query values, bounded 20-item pagination, and separate Ideas/Articles tabs with direct repository counts.

`AICS_Approvals_Page` owns presentation and authenticated request handling. It renders escaped pending-item lists, summary cards, controlled review URLs, read-only detail views, dedicated POST-only approve/reject forms, entity-specific nonces, strict rejection selects, safe redirects, and controlled notices. Idea reviews show planning fields without fingerprints or normalized/internal data. Article reviews render stored content read-only through `wp_kses_post()` and provide no editor or regeneration control.

`AICS_Approval_Workflow_Service` derives all profile/run relationships from persistent entities, enforces current states and the per-cycle idea selection limit, applies atomic repository decisions, evaluates run readiness, resumes waiting runs, and terminates runs when every candidate is rejected. It does not read browser globals, render, verify nonces, redirect, build SQL, invoke AI, generate content, create posts, or send notifications.

Idea approval accepts pending ideas, records the administrator ID and UTC approval time, transitions through approved to queued while preserving approval audit fields, and never creates an article during the request. Selected/downstream states are approved, queued, article-generating, article-generated, and completed. Their count may not exceed normalized `selected_ideas_per_cycle`; excess pending ideas remain unchanged with `idea_approval_limit_reached`. Idea rejection codes are limited to `off_topic`, `duplicate_concept`, `low_business_value`, `not_for_target_audience`, and `not_suitable`.

When pending idea decisions remain, the run stays queued at `waiting_idea_approval`. When none remain and at least one idea was selected, it atomically advances to `queue_idea` without acquiring a worker lock and preserves `active_profile_key`. When every idea is rejected, the run becomes failed with `all_ideas_rejected` and releases its active key. The approval request never invokes the worker.

Article approval requires complete generated persistent content, records administrator/UTC audit fields, and changes pending approval to approved without creating a WordPress post. Rejection preserves title, excerpt, body, hash, timestamps, and idea/run associations. Article rejection codes are limited to `needs_revision`, `off_brand`, `fact_check_required`, `quality_issue`, and `not_suitable`.

When pending article decisions remain, the run stays at `waiting_article_approval`. When all decisions are resolved and at least one article is approved or downstream, it atomically advances to `create_post` while remaining queued and retaining its active key. When every article is rejected, it fails with `all_articles_rejected` and releases the key. WordPress post creation is still not connected.

`AICS_Automation_Run_Repository::transition_waiting_step()` permits only waiting-idea-approval to queue-idea and waiting-article-approval to create-post. `fail_waiting_run()` permits only the matching all-rejected terminal code. Both use conditional updates requiring a queued waiting run with no active valid lock; normal worker-owned methods still require their secret execution token. Neither human transition creates or exposes a lock.

All entity decisions use expected-state transitions. Repeated approvals do not overwrite audit timestamps, repeated readiness checks are safe, conflicting approve/reject submissions preserve the first decision, runs cannot move backward, and terminal failures preserve all content. Approval privacy storage is limited to administrator numeric ID, UTC timestamp, controlled reason, and workflow state; emails, IPs, user agents, POST payloads, nonces, cookies, and free-text comments are excluded.

The Automations page shows bounded pending idea/article counts, links to the Approval Center, and accurately states that approved articles wait at `create_post`. Scoped responsive styles provide horizontally scrollable tables, keyboard-focusable tabs/actions, readable review layouts, and mobile-stacked decision forms without requiring JavaScript.

Files created: `includes/admin/class-approvals-page.php` and `includes/services/class-approval-workflow-service.php`. Files modified for Persistent Content Task 6: `ai-content-studio.php`, `includes/core/class-plugin.php`, `includes/admin/class-admin-menu.php`, `includes/admin/class-assets.php`, `includes/admin/class-automations-page.php`, `includes/database/class-automation-run-repository.php`, `assets/css/admin.css`, and `AI_CONTEXT.md`.

Article/idea editing, regeneration, revision requests, reopening decisions, bulk actions, approval notifications, WordPress post creation, scheduling, and publishing remain unimplemented. No database schema change was required; schema version remains `0.7.0`.

The next planned task was Persistent Content Task 7 — Approved Article to WordPress Post Creation and `create_post` Worker Processing. It is documented as complete below.

## Persistent Content Task 7

Persistent Content Task 7 is complete. The automation worker now claims `create_post` runs and processes at most one approved persistent automation article per invocation. Articles are selected deterministically by dated `planned_publish_at` values first, then creation time and ID. The worker retains the existing atomic run lock and retry policy, keeps the run at `create_post` while another approved article needs delivery, and routes only after all required drafts exist.

`AICS_Automation_Post_Creator` validates persistent article, idea, run, profile, author, category, content, and association data without calling an AI provider. It reuses `AICS_Post_Generator` for sanitization and native `wp_insert_post()` handling, always creates the first persistent WordPress state as `draft`, applies a validated configured author and category/default-category fallback, stores the existing `_aics_generated_post` marker plus private persistent automation association metadata, and associates the post through `AICS_Article_Repository` as `draft_created`.

Post creation is idempotent across repository associations and a bounded `_aics_article_id` lookup. Valid existing posts are recovered, conflicting associations fail safely, and a newly created concurrent duplicate is moved to Trash when another valid association won. Downstream `draft_created`, `scheduled`, and `published` articles are never moved backward. Controlled failures use the existing 15/60/240-minute retry schedule; terminal article failures move approved articles to `needs_attention` while preserving generated and approval data.

After delivery, draft mode routes to `finalize`; schedule/publish modes route to `schedule_post`/`publish_post` when final approval is not required, or `waiting_publish_approval` when it is. None of those destination steps is processed here. No scheduling, publishing, final publish approval action, content generation, schema change, or new table was added.

Product decision: every automated article first becomes a native WordPress `draft`, including profiles configured for schedule or publish. Later workflow tasks may safely change that draft to `future` or `publish`, or retain it as a draft. This provides one consistent, recoverable delivery pipeline.

## Persistent Content Task 8

Persistent Content Task 8 is complete. The unified Approval Center now has Ideas, Articles, and Publishing tabs, with direct pending counts, bounded 20-item pagination, and a waiting-run list limited to queued `waiting_publish_approval` runs. Publishing review is read-only and shows the stored delivery mode, workflow details, associated draft summaries, planned dates, native WordPress edit links, explicit status text, and no complete article body.

Final publishing decisions remain a pure workflow boundary. `AICS_Approval_Workflow_Service` derives the run, profile, publishing mode, final-approval requirement, articles, and post IDs from persistent records. Before presenting active controls or accepting a decision it validates each article association and requires the native post, post type, draft status, `_aics_generated_post`, article ID, run ID, and profile ID metadata to agree. It never schedules, publishes, creates, edits, or regenerates a post and never invokes an AI provider.

Approval uses a run-specific POST nonce and atomically advances `waiting_publish_approval` to `schedule_post` or `publish_post` according to the server-side profile setting. The run remains queued, its active profile key is preserved, and no WordPress status changes during the request. The safe decline action uses a separate nonce and controlled reason, atomically cancels only an unlocked queued waiting run, clears its active profile key, and preserves the waiting step, drafts, articles, ideas, profile, attempts, and all generated content. Repeated and competing decisions are idempotent or fail with controlled conflict results.

`AICS_Automation_Run_Repository` now supports strictly filtered direct counts, waiting-publish transitions, and controlled waiting-run cancellation. `AICS_Article_Repository` provides a direct associated-post count for the publishing list. The Automations summary displays final publishing approvals and links to the Publishing tab while stating that WordPress scheduling and publishing execution remain Task 9.

No schema change was introduced. The first version uses the run `updated_at` transition time or `completed_at` cancellation time as its workflow audit. No administrator identity is persisted because the current usage log is operation-oriented and is not a suitable generic workflow-decision audit store. A dedicated decision-audit model may be added in a future version.

Task 9 remains responsible for WordPress scheduling, publishing, run finalization, profile runtime completion, and idea/article completion. None of those actions is implemented or processed by Task 8.

## Persistent Content Task 9

Persistent Content Task 9 is complete. `AICS_Automation_Delivery_Service` centrally validates persistent article/post associations and private automation ownership metadata, schedules existing drafts through `wp_update_post()` as native `future` posts, publishes existing drafts through `wp_update_post()` as native `publish` posts, recovers already delivered WordPress states, and synchronizes article states without creating posts or generating content.

The worker now claims `schedule_post`, `publish_post`, and `finalize` in addition to the earlier active steps. It still claims one run and performs at most one WordPress delivery action per invocation. Waiting approval and complete steps remain non-claimable. Schedule and publish stages retain the 600-second lock policy, recover from actual post state after interruptions, route to `finalize` only after all required articles are delivered, and preserve all content and ownership metadata.

Scheduling uses `AICS_Schedule_Calculator` and the WordPress site timezone. The first newly calculated slot is strictly after a five-minute UTC safety reference; subsequent articles use the next eligible calendar slot after the latest slot already assigned in the run. Valid distinct future `planned_publish_at` values are preserved on retry. The authoritative slot is stored in UTC before WordPress is updated; `post_date` is site-local and `post_date_gmt` is UTC. No fixed-day arithmetic is used, so site timezone and daylight-saving rules remain authoritative.

Each selected article receives one distinct configured publishing time. `selected_ideas_per_cycle` remains the per-run delivery count. `posts_per_period` is preserved but does not create multiple same-day time windows in Milestone 1; richer distribution remains a future Content Calendar capability.

Article final states continue to represent real delivery: `draft_created` for retained drafts, `scheduled` for future posts, and `published` for published posts. No generic completed article status was added. Focused repository synchronization preserves generated content, hashes, approvals, post creation time, and planned scheduling data. Missing posts, unsafe ownership, invalid delivery states, and exhausted/no-future schedule configurations fail with controlled codes and preserve the posts and content.

Finalization validates mode-specific article and WordPress readiness, completes only `article_generated` ideas associated with successfully delivered articles, updates the active profile's UTC `last_run_at` while preserving `next_run_at`, and then atomically completes the run. Completion sets status `completed`, step `complete`, clears locks/retry state and `active_profile_key`, and permits the dispatcher to create the next due cycle. Rejected, failed, paused, or unrelated ideas are not completed.

The retry budget is now stage-scoped. A successful unfinished lock release and final completion reset `attempt_count` to zero and clear `next_retry_at` and the run retry error. Repeated failures in the same stage continue to increment claims and use the existing 15/60/240-minute retry schedule up to `max_attempts`. Failed claims do not increment attempts. This prevents a healthy multi-invocation cycle from exhausting a lifetime claim counter while retaining bounded retries for each failing stage.

Content History already includes `draft`, `future`, and `publish` posts through the unchanged `_aics_generated_post` marker. Dashboard AI metrics and the native current-draft count remain unchanged. Automations now reports the connected core engine and bounded failed-run attention count. System Status treats missing dispatcher/worker events, required tables, or the delivery service as critical and explains the `DISABLE_WP_CRON` warning without executing delivery.

No new usage rows were added: the current logger has controlled AI, post-creation, and system-test operations but no clean generic delivery/completion audit vocabulary. This avoids distorting existing metrics. No schema, approval action, endpoint, notification, post editor, or run-history interface was added.

Files created: `includes/services/class-automation-delivery-service.php`. Files modified: `ai-content-studio.php`, `includes/services/class-automation-worker.php`, `includes/database/class-automation-run-repository.php`, `includes/database/class-article-repository.php`, `includes/admin/class-automations-page.php`, `includes/services/class-system-check.php`, and `AI_CONTEXT.md`.

## Milestone 1 — Core End-to-End Automation MVP

Milestone 1 implementation is complete. It includes persistent automation configuration, site-timezone scheduling, dispatcher/worker execution and locks, automated idea generation and duplicate prevention, scoring and routing, idea/article/final publishing approvals, persistent article generation, native draft staging, future-post scheduling, automatic publishing, mode-aware finalization, and future-cycle readiness. Static validation has passed; the configured live acceptance matrix below remains required before production sign-off.

The next planned milestone is **Milestone 2 — Operational Visibility, Recovery Controls, and Private Beta Hardening**. Its suggested first task is **Run History and Needs Attention Center**. It has not begun.

Current limitations: `posts_per_period` does not yet distribute multiple same-day slots; no run-history or recovery UI exists; no delivery notifications are sent; and full browser/cron end-to-end scenarios still require execution in a configured WordPress test site with valid profiles and provider access.

## Important Instruction for Codex

## Milestone 2 Task 1 — Read-only Automation Runs

Milestone 1 Core End-to-End Automation MVP was accepted on 2026-08-02. Milestone 2 — Operational Visibility, Recovery Controls, and Private Beta Hardening has started, and Task 1 is complete.

The protected `aics-automation-runs` submenu appears between Automations and Approvals. `AICS_Automation_Runs_Page` owns capability enforcement, strict GET allowlists, server-rendered tabs and filters, 20-row bounded pagination, escaped list/detail presentation, WordPress edit-link permission checks, and accessible empty states. It provides All Runs and Needs Attention views without executing cron or modifying records.

`AICS_Automation_Run_Inspector` loads runs, current profile names, centralized effective configuration, bounded idea/article collections, post associations, safe aggregate counts, attention reasons, and controlled status/step/error labels. List content totals use batch aggregate repository queries rather than per-row count queries. Needs Attention counts distinct non-completed, non-cancelled runs that are retrying/failed, have a controlled run error, have failed/needs-attention articles, or store an association to a missing WordPress post. Intentional cancelled runs remain available only in All Runs by default.

Filters accept only allowlisted run statuses, workflow steps, trigger types, current numeric profile IDs, and fixed 7/30/90-day UTC boundaries. Browser-supplied ordering and SQL fragments are not accepted. Details accept only an absolute numeric run ID and display actual lifecycle timestamps without fabricating step history.

Run details show a bounded configuration summary: workflow mode, cycle counts, article length, approval booleans, publishing mode, frequency/time, and valid author/category names. Complete snapshots, business context, credentials, prompts, and provider responses are never rendered. Snapshot runs are labeled Saved Run Configuration; legacy runs show Legacy Profile Fallback with a historical-accuracy notice.

Idea summaries exclude outlines, fingerprints, emails, and action controls. Article summaries exclude bodies and show status, word count, generation attempts, planned time, controlled errors, and post association state. Existing posts show status and capability-checked WordPress edit links; missing stored post IDs are clearly flagged.

The Dashboard now shows Runs Needing Attention with a link to the filtered view. Automations links to All Runs and Needs Attention and uses the same distinct attention definition. This task is strictly read-only: retry, resume, cancel, delete, repair, rerun, dispatcher, and worker controls are not implemented.

Files created: `includes/admin/class-automation-runs-page.php` and `includes/services/class-automation-run-inspector.php`. Files modified: `ai-content-studio.php`, `includes/admin/class-admin-menu.php`, `includes/admin/class-assets.php`, `includes/admin/class-dashboard-page.php`, `includes/admin/class-automations-page.php`, `includes/database/class-automation-run-repository.php`, `includes/database/class-content-idea-repository.php`, `includes/database/class-article-repository.php`, `assets/css/admin.css`, and `AI_CONTEXT.md`. No schema change was required; version remains `0.8.0`.

Current limitations: the database does not store per-step event history; current profile names may be unavailable; lists are deliberately bounded to 100 related ideas/articles in details; there are no operational mutation controls or notifications. Next planned task: **Milestone 2 Task 2 — Safe Retry, Resume, and Cancel Controls**. It has not begun.

## Milestone 1 Blocker Repair Task 2 — Article Safety Validation Diagnostics and Retry Recovery

The confirmed failure path was `AICS_AI_Engine::generate_automation_article()` → `AICS_Post_Generator::validate_and_prepare_article()`. The old validator returned only `unsafe_article_content` for both active markup and several incomplete-content patterns. It also treated raw-pattern matching as a broad decision boundary. After that response, the worker scheduled a retry without returning the empty persistent article from `generating`, leaving article 16 and idea 53 in their in-progress states.

`AICS_Article_Content_Validator` is now the shared generated-article validation policy for Manual Studio, automated generation, and generated article preparation before WordPress draft creation. It returns structured success/failure results, controlled codes, a sanitized article only on success, and bounded diagnostics containing categories, wrapper/sanitization booleans, and word counts—never content or matched fragments. The established `wp_kses()` allowlist remains unchanged: paragraphs, H2–H4, lists, strong/emphasis, blockquotes, and links with only `href`/`title`.

Raw content is pre-scanned for scripts, styles, embeds, forms/inputs/buttons, active document metadata, event handlers, scriptable URL schemes, dangerous data URLs, and SVG. These receive precise codes such as `unsafe_script_element`, `unsafe_event_handler`, `unsafe_url_scheme`, and `unsafe_svg_content`. Markdown fences and safe document wrappers are removed before sanitization. Harmless wrappers, unsupported elements, and unsupported attributes may be removed, with only sanitized content accepted. Meaningful text, placeholders, raw JSON wrappers, field lengths, the 100,000-character maximum, and broad short/medium/long minimums of 200/350/600 words are validated.

Retry policy now preserves the existing article ID and UUID. A retryable article-generation failure atomically moves an empty `generating` placeholder to `queued`, stores the precise error code, keeps its idea at `article_generating`, and leaves the run at `generate_article` for the existing 15/60/240-minute schedule. Deterministic selection prioritizes empty queued placeholders owned by `article_generating` ideas, then legacy empty `generating` placeholders, then new queued ideas. No provider call occurs during status repair, and `generation_attempts` remains incremented exactly once immediately before each real generation request. Successful storage clears the article error through the existing repository write and advances the idea to `article_generated`.

When retry handling is terminal, an empty queued/generating article moves to `needs_attention` with the latest precise code. The run follows the existing terminal failure path, clears its active profile key and lock state, and preserves the related idea at `article_generating` for diagnosis. No activation migration or automatic mutation of run 26/article 16/idea 53 is performed.

Automation usage logs retain one row per real provider request and may add only an allowlisted `validation_category` alongside source, requested length, and tone. Article content, title, prompt, provider response, business context, credentials, and locks remain excluded.

Files created: `includes/services/class-article-content-validator.php`. Files modified: `ai-content-studio.php`, `includes/services/class-post-generator.php`, `includes/ai/class-ai-engine.php`, `includes/services/class-automation-article-generator.php`, `includes/services/class-automation-worker.php`, `includes/services/class-usage-logger.php`, `includes/database/class-article-repository.php`, `includes/database/class-content-idea-repository.php`, and `AI_CONTEXT.md`. No schema change was required; schema version remains `0.8.0`. Immutable run configuration is unchanged.

Current limitations: no regeneration UI, retry button, run-history page, or raw-content diagnostic storage exists. The next repair task has not been started.

## Milestone 1 Blocker Repair Task 1 — Immutable Run Configuration

The confirmed defect was reproduced from persistent evidence: run 25 completed while article 15 and WordPress post 73 remained a draft, despite final publishing approval having been enabled during that cycle. Later stages were reading the mutable current profile, allowing later profile edits to bypass the run's original approval rule.

New automation runs now store a nullable `configuration_snapshot` LONGTEXT column in `aics_automation_runs`; database schema version is `0.8.0`. The JSON snapshot has `snapshot_version` 1 plus controlled `mode`, `business_context`, `content_settings`, `schedule_settings`, `workflow_rules`, and `publishing_settings` sections. It excludes credentials, nonces, prompts, responses, cookies, locks, emails, runtime errors, and profile runtime fields.

The dispatcher validates and normalizes the due profile with `AICS_Automation_Profile_Service`, encodes the controlled snapshot with `wp_json_encode()`, validates it before insertion, and does not create a run or advance `next_run_at` when snapshot creation fails. `AICS_Automation_Run_Repository` centrally decodes snapshots through section/key allowlists and returns effective configuration with an explicit `snapshot` or `profile_fallback` source. Empty legacy snapshots use the current profile and report `legacy_profile_fallback=true`; malformed non-empty snapshots fail closed. Existing completed runs are not rewritten, and precise historical configuration cannot be recovered for legacy runs.

The worker overlays only the effective run configuration onto live profile identity/runtime data, so idea generation/evaluation, article generation and routing, post author/category selection, final-approval routing, scheduling, publishing, and finalization use the saved cycle settings. Live profile status and runtime updates remain live policy. Profile edits are allowed and apply only to future cycles; the Automations page explains this behavior.

Publishing approval lists are selected solely by queued `waiting_publish_approval` run state and derive their delivery label from effective run configuration. Publishing review and approval validate the saved approval requirement and publishing mode and route only to `schedule_post` or `publish_post`; browser input is not authoritative. Legacy waiting runs use the documented fallback.

Finalization performs a second centralized readiness preflight before updating `last_run_at` or completing a run. It requires persistent non-rejected articles, valid owned WordPress posts, mode-appropriate article/post states, and prevents schedule/publish completion while final approval remains incomplete and posts are drafts. Controlled failures include `run_has_no_articles`, `run_has_no_posts`, `run_not_ready_to_finalize`, and `final_publish_approval_not_completed`.

Files created: none. Files modified: `ai-content-studio.php`, `includes/database/class-installer.php`, `includes/database/class-automation-run-repository.php`, `includes/services/class-automation-dispatcher.php`, `includes/services/class-automation-worker.php`, `includes/services/class-approval-workflow-service.php`, `includes/admin/class-approvals-page.php`, `includes/admin/class-automations-page.php`, and `AI_CONTEXT.md`.

Current limitations: legacy runs cannot recover historical configuration and deliberately use current-profile fallback; no existing completed run is repaired automatically. The unsafe article-content validator remains unchanged. The next repair task is **Milestone 1 Blocker Repair Task 2 — Article Safety Validation Diagnostics and Retry Recovery** and has not begun.

Before making any code changes:

1. Read `AI_CONTEXT.md`.
2. Inspect all current project files.
3. Summarize the current state.
4. Complete only the requested task.
5. Do not continue into another phase automatically.

## Milestone 2 Task 2 — Safe Retry, Resume, and Cancel Controls

Milestone 2 Task 1 remains complete. Task 2 adds capability-protected, POST-only recovery controls to Automation Run details without adding any worker, dispatcher, provider, post mutation, or approval mutation to the browser request.

`AICS_Automation_Run_Control_Service` coordinates a validated action, current run state, recovery plan, strict compare-and-swap transition, and action audit inside one InnoDB transaction. An audit failure rolls back the run transition. It never reads request globals, verifies nonces, renders HTML, or invokes processing. `AICS_Automation_Run_Recovery_Planner` is read-only and centralizes the actual executable steps, retryable error allowlist, UTC lock classification, immutable configuration/legacy fallback checks, approvals, article state, and WordPress ownership checks. It derives every target status and step server-side. `AICS_Automation_Run_Action_Repository` inserts only allowlisted successful action records and loads at most 50 records (20 by default) newest first with prepared SQL.

Database schema version is `0.9.0`. The new `${wpdb->prefix}aics_automation_run_actions` table stores an unsigned ID, run ID, controlled action type, previous/resulting status and step, previous/resulting attempt counts, nullable administrator user ID, nullable controlled reason code, and UTC creation time. It indexes run, action, actor, and creation time. It stores no request, nonce, IP, user agent, email, credentials, lock token, prompts, content, provider response, or raw error. The table follows the existing uninstall-data policy: persistent plugin tables are preserved because destructive uninstall cleanup remains deferred; deactivation never removes it.

Retry is limited to `retrying` or `failed` runs at real executable steps with a current allowlisted retryable run or supported article error. Retry preserves the safe step, snapshots, children, posts and maximum attempts; clears retry/expired-lock fields; preserves attempts for `retrying`; and resets attempts only for administrator-authorized failed recovery. Empty failed/needs-attention article placeholders are requeued transactionally at article generation so the existing worker can recover them without duplication. Previous controlled error evidence remains on the run and in the audit reason.

Resume supports stale unlocked/expired-lock `running` runs, queued executable runs carrying an expired lock, completed idea approval waits with no pending ideas and at least one eligible idea, and completed article approval waits with no pending articles and at least one eligible article. It preserves attempts and selects `queue_idea`, `create_post`, or the same executable step server-side. A run still at publishing approval is not treated as approved because the current approval service advances approved runs immediately; guessing would bypass approval.

Cancel supports recognized non-terminal queued/retrying/stale-running and approval-waiting states. It preserves the diagnostic step, errors, snapshot, children, approvals, profile schedule and every WordPress post; sets cancellation completion time when absent; releases `active_profile_key`; and clears retry/expired-lock fields. The UI and server require explicit acknowledgement that existing drafts, future posts, and published posts are unchanged.

Every manual transition matches run ID, expected status, expected step, `updated_at`, and absence/expiry of a conflicting lock. Valid unexpired locks block all actions. Expired locks are cleared only inside an eligible action. Retry/resume atomically restore the profile reservation only when no other active run owns it; the unique active-profile key remains the database backstop. Stale/double submissions fail without audit as `run_state_changed`.

Approval waits are inspected from persistent decision states and are never changed by controls. At post-related steps, existing associations are checked through `AICS_Automation_Delivery_Service`; missing required posts or conflicting ownership refuse recovery, while an absent post at `create_post` remains a valid idempotent creation state. No control changes a WordPress post.

Run Details now presents eligible Retry Now, Resume Run, and Cancel Run forms with action/run-specific nonces, expected-state revisions, accessible post-preservation acknowledgement, controlled notices, and explanatory text. Administrator Actions shows a bounded read-only history with action, state/attempt changes, display name or Deleted User, controlled reason, and site-local date. Administrator email is never shown. When no action is safe, a controlled terminal, active-worker, pending-approval, or manual-review explanation is shown.

Files created: `includes/services/class-automation-run-control-service.php`, `includes/services/class-automation-run-recovery-planner.php`, and `includes/database/class-automation-run-action-repository.php`. Files modified: `ai-content-studio.php`, `includes/core/class-plugin.php`, `includes/database/class-installer.php`, `includes/database/class-automation-run-repository.php`, `includes/database/class-article-repository.php`, `includes/services/class-automation-run-inspector.php`, `includes/admin/class-automation-runs-page.php`, `uninstall.php`, and `AI_CONTEXT.md`.


## Milestone 2 Task 3 — Automation Health Monitoring and Stale Run Detection

Milestone 2 Task 2 is complete and its safe controls were validated with disposable cancellation and failed-retry runs, including compare-and-swap double-submit protection, profile reservation handling, attempt auditing, and active-lock refusal. Task 3 adds centralized read-only automation health monitoring. It never retries, resumes, cancels, clears locks, releases reservations, modifies profiles, changes posts, executes the dispatcher or worker, or calls an AI provider. Recovery remains exclusively available through the Task 2 Run Details controls.

`AICS_Automation_Health_Monitor` loads bounded candidates, profiles, batch post associations, and cron state; invokes the detector; calculates `healthy`, `warning`, `critical`, or `unknown`; and persists a safe snapshot. A five-minute option lock prevents concurrent scans. Queries are capped at 100 candidates, findings at 50, and affected run/profile IDs at 20, with truncation disclosed.

`AICS_Automation_Stale_Run_Detector` owns UTC defaults: 10 minutes for running without a valid lock, 30 minutes for queued executable runs, 15 minutes after retry time, 30 minutes for overdue profiles without runs, and two hours for snapshot freshness. Approval waits and recent states are excluded.

Findings cover stale/lockless running, stalled queued, overdue or malformed retries, terminal/failed reservation conflicts, multiple active runs, reservation mismatch, overdue profiles, missing/conflicting posts, unknown active steps, incomplete completion, and missing dispatcher/worker/health cron events. Failed runs normally release reservations, so retention is critical. Ownership reuses `AICS_Automation_Delivery_Service`.

The hourly `aics_automation_health_check` hook uses the existing scheduler, is unique, clears on deactivation, and is recreated on activation/initialization. The non-autoloaded `aics_automation_health_snapshot` schema 1 contains only controlled bounded operational data and no credentials, lock tokens, prompts, responses, content, business context, personal request data, raw SQL/provider errors, or snapshots.

Automation Runs exposes a mutation-free Health view. Run Details shows advisory findings before authoritative controls; Needs Attention includes affected IDs without duplicates. Dashboard and System Status use the cached snapshot. One capability-protected critical notice appears only on AI Content Studio pages.

Files created: `includes/services/class-automation-health-monitor.php` and `includes/services/class-automation-stale-run-detector.php`. Files modified: `ai-content-studio.php`, scheduler, run/profile/article repositories, run inspector/page, Dashboard, System Status, admin CSS, uninstall cleanup, and `AI_CONTEXT.md`. No schema change was required.

Current limitations: findings are advisory snapshots; scans are deliberately bounded; browser responsive/console and full cron/provider regressions remain manual. Next planned task: **Milestone 2 Task 4 — Private Beta Diagnostics Export and Support Bundle**. It has not begun.

## Locked V1 Roadmap and V1 Task 1.1 — Shared Featured Image Foundation

The locked V1 roadmap is: (1) Featured Image Pipeline, (2) SEO and Publishing Metadata, (3) Manual and Automation Parity, and (4) feature-completion regression. V1 Task 1 has started. Task 1.1 is implemented as a persistence and lifecycle foundation only.

`AICS_Featured_Image_State` is the centralized policy for `not_requested`, `pending`, `generating`, `uploaded`, `attached`, `retrying`, `failed`, `needs_attention`, and `skipped`. Unknown values normalize to `needs_attention`, never to success. Allowed transitions are: `not_requested` to `pending` or `skipped`; `pending` to `generating`, `skipped`, or `failed`; `generating` to `uploaded`, `retrying`, `failed`, or `needs_attention`; `retrying` to `generating`, `failed`, or `needs_attention`; `failed` to `pending`, `needs_attention`, or `skipped`; `needs_attention` to `pending` or `skipped`; `uploaded` to `attached`, `retrying`, `failed`, or `needs_attention`; and `skipped` to `pending`. `attached` is terminal. `skipped` is normally terminal but supports a future explicit administrator return to `pending`.

The shared article record now stores `featured_image_required`, `featured_image_status`, `featured_image_attachment_id`, `featured_image_prompt`, `featured_image_alt_text`, `featured_image_provider`, `featured_image_model`, `featured_image_attempts`, `featured_image_generated_at`, `featured_image_uploaded_at`, `featured_image_attached_at`, and `featured_image_last_error_code`. Existing rows default safely to not required, `not_requested`, zero attempts, and no attachment. Image timestamps follow the project's UTC MySQL timestamp convention. Indexes cover image status and attachment ID. Database schema version is `0.10.1` after the Task 1.1 migration blocker repair.

`AICS_Article_Repository` exposes focused read, initialization, compare-and-swap transition, idempotent attachment association, and controlled error-recording operations. Statuses and changed fields are allowlisted, IDs use absolute-integer validation, and state changes match the expected stored status. Re-associating the same attachment is a successful no-op; a different attachment cannot overwrite an attached image. A future replacement requires a separate explicit controlled operation.

Controlled future pipeline errors are limited to provider request failure, timeout, rate limiting, invalid response, unsupported format, download failure, file-validation failure, Media Library upload failure, attachment-persistence failure, assignment failure, ownership conflict, and retry exhaustion. Only codes are persisted; unknown codes receive a generic safe label. Raw provider messages and database errors are not stored.

No provider request, image generation, download, Media Library upload, featured-image assignment, post mutation, image UI, or automation worker image step exists in Task 1.1. No API key, authorization header, temporary URL, binary/base64 image, provider response, nonce, cookie, administrator email, IP address, or browser user agent is stored by this foundation.

Manual Studio currently uses transient request state and creates a WordPress draft directly; it does not persist Manual articles in `aics_articles`. Automation articles do use `aics_articles`. Task 1.1 deliberately creates one shared schema, state policy, and repository rather than parallel Manual/Automation models. Persisting Manual Studio through that shared model remains V1 Task 3.

File created: `includes/services/class-featured-image-state.php`. Files modified for Task 1.1: `ai-content-studio.php`, `includes/database/class-installer.php`, `includes/database/class-article-repository.php`, and `AI_CONTEXT.md`. Deactivation and uninstall data policy are unchanged and continue to preserve plugin tables and image metadata.

Current limitations: there is no image provider configuration or provider abstraction, generation, media upload, attachment ownership verification against WordPress, thumbnail assignment, regeneration, approval, or UI. Next task: **V1 Task 1.2 — Image Provider Settings and Provider-Agnostic Generation Interface**. It has not begun.

## V1 Task 1.1 Migration Blocker Repair

The Task 1.1 migration blocker was caused by the two featured-image `KEY` declarations being placed inside the `aics_content_ideas` `CREATE TABLE` SQL during an earlier migration attempt, while their columns existed only in the `aics_articles` definition. `dbDelta()` therefore attempted to add `featured_image_status` and `featured_image_attachment_id` indexes to the ideas table and MySQL rejected both because those columns were not defined there.

The corrected installer keeps all 12 `featured_image_*` columns and both matching indexes exclusively in the articles-table SQL. The content-ideas SQL retains its prior columns and indexes and contains no featured-image declaration. Every `KEY` in both definitions now references a column in its own `CREATE TABLE` statement.

Database repair schema version `0.10.1` ensures a site whose option already advanced to the affected `0.10.0` state automatically re-enters the existing `dbDelta()` upgrade on the next WordPress `init`; no manual option edit is required. The version is written only after the installer verifies all 12 strict article column names and verifies exactly one single-column `featured_image_status` index and one single-column `featured_image_attachment_id` index on `aics_articles`. Missing columns, missing indexes, or another failed `dbDelta()` leave the stored version behind so a later page load retries safely. Repeated loads, activation, and reactivation remain idempotent.

Files repaired: `ai-content-studio.php`, `includes/database/class-installer.php`, and `AI_CONTEXT.md`. The state service and article repository remain unchanged by this blocker repair. No ideas, articles, runs, approvals, WordPress posts, attachments, or thumbnail metadata are deleted or rewritten.

Validation used an exact disposable missing-index simulation at stored version `0.10.0`. A normal WordPress bootstrap restored the index, advanced to `0.10.1`, left 12 article image columns and exactly two requested indexes, and left content ideas with zero featured-image columns or indexes. Existing article defaults remained safe. Full PHP lint, repeated bootstrap, activation/deactivation, admin-page rendering, attachment/thumbnail counts, and the WordPress debug log were also checked.

Task 1.1 is complete after this blocker repair. V1 Task 1.2 has not begun.

## V1 Task 1.2 — Image Provider Settings and Provider-Agnostic Generation Interface

Task 1.1 remains complete and tested at database schema `0.10.1`. Task 1.2 adds the shared settings and provider-abstraction portion of the Featured Image Pipeline only. It does not connect image generation to Manual Studio, automation workers, articles, WordPress posts, or media handling.

Global defaults are stored as a controlled `featured_images` section inside the existing non-autoloaded `aics_settings` option: generation disabled, provider `openai`, model `gpt-image-2`, landscape aspect ratio, standard quality, and PNG output. Supported aspect ratios are landscape, square, and portrait; qualities are standard and high; formats are PNG, JPEG, and WebP. Existing text settings writes preserve the nested image section.

The OpenAI image adapter reuses the existing `openai_api_key` credential accessor. No duplicate credential field or option exists, and the credential is never returned by the image adapter, factory, request, result, capabilities, or rendered image-settings section. The existing text-provider Test Connection is unchanged.

`AICS_Image_Provider_Interface` defines provider identity, display name, configuration state and validation, controlled capabilities, and normalized generation. `AICS_Image_Provider_Factory` uses a strict internal provider-key-to-class registry and rejects unknown keys; it never derives a class or path from browser data. `AICS_OpenAI_Image_Provider` reports supported models, ratios, quality levels, formats, prompt-revision support, and output types. Its `generate()` method returns the controlled failure `image_generation_not_implemented` and performs no HTTP request.

`AICS_Image_Generation_Request` is an immutable, non-persisting value object with a positive optional article ID, bounded plain-text prompt, strict provider/model identifiers, and allowlisted aspect ratio, quality, and format. It accepts no credentials, headers, URL, or filesystem path. `AICS_Image_Generation_Result` structurally represents controlled success or failure metadata without storing binary/base64 content, signed URLs, authorization data, or raw responses. Unsafe URL/data references are rejected.

`AICS_Featured_Image_Settings` owns defaults, normalization, strict provider-capability validation, persistence of the nested option section, effective settings, and controlled configuration status. It never renders HTML, reads request globals, calls a provider, or exposes credentials. The Settings page owns capability and nonce enforcement and presents a separate Featured Images form, credential-reuse explanation, and controlled status. Invalid provider, model, ratio, quality, or format submissions do not change image settings.

Files created: `includes/providers/interface-image-provider.php`, `includes/providers/class-image-provider-factory.php`, `includes/providers/class-openai-image-provider.php`, `includes/services/class-image-generation-request.php`, `includes/services/class-image-generation-result.php`, and `includes/services/class-featured-image-settings.php`. Files modified: `ai-content-studio.php`, `includes/services/class-settings.php`, `includes/admin/class-settings-page.php`, and `AI_CONTEXT.md`. No database schema or asset change is required.

Current limitations: there is no real image request, prompt builder, generation service, download, base64 decoding, filesystem write, Media Library upload, attachment creation, featured-image assignment, regeneration, approval, Manual control, or automation image step. Next task: **V1 Task 1.3 — Featured Image Prompt Builder and Image Generation Service**. It has not begun.

## V1 Task 1.3 — Featured Image Prompt Builder and Real Image Generation Service

V1 Task 1.2 is complete and tested. Task 1.3 completes the shared one-shot generation service and administrator settings test only; it does not connect image generation to Manual Studio, automation, articles, posts, or WordPress media.

`AICS_Featured_Image_Prompt_Builder` deterministically builds a professional provider prompt without another model call. The title is required. Optional summary, business name, category, and future article content are converted to plain text, stripped of markup, whitespace-normalized, and combined within a 2,000-character source limit. The final prompt is limited to 4,000 characters. Article values are explicitly delimited as untrusted subject matter, and known instruction-injection phrases concerning prior instructions, credentials, hidden text, and output changes are removed. Visual style is selected only from editorial, photorealistic, modern illustration, minimal 3D, and flat illustration; unknown effective values fall back to editorial. The saved setting remains strict and rejects unknown submitted styles.

`AICS_Featured_Image_Generation_Service` loads effective server-side settings, validates the configured provider, builds the prompt, creates the existing provider-neutral request object, resolves the existing factory, and returns the normalized result. The OpenAI adapter posts to the centralized `https://api.openai.com/v1/images/generations` endpoint using the existing securely stored API key, the centralized `gpt-image-2` model, a 180-second timeout, SSL verification, and exactly the fields `model`, `prompt`, `size`, `quality`, `output_format`, and `n`, where `n` is always 1. Ratios map square to `1024x1024`, landscape to `1536x1024`, and portrait to `1024x1536`; standard quality maps to `medium` and high maps to `high`; formats remain strict PNG, JPEG, or WebP.

Successful responses require a 2xx status and a non-empty `data[0].b64_json`. Base64 decoding is strict, encoded and decoded lengths are bounded, and decoded files may not exceed 25 MB. Controlled failures cover missing configuration, authentication, permission, rate limiting, timeout, temporary network/5xx failure, invalid response, invalid base64, excessive size, unsupported format, file validation, temporary creation, and unsupported generation. Only rate limiting, timeouts, network failures, and 5xx failures are retryable. Raw provider bodies are never exposed.

`AICS_Temporary_Image_File` creates an `aics-image-` file only under the WordPress temporary directory, adds the controlled requested extension, writes bytes completely with a binary-safe locked write, and validates existence, non-empty size, the 25 MB limit, actual image data, positive dimensions, supported MIME, and requested-format/MIME agreement. The result owns this internal-only object. Callers have an explicit cleanup method; a destructor is only a secondary safeguard. Failed writes and validation delete the partial file.

Settings → Featured Images now includes the controlled visual-style selector and an administrator-only, POST-only Test Image Generation form with an action-specific nonce and a sanitized 250-character topic. The handler loads provider settings and credentials only on the server, generates exactly one image, validates it, and always explicitly deletes it before redirecting. A two-minute user-scoped transient contains only a controlled result code and safe provider/model/dimension/format/status metadata, is consumed once, and never contains the prompt, base64, image bytes, API key, response, or temporary path. The success notice confirms validation and deletion; failures show only controlled messages and codes.

The centralized usage logger now accepts `image_generation_test` and records only event/operation, success or failure, controlled provider/model/error code, duration, test type, safe HTTP status, and the logger's existing user convention. It never records prompts, image content, credentials, authorization headers, remote response bodies, or temporary paths.

Files created: `includes/services/class-featured-image-prompt-builder.php`, `includes/services/class-featured-image-generation-service.php`, and `includes/services/class-temporary-image-file.php`. Files modified: `ai-content-studio.php`, `includes/providers/class-openai-image-provider.php`, `includes/services/class-featured-image-settings.php`, `includes/services/class-http-client.php`, `includes/services/class-image-generation-result.php`, `includes/services/class-usage-logger.php`, `includes/admin/class-settings-page.php`, and `AI_CONTEXT.md`. No schema or asset change is required.

Current limitations: generated images are temporary and immediately deleted by the settings test. There is no Media Library upload, attachment creation, featured-image assignment, persistent preview/history, article image-state transition, Manual Studio control, automation worker step, approval, or regeneration. Next task: **V1 Task 1.4 — WordPress Media Library Upload and Featured Image Assignment Service**. It has not begun.

## V1 Task 1.4 — WordPress Media Library Upload and Featured-Image Assignment Service

V1 Task 1.3 is complete and tested. Task 1.4 adds the persistent shared Media Library and assignment pipeline plus a disposable-draft administrator test. It does not connect the pipeline to Manual Studio or automation workers.

`AICS_Media_Library_Image_Service` accepts only an existing `AICS_Temporary_Image_File`, revalidates its size, extension, actual MIME, raster dimensions, and controlled PNG/JPEG/WebP format at the media boundary, creates a sanitized `aics-featured-{article-id}-{short-uuid}` filename, and uploads through `media_handle_sideload()`. It creates attachment metadata through WordPress, persists bounded deterministic alt text, and removes a newly created attachment when its required ownership metadata cannot be verified.

Generated attachments store `_aics_generated_image`, `_aics_image_source`, article ID/UUID, available run/profile/post IDs, controlled provider/model, and a SHA-256 prompt hash. They never store the complete prompt, base64, bytes, API key, authorization data, provider response, browser ownership data, or temporary path. Alt text defaults to `Featured image for {article title}`, is plain text with normalized whitespace, and is limited to 250 characters in `_wp_attachment_image_alt`.

`AICS_Featured_Image_Ownership_Service` is read-only. It verifies attachment type/image status, generated-image markers, exact article ID and UUID, and available run/profile/post associations; searches for one existing article-owned attachment; rejects ambiguous results; and validates the article's associated AICS post and ownership metadata. Missing, incomplete, foreign, and conflicting ownership remain distinct controlled outcomes.

`AICS_Featured_Image_Assignment_Service` verifies article/post ownership, supported non-trash post states, optional actor authority, attachment ownership, and the existing post thumbnail. The same attachment is idempotent. A different thumbnail is never replaced automatically. Assignment uses `set_post_thumbnail()`, verifies `get_post_thumbnail_id()`, and only then advances the article from `uploaded` to `attached` through the repository.

`AICS_Featured_Image_Pipeline_Service` coordinates article/post validation, duplicate lookup, compare-and-swap state transitions, deterministic prompt metadata, provider generation, Media Library upload, upload persistence, assignment, safe failure routing, and explicit temporary cleanup. The repository now has a separate atomic `mark_featured_image_uploaded()` operation, so an attachment ID and upload timestamps are committed while state is `uploaded` before assignment begins. Assignment failures retain the owned attachment and enter `retrying`; the next run restores `uploaded` and retries assignment without provider or upload work.

The normal new-image path is `not_requested → pending → generating → uploaded → attached`. Existing owned attachments can recover safely to `uploaded` from pending, generating, retrying, failed, or needs-attention states. One compare-and-swap transition increments `featured_image_attempts` exactly when a provider attempt begins. Duplicate, assignment-only, rejected, or already-attached execution does not increment it. Provider retryable failures enter `retrying` while attempts remain; exhaustion and unsafe failures enter failed or needs-attention. Written-article status and content are never changed.

Settings → Featured Images retains the generation-only test and adds **Test Media Library and Featured Image**. It accepts only a post ID, requires the management capability and an action-specific nonce, then resolves the article and all ownership server-side. The selected object must be an editable AICS-generated `post` in draft status with matching `_aics_article_id` and `_aics_article_uuid`. Safe transient notices show only controlled IDs, title, dimensions, format, created/reused behavior, and authorized edit links.

Disposable integration validation used a mocked provider response with a real WordPress Media Library insertion. The first execution made one provider request, created one attachment, stored ownership and alt metadata, assigned it, produced state `attached`, and set attempts to one. The second execution made no provider request, created no attachment, retained the same attachment and attempt count, and reported idempotent success. A conflicting unrelated thumbnail was not overwritten; the generated attachment remained in `retrying`, a repeated attempt did not regenerate, and assignment succeeded after the conflict was deliberately removed. All disposable posts, articles, attachments, uploads, logs, settings, and temporary files were removed after testing.

Files created: `includes/services/class-media-library-image-service.php`, `includes/services/class-featured-image-assignment-service.php`, `includes/services/class-featured-image-ownership-service.php`, and `includes/services/class-featured-image-pipeline-service.php`. Files modified: `ai-content-studio.php`, `includes/database/class-article-repository.php`, `includes/services/class-featured-image-state.php`, `includes/services/class-usage-logger.php`, `includes/admin/class-settings-page.php`, and `AI_CONTEXT.md`. No database schema or asset change is required.

Current limitations: Task 1.4 provides a shared pipeline and administrator test only. Manual Studio controls, automation worker integration, image approval, regeneration, replacement, persistent image history, and SEO are not implemented. Next task: **V1 Task 1.5 — Manual Studio Featured Image Generation and Review Flow**. It has not begun.

## V1 Task 1.5A — Manual Studio Shared Article Persistence and WordPress Ownership Integration

V1 Task 1.4 is complete and tested. Testing exposed the Manual Studio ownership gap through WordPress post 90: the legacy draft had `_aics_generated_post = 1` but no shared article row, article ID, UUID, or controlled source metadata, so the featured-image pipeline correctly rejected it. Ownership validation was not weakened, and post 90 was not changed or backfilled.

New Manual Studio generations now immediately create a persistent row in the shared `aics_articles` table through `AICS_Manual_Article_Persistence_Service` and `AICS_Article_Repository`. The existing `source_type` allowlist is authoritative: new Manual Studio rows use `manual`, while automation remains `automation`. The UI transient remains only short-lived workflow state and carries the validated persistent article ID and UUID; it is no longer the permanent article store.

Schema version `0.11.0` makes the automation-only `idea_id`, `profile_id`, and `run_id` columns nullable with `NULL` defaults. The existing `source_type` column already met the product requirement, so no redundant origin column was added. The migration uses the existing installer, preserves all existing values and indexes, verifies nullable columns before advancing the version, and is idempotent. Existing automation rows retain their real idea, profile, and run IDs; manual rows use SQL `NULL`, never sentinel zero or fabricated parent records.

Manual article creation stores a UUID, sanitized title/excerpt/content, content hash, word count, `generated` status, one generation attempt, generation timestamps, current user ownership, and safe featured-image defaults. Regeneration while the workflow retains its article identity updates the same manual row. Repeated manual saves update only title, excerpt, content, hash, word count, updater, and update time on that same owned row. UUID, source, image state, attachment, and post association are preserved. As before, edits made in Manual Studio after creating a WordPress draft update the shared article only; they do not silently rewrite the existing WordPress post.

Manual draft creation loads the owned persistent article first. A valid existing associated post is returned without insertion or additional usage logging. Recovery can find one post with the matching article ID and verify its ownership before associating it. A new draft is created through `AICS_Post_Generator`, then associated through the repository. It stores `_aics_generated_post = 1`, `_aics_source = manual`, `_aics_article_id`, `_aics_article_uuid`, the creating user and existing controlled content metadata. It omits `_aics_idea_id`, `_aics_run_id`, and `_aics_profile_id` because no real automation relationships exist.

Featured-image post ownership now requires the post source to match the article source. Manual articles are verified by generated marker, `manual` source, article ID, article UUID, and persistent post association without run/profile requirements. Automation validation remains strict and additionally requires its real idea, run, and profile metadata. Normal WordPress posts, mismatched posts, and legacy unassociated Manual Studio drafts remain rejected. Generated attachment metadata now also omits run/profile keys when those IDs are unavailable.

Content History remains WordPress-post-based and therefore does not duplicate a manual item merely because a shared article row also exists. New manual drafts continue to appear once through `_aics_generated_post`; `_aics_source = manual` provides their controlled origin without redesigning the page.

Validation created two disposable manual rows to confirm multiple `NULL` values work under the unique idea index, updated one row twice while preserving its ID/UUID and image defaults, created and reused one WordPress draft, verified complete manual ownership and absence of fake automation metadata, confirmed featured-image ownership acceptance, and confirmed one Content History match. All eight existing automation rows retained non-null automation relationships. Post 90 remained unchanged and rejected. Disposable rows and posts were deleted after testing.

File created: `includes/services/class-manual-article-persistence-service.php`. Files modified: `ai-content-studio.php`, `includes/database/class-installer.php`, `includes/database/class-article-repository.php`, `includes/admin/class-content-studio-page.php`, `includes/services/class-featured-image-ownership-service.php`, `includes/services/class-media-library-image-service.php`, and `AI_CONTEXT.md`.

Current limitations: historical unassociated Manual Studio drafts are intentionally not backfilled; persistent manual articles without WordPress posts do not appear in the post-based Content History screen; post-creation edits do not synchronize into the existing WordPress draft. Manual Studio image generation/review controls, automation image steps, image approval/regeneration/replacement, and SEO are not implemented. Next task: **V1 Task 1.5B — Manual Studio Featured Image Generation and Review Controls**. It has not begun.

## V1 Task 1.5B — Manual Studio Featured Image Generation and Review Controls

V1 Task 1.5A is complete and tested at schema version `0.11.0`. Task 1.5B integrates the existing shared featured-image pipeline into the persistent Manual Studio review screen without adding image logic to the controller or triggering images during article generation or draft creation.

`AICS_Manual_Featured_Image_Service` validates the current user's persistent manual article, its server-side WordPress post association, edit authority, provider readiness, image state, and attachment ownership. It delegates all generation, upload, duplicate prevention, recovery, and assignment to `AICS_Featured_Image_Pipeline_Service`. The explicit Manual Studio action can generate when the global default is disabled, provided the saved provider configuration is valid; the global setting remains the future automation/default policy.

The Manual Studio article review now shows a Featured Image section after a persistent article exists. Before draft creation it displays draft-first guidance and never calls the provider. After draft creation it renders controlled status-specific actions: Generate for not requested, no second action while pending/generating, assignment retry for uploaded, safe retry for supported failure states, and a readable skipped state. Attached images show a bounded WordPress attachment preview, attachment ID, dimensions, MIME-derived format, synchronized alt text, and capability-checked Edit Post/Edit Media links. No Generate or replacement action appears after attachment.

The generation handler is POST-only, uses the central management capability and an article-specific nonce, and accepts only the persistent article ID. It also requires that ID to match the current server-side Manual Studio transient, then resolves source, post, attachment, provider, model, prompt, and status server-side. Repeated/stale attached execution is idempotent: it returns the existing attachment without provider work, upload, attempt increment, or replacement. Uploaded/retrying state with a valid attachment performs assignment-only recovery even if generation defaults are disabled; a retry that would require generation still requires a ready provider. Compare-and-swap pipeline transitions remain the concurrency authority.

Alt-text editing is a separate POST action with its own article-specific nonce. The server resolves and verifies the owned attachment, strips markup, collapses whitespace, limits text to 250 characters, and uses `Featured image for {article title}` when submitted empty. `AICS_Article_Repository::update_featured_image_alt_text()` updates only the owned manual article's image alt field without changing written-content or image status. The service synchronizes `_wp_attachment_image_alt` and rolls it back if article persistence fails. It never calls a provider or increments attempts.

Controlled image-action notices are held for two minutes in a current-user transient and contain only success, controlled code, and controlled message. URLs contain only the allowlisted notice selector. The UI never accepts or exposes post IDs as authority, attachment IDs, provider settings, prompts, image bytes, base64, API keys, raw responses, or temporary paths. Legacy unassociated Manual Studio post 90 remains unsupported, and automation ownership requirements remain strict.

Disposable validation confirmed that an article without a post returns draft-first guidance with zero provider calls; an explicit action succeeds while global generation defaults are disabled; the first action makes one mocked provider request, creates and assigns one attachment, ends attached with attempts one, and renders a preview after refresh. A stale second execution creates nothing and makes no provider request. Alt editing synchronizes article and attachment values without provider work. Content History remains one post. Disposable article, post, attachment, upload, log, transient, and setting data were removed.

File created: `includes/services/class-manual-featured-image-service.php`. Files modified for Task 1.5B: `ai-content-studio.php`, `includes/admin/class-content-studio-page.php`, `includes/database/class-article-repository.php`, `assets/css/admin.css`, and `AI_CONTEXT.md`.

Current limitations: attached-image replacement and regeneration are intentionally unsupported; no image selection, deletion, cropping, upload, or separate approval flow exists. Automation featured-image configuration and worker integration are not implemented. Next task: **V1 Task 1.6 — Automation Featured Image Configuration and Worker Integration**. It has not begun.

## V1 Task 1.6A — Automation Featured Image Configuration and Immutable Run Snapshot

V1 Task 1.5B is complete and tested. Task 1.6A adds automation configuration and immutable run snapshot support only; automation worker image generation and assignment are explicitly not implemented yet.

Automation profile image settings are stored together at `content_settings.featured_images`. The normalized structure contains `enabled`, `required`, `settings_source`, `provider`, `model`, `visual_style`, `aspect_ratio`, `quality`, and `output_format`. Existing profiles and invalid legacy stored values normalize safely to disabled, optional, global source, editorial style, landscape, standard quality, PNG, and empty provider/model overrides. Disabled settings always force `required` to false.

The Automations page provides a Featured Images section with optional/required behavior, global/override selection, implemented provider and model choices, controlled style/aspect/quality/format values, a non-sensitive global configuration summary, and a readiness warning. Override values remain stored when global mode is selected. Server validation rejects unknown controlled values. Enabled configurations must have valid saved provider credentials. No API keys, endpoints, capability maps, or decrypted credentials are accepted or rendered.

At dispatch, global mode resolves saved global image values and override mode resolves profile values. The resolved non-sensitive values are copied to the run snapshot as top-level `featured_image_settings`. Later profile and global edits cannot alter that snapshot. An invalid enabled configuration records `featured_image_configuration_invalid` and no unusable run is created. Disabled image profiles dispatch normally without requiring a provider.

The effective run-configuration accessor validates new snapshot image settings. Older snapshots without image configuration and legacy runs with no snapshot resolve to disabled/optional and are labeled as legacy instead of reading live image settings. Automation Run Details shows an escaped summary and omits irrelevant provider fields when disabled. The Automations summary shows the saved image state.

Security decisions: capabilities come only from `AICS_Image_Provider_Factory`; controlled fields are allowlisted; credentials remain in the existing settings store; profile JSON and run snapshots never contain API keys, ciphertext, authorization headers, endpoints, prompts, article content, image bytes, or temporary paths. Rendering, saving, and dispatch resolution never call image generation.

File created: `includes/services/class-automation-featured-image-settings.php`. Files modified for Task 1.6A: `ai-content-studio.php`, `assets/js/admin.js`, `includes/admin/class-automations-page.php`, `includes/admin/class-automation-runs-page.php`, `includes/database/class-automation-profile-repository.php`, `includes/database/class-automation-run-repository.php`, `includes/services/class-automation-profile-service.php`, `includes/services/class-automation-dispatcher.php`, and `AI_CONTEXT.md`.

Validation covered full plugin PHP lint, Automations page rendering, existing-profile defaults, disabled/required normalization, strict invalid controlled-value rejection, legacy-run disabling, snapshot decoding, and API-key absence from HTML. No worker step, article image-state transition, provider generation request, Media Library attachment, image approval, retry behavior, or replacement behavior was added.

Current limitation: the required/optional rule is stored and displayed but is not yet enforced during article delivery. The worker continues to use existing article image defaults. Next task: **V1 Task 1.6B — Automation Worker Featured Image Generation and Assignment**. It has not begun.

## Combined Featured Image Pipeline Completion — Accelerated Development Exception

The remaining V1 Featured Image Pipeline work was completed as one combined development task by explicit product decision. Manual acceptance testing is intentionally deferred until the complete pipeline can be exercised end to end. SEO and V2 work have not begun.

Automation article creation now receives the effective immutable run image configuration. New automation articles initialize to `not_requested` and optional when images are disabled, or `pending` with the snapshot's required flag when enabled. Existing articles and Manual Studio creation remain unchanged.

The controlled `generate_featured_image` workflow step runs after every eligible WordPress draft exists and before final publishing approval, scheduling, publishing, or finalization. `AICS_Automation_Featured_Image_Service` processes at most one article per worker invocation, coordinates the existing shared pipeline, and uses only the snapshot provider, model, style, ratio, quality, format, enabled flag, and required flag. Provider credentials still come from the secure settings accessor at request time. Multiple articles are processed deterministically over later worker invocations.

The shared pipeline accepts the immutable automation settings without changing Manual Studio's live-settings behavior. Attached images are verified and reused, uploaded attachments receive assignment-only recovery, and provider attempts increment only when actual generation begins. Worker locks and article compare-and-swap image transitions remain the concurrency boundaries; no second lock system was introduced.

Optional temporary failures use the existing run retry/backoff system while safe attempts remain. Terminal optional failures retain the article image error and permit the written-content workflow to continue. Required failures retain attachments and child records, retry safe temporary errors, and otherwise fail the run before approval or delivery. Administrator Retry and Resume only requeue controlled state; they never generate, upload, reset article image attempts, or accept a browser-selected target step.

Delivery and finalization validate the immutable rule. Required images must be attached, owned by the correct automation article/post/run/profile, exist in Media Library, and match the WordPress thumbnail. Optional images may deliver only when attached or in a controlled terminal failure/skipped state. Pending, generating, retrying, and uploaded-but-unassigned images block delivery and completion.

Article Approval explains that image generation follows approval and draft creation. Final Publishing Approval shows bounded previews, status, alt text, and permitted media links; optional failures display a continuation warning, while missing required images remove approval controls and link to Run Details. Run Details now shows requirement, status, attachment/thumbnail verification, attempts, provider/model, controlled error, timestamps, and bounded previews. Required image failures participate in Needs Attention without duplicating run rows.

The recovery planner, run repository, worker claim allowlist, run filters, and readable step labels recognize `generate_featured_image`. Controlled image error labels use a bounded allowlist and unknown values display a generic internal-error message. Usage logging records one entry per real provider attempt with article/run IDs and safe provider/model/status/duration metadata; it does not log prompts, image bytes, base64, credentials, raw responses, or temporary paths.

File created: `includes/services/class-automation-featured-image-service.php`. Files modified for the combined task: `ai-content-studio.php`, `assets/css/admin.css`, `includes/admin/class-approvals-page.php`, `includes/admin/class-automation-runs-page.php`, `includes/database/class-article-repository.php`, `includes/database/class-automation-run-repository.php`, `includes/services/class-approval-workflow-service.php`, `includes/services/class-automation-run-inspector.php`, `includes/services/class-automation-run-recovery-planner.php`, `includes/services/class-automation-worker.php`, `includes/services/class-featured-image-generation-service.php`, `includes/services/class-featured-image-pipeline-service.php`, `includes/services/class-featured-image-state.php`, and `AI_CONTEXT.md`.

Current limitations: there is no separate image approval stage, attached-image regeneration or replacement, media selection/upload UI, crop/delete/gallery workflow, or historical image revision list. Manual acceptance testing remains pending by instruction. Next task: **Combined Featured Image Pipeline End-to-End Acceptance Testing**.

## V1 Task 2.1 — SEO Configuration, Persistence and Adapter Foundation

The Featured Image Pipeline development is complete. Its main Manual Studio and Approval Workflow acceptance path passed; the required-image failure/retry acceptance scenario was intentionally skipped and remains untested. Task 2.1 adds SEO foundations only and does not return to image development.

Database schema version `0.12.0` extends `{$wpdb->prefix}aics_articles` idempotently through the existing `dbDelta()` migration. It adds persistent status, title, meta description, focus keyword, slug, JSON-compatible category/tag/internal-link/external-link/analysis text, target adapter, generated/applied/updated timestamps, and controlled last-error fields. Existing title, excerpt, content, featured-image alt text, and WordPress association fields are reused rather than duplicated. Legacy rows default to `not_requested`; the useful `seo_status` index is verified before the installed version advances.

`AICS_SEO_State` centralizes `not_requested`, `pending`, `generating`, `generated`, `review_required`, `approved`, `applied`, `retrying`, `failed`, `needs_attention`, and `skipped`, readable labels, strict transitions, and processing/success/failure groupings. `AICS_SEO_Data` bounds strings, normalizes arrays and analysis values, and exports safe persistence/adapter arrays without prompts, credentials, or raw provider responses. `AICS_Article_Repository` decodes SEO JSON-compatible fields centrally and exposes a bounded SEO-data persistence method; no Task 2.1 path calls it automatically.

`AICS_SEO_Configuration` is the shared Manual Studio and automation configuration authority. Exact defaults are: target `auto`, blank instructions, generated focus-keyword mode, number optional, power word enabled, sentiment optional, internal links enabled/maximum 3, external links enabled/maximum 2, density 0.5–1.5, all six keyword-placement checks enabled, and featured-image content source enabled after the introduction. Manual Studio defaults enabled for a new workflow; automation profiles and all legacy profiles/runs default disabled. Targets, title/sentiment rules, link maxima (0–10), density (0–5 with minimum not exceeding maximum), and image placement are strictly controlled.

Manual Studio stores SEO configuration in the existing current-user workflow transient. It displays SEO Instructions, target adapter, title rules, link limits, keyword checks, and content-image placement while clearly stating that generation is not implemented. The workflow now visibly preserves four distinct bounded instruction fields: Idea Instructions, Article Instructions, Featured Image Instructions, and SEO Instructions. These remain stage-specific and are not combined or sent to an AI provider by this task.

Automation configuration is stored inside the existing `content_settings` JSON as `seo`, alongside the established featured-image structure. It includes the same normalized fields and the four separate instruction values. Repository normalization preserves legacy profiles as SEO-disabled. Dispatch copies the complete normalized non-sensitive configuration into immutable top-level `seo_settings` in each new run snapshot; edits to the profile cannot change existing snapshot JSON. Existing snapshots without SEO and legacy profile-fallback runs resolve to disabled and carry the `seo_legacy` marker. Run Details displays a bounded, escaped Configuration Used — SEO summary and never renders raw snapshot JSON.

`AICS_SEO_Plugin_Detector` detects Yoast, Rank Math, and AIOSEO using stable constants/classes after plugins load. Auto resolution is deterministic: Yoast, then Rank Math, then AIOSEO, then native WordPress. Multiple detections return the controlled `multiple_seo_plugins_active` warning and only the first adapter key is selected. Missing or explicitly unavailable integrations fall back to native; nothing installs or activates plugins.

`AICS_SEO_Adapter_Interface`, `AICS_Native_WordPress_SEO_Adapter`, and `AICS_SEO_Adapter_Factory` establish the adapter boundary. The factory accepts controlled registered keys only and always has native fallback. Native title/slug/excerpt/category/tag/attachment-alt capabilities are represented conceptually, but `apply_to_post()` and reads return controlled not-implemented errors. Yoast, Rank Math, and AIOSEO metadata writes are intentionally absent and no integration is represented as complete.

Security decisions: current management capability/nonces remain authoritative; browser fields are allowlisted and sanitized; numeric bounds are centralized; JSON uses `wp_json_encode()` and safe decoding; previews are bounded and escaped. No API keys, authorization headers, raw prompts, raw AI responses, plugin database internals, arbitrary classes, or arbitrary metadata keys are stored. No public REST/AJAX endpoint, AI call, article-content modification, link insertion, image insertion, plugin-specific metadata write, SEO scoring promise, or publishing guard was added.

Files created: `includes/services/class-seo-state.php`, `class-seo-configuration.php`, `class-seo-data.php`, `interface-seo-adapter.php`, `class-native-wordpress-seo-adapter.php`, `class-seo-plugin-detector.php`, and `class-seo-adapter-factory.php`.

Files modified: `ai-content-studio.php`, `includes/database/class-installer.php`, `includes/database/class-article-repository.php`, `includes/database/class-automation-profile-repository.php`, `includes/database/class-automation-run-repository.php`, `includes/services/class-automation-profile-service.php`, `includes/services/class-automation-dispatcher.php`, `includes/admin/class-content-studio-page.php`, `includes/admin/class-automations-page.php`, `includes/admin/class-automation-runs-page.php`, and `AI_CONTEXT.md`.

## V1 Task 2.2 — AI SEO Metadata Generation and Deterministic Quality Analysis

Task 2.1 is complete and tested at schema version `0.12.0`. Task 2.2 implements shared provider-neutral SEO generation, deterministic analysis, and Manual Studio generation/editing. Automation SEO generation, plugin-specific metadata application, link insertion, and in-content image insertion remain unimplemented.

`AICS_SEO_Generation_Request` contains only the persistent article ID/title/excerpt/bounded sanitized content, locale, public site context, controlled target, bounded SEO Instructions, SEO rules, and existing image description. `AICS_SEO_Generation_Result` exposes normalized results only. Neither contains credentials, headers, nonces, cookies, request globals, lock tokens, or paths.

`AICS_SEO_Prompt_Builder` uses the existing `AICS_AI_Request` with task `seo_metadata`, strict JSON Schema, and a bounded 1,800-token response. It requests exactly focus keyword, title, description, slug, excerpt, categories, tags, and image alt text as JSON without Markdown. It forbids ranking promises and treats SEO Instructions as user preferences that cannot override security, accuracy, structure, or limits. SEO Instructions are used only in this SEO stage.

The existing provider interface, request/response objects, and OpenAI provider now allow `seo_metadata`. Provider JSON-schema parsing retains the single-fence recovery. The SEO service then requires exact top-level keys, controlled types, no markup, nonempty core fields, bounded/deduplicated arrays and strings, a URL-free focus phrase, and a `sanitize_title()` slug. Raw malformed output is never stored or shown.

`AICS_SEO_Recommendation_Profile` centralizes conservative native, Yoast, Rank Math, and AIOSEO title/meta/slug guidance and notes that plugin width/score calculations may differ. Auto mode uses the Task 2.1 detector and native fallback.

`AICS_SEO_Quality_Analyzer` deterministically checks core metadata; recommendation lengths; focus phrase placement in title, description, slug, first 150 visible words, H2/H3, and image alt; whole-phrase occurrences and density; numeric, power, and sentiment title rules; existing internal/external links; featured image and alt availability; word count; excerpt; and suggested terms. Density is occurrences divided by visible word count times 100, rounded to two decimals. HTML, shortcodes, entities, malformed links, fragments, mailto, tel, and javascript targets are handled safely. No content is modified.

SEO Readiness is a centralized weighted 0–100 result labeled Needs Work, Fair, Good, or Excellent, with passed checks, warnings, and blocking issues. It is not a plugin score and does not guarantee rankings.

`AICS_Manual_SEO_Service` resolves the owned persistent manual article and current user-scoped SEO configuration server-side. Explicit generation advances through pending/generating, makes one provider request, analyzes, and persists on the same article as generated or review_required. Regeneration is explicit and forbidden after applied. Manual editing normalizes fields and reruns analysis without AI or usage increment.

Manual Studio adds article-specific nonce-protected POST actions for Generate/Regenerate and Save SEO Data. The review shows status, target, editable normalized fields, recommendation counts, SEO Readiness, passes, warnings, and blockers. It never renders raw prompts, responses, stack traces, or analysis JSON. Repository persistence reuses the shared excerpt and featured-image alt fields, records target/timestamps/JSON analysis, and does not change article content or written-content status. Usage logging adds `seo_metadata_generation` with only safe operational fields.

Files created: `includes/seo/index.php`, `class-seo-generation-request.php`, `class-seo-generation-result.php`, `class-seo-recommendation-profile.php`, `class-seo-prompt-builder.php`, `class-seo-quality-analyzer.php`, `class-seo-generation-service.php`, and `includes/services/class-manual-seo-service.php`.

Files modified: `ai-content-studio.php`, `includes/ai/class-ai-request.php`, `includes/providers/class-openai-provider.php`, `includes/services/class-usage-logger.php`, `includes/services/class-seo-state.php`, `includes/database/class-article-repository.php`, `includes/admin/class-content-studio-page.php`, and `AI_CONTEXT.md`.

Disposable validation used an injected provider implementing the existing contract. One generation caused one provider call and one usage row, persisted on the same article, calculated phrase density and existing link counts, and a manual Save reran analysis without another call or usage row. Malformed, incomplete, empty-keyword, and URL-slug responses returned controlled errors. Disposable records were removed.

Security/privacy: no public endpoint, terms, post metadata, plugin metadata, post-content changes, link insertion, or image insertion were added. No key, authorization data, cookie, nonce, IP, user agent, raw prompt/response, article content, or SEO Instructions are logged.

## Combined V1 SEO Tasks 2.3–2.5 — Links, In-Content Image, and Native Application

Task 2.2 implementation is complete at code level; its manual acceptance testing and Git commit remain intentionally deferred until the complete SEO feature is ready. Combined Tasks 2.3–2.5 implement Manual Studio SEO enhancement and native WordPress application only. Automation worker SEO and Yoast, Rank Math, and AIOSEO metadata integration have not begun.

`AICS_Internal_Link_Candidate_Service` queries a bounded pool of published, public posts/pages and viewable products without loading full candidate content. It excludes the current post, protected/non-public content, unusable/duplicate permalinks, and URLs already linked. `AICS_Internal_Link_Recommendation_Service` ranks controlled server-built candidates by normalized focus/title/slug/taxonomy overlap and returns only verified post IDs/URLs with natural anchors found in the article. No AI-returned or browser URL can become an internal target; deterministic recommendation is always available.

External candidates come only from normalized trusted URLs and domains in SEO configuration. The same configuration is available to Manual Studio, automation profiles, and immutable snapshots. `AICS_External_Link_Validation_Service` permits HTTP(S) only, rejects credentials, localhost/local/internal names, private/reserved/loopback/link-local resolution, admin/login paths, invalid DNS, unsafe schemes, and unvalidated redirects. It uses bounded `wp_safe_remote_head()` requests with SSL verification and revalidates a controlled redirect before a second request. Timeouts and non-2xx responses are not treated as verification. No search or fabricated URL exists.

`AICS_SEO_Link_Insertion_Service` modifies only simple safe paragraph text, skips headings, existing anchors, code/pre/script/style/button/captions, preserves Gutenberg comments and existing HTML, detects existing URLs, and records inserted/recommended/failed status without arbitrary HTML. Reapplication inspects the current content and does not duplicate URLs. Missing safe placements remain recommendations with a controlled reason.

`AICS_Content_Image_Insertion_Service` validates the existing article-owned featured-image attachment through the shared ownership service and reuses its attachment ID. It creates a native core/image block with safe HTML fallback, supports disabled/after-introduction/before-first-H2/after-first-H2/middle placement, uses the first suitable paragraph as fallback, and detects the attachment ID/wp-image class before insertion. It never generates, uploads, copies, replaces, or creates another attachment.

`AICS_Native_SEO_Application_Service` validates the owned Manual Studio post, applies post slug/excerpt/enhanced content, creates or reuses bounded category/tag terms through WordPress APIs, appends assignments without deleting unrelated terms, synchronizes reviewed alt text, reruns deterministic SEO analysis, persists final content/link records/analysis, and marks SEO applied only after the WordPress update. WordPress core has no dedicated SEO-title or meta-description fields, so those remain in the AICS article record and no meta-description tag is injected.

Content conflict protection requires exact article/post equality for the first application. Successful application stores only SHA-256 ownership evidence in `_aics_managed_content_hash`, `_aics_last_seo_application_hash`, `_aics_seo_applied`, and `_aics_content_image_attachment_id`. Later application refuses to overwrite a post whose current content no longer matches the managed hash, returning `seo_post_content_conflict`. It never stores full content in post metadata.

Repeated native application reuses the associated post, attachment, terms, links, slug, and managed content. Existing AICS insertions are detected, deterministic application makes no provider request, and direct WordPress editor changes are preserved through conflict refusal. Controlled application failures persist a bounded SEO error/needs-attention state unless the article was already applied.

Manual Studio adds trusted-source controls, a bounded application preview, link/image/application summaries, and an article-specific nonce-protected **Apply SEO to WordPress Draft** action. The browser cannot submit authoritative post content, attachment IDs, target URLs, plugin target, or analysis. Rendering performs no external verification and changes no post.

Files created: `includes/seo/class-internal-link-candidate-service.php`, `class-internal-link-recommendation-service.php`, `class-external-link-validation-service.php`, `class-external-link-recommendation-service.php`, `class-seo-link-insertion-service.php`, `class-content-image-insertion-service.php`, `class-native-seo-application-service.php`, and `includes/services/class-manual-seo-application-service.php`.

Files modified for the combined task: `ai-content-studio.php`, `includes/services/class-seo-configuration.php`, `includes/database/class-article-repository.php`, `includes/admin/class-content-studio-page.php`, `includes/admin/class-automations-page.php`, and `AI_CONTEXT.md`.

Code-level disposable validation confirmed native post application, stable reapplication, term reuse, Gutenberg-comment preservation, SHA-256 managed hashes, direct-editor conflict refusal, private-loopback URL rejection, safe paragraph insertion without existing-link damage, and owned attachment reuse with second-pass image detection. No manual acceptance testing has been claimed and no Git commit has been created.

## Final Combined V1 SEO Implementation

The accelerated-development exception authorized one remaining V1 SEO code pass while deferring manual acceptance. Tasks 2.2 and 2.3–2.5 were audited and retained: structured generation, deterministic AICS SEO Readiness, trusted link validation/insertion, featured-image reuse, native application, idempotency, and managed-content conflict protection form the shared foundation.

Schema `0.13.0` adds SEO generation/application attempts, provider/model, adapter/version, bounded application report, and analyzed/approved timestamps without recreating the articles table or erasing rows. Reports exclude content, prompts, raw responses, credentials, SQL, stack traces, and plugin objects.

`AICS_SEO_Application_Result` normalizes application output. Native remains the always-available fallback and does not inject head meta. Yoast centralizes `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, and `_yoast_wpseo_focuskw`; Rank Math centralizes `rank_math_title`, `rank_math_description`, and `rank_math_focus_keyword`; AIOSEO uses its current Post model and `save()` rather than custom table SQL or obsolete `_aioseop_*` keys. Installed-source inspection recorded Yoast 28.2, Rank Math 1.0.275, and AIOSEO 5.0.0.1 as audit facts, not hardcoded gates.

Auto priority is Yoast, Rank Math, AIOSEO, then native. Explicit unavailable targets fail without fallback. Only one plugin adapter receives writes after native application. Values are read back and compared, refresh is defensive, and plugin scores are not scraped.

`AICS_SEO_Workflow_Service` is shared by Manual Studio and automation. `generate_seo` and `apply_seo` are executable/recoverable worker steps; each invocation handles at most one article, so multiple articles advance sequentially. Snapshot runs consume immutable `seo_settings`; legacy snapshots resolve SEO disabled. Generation/application attempt policies preserve valid generated data on application retry. Posts, attachments, terms, links, and adapter selection are reused. Content hashes refuse external-editor conflicts and insertions remain duplicate-safe.

The configurable SEO Quality Gate supports a 0–100 minimum, core metadata, successful application, and adapter verification. Manual Studio treats its threshold as guidance; automation uses its snapshot. The four instruction fields remain isolated by stage. System Status reports adapter detection, selection, version, compatibility, supported fields, and multiple-plugin warnings without sensitive data.

Files created in this pass: `class-seo-application-result.php`, `class-abstract-postmeta-seo-adapter.php`, `class-yoast-seo-adapter.php`, `class-rank-math-seo-adapter.php`, `class-aioseo-adapter.php`, `class-seo-quality-gate.php`, `class-seo-workflow-service.php`, and `class-automation-seo-service.php`. Modified areas include bootstrap/schema, article/run persistence, worker/recovery/status maps, Manual SEO application, SEO configuration, Manual Studio, Automations, System Status, and this context.

Known limitations: third-party indexing refresh is best-effort; plugin analysis scores are intentionally absent; external links require trusted reachable sources and natural anchors; no SERP/volume/rank/schema/canonical/robots/redirect/social/licensing/billing/V2 work was added. Manual SEO acceptance testing remains pending. Yoast, Rank Math, and AIOSEO must be tested one at a time.

Next task: **Combined V1 SEO End-to-End Acceptance Testing**.

## Rank Math Quality Gate Consistency Fix

Manual Studio testing with Rank Math 1.0.275 populated `rank_math_title`, `rank_math_description`, and `rank_math_focus_keyword` successfully and produced an AICS SEO Readiness score of 79 against the saved Manual Studio minimum of 50. The adapter application report showed native and Rank Math application succeeded and all three fields passed read-back verification. A contradictory persisted state was nevertheless observed: `seo_status = applied` with `seo_last_error_code = seo_quality_gate_failed`.

The exact root cause was not Rank Math's local-site noindex notice or plugin scoring. The deterministic analyzer had classified missing focus-keyword placement in the first 150 visible words as an analysis blocker. The Quality Gate treated every item in `blocking_issues` as fatal even when it represented a placement preference, and the Manual application wrapper then recorded the generic gate error onto an already-applied row. The existing applied-row shortcut also prevented a normal reapplication from repairing that stale error.

Analyzer and gate severity are now centralized around genuine structural blockers. Keyword placement, density, optional title preferences, link availability, plugin-score availability, and similar optimization checks remain warnings. The gate separately reports missing core metadata, readiness below the normalized 0–100 minimum, genuine analysis blockers, native application failure, and required adapter verification failure. Numeric scores and minimums are compared numerically in the same 0–100 scale; 79 passes 50, while 0.79 remains 0.79 rather than being silently rescaled.

The site-wide WordPress noindex setting is now represented only as the controlled `site_noindex` environment notice. It does not change AICS SEO Readiness, become an article blocker, affect Rank Math verification, or prevent applying SEO to a local draft.

Rank Math read-back comparison normalizes slashes, HTML entities, whitespace, and WordPress metadata values. Its focus-keyword comparison safely uses the first comma-separated focus phrase when Rank Math stores more than one phrase. It does not require a Rank Math score, account, Content AI, or Pro.

Final gate evaluation now uses the deterministic analysis generated after native content/link/image application and adapter verification. The repository persists final content, analysis, bounded application report, status, applied timestamp, and controlled error in one update. A passed application stores `applied` and clears stale errors. A genuine final gate failure stores `needs_attention`, omits the applied timestamp, retains the high-level gate error, and records exact safe failed-condition IDs in the application report. Manual Studio no longer writes a second generic error after the workflow already persisted a consistent outcome. Applied rows with a stale error are allowed through the normal idempotent reapplication path, with no AI request or duplicate content elements.

Files modified for this fix: `includes/seo/class-seo-quality-analyzer.php`, `includes/services/class-seo-quality-gate.php`, `includes/services/class-abstract-postmeta-seo-adapter.php`, `includes/services/class-rank-math-seo-adapter.php`, `includes/services/class-seo-workflow-service.php`, `includes/services/class-manual-seo-application-service.php`, `includes/database/class-article-repository.php`, and `AI_CONTEXT.md`.

Code-level validation confirmed that the observed 79/50 case passes, the former introduction issue is a warning, simulated noindex remains non-blocking, and Rank Math title/description/focus-keyword read-back succeeds. Manual reapplication testing through the browser remains required.

Current limitations: external links require a reachable trusted URL and a natural existing anchor; no web search exists. Image replacement after a future featured-image change remains manual. Yoast, Rank Math, and AIOSEO metadata integration and Automation SEO generation/application are not implemented. Next task: **Combined V1 SEO Task 2.6–2.8 — SEO Plugin Adapters, Automation Worker Integration, Approval and Publishing Enforcement**. It has not begun.

Current limitations: plugin metadata application, term creation, link recommendation/insertion, external verification, image insertion, automation SEO generation, SEO approval, publishing guards, keyword research, SERP analysis, and rank tracking remain unimplemented. Next task: **V1 Task 2.3 — Internal and External Link Recommendation and Insertion Engine**. It has not begun.

Validation: all plugin PHP files pass syntax validation with the XAMPP PHP CLI, and `git diff --check` reports no whitespace errors. Current limitations: SEO metadata generation/analysis, keyword research, link selection/verification, content-image insertion, SEO approval, adapter metadata reads/writes, and plugin-specific adapters remain unimplemented. The required-image failure/retry acceptance scenario also remains untested. Next task: **V1 Task 2.2 — AI SEO Metadata Generation and Quality Analysis**. It has not begun.
## Create Content AJAX Wizard Redesign

The Create Content page is now a server-authoritative, single-page Manual Studio wizard with six stages: Context, Ideas, Article, Image, SEO, and Complete. Only the active stage is rendered. The ordered progress stepper remains visible, marks completed/current/pending states in text and visually, permits only server-confirmed revisits, and keeps future steps disabled.

`AICS_Manual_Wizard_State_Service` owns user-scoped context, ideas, selection, active article identity, current allowed step, progress, persistent-article resume, reset, and short operation locks. Browser step names and article identifiers never grant access. Persistent Manual Studio ownership is revalidated through `AICS_Manual_Article_Persistence_Service`; refresh restores the active article without repeating provider work. Start New Content clears only current user workflow pointers/transients and preserves historical articles, WordPress posts, attachments, and metadata.

`AICS_Manual_Studio_Wizard_Controller` exposes one authenticated `wp_ajax_aics_manual_wizard` endpoint with a strict operation allowlist, centralized capability and nonce checks, server-side step validation, controlled messages, and bounded HTML responses. It orchestrates the existing AI engine, Manual article persistence, draft creation, featured-image pipeline, SEO generation/editing, and shared SEO application workflow. No public endpoint, provider duplication, raw provider response, credential, path, SQL error, or trusted browser workflow state was introduced. Provider and persistence operations use per-user/per-operation locks in addition to client-side submission prevention.

The dedicated `manual-studio-wizard.js` bundle loads only on Create Content. It uses `fetch`, `FormData`, `AbortController`, stale-response sequence checks, event delegation, immediate step-specific skeletons, delayed long-operation guidance, focus movement, an aria-live status region, `aria-busy`, and safe step-only history. Back navigation never invokes AI. Controlled failures restore the current server-rendered step and preserve inputs. Skeleton animation is disabled under `prefers-reduced-motion`.

Step flow: Context validates and persists business/topic/tone/length/format plus bounded optional instructions before generating ideas. Ideas supports one selection and explicit regeneration. Article generation persists one owned Manual article; edits save without AI and draft creation reuses the existing association. Changing to a different regenerated idea creates a new persistent Manual article so an older post is never silently repurposed. Image generation reuses the shared idempotent pipeline and supports alt editing or the existing optional-image policy. SEO generation occurs only through an explicit transition when data is absent, supports review/save, and applies through the shared Quality Gate and adapter workflow. Complete renders a safe native summary plus the WordPress-generated same-origin preview URL, Edit Post, Full Preview, optional View Post, and Start New Content.

Upstream revisits do not delete downstream records or automatically call providers. Explicit regeneration replaces only the current transient idea set. A changed idea creates a new article, preserving old downstream records. Existing post/image/SEO ownership and conflict protections remain authoritative. The current product supports draft creation only in Manual Studio, so final publishing remains in the WordPress editor; automation scheduling and publishing are unchanged.

Accessibility/responsive decisions include semantic headings and forms, labelled controls, an ordered progress list, `aria-current="step"`, screen-reader state text, keyboard buttons, visible focus rules inherited from the admin design system, touch-sized controls, an aria-live region, reduced motion, horizontally scrollable progress on narrow screens, stacked fields/actions, relative preview sizing, and bounded iframe height.

Files created: `includes/services/class-manual-wizard-state-service.php`, `includes/admin/class-manual-studio-wizard-controller.php`, `assets/js/manual-studio-wizard.js`, and six templates under `templates/admin/manual-wizard/`. Files modified: `ai-content-studio.php`, `includes/admin/class-content-studio-page.php`, `includes/admin/class-assets.php`, `assets/css/admin.css`, and `AI_CONTEXT.md`.

Known limitations: Manual Studio completion still hands publishing/scheduling to the WordPress editor because no existing Manual publishing service exists. The AJAX experience intentionally requires JavaScript; the no-script state is controlled and preserves existing work. Manual browser, accessibility-technology, slow-network, provider, and cross-browser acceptance testing remains required.

Next task: **Create Content Wizard Manual Acceptance and Usability Testing**. Do not begin the Automations page redesign.
