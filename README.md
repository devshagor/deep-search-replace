# Deep Search & Replace

A WordPress plugin that searches **all database tables** for a URL or text string and replaces it — including serialized data in post meta, options, widgets, theme mods, and more.

## Why Use This Plugin?

WordPress stores URLs and text across dozens of tables and often inside PHP serialized strings. A simple SQL `REPLACE` query will break serialized data. Deep Search & Replace handles this correctly by unserializing, replacing, and re-serializing the data.

## Features

- Searches **every table** in your database, not just core WordPress tables
- Safely handles **PHP serialized data** (options, widgets, theme mods, post meta)
- **Preview mode** — see all matches before making any changes
- **Protect slugs & URLs** — skips `post_name`, `guid`, term `slug`, and other permalink columns to prevent 404 errors
- **Inline URL protection** — preserves `http://` and `https://` links inside content during plain text replacement
- **One-click database backup** — download a full `.sql` backup directly from the plugin page before making changes
- Displays table name, column, match count, and a sample for each result
- Skipped columns shown with a clear **"Skipped — slug/permalink"** badge
- Handles **nested serialized data** recursively
- Automatically **flushes the object cache** after replacement
- Not-found **troubleshooting tips** when no matches are found
- **Settings link** on the plugins list page for quick access
- Redirects to the plugin page on activation
- **Zero frontend impact** — all code loads exclusively in wp-admin
- Fully translatable (i18n ready with `.pot` file)

## Use Cases

- **Domain migration** — moving a site from one URL to another
- **HTTP to HTTPS** — fixing hardcoded `http://` URLs after enabling SSL
- **CDN changes** — swapping old CDN URLs for new ones
- **Staging to production** — replacing staging domain references
- **Bulk text updates** — changing any text stored in the database (without breaking slugs or links)

## Installation

1. Download or clone this repository into your `/wp-content/plugins/` directory
2. Activate the plugin through **Plugins > Installed Plugins** in WordPress
3. Go to **Tools > Deep Search & Replace**

Or install directly from the WordPress plugin directory (search for "Deep Search & Replace").

## How It Works

1. Navigate to **Tools > Deep Search & Replace** in your WordPress admin
2. **Download a backup** using the backup card at the top of the page
3. Enter the text or URL you want to find in the **Search for** field
4. Click **Search Only** to preview all matches across your database
5. Review the results — each match shows the table, column, count, and a sample
6. If satisfied, enter your replacement text in the **Replace with** field
7. Keep **"Protect slugs & URLs"** checked (recommended) to prevent 404 errors
8. Click **Search & Replace** to perform the replacement
9. The plugin processes each match, handling serialized data automatically
10. Object cache is flushed after replacement completes

### Slug & URL Protection

When **"Protect slugs & URLs"** is enabled (default), the plugin:

- **Skips slug columns entirely** — `post_name`, `guid`, `slug`, `user_login`, `user_nicename`, `user_email` are never modified, preventing broken permalinks and 404 errors
- **Protects inline URLs** — URLs embedded in post content, meta values, and widget text are preserved during replacement

**Example:** Searching for `hello` and replacing with `hello world`:
- `post_name: hello-page` — **Skipped** (permalink stays intact)
- `post_content: "Visit the hello page"` — Replaced to `"Visit the hello world page"` (URLs inside content are preserved)

### Serialized Data Handling

WordPress stores complex data structures (arrays, objects) as serialized strings in the database. A naive string replacement changes the content but not the string length metadata, corrupting the data. This plugin:

1. Detects serialized strings using WordPress's `is_serialized()` function
2. Unserializes the data into its original PHP structure
3. Recursively walks through arrays and objects to replace the target string
4. Re-serializes the data with correct string lengths

This ensures options, widgets, theme mods, and plugin settings remain intact after replacement.

### Database Backup

The plugin includes a built-in database backup feature:

- Downloads a full `.sql` dump of all tables
- Streams directly to the browser (no files stored on server)
- Includes `DROP TABLE`, `CREATE TABLE`, and `INSERT` statements
- Memory-efficient chunked export (500 rows at a time)
- One click — no configuration needed

## Plugin Structure

```
deep-search-replace/
├── deep-search-replace.php       # Main bootstrap file
├── uninstall.php                  # Clean uninstall handler
├── includes/
│   ├── class-dsr-admin.php        # Admin menu, enqueue, page render
│   ├── class-dsr-backup.php       # Database backup download
│   └── class-dsr-replacer.php     # Core search & replace engine
├── admin/
│   ├── css/
│   │   └── admin-style.css        # Admin styles
│   └── views/
│       ├── admin-page.php         # Main admin page template
│       └── results.php            # Results partial template
├── languages/
│   └── deep-search-replace.pot    # Translation template
├── readme.txt                     # WordPress.org readme
└── package.json                   # Build tools (npm run build)
```

## Build

Generate a wp.org-ready zip file:

```bash
npm run build
```

This cleans the build directory and creates `build/deep-search-replace.zip` excluding all development files (`.git`, `node_modules`, `scripts`, etc.).

Generate the `.pot` file manually before building if translations have changed.

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
- Table and column names are validated against a strict regex pattern
- WordPress Plugin Check compliant

## Important

**Always back up your database before performing replacements.** The plugin includes a one-click backup feature for this purpose. While the plugin handles serialized data safely, database modifications are irreversible without a backup.

## License

GPL-2.0-or-later - see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

## Author

[devshagor](https://profiles.wordpress.org/shagors/)
