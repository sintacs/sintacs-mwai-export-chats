# Sintacs Mwai Export Chats

**Contributors:** sintacs  
**Tags:** ai, chatbot, export, mwai  
**Requires at least:** 5.0  
**Tested up to:** 6.6  
**Stable tag:** 2.0.0  
**Requires PHP:** 7.4  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Export your Meow AI Engine (Pro) chat data into various formats with the Sintacs Mwai Export Chats plugin, easily and efficiently.

## Description

This plugin allows you to export chatbot conversations from the AI Engine plugin in CSV, JSON, and PDF formats. It provides an easy-to-use interface for filtering and exporting chat data.
Be sure to activate the chatbot disccussions in the AI Engine (Pro) plugin settings. This enables the discussion tab and the saving of the discussions.

## Installation

1. Upload the `sintacs-mwai-export-chats` folder to the `/wp-content/plugins/` directory or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the sub menu item 'AI Engine Export' under the Meows Apps menu item to access the export features.

## Frequently Asked Questions

### Does this plugin require the AI Engine (Pro) plugin to be installed?

Yes, the Sintacs Mwai Export Chats plugin is built to work with the AI Engine (Pro) plugin. Make sure it is installed and activated to utilize all features.

### What formats can I export the chats to?

You can export your chats in CSV, JSON, and PDF formats.

### Can I export all chats at once?

Yes, there is an option to export all chats, or you can select specific chat IDs to export.

## Changelog

## 2.0.0
Added
- Option to Upgrade to the premium version with filters
- Filters options to filter the disccussions by Date/Time, User-ID, Bot-ID and Chat-ID (Premium)
- Streamlined the code
- Various other improvements

### 1.07

Added
- New column with the total number of messages in a discussion / chat added to the discussions table.
- New column with the total number of messages added to the CSV- and JSON-Export.

### 1.06

New
- Added new column Chatbot-Name to Discussions export table and to the export-files

### 1.05

Fix
- streamlined post export action from generic export to sintacs_mwai_export

### 1.04

Fix
- Pagination: Prev-Buttons were missing the urls.

New
- Menu is now also in the tools section

### 1.03

- Instead of checking if the plugin path/file exists, we check now if the database table {prefix}_mwai_chats exists.
- Commented the datepicker css inclusion
- Menu is now a sub menu of the Meow Apps menu item

### 1.02

Fix
- Check if Ai-Engine Pro is installed also

### 1.01

New
- Feature: Export all chats / disscusion at once

Fix
- Error in CSV-Export causes by new lines. Now they are removed before export.

### 1.0
* Initial release.

## Upgrade Notice

### 1.0
* Initial release. Please let me know of any bugs or issues (support@sintacs.de).