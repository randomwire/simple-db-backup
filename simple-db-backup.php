<?php
/**
 * Plugin Name:       Simple DB Backup
 * Plugin URI:        https://github.com/randomwire/simple-db-backup
 * Description:       Safe, modern database backups for WordPress — create, schedule, download, restore, optimize and repair.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            David Gilbert
 * Author URI:        https://randomwire.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-db-backup
 *
 * Based on WP-DBManager by Lester 'GaMerZ' Chan
 * (https://github.com/lesterchan/wp-dbmanager), licensed GPL-2.0-or-later.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/updater.php';
randomwire_init_github_updater( __FILE__ );

define( 'SIMPLE_DB_BACKUP_VERSION', '1.0.0' );
define( 'SIMPLE_DB_BACKUP_FILE', __FILE__ );
define( 'SIMPLE_DB_BACKUP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_DB_BACKUP_URL', plugin_dir_url( __FILE__ ) );

require_once SIMPLE_DB_BACKUP_DIR . 'includes/class-simple-db-backup-filesystem.php';
require_once SIMPLE_DB_BACKUP_DIR . 'includes/class-simple-db-backup-settings.php';
require_once SIMPLE_DB_BACKUP_DIR . 'includes/class-simple-db-backup-backup.php';
require_once SIMPLE_DB_BACKUP_DIR . 'includes/class-simple-db-backup-restore.php';
require_once SIMPLE_DB_BACKUP_DIR . 'includes/class-simple-db-backup-manage.php';
require_once SIMPLE_DB_BACKUP_DIR . 'includes/class-simple-db-backup-maintenance.php';
require_once SIMPLE_DB_BACKUP_DIR . 'includes/class-simple-db-backup-cron.php';
require_once SIMPLE_DB_BACKUP_DIR . 'includes/class-simple-db-backup-plugin.php';

/**
 * Boot the plugin once all classes are loaded.
 */
function simple_db_backup_bootstrap() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new Simple_DB_Backup_Plugin();
		$plugin->run();
	}

	return $plugin;
}
simple_db_backup_bootstrap();

/**
 * Activation: set default options, create and harden the backup directory,
 * and schedule any enabled cron events.
 */
function simple_db_backup_activate() {
	Simple_DB_Backup_Settings::install_defaults();
	Simple_DB_Backup_Filesystem::ensure_protected_dir();
	Simple_DB_Backup_Cron::reschedule_all();
}
register_activation_hook( __FILE__, 'simple_db_backup_activate' );

/**
 * Deactivation: clear scheduled events. Backups are intentionally preserved.
 */
function simple_db_backup_deactivate() {
	Simple_DB_Backup_Cron::clear_all();
}
register_deactivation_hook( __FILE__, 'simple_db_backup_deactivate' );

/**
 * Append a Donate link to this plugin's row on the Plugins page.
 *
 * @param string[] $links Existing row meta.
 * @param string   $file  Plugin file being filtered.
 * @return string[]
 */
function simple_db_backup_plugin_row_meta( $links, $file ) {
	if ( plugin_basename( __FILE__ ) === $file ) {
		$links[] = '<a href="https://ko-fi.com/randomwire" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'Donate', 'simple-db-backup' )
			. '</a>';
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'simple_db_backup_plugin_row_meta', 10, 2 );
