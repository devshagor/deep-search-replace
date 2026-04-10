<?php
/**
 * Results template partial.
 *
 * @package Deep_Search_Replace
 *
 * @var array  $results Results from DSR_Replacer::process().
 * @var string $search  Current search string.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this included file.

$deep_sr_is_replace     = $results['is_replace'];
$deep_sr_total_found    = $results['total_found'];
$deep_sr_total_replaced = $results['total_replaced'];
$deep_sr_rows_data      = $results['rows_data'];
?>
<div class="dsr-card dsr-results-card">
	<h2 class="dsr-results-title">
		<?php $deep_sr_is_replace ? esc_html_e( 'Search & Replace Results', 'deep-search-replace' ) : esc_html_e( 'Search Results', 'deep-search-replace' ); ?>
	</h2>

	<table class="dsr-results-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Table', 'deep-search-replace' ); ?></th>
				<th><?php esc_html_e( 'Column', 'deep-search-replace' ); ?></th>
				<th style="text-align:center;"><?php esc_html_e( 'Found', 'deep-search-replace' ); ?></th>
				<?php if ( $deep_sr_is_replace ) : ?>
					<th style="text-align:center;"><?php esc_html_e( 'Replaced', 'deep-search-replace' ); ?></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'Sample', 'deep-search-replace' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $deep_sr_rows_data as $deep_sr_row ) : ?>
				<tr<?php echo ! empty( $deep_sr_row['skipped'] ) ? ' class="dsr-row-skipped"' : ''; ?>>
					<td><span class="dsr-table-name"><?php echo esc_html( $deep_sr_row['table'] ); ?></span></td>
					<td>
						<span class="dsr-col-name"><?php echo esc_html( $deep_sr_row['col_name'] ); ?></span>
						<?php if ( ! empty( $deep_sr_row['skipped'] ) ) : ?>
							<span class="dsr-badge-skipped"><?php esc_html_e( 'Skipped — slug/permalink', 'deep-search-replace' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="dsr-count"><?php echo (int) $deep_sr_row['found']; ?></td>
					<?php if ( $deep_sr_is_replace ) : ?>
						<td class="dsr-replaced-count">
							<?php if ( ! empty( $deep_sr_row['skipped'] ) ) : ?>
								—
							<?php else : ?>
								<?php echo (int) $deep_sr_row['replaced_count']; ?>
							<?php endif; ?>
						</td>
					<?php endif; ?>
					<td><span class="dsr-sample"><?php echo esc_html( $deep_sr_row['sample_short'] ); ?></span></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( 0 === $deep_sr_total_found ) : ?>
		<?php
		$deep_sr_host = wp_parse_url( $search, PHP_URL_HOST );
		?>
		<div class="dsr-not-found">
			<span class="dashicons dashicons-info-outline"></span>
			<div>
				<strong><?php esc_html_e( 'Not found anywhere in the database!', 'deep-search-replace' ); ?></strong>
				<ul>
					<li><?php esc_html_e( 'Remove trailing slash and search again', 'deep-search-replace' ); ?></li>
					<?php if ( $deep_sr_host ) : ?>
						<li>
							<?php
							printf(
								/* translators: %s: hostname extracted from search URL */
								esc_html__( 'Try searching just the domain: %s', 'deep-search-replace' ),
								'<code>' . esc_html( $deep_sr_host ) . '</code>'
							);
							?>
						</li>
					<?php endif; ?>
					<li><?php esc_html_e( 'Try searching a partial URL path', 'deep-search-replace' ); ?></li>
					<?php /* translators: %2F is a URL-encoded forward slash shown as an example */ ?>
					<li><?php esc_html_e( 'Check if the URL is URL-encoded (e.g., %2F instead of /)', 'deep-search-replace' ); ?></li>
					<li><?php esc_html_e( 'The content might be in a cached/static file, not in the database', 'deep-search-replace' ); ?></li>
				</ul>
			</div>
		</div>
	<?php else : ?>
		<div class="dsr-summary-bar">
			<div class="dsr-stat">
				<span class="dsr-stat-value"><?php echo (int) $deep_sr_total_found; ?></span>
				<span class="dsr-stat-label"><?php esc_html_e( 'occurrences found', 'deep-search-replace' ); ?></span>
			</div>
			<?php if ( $deep_sr_is_replace ) : ?>
				<div class="dsr-stat">
					<span class="dsr-stat-value"><?php echo (int) $deep_sr_total_replaced; ?></span>
					<span class="dsr-stat-label"><?php esc_html_e( 'rows replaced', 'deep-search-replace' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $deep_sr_is_replace && $deep_sr_total_replaced > 0 ) : ?>
		<div class="dsr-success-banner">
			<span class="dashicons dashicons-yes-alt"></span>
			<?php esc_html_e( 'Replacement complete. Object cache flushed. You may also want to clear any page cache plugin.', 'deep-search-replace' ); ?>
		</div>
	<?php endif; ?>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
