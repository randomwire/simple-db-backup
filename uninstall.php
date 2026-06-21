<?php
/**
 * Uninstall Simple DB Backup: remove options, scheduled events and backups.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Recover the backup directory name before deleting the option.
$options = get_option( 'simple_db_backup_options', array() );
$dirname = is_array( $options ) && ! empty( $options['backup_dirname'] ) ? $options['backup_dirname'] : '';

// Remove the stored options.
delete_option( 'simple_db_backup_options' );

// Clear any scheduled events.
foreach ( array(
	'simple_db_backup_cron_backup',
	'simple_db_backup_cron_optimize',
	'simple_db_backup_cron_repair',
) as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// Remove the backup directory and its contents (confined to uploads).
if ( '' !== $dirname ) {
	$uploads = wp_get_upload_dir();
	$dir     = untrailingslashit( $uploads['basedir'] ) . '/' . basename( $dirname );

	if ( is_dir( $dir ) && 0 === strpos( $dir, untrailingslashit( $uploads['basedir'] ) ) ) {
		$items = glob( $dir . '/*' );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( is_file( $item ) ) {
					wp_delete_file( $item );
				}
			}
		}
		// Remove protection dotfiles and any leftover temporary credentials files.
		$dotfiles = array( '.htaccess', 'web.config', 'index.php', 'index.html' );
		$leftover = glob( $dir . '/.sdb-*.cnf' );
		if ( is_array( $leftover ) ) {
			foreach ( $leftover as $cnf ) {
				$dotfiles[] = basename( $cnf );
			}
		}
		foreach ( $dotfiles as $dotfile ) {
			$path = $dir . '/' . $dotfile;
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
