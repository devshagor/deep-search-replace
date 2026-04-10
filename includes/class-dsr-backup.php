<?php
/**
 * Database backup handler.
 *
 * @package Deep_Search_Replace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams a full .sql database dump as a browser download.
 */
class DSR_Backup {

	/**
	 * Handle the backup download request.
	 *
	 * Should be called early (admin_init) before any output is sent.
	 */
	public static function handle_download() {
		if ( ! isset( $_POST['dsr_backup_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsr_backup_nonce'] ) ), 'dsr_backup' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$filename = 'db-backup-' . sanitize_file_name( DB_NAME ) . '-' . gmdate( 'Y-m-d-His' ) . '.sql';

		header( 'Content-Type: application/sql' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Outputting raw SQL for .sql file download, not HTML.
		echo '-- Deep Search & Replace — Database Backup' . "\n";
		echo '-- Date: ' . esc_html( gmdate( 'Y-m-d H:i:s' ) ) . " UTC\n";
		echo '-- Database: ' . esc_html( DB_NAME ) . "\n\n";
		echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

		$tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Raw SQL export requires direct queries; caching not applicable for backup dump.

		foreach ( $tables as $table ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $table ) ) {
				continue;
			}

			self::dump_table( $table );
		}

		echo "SET FOREIGN_KEY_CHECKS=1;\n";
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}

	/**
	 * Dump a single table's structure and data.
	 *
	 * @param string $table Validated table name.
	 */
	private static function dump_table( $table ) {
		global $wpdb;

		// Table structure.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name validated via regex; SHOW CREATE TABLE does not support placeholders; caching not applicable for backup dump.
		$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
		if ( $create ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw SQL DDL output for .sql backup file, not HTML context.
			echo "DROP TABLE IF EXISTS `" . esc_sql( $table ) . "`;\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw SQL DDL required for backup.
			echo $create[1] . ";\n\n";
		}

		// Table data — fetch in chunks to keep memory low.
		$offset   = 0;
		$chunk    = 500;
		$col_list = null;

		while ( true ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` LIMIT %d OFFSET %d",
					$chunk,
					$offset
				),
				ARRAY_N
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

			if ( empty( $rows ) ) {
				break;
			}

			if ( null === $col_list ) {
				$col_names = $wpdb->get_col_info( 'name' );
				$col_list  = '`' . implode( '`, `', $col_names ) . '`';
			}

			foreach ( $rows as $row ) {
				$values = array();
				foreach ( $row as $value ) {
					if ( null === $value ) {
						$values[] = 'NULL';
					} else {
						$values[] = "'" . esc_sql( $value ) . "'";
					}
				}
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values escaped via esc_sql(); raw SQL output for .sql backup file.
				echo "INSERT INTO `" . esc_sql( $table ) . "` ({$col_list}) VALUES (" . implode( ', ', $values ) . ");\n";
			}

			$offset += $chunk;
		}

		echo "\n";
	}
}
