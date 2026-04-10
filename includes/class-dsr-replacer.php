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
	 * Process search/replace across all database tables.
	 *
	 * @param string $search  The string to search for.
	 * @param string $replace The replacement string.
	 * @param string $action  Either 'search' or 'replace'.
	 * @return array {
	 *     @type bool  $is_replace    Whether this was a replace action.
	 *     @type int   $total_found   Total occurrences found.
	 *     @type int   $total_replaced Total rows replaced.
	 *     @type array $rows_data     Per-column result rows.
	 * }
	 */
	public static function process( $search, $replace, $action ) {
		global $wpdb;

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

				$sample = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT `{$col_name}` FROM `{$table}` WHERE `{$col_name}` LIKE %s LIMIT 1",
						'%' . $wpdb->esc_like( $search ) . '%'
					)
				);

				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

				$sample_short   = mb_substr( wp_strip_all_tags( $sample ), 0, 120 ) . '...';
				$replaced_count = 0;

				if ( $is_replace ) {
					$replaced_count = self::replace_in_column( $table, $col_name, $columns, $search, $replace, (int) $found );
					$total_replaced += $replaced_count;
				}

				$rows_data[] = array(
					'table'          => $table,
					'col_name'       => $col_name,
					'found'          => (int) $found,
					'replaced_count' => $replaced_count,
					'sample_short'   => $sample_short,
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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

		// No primary key — use direct SQL replace.
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
			$data = str_replace( $search, $replace, $data );
		}

		return $data;
	}
}
