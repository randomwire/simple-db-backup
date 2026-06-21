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

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( $command, $descriptors, $pipes );
		if ( ! is_resource( $process ) ) {
			$gzip ? gzclose( $in ) : fclose( $in );
			return self::error( __( 'Could not start the restore process.', 'simple-db-backup' ) );
		}

		while ( ! ( $gzip ? gzeof( $in ) : feof( $in ) ) ) {
			$chunk = $gzip ? gzread( $in, 65536 ) : fread( $in, 65536 );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			fwrite( $pipes[0], $chunk );
		}

		$gzip ? gzclose( $in ) : fclose( $in );
		fclose( $pipes[0] );

		stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$exit_code = proc_close( $process );

		if ( 0 !== $exit_code ) {
			$detail = trim( (string) $stderr );
			if ( '' === $detail ) {
				/* translators: %d: process exit code. */
				$detail = sprintf( __( 'process exited with code %d', 'simple-db-backup' ), $exit_code );
			}
			if ( defined( 'DB_PASSWORD' ) && '' !== DB_PASSWORD ) {
				$detail = str_replace( DB_PASSWORD, '******', $detail );
			}
			return self::error( $detail );
		}

		return array( 'success' => true, 'message' => '' );
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
