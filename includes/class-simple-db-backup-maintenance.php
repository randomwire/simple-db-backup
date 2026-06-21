<?php
/**
 * Maintenance: non-destructive OPTIMIZE / REPAIR over selected tables.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs OPTIMIZE TABLE / REPAIR TABLE on tables chosen from the live table
 * list. Table identifiers are validated against that list and back-tick
 * quoted; there is no TRUNCATE/DROP anywhere.
 */
class Simple_DB_Backup_Maintenance {

	/**
	 * Base tables in the current database. Views are excluded — they cannot be
	 * optimized or repaired and would only produce error rows.
	 *
	 * @return string[]
	 */
	public static function get_tables() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'", ARRAY_N );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$tables = array();
		foreach ( $rows as $row ) {
			$tables[] = $row[0];
		}
		return $tables;
	}

	/**
	 * Optimize the given tables.
	 *
	 * @param string[] $tables Requested table names.
	 * @return array{success:bool,message:string}
	 */
	public static function optimize( array $tables ) {
		return self::run( 'OPTIMIZE', $tables );
	}

	/**
	 * Repair the given tables.
	 *
	 * @param string[] $tables Requested table names.
	 * @return array{success:bool,message:string}
	 */
	public static function repair( array $tables ) {
		return self::run( 'REPAIR', $tables );
	}

	/**
	 * Validate the requested tables and run the given statement.
	 *
	 * @param string   $statement Either 'OPTIMIZE' or 'REPAIR'.
	 * @param string[] $requested Requested table names.
	 * @return array{success:bool,message:string}
	 */
	private static function run( $statement, array $requested ) {
		global $wpdb;

		$statement = 'REPAIR' === $statement ? 'REPAIR' : 'OPTIMIZE';
		$allowed   = self::get_tables();
		$valid     = array_values( array_intersect( $requested, $allowed ) );

		if ( empty( $valid ) ) {
			return array(
				'success' => false,
				'message' => __( 'No valid tables were selected.', 'simple-db-backup' ),
			);
		}

		$quoted = array_map(
			static function ( $table ) {
				return '`' . str_replace( '`', '``', $table ) . '`';
			},
			$valid
		);

		$label = 'OPTIMIZE' === $statement ? __( 'Optimize', 'simple-db-backup' ) : __( 'Repair', 'simple-db-backup' );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $statement . ' TABLE ' . implode( ', ', $quoted ) );

		if ( ! is_array( $rows ) ) {
			return array(
				'success' => false,
				/* translators: %s: Optimize or Repair. */
				'message' => sprintf( __( '%s failed: the database returned an error.', 'simple-db-backup' ), $label ),
			);
		}

		// Collect tables the server reported an error for. InnoDB "note" rows
		// (e.g. "doesn't support optimize/repair") are informational, not errors.
		$failed = array();
		foreach ( $rows as $row ) {
			if ( isset( $row->Msg_type ) && 'error' === strtolower( (string) $row->Msg_type ) ) {
				$failed[ (string) $row->Table ] = true;
			}
		}

		if ( ! empty( $failed ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: Optimize or Repair, 2: comma-separated table names. */
					__( '%1$s reported errors on: %2$s', 'simple-db-backup' ),
					$label,
					implode( ', ', array_keys( $failed ) )
				),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: 1: Optimize or Repair, 2: number of tables. */
				_n( '%1$s completed on %2$d table.', '%1$s completed on %2$d tables.', count( $valid ), 'simple-db-backup' ),
				$label,
				count( $valid )
			),
		);
	}
}
