<?php
/**
 * Restore: import a managed backup file back into the database.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restores from a backup that already lives in the managed directory. There is
 * no arbitrary-path or external-upload entry point, and no DROP/empty path —
 * a restore only imports the SQL the dump contains.
 */
class Simple_DB_Backup_Restore {

	/**
	 * Restore from a backup file selected by name.
	 *
	 * @param string $name Backup file name (validated against the managed dir).
	 * @return array{success:bool,message:string}
	 */
	public static function restore( $name ) {
		if ( ! Simple_DB_Backup_Backup::proc_available() ) {
			return self::error( __( 'Restores require the PHP proc_open function, which is disabled on this server.', 'simple-db-backup' ) );
		}

		$mysql = Simple_DB_Backup_Backup::validate_binary( Simple_DB_Backup_Settings::get( 'mysql_path', '' ) );
		if ( false === $mysql ) {
			return self::error( __( 'The mysql path is not set or is not a valid executable. Set it on the Settings screen.', 'simple-db-backup' ) );
		}

		$path = Simple_DB_Backup_Filesystem::resolve_backup_path( $name );
		if ( false === $path ) {
			return self::error( __( 'That backup file could not be found.', 'simple-db-backup' ) );
		}

		$is_gzip = '.gz' === strtolower( substr( $path, -3 ) );
		if ( $is_gzip && ! Simple_DB_Backup_Backup::gzip_available() ) {
			return self::error( __( 'This backup is gzip-compressed but gzip support is unavailable on this server.', 'simple-db-backup' ) );
		}

		$dir = Simple_DB_Backup_Filesystem::get_backup_dir();
		$cnf = Simple_DB_Backup_Backup::write_defaults_file( $dir );
		if ( false === $cnf ) {
			return self::error( __( 'Could not create the temporary credentials file.', 'simple-db-backup' ) );
		}

		try {
			$command = array(
				$mysql,
				'--defaults-extra-file=' . $cnf,
				'--default-character-set=utf8mb4',
				DB_NAME,
			);

			$result = self::run_from_file( $command, $path, $is_gzip );
		} finally {
			Simple_DB_Backup_Backup::remove_file( $cnf );
		}

		if ( ! $result['success'] ) {
			return self::error(
				sprintf(
					/* translators: %s: error detail from mysql. */
					__( 'Restore failed: %s', 'simple-db-backup' ),
					$result['message']
				)
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Database restored successfully.', 'simple-db-backup' ),
		);
	}

	/**
	 * Run a process, streaming a (optionally gzipped) file into its stdin.
	 *
	 * Delegates to the shared, deadlock-safe runner so that mysql's stdin is
	 * fed while its stdout/stderr are drained concurrently.
	 *
	 * @param string[] $command Argv array (no shell involved).
	 * @param string   $source  Source backup file path.
	 * @param bool     $gzip    Whether the source is gzip-compressed.
	 * @return array{success:bool,message:string}
	 */
	private static function run_from_file( array $command, $source, $gzip ) {
		$in = $gzip ? gzopen( $source, 'rb' ) : fopen( $source, 'rb' );
		if ( false === $in ) {
			return self::error( __( 'Could not open the backup file for reading.', 'simple-db-backup' ) );
		}

		$reader = static function () use ( $in, $gzip ) {
			if ( $gzip ? gzeof( $in ) : feof( $in ) ) {
				return '';
			}
			$chunk = $gzip ? gzread( $in, 65536 ) : fread( $in, 65536 );
			return false === $chunk ? '' : $chunk;
		};

		$result = Simple_DB_Backup_Backup::run_process( $command, $reader, null );

		if ( $gzip ) {
			gzclose( $in );
		} else {
			fclose( $in );
		}

		return $result;
	}

	/**
	 * Build a failure result.
	 *
	 * @param string $message Error message.
	 * @return array{success:bool,message:string}
	 */
	private static function error( $message ) {
		return array( 'success' => false, 'message' => $message );
	}
}
