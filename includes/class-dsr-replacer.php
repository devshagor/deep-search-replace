<?php
/**
 * Core search & replace engine.
 *
 * @package Deep_Search_Replace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles searching and replacing across all database tables.
 *
 * Note: This class intentionally uses direct database queries with dynamically-built
 * SQL identifiers (table/column names). All identifiers are validated against a strict
 * regex (/^[a-zA-Z0-9_\-]+$/) before use. MySQL identifiers (table/column names) cannot
 * be parameterised via $wpdb->prepare() placeholders, so interpolation is required.
 */
class DSR_Replacer {

	/**
	 * Whether to protect slugs and URLs during replacement.
	 *
	 * @var bool
	 */
	private static $skip_urls = false;

	/**
	 * Column names that store slugs, permalinks, or identifiers.
	 * These columns are skipped entirely when "Skip URLs" is enabled,
	 * because modifying them breaks permalinks and causes 404 errors.
	 *
	 * @var array
	 */
	private static $protected_columns = array(
		'post_name',      // Post/page slug.
		'guid',           // Permanent post identifier URL.
		'slug',           // Term slug (wp_terms).
		'user_login',     // Username.
		'user_nicename',  // URL-safe username slug.
		'user_email',     // Email address.
		'comment_agent',  // Browser user agent.
	);

	/**
	 * URL pattern for protecting URLs within text content.
	 *
	 * @var string
	 */
	private static $url_pattern = '~(?:https?://|//)[^\s<>"\'`\)\]\},;]+~i';

	/**
	 * Process search/replace across all database tables.
	 *
	 * @param string $search    The string to search for.
	 * @param string $replace   The replacement string.
	 * @param string $action    Either 'search' or 'replace'.
	 * @param bool   $skip_urls Whether to protect slugs and URLs.
	 * @return array {
	 *     @type bool  $is_replace    Whether this was a replace action.
	 *     @type int   $total_found   Total occurrences found.
	 *     @type int   $total_replaced Total rows replaced.
	 *     @type array $rows_data     Per-column result rows.
	 * }
	 */
	public static function process( $search, $replace, $action, $skip_urls = false ) {
		global $wpdb;

		self::$skip_urls = $skip_urls;

		$is_replace     = ( 'replace' === $action && '' !== $replace );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SHOW TABLES has no WP API equivalent; result is used once per request.
		$tables         = $wpdb->get_col( 'SHOW TABLES' );
		$total_found    = 0;
		$total_replaced = 0;
		$rows_data      = array();

		foreach ( $tables as $table ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $table ) ) {
				continue;
			}

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

			$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" );

			foreach ( $columns as $col ) {
				if ( ! preg_match( '/(char|text|varchar|longtext|mediumtext|blob)/i', $col->Type ) ) {
					continue;
				}

				$col_name = $col->Field;
				if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $col_name ) ) {
					continue;
				}

				$found = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM `{$table}` WHERE `{$col_name}` LIKE %s",
						'%' . $wpdb->esc_like( $search ) . '%'
					)
				);

				if ( (int) $found < 1 ) {
					continue;
				}

				$total_found += (int) $found;

				// Check if this column is protected (slug/permalink column).
				$is_protected = $is_replace && self::$skip_urls && in_array( $col_name, self::$protected_columns, true );

				$sample = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT `{$col_name}` FROM `{$table}` WHERE `{$col_name}` LIKE %s LIMIT 1",
						'%' . $wpdb->esc_like( $search ) . '%'
					)
				);

				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

				$sample_short   = mb_substr( wp_strip_all_tags( $sample ), 0, 120 ) . '...';
				$replaced_count = 0;

				if ( $is_replace && ! $is_protected ) {
					$replaced_count = self::replace_in_column( $table, $col_name, $columns, $search, $replace, (int) $found );
					$total_replaced += $replaced_count;
				}

				$rows_data[] = array(
					'table'          => $table,
					'col_name'       => $col_name,
					'found'          => (int) $found,
					'replaced_count' => $replaced_count,
					'sample_short'   => $sample_short,
					'skipped'        => $is_protected,
				);
			}
		}

		if ( $is_replace && $total_replaced > 0 ) {
			wp_cache_flush();
		}

		return array(
			'is_replace'     => $is_replace,
			'total_found'    => $total_found,
			'total_replaced' => $total_replaced,
			'rows_data'      => $rows_data,
		);
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
	private static function replace_in_column( $table, $col_name, $columns, $search, $replace, $found ) {
		global $wpdb;

		$pk = null;
		foreach ( $columns as $c ) {
			if ( 'PRI' === $c->Key ) {
				$pk = $c->Field;
				break;
			}
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( $pk && preg_match( '/^[a-zA-Z0-9_\-]+$/', $pk ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `{$pk}`, `{$col_name}` FROM `{$table}` WHERE `{$col_name}` LIKE %s",
					'%' . $wpdb->esc_like( $search ) . '%'
				),
				ARRAY_A
			);

			$replaced_count = 0;
			foreach ( $rows as $row ) {
				$old_val = $row[ $col_name ];
				$new_val = self::recursive_replace( $search, $replace, $old_val );
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

			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

			return $replaced_count;
		}

		// No primary key — when skip_urls is enabled, we must fetch rows individually
		// since SQL REPLACE cannot selectively skip URLs.
		if ( self::$skip_urls ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE `{$col_name}` LIKE %s",
					'%' . $wpdb->esc_like( $search ) . '%'
				),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

			$replaced_count = 0;
			foreach ( $rows as $row ) {
				$old_val = $row[ $col_name ];
				$new_val = self::recursive_replace( $search, $replace, $old_val );
				if ( $old_val !== $new_val ) {
					$where = $row;
					unset( $where[ $col_name ] );
					if ( ! empty( $where ) ) {
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->update(
							$table,
							array( $col_name => $new_val ),
							$where
						);
						// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						++$replaced_count;
					}
				}
			}

			return $replaced_count;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

		// No primary key and skip_urls off — use direct SQL replace.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET `{$col_name}` = REPLACE(`{$col_name}`, %s, %s) WHERE `{$col_name}` LIKE %s",
				$search,
				$replace,
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $found;
	}

	/**
	 * Recursively handle serialized data replacement.
	 *
	 * @param string $search  Search string.
	 * @param string $replace Replace string.
	 * @param string $data    Data to process.
	 * @return string Processed data.
	 */
	private static function recursive_replace( $search, $replace, $data ) {
		if ( is_serialized( $data ) ) {
			$unserialized = unserialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Required for serialized data replacement.
			if ( false !== $unserialized ) {
				$unserialized = self::recursive_replace_value( $search, $replace, $unserialized );
				return serialize( $unserialized ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Required for serialized data replacement.
			}
		}

		if ( is_string( $data ) ) {
			return self::safe_str_replace( $search, $replace, $data );
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
	private static function recursive_replace_value( $search, $replace, $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = self::recursive_replace_value( $search, $replace, $value );
			}
		} elseif ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->$key = self::recursive_replace_value( $search, $replace, $value );
			}
		} elseif ( is_string( $data ) ) {
			if ( is_serialized( $data ) ) {
				return self::recursive_replace( $search, $replace, $data );
			}
			$data = self::safe_str_replace( $search, $replace, $data );
		}

		return $data;
	}

	/**
	 * Replace a string while optionally protecting URLs within text content.
	 *
	 * When skip_urls is enabled, inline URLs (http/https links) found inside
	 * longer text are extracted, replaced with placeholders, the search/replace
	 * runs on the remaining text, and then URLs are restored.
	 *
	 * Note: Slug columns (post_name, guid, etc.) are already skipped entirely
	 * at the column level — this method handles URLs embedded in post content,
	 * meta values, widget text, etc.
	 *
	 * @param string $search  Search string.
	 * @param string $replace Replace string.
	 * @param string $data    The string to process.
	 * @return string Processed string.
	 */
	private static function safe_str_replace( $search, $replace, $data ) {
		if ( ! self::$skip_urls || strpos( $data, $search ) === false ) {
			return str_replace( $search, $replace, $data );
		}

		// Extract URLs, replace with placeholders, do the replacement, restore URLs.
		$placeholders = array();
		$counter      = 0;
		$prefix       = "\x00DSR_URL_" . wp_rand( 100000, 999999 ) . '_';

		$protected = preg_replace_callback(
			self::$url_pattern,
			function ( $match ) use ( &$placeholders, &$counter, $prefix ) {
				$key                  = $prefix . $counter . "\x00";
				$placeholders[ $key ] = $match[0];
				++$counter;
				return $key;
			},
			$data
		);

		// If regex failed, fall back to plain replace.
		if ( null === $protected ) {
			return str_replace( $search, $replace, $data );
		}

		// Perform the replacement on the protected string (URLs are now placeholders).
		$result = str_replace( $search, $replace, $protected );

		// Restore the original URLs.
		$result = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $result );

		return $result;
	}
}
