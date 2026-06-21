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
	 * All tables in the current database.
	 *
	 * @return string[]
	 */
	public static function get_tables() {
		global $wpdb;
		$tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_array( $tables ) ? $tables : array();
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

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $statement . ' TABLE ' . implode( ', ', $quoted ) );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: 1: OPTIMIZE or REPAIR, 2: number of tables. */
				_n( '%1$s completed on %2$d table.', '%1$s completed on %2$d tables.', count( $valid ), 'simple-db-backup' ),
				'OPTIMIZE' === $statement ? __( 'Optimize', 'simple-db-backup' ) : __( 'Repair', 'simple-db-backup' ),
				count( $valid )
			),
		);
	}
}
