<?php
/**
 * Manage backups: stream downloads and delete files, with strict path checks.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download and delete operations. Every filename is resolved through
 * Simple_DB_Backup_Filesystem::resolve_backup_path(), so path traversal to
 * files outside the managed directory is impossible.
 */
class Simple_DB_Backup_Manage {

	/**
	 * Stream a backup file to the browser as a download, then exit.
	 *
	 * @param string $name Backup file name.
	 */
	public static function download( $name ) {
		$path = Simple_DB_Backup_Filesystem::resolve_backup_path( $name );
		if ( false === $path ) {
			wp_die( esc_html__( 'That backup file could not be found.', 'simple-db-backup' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		$handle = fopen( $path, 'rb' );
		if ( false !== $handle ) {
			while ( ! feof( $handle ) ) {
				echo fread( $handle, 65536 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				flush();
			}
			fclose( $handle );
		}
		exit;
	}

	/**
	 * Delete a backup file.
	 *
	 * @param string $name Backup file name.
	 * @return bool True on success.
	 */
	public static function delete( $name ) {
		$path = Simple_DB_Backup_Filesystem::resolve_backup_path( $name );
		if ( false === $path ) {
			return false;
		}
		wp_delete_file( $path );
		return ! file_exists( $path );
	}
}
