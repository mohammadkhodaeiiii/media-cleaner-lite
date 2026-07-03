=== Media Cleaner Lite ===
Contributors: mohammadkhodaei
Tags: media, cleanup, unused images, storage, attachments
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely detect unused media files, analyze references across WordPress, and reclaim storage without risking accidental deletion.

== Description ==

Media Cleaner Lite is a lightweight, high-performance WordPress plugin that indexes your media library, scans content for references, and helps administrators identify unused files.

The plugin analyzes posts, pages, custom post types, WooCommerce products, widgets, menus, theme mods, custom fields, and popular page builders including Gutenberg, Elementor, Bricks, Beaver Builder, and Divi.

Built for performance and security: batch scanning, memory-efficient queries, transient caching, vanilla JavaScript with no jQuery, and a fully native WordPress admin interface.

= Features =

* Index every attachment with metadata, file size, dimensions and MIME type.
* Detect media usage in post content, featured images, Gutenberg blocks, and classic editor galleries.
* Support for WooCommerce product images and galleries.
* Detect references in Elementor, Bricks, Beaver Builder, and Divi data.
* Scan ACF image fields, custom fields, widgets, menus, and theme mods.
* Classify media as used, unused, or potentially unused with confidence scores.
* Safe delete: re-check references, verify file path, move to trash, log operations.
* Native admin dashboard with summary cards, progress indicators and reports.
* Secure AJAX: start, continue, pause, cancel, delete, restore, refresh, clear cache.
* Internal REST API for reports, unused media and index data.
* Settings via the WordPress Settings API.
* Translation ready (POT included) and RTL compatible.
* Accessible: ARIA progressbar, keyboard friendly, prefers-reduced-motion.
* Extensible service-based architecture ready for a future Pro version.

== Installation ==

1. Upload the `media-cleaner-lite` folder to `/wp-content/plugins/`, or install the ZIP through Plugins → Add New → Upload Plugin.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open **Media Cleaner Lite** in the admin menu, configure Settings, then run a scan from the Scanner page.

== Frequently Asked Questions ==

= Does it require jQuery? =

No. The plugin uses vanilla JavaScript only.

= Does it permanently delete media immediately? =

No. By default, unused media is moved to the WordPress trash. Permanent deletion requires explicit confirmation.

= Will it work on large sites? =

Yes. Scanning runs in configurable batches and uses optimized WP_Query arguments to keep memory usage low.

= Does it send data anywhere? =

No. There is no tracking, telemetry or external API. Everything runs on your server.

== Screenshots ==

1. Dashboard with reference coverage, summary cards and largest unused files.
2. Scanner with batch progress and pause/cancel controls.
3. Unused media table with confidence scores and safe trash action.
4. Detailed reports and settings screen.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
