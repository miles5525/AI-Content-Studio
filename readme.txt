=== AI Content Studio ===
Tags: ai, content, automation, seo, images
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.9.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate, review, and manage AI-assisted WordPress content with manual and automated workflows.

== Description ==

AI Content Studio provides manual content generation, configurable automation profiles, approval workflows, WordPress draft delivery, featured-image generation, SEO metadata, content history, and operational diagnostics.

AI Content Studio is developed by SamSiyu. Learn more at https://samsiyu.com/ai-content-studio/.

= External service =

This plugin uses the OpenAI API for text, SEO metadata, and image generation. It contacts OpenAI only when an authorized administrator requests a generation or connection test, or enables an automation that performs generation.

Depending on the action, the plugin sends generation instructions, business context entered by an administrator, article ideas or article content, SEO inputs, image prompts, the selected model, and the configured API key used for authentication. OpenAI processes this data under its Terms of Use and Privacy Policy:

* OpenAI Terms of Use: https://openai.com/policies/terms-of-use/
* OpenAI Privacy Policy: https://openai.com/policies/privacy-policy/

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-content-studio`, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen.
3. Open AI Content Studio > Settings.
4. Review the external-service notice, add an OpenAI API key, and test the connection.

== Frequently Asked Questions ==

= Does the plugin send data automatically? =

Only after an administrator explicitly runs a generation or test, or enables a configured automation. The plugin does not contact OpenAI merely because it is activated.

= What happens when the plugin is removed? =

Scheduled events and scheduler locks are removed. Generated WordPress content, plugin settings, and audit records are preserved to avoid unexpected data loss.

== Changelog ==

= 0.9.1 =

* Added WordPress privacy-policy integration and complete external-service disclosure.
* Hardened automation-form JavaScript against incomplete markup.
* Fixed scheduled cleanup removal during uninstall and corrected display encoding.
* Unified admin colors, card treatments, control heights, and responsive action sizing.

= 0.9.0 =

* Initial pre-release feature set.
