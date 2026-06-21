<?php
/**
 * Scheduled tasks: automatic backup, optimize and repair via WP-Cron.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers cron schedules and event handlers, and keeps the scheduled
 * events in sync with the frequencies chosen on the Settings screen.
 */
class Simple_DB_Backup_Cron {

	const HOOK_BACKUP = 'simple_db_backup_cron_backup';

	/**
	 * Hooks scheduled by earlier versions that no longer exist (optimize and
	 * repair are now part of the backup run). Cleared so no orphan events remain.
	 *
	 * @var string[]
	 */
	private static $legacy_hooks = array(
		'simple_db_backup_cron_optimize',
		'simple_db_backup_cron_repair',
	);

	/**
	 * Map of option key => cron hook.
	 *
	 * @return array<string,string>
	 */
	private static function task_map() {
		return array(
			'backup_frequency' => self::HOOK_BACKUP,
		);
	}

	/**
	 * Map a configured frequency to a WP-Cron recurrence name.
	 *
	 * @param string $frequency One of never|daily|weekly|monthly.
	 * @return string|false Recurrence name, or false for "never".
	 */
	private static function recurrence( $frequency ) {
		switch ( $frequency ) {
			case 'daily':
				return 'daily';
			case 'weekly':
				return 'weekly';
			case 'monthly':
				return 'sdb_monthly';
			default:
				return false;
		}
	}

	/**
	 * Register cron schedules and handlers.
	 */
	public function run() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) );
		add_action( self::HOOK_BACKUP, array( __CLASS__, 'do_backup' ) );
	}

	/**
	 * Add custom cron schedules (WordPress lacks a monthly schedule).
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_schedules( $schedules ) {
		if ( ! isset( $schedules['sdb_monthly'] ) ) {
			$schedules['sdb_monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'simple-db-backup' ),
			);
		}
		return $schedules;
	}

	/**
	 * Re-sync all scheduled events with the current settings.
	 */
	public static function reschedule_all() {
		// Drop any events scheduled by earlier versions.
		foreach ( self::$legacy_hooks as $legacy ) {
			wp_clear_scheduled_hook( $legacy );
		}

		foreach ( self::task_map() as $option_key => $hook ) {
			$recurrence = self::recurrence( (string) Simple_DB_Backup_Settings::get( $option_key, 'never' ) );

			$existing = wp_next_scheduled( $hook );
			if ( $existing ) {
				wp_unschedule_event( $existing, $hook );
			}

			if ( false !== $recurrence ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, $hook );
			}
		}
	}

	/**
	 * Unschedule every event (used on deactivation/uninstall).
	 */
	public static function clear_all() {
		foreach ( self::task_map() as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		foreach ( self::$legacy_hooks as $legacy ) {
			wp_clear_scheduled_hook( $legacy );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Scheduled backup. Repair and optimize run inside create().
	 */
	public static function do_backup() {
		Simple_DB_Backup_Backup::create();
	}
}
