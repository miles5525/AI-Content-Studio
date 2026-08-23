# AI Content Studio

AI Content Studio generates, reviews, and manages AI-assisted WordPress content. It supports manual content creation, automation profiles and approvals, WordPress draft delivery, featured images, SEO metadata, operational history, and system diagnostics.

Developed by [SamSiyu](https://samsiyu.com/). Visit the [AI Content Studio product page](https://samsiyu.com/ai-content-studio/) for product information.

## Requirements

- WordPress 6.4 or newer
- PHP 8.0 or newer
- An OpenAI API key for AI generation features

## Installation

1. Copy the plugin directory into `wp-content/plugins/`.
2. Activate **AI Content Studio** from **Plugins > Installed Plugins**.
3. Open **AI Content Studio > Settings** and review the external-service notice before entering an API key.

## External service

The plugin connects to OpenAI only when an administrator requests a generation or connection test, or enables an automation that performs generation. It sends the instructions and relevant content needed for the request, plus the configured API key for authentication.

- [OpenAI Terms of Use](https://openai.com/policies/terms-of-use/)
- [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy/)

## Data and removal

Deactivation stops scheduled tasks but preserves settings and generated records. Uninstall removes scheduler locks and scheduled events but intentionally preserves generated content, settings, and audit records so site content is not deleted unexpectedly.

## License

GPL-2.0-or-later.
