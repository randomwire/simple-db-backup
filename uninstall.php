<?php
/**
 * Uninstall Simple DB Backup: remove options, scheduled events and backups.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Resolve the effective backup directory before deleting the option: a custom
// absolute path wins, otherwise the default folder under uploads.
$options = get_option( 'simple_db_backup_options', array() );
$options = is_array( $options ) ? $options : array();
$custom  = ! empty( $options['backup_path'] ) ? trim( (string) $options['backup_path'] ) : '';
$dirname = ! empty( $options['backup_dirname'] ) ? $options['backup_dirname'] : '';

if ( '' !== $custom ) {
	$dir = untrailingslashit( wp_normalize_path( $custom ) );
} elseif ( '' !== $dirname ) {
	$uploads = wp_get_upload_dir();
	$dir     = untrailingslashit( $uploads['basedir'] ) . '/' . basename( $dirname );
} else {
	$dir = '';
}

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

// Remove state created by the bundled Plugin Update Checker (slug == text
// domain). It caches update data in a site option and schedules its own check.
$sdb_slug = 'simple-db-backup';
delete_site_option( 'external_updates-' . $sdb_slug );
wp_clear_scheduled_hook( 'puc_cron_check_updates-' . $sdb_slug );

// Sweep any leftover one-time admin notice transients (these normally expire on
// their own within a minute).
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_simple\_db\_backup\_notice\_%'
	    OR option_name LIKE '\_transient\_timeout\_simple\_db\_backup\_notice\_%'"
);

// Remove only our own files (backups + guards + stray credentials files), then
// the directory if it ends up empty. We never blanket-delete folder contents,
// since a custom path may sit alongside unrelated files.
if ( '' !== $dir && is_dir( $dir ) ) {
	$files = array();

	foreach ( array( '*.sql', '*.sql.gz' ) as $pattern ) {
		$matches = glob( $dir . '/' . $pattern );
		if ( is_array( $matches ) ) {
			$files = array_merge( $files, $matches );
		}
	}

	$leftover = glob( $dir . '/.sdb-*.cnf' );
	if ( is_array( $leftover ) ) {
		$files = array_merge( $files, $leftover );
	}

	foreach ( array( '.htaccess', 'web.config', 'index.php', 'index.html' ) as $dotfile ) {
		$files[] = $dir . '/' . $dotfile;
	}

	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}

	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged — only succeeds if now empty.
}
