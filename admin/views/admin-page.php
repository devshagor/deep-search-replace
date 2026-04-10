<?php
/**
 * Admin page template.
 *
 * @package Deep_Search_Replace
 *
 * @var string     $search    Current search string.
 * @var string     $replace   Current replace string.
 * @var bool       $skip_urls Whether to skip URLs during replacement.
 * @var array|null $results   Results from DSR_Replacer::process() or null.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap dsr-wrap">
	<div class="dsr-header">
		<h1><?php esc_html_e( 'Deep Search & Replace', 'deep-search-replace' ); ?></h1>
		<span class="dsr-version">v<?php echo esc_html( DSR_VERSION ); ?></span>
	</div>

	<!-- Backup Card -->
	<div class="dsr-card dsr-backup-card">
		<div class="dsr-backup-info">
			<div class="dsr-backup-icon">
				<span class="dashicons dashicons-database"></span>
			</div>
			<div class="dsr-backup-text">
				<h3><?php esc_html_e( 'Database Backup', 'deep-search-replace' ); ?></h3>
				<p><?php esc_html_e( 'Download a full .sql backup before making any changes.', 'deep-search-replace' ); ?></p>
			</div>
		</div>
		<form method="post">
			<?php wp_nonce_field( 'dsr_backup', 'dsr_backup_nonce' ); ?>
			<button type="submit" class="dsr-btn dsr-btn-backup">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Download Backup', 'deep-search-replace' ); ?>
			</button>
		</form>
	</div>

	<!-- Search & Replace Card -->
	<div class="dsr-card">
		<div class="dsr-warning-banner">
			<span class="dashicons dashicons-warning"></span>
			<span><?php esc_html_e( 'Always backup your database before replacing. Use "Search Only" first to preview results.', 'deep-search-replace' ); ?></span>
		</div>

		<form method="post">
			<?php wp_nonce_field( 'dsr_action', 'dsr_nonce' ); ?>
			<div class="dsr-field">
				<label for="dsr_search"><?php esc_html_e( 'Search for', 'deep-search-replace' ); ?></label>
				<input
					type="text"
					id="dsr_search"
					name="dsr_search"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="https://old-url.com/page/"
					required
				/>
				<p class="dsr-field-hint"><?php esc_html_e( 'Enter the URL or text string you want to find in your database.', 'deep-search-replace' ); ?></p>
			</div>
			<div class="dsr-field">
				<label for="dsr_replace"><?php esc_html_e( 'Replace with', 'deep-search-replace' ); ?></label>
				<input
					type="text"
					id="dsr_replace"
					name="dsr_replace"
					value="<?php echo esc_attr( $replace ); ?>"
					placeholder="https://new-url.com/page/"
				/>
				<p class="dsr-field-hint"><?php esc_html_e( 'Leave empty if you only want to search. Handles serialized data safely.', 'deep-search-replace' ); ?></p>
			</div>
			<div class="dsr-field dsr-checkbox-field">
				<label for="dsr_skip_urls">
					<input
						type="checkbox"
						id="dsr_skip_urls"
						name="dsr_skip_urls"
						value="1"
						<?php checked( $skip_urls ); ?>
					/>
					<?php esc_html_e( 'Protect slugs & URLs (recommended)', 'deep-search-replace' ); ?>
				</label>
				<p class="dsr-field-hint"><?php esc_html_e( 'Skips slug columns (post_name, guid, term slug) and protects inline URLs in content. Prevents 404 errors and broken links when replacing plain text.', 'deep-search-replace' ); ?></p>
			</div>
			<div class="dsr-actions">
				<button type="submit" name="dsr_action" value="search" class="dsr-btn dsr-btn-secondary">
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'Search Only', 'deep-search-replace' ); ?>
				</button>
				<button type="submit" name="dsr_action" value="replace" class="dsr-btn dsr-btn-primary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure? This will modify your database and cannot be undone.', 'deep-search-replace' ) ); ?>');">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Search & Replace', 'deep-search-replace' ); ?>
				</button>
			</div>
		</form>
	</div>

	<?php if ( $results ) : ?>
		<?php include DSR_PLUGIN_DIR . 'admin/views/results.php'; ?>
	<?php endif; ?>
</div>
