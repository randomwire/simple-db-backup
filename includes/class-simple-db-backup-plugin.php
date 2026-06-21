<?php
/**
 * Plugin loader: menus, admin-post action handlers and contextual notices.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the admin UI and request handling together. All state-changing
 * actions run through admin-post.php with a per-action nonce and a capability
 * re-check inside the handler (the CVE-2022-2354 lesson).
 */
class Simple_DB_Backup_Plugin {

	const MENU_SLUG = 'simple-db-backup';
	const INFO_SLUG = 'simple-db-backup-info';

	/**
	 * Settings instance.
	 *
	 * @var Simple_DB_Backup_Settings
	 */
	private $settings;

	/**
	 * The capability required to use the plugin.
	 *
	 * On multisite we require super-admin (manage_network_options) so a mere
	 * site administrator cannot reach shell-backed operations.
	 *
	 * @return string
	 */
	public static function capability() {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Register all hooks.
	 */
	public function run() {
		$this->settings = new Simple_DB_Backup_Settings();
		$this->settings->run();

		( new Simple_DB_Backup_Cron() )->run();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		add_action( 'admin_post_simple_db_backup_create', array( $this, 'handle_create' ) );
		add_action( 'admin_post_simple_db_backup_restore', array( $this, 'handle_restore' ) );
		add_action( 'admin_post_simple_db_backup_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_simple_db_backup_download', array( $this, 'handle_download' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Menu + pages
	 * ------------------------------------------------------------------ */

	/**
	 * Register the admin menu and sub-pages.
	 */
	public function register_menu() {
		$cap = self::capability();

		add_menu_page(
			__( 'Simple DB Backup', 'simple-db-backup' ),
			__( 'DB Backup', 'simple-db-backup' ),
			$cap,
			self::MENU_SLUG,
			array( $this, 'render_backups_page' ),
			'dashicons-database'
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Backups', 'simple-db-backup' ),
			__( 'Backups', 'simple-db-backup' ),
			$cap,
			self::MENU_SLUG,
			array( $this, 'render_backups_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Database Info', 'simple-db-backup' ),
			__( 'Database Info', 'simple-db-backup' ),
			$cap,
			self::INFO_SLUG,
			array( $this, 'render_info_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'simple-db-backup' ),
			__( 'Settings', 'simple-db-backup' ),
			$cap,
			Simple_DB_Backup_Settings::PAGE_SLUG,
			array( $this->settings, 'render_page' )
		);
	}

	/**
	 * Render the Backups page.
	 */
	public function render_backups_page() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		$backups    = Simple_DB_Backup_Filesystem::list_backups();
		$can_backup = Simple_DB_Backup_Backup::proc_available()
			&& false !== Simple_DB_Backup_Backup::validate_binary( Simple_DB_Backup_Settings::get( 'mysqldump_path', '' ) );

		$this->render_view( 'backups', compact( 'backups', 'can_backup' ) );
	}

	/**
	 * Render the Database Info page.
	 */
	public function render_info_page() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT TABLE_NAME AS name, TABLE_ROWS AS rows_count, (DATA_LENGTH + INDEX_LENGTH) AS size
				 FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s ORDER BY TABLE_NAME',
				DB_NAME
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$server_version = $wpdb->db_version();

		$this->render_view( 'info', compact( 'rows', 'server_version' ) );
	}

	/**
	 * Include an admin view template with the given variables in scope.
	 *
	 * @param string              $name View base name (without extension).
	 * @param array<string,mixed> $vars Variables exposed to the template.
	 */
	private function render_view( $name, array $vars = array() ) {
		$file = SIMPLE_DB_BACKUP_DIR . 'admin/views/' . $name . '.php';
		if ( ! is_readable( $file ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_SKIP );
		require $file;
	}

	/* ------------------------------------------------------------------ *
	 * Action handlers (admin-post.php)
	 * ------------------------------------------------------------------ */

	/**
	 * Create a backup now.
	 */
	public function handle_create() {
		$this->require_cap();
		check_admin_referer( 'simple_db_backup_create' );

		$result = Simple_DB_Backup_Backup::create();
		$this->set_notice( $result['success'] ? 'success' : 'error', $result['message'] );
		$this->redirect( self::MENU_SLUG );
	}

	/**
	 * Restore from a selected backup.
	 */
	public function handle_restore() {
		$this->require_cap();
		check_admin_referer( 'simple_db_backup_restore' );

		$name   = isset( $_POST['backup'] ) ? sanitize_text_field( wp_unslash( $_POST['backup'] ) ) : '';
		$result = Simple_DB_Backup_Restore::restore( $name );
		$this->set_notice( $result['success'] ? 'success' : 'error', $result['message'] );
		$this->redirect( self::MENU_SLUG );
	}

	/**
	 * Delete a backup.
	 */
	public function handle_delete() {
		$this->require_cap();
		check_admin_referer( 'simple_db_backup_delete' );

		$name = isset( $_POST['backup'] ) ? sanitize_text_field( wp_unslash( $_POST['backup'] ) ) : '';
		if ( Simple_DB_Backup_Manage::delete( $name ) ) {
			$this->set_notice( 'success', __( 'Backup deleted.', 'simple-db-backup' ) );
		} else {
			$this->set_notice( 'error', __( 'That backup could not be deleted.', 'simple-db-backup' ) );
		}
		$this->redirect( self::MENU_SLUG );
	}

	/**
	 * Stream a backup download.
	 */
	public function handle_download() {
		$this->require_cap();
		check_admin_referer( 'simple_db_backup_download' );

		$name = isset( $_GET['backup'] ) ? sanitize_text_field( wp_unslash( $_GET['backup'] ) ) : '';
		Simple_DB_Backup_Manage::download( $name ); // Streams and exits.
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Stop the request unless the current user has the required capability.
	 */
	private function require_cap() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage database backups.', 'simple-db-backup' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Store a one-time contextual notice for the current user.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Message text.
	 */
	private function set_notice( $type, $message ) {
		set_transient(
			'simple_db_backup_notice_' . get_current_user_id(),
			array(
				'type'    => 'error' === $type ? 'error' : 'success',
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Render and clear the current user's one-time notice (called from views).
	 * This is scoped to the plugin's own screens — we never hook admin_notices.
	 */
	public static function render_notices() {
		$key    = 'simple_db_backup_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( empty( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			'error' === $notice['type'] ? 'error' : 'success',
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Redirect back to one of the plugin's admin pages.
	 *
	 * @param string $page Page slug.
	 */
	private function redirect( $page ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}
}
