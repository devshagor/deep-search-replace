# Deep Search & Replace

A WordPress plugin that searches **all database tables** for a URL or text string and replaces it — including serialized data in post meta, options, widgets, theme mods, and more.

## Why Use This Plugin?

WordPress stores URLs and text across dozens of tables and often inside PHP serialized strings. A simple SQL `REPLACE` query will break serialized data. Deep Search & Replace handles this correctly by unserializing, replacing, and re-serializing the data.

## Features

- Searches **every table** in your database, not just core WordPress tables
- Safely handles **PHP serialized data** (options, widgets, theme mods, post meta)
- **Preview mode** — see all matches before making any changes
- Displays table name, column, match count, and a sample for each result
- Handles **nested serialized data** recursively
- Automatically **flushes the object cache** after replacement
- Redirects to the plugin page on activation for quick access
- Fully translatable (i18n ready)

## Use Cases

- **Domain migration** — moving a site from one URL to another
- **HTTP to HTTPS** — fixing hardcoded `http://` URLs after enabling SSL
- **CDN changes** — swapping old CDN URLs for new ones
- **Staging to production** — replacing staging domain references
- **Bulk text updates** — changing any text stored in the database

## Installation

1. Download or clone this repository into your `/wp-content/plugins/` directory
2. Activate the plugin through **Plugins > Installed Plugins** in WordPress
3. Go to **Tools > Deep Search & Replace**

Or install directly from the WordPress plugin directory (search for "Deep Search & Replace").

## How It Works

1. Navigate to **Tools > Deep Search & Replace** in your WordPress admin
2. Enter the text or URL you want to find in the **Search for** field
3. Click **Search Only (Safe Preview)** to see all matches across your database
4. Review the results — each match shows the table, column, count, and a sample
5. If satisfied, enter your replacement text in the **Replace with** field
6. Click **Search & Replace** to perform the replacement
7. The plugin will process each match, handling serialized data automatically
8. Object cache is flushed after replacement completes

### Serialized Data Handling

WordPress stores complex data structures (arrays, objects) as serialized strings in the database. A naive string replacement changes the content but not the string length metadata, corrupting the data. This plugin:

1. Detects serialized strings using WordPress's `is_serialized()` function
2. Unserializes the data into its original PHP structure
3. Recursively walks through arrays and objects to replace the target string
4. Re-serializes the data with correct string lengths

This ensures options, widgets, theme mods, and plugin settings remain intact after replacement.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Administrator access (`manage_options` capability)

## Security

- Nonce verification on all form submissions
- Capability checks restrict access to administrators only
- All user input is sanitized with `sanitize_text_field()`
- All output is escaped with `esc_html()`, `esc_attr()`, `esc_js()`
- Database queries use `$wpdb->prepare()` with parameterized values
- Table and column names are validated against a strict whitelist pattern

## Important

**Always back up your database before performing replacements.** While the plugin handles serialized data safely, database modifications are irreversible without a backup.

## License

GPL-2.0-or-later - see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.
