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

Task 1 is complete. The plugin foundation now includes:

* A namespaced plugin bootstrap and lifecycle handlers
* Minimum PHP and WordPress version checks during activation
* Installation metadata stored in WordPress options
* A centralized `Permissions` class based on `manage_options`
* An AI Content Studio admin menu with seven placeholder pages
* Admin CSS and JavaScript scoped to the plugin's admin pages
* Direct-access protection, uninstall safety, README documentation, and Git ignore rules

## Current Task Boundary

Task 1 added no AI integrations, settings functionality, custom database tables, content generation, image generation, SEO functionality, or social functionality.

## Important Instruction for Codex

Before making any code changes:

1. Read `AI_CONTEXT.md`.
2. Inspect all current project files.
3. Summarize the current state.
4. Complete only the requested task.
5. Do not continue into another phase automatically.
