=== Sintacs Mwai Export Chats ===
Tags: chat, export, AI Engine, JSON, CSV, PDF
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Export your Meow AI Engine chat data into various formats with the Sintacs Mwai Export Chats plugin, easily and efficiently.

== Description ==

The Sintacs Mwai Export Chats plugin is a powerful tool who need to export chat data from the WordPress database. Whether you need the data in CSV, JSON, or PDF format, this plugin provides a simple and user-friendly interface to select and download your data. It is designed to work seamlessly with the AI Engine plugin to offer additional functionality.

== Installation ==

1. Upload the `sintacs-mwai-export-chats` folder to the `/wp-content/plugins/` directory or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the sub menu item 'AI Engine Export' under the Meows Apps menu item to access the export features.

== Frequently Asked Questions ==

= Does this plugin require the AI Engine plugin to be installed? =

Yes, the Sintacs Mwai Export Chats plugin is built to work with the AI Engine plugin. Make sure it is installed and activated to utilize all features.

= What formats can I export the chats to? =

You can export your chats in CSV, JSON, and PDF formats.

= Can I export all chats at once? =

Yes, there is an option to export all chats, or you can select specific chat IDs to export.


== Changelog ==

= 1.05 =

Fix
- streamlined post export action from generic export to sintacs_mwai_export

= 1.04 =

Fix
- Pagination: Prev-Buttons were missing the urls.

New
- Menu is now also in the tools section

= 1.03 =

- Instead of checking if the plugin path/file exists, we check now if the database table {prefix}_mwai_chats exists.
- Commented the datepicker css inclusion
- Menu is now a sub menu of the Meow Apps menu item

= 1.02 =

Fix
- Check if Ai-Engine Pro is installed also


= 1.01 =

New
- Feature: Export all chats / disscusion at once

Fix
- Error in CSV-Export causes by new lines. Now they are removed before export.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 1.0 =
* Initial release. Please let me know of any bugs or issues (chats-export@sintacs.de).