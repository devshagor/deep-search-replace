=== Deep Search & Replace ===
Contributors: webappick
Tags: search, replace, database, url, migration
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Search and replace text across all database tables, including serialized data in post meta, options, widgets, and more.

== Description ==

Deep Search & Replace scans every table in your WordPress database for a given string and optionally replaces it. It correctly handles serialized data so that replacements in options, widgets, and post meta do not corrupt your database.

**Features:**

* Searches all database tables, not just core WordPress tables.
* Safely handles PHP serialized data (post meta, options, widgets, theme mods).
* Preview mode lets you see all matches before making changes.
* Shows table name, column, match count, and a sample for every result.
* Automatically flushes the object cache after replacement.

**Use cases:**

* Migrating a site to a new domain or URL structure.
* Replacing old CDN URLs with new ones.
* Fixing hardcoded URLs after moving from HTTP to HTTPS.
* Bulk-updating any text stored in the database.

**Important:** Always create a full database backup before performing replacements.

== Installation ==

1. Upload the `deep-search-replace` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Tools > Deep Search & Replace** to use the plugin.

== Frequently Asked Questions ==

= Will this plugin break my serialized data? =

No. The plugin detects serialized data and unserializes it before replacing, then re-serializes it so that string lengths and structure remain valid.

= Can I preview results before making changes? =

Yes. Use the "Search Only (Safe Preview)" button to see all matches without modifying anything.

= Does it search custom tables? =

Yes. It searches every table returned by `SHOW TABLES`, including tables created by other plugins.

== Screenshots ==

1. The search and replace admin interface under Tools.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
