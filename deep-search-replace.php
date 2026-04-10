<?php
/**
 * Plugin Name:       Deep Search & Replace
 * Plugin URI:        https://developer.wordpress.org/plugins/deep-search-replace/
 * Description:       Searches ALL database tables for a URL/text and replaces it. Finds links hidden in serialized data, post meta, options, widgets, and more.
 * Version:           1.0.0
 * Author:            devshagor
 * Author URI:        https://profiles.wordpress.org/shagors/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       deep-search-replace
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Settings link on the plugins list page.
 *
 * @param array $links Existing action links.
 * @return array Modified action links.
 */
function deep_search_replace_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'tools.php?page=deep-search-replace' ) ) . '">' . esc_html__( 'Settings', 'deep-search-replace' ) . '</a>';
	$links[] = $settings_link;
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'deep_search_replace_action_links' );

/**
 * Redirect to the plugin page on activation.
 */
function deep_search_replace_activate() {
	add_option( 'deep_search_replace_do_activation_redirect', true );
}
register_activation_hook( __FILE__, 'deep_search_replace_activate' );

/**
 * Handle the activation redirect.
 */
function deep_search_replace_activation_redirect() {
	if ( ! get_option( 'deep_search_replace_do_activation_redirect', false ) ) {
		return;
	}

	delete_option( 'deep_search_replace_do_activation_redirect' );

	if ( isset( $_GET['activate-multi'] ) ) {
		return;
	}

	wp_safe_redirect( admin_url( 'tools.php?page=deep-search-replace' ) );
	exit;
}
add_action( 'admin_init', 'deep_search_replace_activation_redirect' );

/**
 * Clean up on uninstall.
 */
function deep_search_replace_uninstall() {
	delete_option( 'deep_search_replace_do_activation_redirect' );
}
register_uninstall_hook( __FILE__, 'deep_search_replace_uninstall' );

/**
 * Load plugin text domain.
 */
function deep_search_replace_load_textdomain() {
	load_plugin_textdomain( 'deep-search-replace', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'deep_search_replace_load_textdomain' );

/**
 * Register admin menu item under Tools.
 */
function deep_search_replace_admin_menu() {
	$hook = add_management_page(
		__( 'Deep Search & Replace', 'deep-search-replace' ),
		__( 'Deep Search & Replace', 'deep-search-replace' ),
		'manage_options',
		'deep-search-replace',
		'deep_search_replace_admin_page'
	);

	if ( $hook ) {
		add_action( 'admin_print_styles-' . $hook, 'deep_search_replace_admin_styles' );
	}
}
add_action( 'admin_menu', 'deep_search_replace_admin_menu' );

/**
 * Print admin page styles.
 */
function deep_search_replace_admin_styles() {
	?>
	<style>
		.dsr-warning {
			color: #d63638;
			font-weight: bold;
		}
		.dsr-form {
			max-width: 700px;
		}
		.dsr-results-table {
			max-width: 900px;
		}
		.dsr-not-found {
			background: #fff3cd;
			border: 1px solid #ffc107;
			padding: 12px;
			margin: 15px 0;
			border-radius: 4px;
		}
		.dsr-summary {
			font-size: 14px;
			margin-top: 15px;
		}
		.dsr-success {
			color: green;
		}
	</style>
	<?php
}

/**
 * Render the admin page.
 */
function deep_search_replace_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'deep-search-replace' ) );
	}

	$search  = '';
	$replace = '';
	$action  = '';

	$nonce_valid = false;

	if ( isset( $_POST['dsr_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsr_nonce'] ) ), 'dsr_action' ) ) {
		$nonce_valid = true;
		$search      = isset( $_POST['dsr_search'] ) ? sanitize_text_field( wp_unslash( $_POST['dsr_search'] ) ) : '';
		$replace     = isset( $_POST['dsr_replace'] ) ? sanitize_text_field( wp_unslash( $_POST['dsr_replace'] ) ) : '';
		$action      = isset( $_POST['dsr_action'] ) ? sanitize_text_field( wp_unslash( $_POST['dsr_action'] ) ) : '';
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Deep Search & Replace', 'deep-search-replace' ); ?></h1>
		<p class="dsr-warning">
			<?php esc_html_e( 'Always backup your database before replacing. Use "Search Only" first to preview results.', 'deep-search-replace' ); ?>
		</p>

		<form method="post" class="dsr-form">
			<?php wp_nonce_field( 'dsr_action', 'dsr_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="dsr_search"><?php esc_html_e( 'Search for:', 'deep-search-replace' ); ?></label></th>
					<td>
						<input
							type="text"
							id="dsr_search"
							name="dsr_search"
							value="<?php echo esc_attr( $search ); ?>"
							class="regular-text"
							style="width:100%;"
							placeholder="https://old-url.com/page/"
							required
						/>
					</td>
				</tr>
				<tr>
					<th><label for="dsr_replace"><?php esc_html_e( 'Replace with:', 'deep-search-replace' ); ?></label></th>
					<td>
						<input
							type="text"
							id="dsr_replace"
							name="dsr_replace"
							value="<?php echo esc_attr( $replace ); ?>"
							class="regular-text"
							style="width:100%;"
							placeholder="https://new-url.com/page/"
						/>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" name="dsr_action" value="search" class="button button-secondary">
					<?php esc_html_e( 'Search Only (Safe Preview)', 'deep-search-replace' ); ?>
				</button>
				&nbsp;
				<button type="submit" name="dsr_action" value="replace" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure? This will modify your database.', 'deep-search-replace' ) ); ?>');">
					<?php esc_html_e( 'Search & Replace', 'deep-search-replace' ); ?>
				</button>
			</p>
		</form>

		<?php
		if ( $nonce_valid && ! empty( $search ) && in_array( $action, array( 'search', 'replace' ), true ) ) {
			deep_search_replace_process( $search, $replace, $action );
		}
		?>
	</div>
	<?php
}

/**
 * Process search/replace across all database tables.
 *
 * @param string $search  The string to search for.
 * @param string $replace The replacement string.
 * @param string $action  Either 'search' or 'replace'.
 */
function deep_search_replace_process( $search, $replace, $action ) {
	global $wpdb;

	$is_replace     = ( 'replace' === $action && '' !== $replace );
	$tables         = $wpdb->get_col( 'SHOW TABLES' );
	$total_found    = 0;
	$total_replaced = 0;

	if ( $is_replace ) {
		echo '<hr><h2>' . esc_html__( 'Search & Replace Results', 'deep-search-replace' ) . '</h2>';
	} else {
		echo '<hr><h2>' . esc_html__( 'Search Results', 'deep-search-replace' ) . '</h2>';
	}

	echo '<table class="widefat striped dsr-results-table">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__( 'Table', 'deep-search-replace' ) . '</th>';
	echo '<th>' . esc_html__( 'Column', 'deep-search-replace' ) . '</th>';
	echo '<th>' . esc_html__( 'Found', 'deep-search-replace' ) . '</th>';
	if ( $is_replace ) {
		echo '<th>' . esc_html__( 'Replaced', 'deep-search-replace' ) . '</th>';
	}
	echo '<th>' . esc_html__( 'Sample', 'deep-search-replace' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $tables as $table ) {
		// Validate table name: only allow alphanumeric, underscores, and hyphens.
		if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $table ) ) {
			continue;
		}

		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name validated above.

		foreach ( $columns as $col ) {
			if ( ! preg_match( '/(char|text|varchar|longtext|mediumtext|blob)/i', $col->Type ) ) {
				continue;
			}

			// Validate column name.
			$col_name = $col->Field;
			if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $col_name ) ) {
				continue;
			}

			$found = $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers validated above.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE `{$col_name}` LIKE %s",
					'%' . $wpdb->esc_like( $search ) . '%'
				)
			);

			if ( (int) $found < 1 ) {
				continue;
			}

			$total_found += (int) $found;

			$sample = $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers validated above.
				$wpdb->prepare(
					"SELECT `{$col_name}` FROM `{$table}` WHERE `{$col_name}` LIKE %s LIMIT 1",
					'%' . $wpdb->esc_like( $search ) . '%'
				)
			);

			$sample_short = mb_substr( wp_strip_all_tags( $sample ), 0, 120 ) . '...';

			$replaced_count = 0;

			if ( $is_replace ) {
				$replaced_count = deep_search_replace_table_column( $table, $col_name, $columns, $search, $replace, (int) $found );
				$total_replaced += $replaced_count;
			}

			echo '<tr>';
			echo '<td><strong>' . esc_html( $table ) . '</strong></td>';
			echo '<td>' . esc_html( $col_name ) . '</td>';
			echo '<td>' . (int) $found . '</td>';
			if ( $is_replace ) {
				echo '<td>' . (int) $replaced_count . '</td>';
			}
			echo '<td><small>' . esc_html( $sample_short ) . '</small></td>';
			echo '</tr>';
		}
	}

	echo '</tbody></table>';

	if ( 0 === $total_found ) {
		deep_search_replace_not_found_message( $search );
	} else {
		echo '<p class="dsr-summary">';
		printf(
			/* translators: %d: total number of occurrences found */
			'<strong>' . esc_html__( 'Total found: %d occurrences', 'deep-search-replace' ) . '</strong>',
			$total_found
		);
		if ( $is_replace ) {
			printf(
				/* translators: %d: total number of replacements made */
				' | <strong>' . esc_html__( 'Total replaced: %d', 'deep-search-replace' ) . '</strong>',
				$total_replaced
			);
		}
		echo '</p>';
	}

	if ( $is_replace && $total_replaced > 0 ) {
		wp_cache_flush();
		echo '<p class="dsr-success">';
		esc_html_e( 'Replacement complete. Object cache flushed. You may also want to clear any page cache plugin.', 'deep-search-replace' );
		echo '</p>';
	}
}

/**
 * Replace values in a specific table column, handling serialized data.
 *
 * @param string $table    Table name (validated).
 * @param string $col_name Column name (validated).
 * @param array  $columns  All columns for the table.
 * @param string $search   Search string.
 * @param string $replace  Replace string.
 * @param int    $found    Number of rows found.
 * @return int Number of rows replaced.
 */
function deep_search_replace_table_column( $table, $col_name, $columns, $search, $replace, $found ) {
	global $wpdb;

	// Find primary key.
	$pk = null;
	foreach ( $columns as $c ) {
		if ( 'PRI' === $c->Key ) {
			$pk = $c->Field;
			break;
		}
	}

	if ( $pk && preg_match( '/^[a-zA-Z0-9_\-]+$/', $pk ) ) {
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers validated.
			$wpdb->prepare(
				"SELECT `{$pk}`, `{$col_name}` FROM `{$table}` WHERE `{$col_name}` LIKE %s",
				'%' . $wpdb->esc_like( $search ) . '%'
			),
			ARRAY_A
		);

		$replaced_count = 0;
		foreach ( $rows as $row ) {
			$old_val = $row[ $col_name ];
			$new_val = deep_search_replace_recursive( $search, $replace, $old_val );
			if ( $old_val !== $new_val ) {
				$wpdb->update(
					$table,
					array( $col_name => $new_val ),
					array( $pk => $row[ $pk ] ),
					array( '%s' ),
					array( '%s' )
				);
				++$replaced_count;
			}
		}

		return $replaced_count;
	}

	// No primary key — use direct SQL replace.
	$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers validated.
		$wpdb->prepare(
			"UPDATE `{$table}` SET `{$col_name}` = REPLACE(`{$col_name}`, %s, %s) WHERE `{$col_name}` LIKE %s",
			$search,
			$replace,
			'%' . $wpdb->esc_like( $search ) . '%'
		)
	);

	return $found;
}

/**
 * Display not-found troubleshooting message.
 *
 * @param string $search The search string.
 */
function deep_search_replace_not_found_message( $search ) {
	$host = wp_parse_url( $search, PHP_URL_HOST );

	echo '<div class="dsr-not-found">';
	echo '<strong>' . esc_html__( 'Not found anywhere in the database!', 'deep-search-replace' ) . '</strong><br>';
	echo esc_html__( 'Try these troubleshooting tips:', 'deep-search-replace' ) . '<br>';
	echo esc_html__( '- Remove trailing slash and search again', 'deep-search-replace' ) . '<br>';

	if ( $host ) {
		printf(
			/* translators: %s: hostname extracted from search URL */
			esc_html__( '- Try searching just the domain: %s', 'deep-search-replace' ) . '<br>',
			'<code>' . esc_html( $host ) . '</code>'
		);
	}

	echo esc_html__( '- Try searching a partial URL path', 'deep-search-replace' ) . '<br>';
	echo esc_html__( '- Check if the URL is URL-encoded (e.g., %2F instead of /)', 'deep-search-replace' ) . '<br>';
	echo esc_html__( '- The content might be in a cached/static file, not in the database', 'deep-search-replace' ) . '<br>';
	echo '</div>';
}

/**
 * Recursively handle serialized data replacement.
 *
 * @param string $search  Search string.
 * @param string $replace Replace string.
 * @param string $data    Data to process.
 * @return string Processed data.
 */
function deep_search_replace_recursive( $search, $replace, $data ) {
	if ( is_serialized( $data ) ) {
		$unserialized = unserialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Required for serialized data replacement.
		if ( false !== $unserialized ) {
			$unserialized = deep_search_replace_recursive_value( $search, $replace, $unserialized );
			return serialize( $unserialized ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Required for serialized data replacement.
		}
	}

	if ( is_string( $data ) ) {
		return str_replace( $search, $replace, $data );
	}

	return $data;
}

/**
 * Recursively replace values within arrays and objects.
 *
 * @param string $search  Search string.
 * @param string $replace Replace string.
 * @param mixed  $data    Data to process.
 * @return mixed Processed data.
 */
function deep_search_replace_recursive_value( $search, $replace, $data ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			$data[ $key ] = deep_search_replace_recursive_value( $search, $replace, $value );
		}
	} elseif ( is_object( $data ) ) {
		foreach ( get_object_vars( $data ) as $key => $value ) {
			$data->$key = deep_search_replace_recursive_value( $search, $replace, $value );
		}
	} elseif ( is_string( $data ) ) {
		if ( is_serialized( $data ) ) {
			return deep_search_replace_recursive( $search, $replace, $data );
		}
		$data = str_replace( $search, $replace, $data );
	}

	return $data;
}
