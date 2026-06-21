<?php
/**
 * Settings: options schema, defaults, and the Settings API options page.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores configuration in a single serialized option and renders the
 * Settings screen using the core WordPress Settings API.
 */
class Simple_DB_Backup_Settings {

	const OPTION_NAME    = 'simple_db_backup_options';
	const SETTINGS_GROUP = 'simple_db_backup_settings';
	const PAGE_SLUG      = 'simple-db-backup-settings';

	/**
	 * Default option values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'mysqldump_path'     => '',
			'mysql_path'         => '',
			'max_backups'        => 10,
			'gzip'               => true,
			'backup_frequency'   => 'never',
			'optimize_frequency' => 'never',
			'repair_frequency'   => 'never',
			// Unguessable directory segment under uploads; set once on install.
			'backup_dirname'     => '',
		);
	}

	/**
	 * Allowed cron frequencies mapped to display labels.
	 *
	 * @return array<string,string>
	 */
	public static function frequencies() {
		return array(
			'never'   => __( 'Never', 'simple-db-backup' ),
			'daily'   => __( 'Daily', 'simple-db-backup' ),
			'weekly'  => __( 'Weekly', 'simple-db-backup' ),
			'monthly' => __( 'Monthly', 'simple-db-backup' ),
		);
	}

	/**
	 * Get all options merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_options() {
		$options = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		return wp_parse_args( $options, self::defaults() );
	}

	/**
	 * Get a single option value.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default_value Fallback if unset.
	 * @return mixed
	 */
	public static function get( $key, $default_value = null ) {
		$options = self::get_options();
		return array_key_exists( $key, $options ) ? $options[ $key ] : $default_value;
	}

	/**
	 * Seed default options and a random backup directory name on activation.
	 * Existing values are preserved.
	 */
	public static function install_defaults() {
		$options = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$options = wp_parse_args( $options, self::defaults() );

		if ( empty( $options['backup_dirname'] ) ) {
			$options['backup_dirname'] = 'sdb-backups-' . wp_generate_password( 12, false, false );
		}

		// Default gzip on, but only where the server actually supports it.
		if ( ! Simple_DB_Backup_Backup::gzip_available() ) {
			$options['gzip'] = false;
		}

		// Best-effort auto-detection of the MySQL client binaries.
		if ( empty( $options['mysqldump_path'] ) ) {
			$options['mysqldump_path'] = Simple_DB_Backup_Backup::detect_binary( 'mysqldump' );
		}
		if ( empty( $options['mysql_path'] ) ) {
			$options['mysql_path'] = Simple_DB_Backup_Backup::detect_binary( 'mysql' );
		}

		update_option( self::OPTION_NAME, $options );
	}

	/**
	 * Register hooks.
	 */
	public function run() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the setting, sections and fields.
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);

		add_settings_section(
			'simple_db_backup_binaries',
			__( 'MySQL binaries', 'simple-db-backup' ),
			array( $this, 'render_binaries_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'mysqldump_path',
			__( 'Path to mysqldump', 'simple-db-backup' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'simple_db_backup_binaries',
			array( 'key' => 'mysqldump_path', 'placeholder' => '/usr/bin/mysqldump' )
		);

		add_settings_field(
			'mysql_path',
			__( 'Path to mysql', 'simple-db-backup' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'simple_db_backup_binaries',
			array( 'key' => 'mysql_path', 'placeholder' => '/usr/bin/mysql' )
		);

		add_settings_section(
			'simple_db_backup_backups',
			__( 'Backups', 'simple-db-backup' ),
			array( $this, 'render_backups_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'gzip',
			__( 'Compress backups (gzip)', 'simple-db-backup' ),
			array( $this, 'render_gzip_field' ),
			self::PAGE_SLUG,
			'simple_db_backup_backups'
		);

		add_settings_field(
			'max_backups',
			__( 'Backups to keep', 'simple-db-backup' ),
			array( $this, 'render_max_backups_field' ),
			self::PAGE_SLUG,
			'simple_db_backup_backups'
		);

		add_settings_section(
			'simple_db_backup_schedules',
			__( 'Automatic schedules', 'simple-db-backup' ),
			array( $this, 'render_schedules_section' ),
			self::PAGE_SLUG
		);

		foreach ( array(
			'backup_frequency'   => __( 'Backup', 'simple-db-backup' ),
			'optimize_frequency' => __( 'Optimise', 'simple-db-backup' ),
			'repair_frequency'   => __( 'Repair', 'simple-db-backup' ),
		) as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_frequency_field' ),
				self::PAGE_SLUG,
				'simple_db_backup_schedules',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * Sanitize and validate submitted options.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$current  = self::get_options();
		$defaults = self::defaults();
		$clean    = $current;

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		// Binary paths: store sanitized text; validity is enforced at run time.
		foreach ( array( 'mysqldump_path', 'mysql_path' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		$clean['gzip'] = ! empty( $input['gzip'] );

		if ( isset( $input['max_backups'] ) ) {
			$clean['max_backups'] = max( 1, min( 500, absint( $input['max_backups'] ) ) );
		}

		$frequencies = self::frequencies();
		foreach ( array( 'backup_frequency', 'optimize_frequency', 'repair_frequency' ) as $key ) {
			if ( isset( $input[ $key ] ) && isset( $frequencies[ $input[ $key ] ] ) ) {
				$clean[ $key ] = $input[ $key ];
			}
		}

		// Never allow the directory name to be altered through the form.
		$clean['backup_dirname'] = $current['backup_dirname'];
		if ( empty( $clean['backup_dirname'] ) ) {
			$clean['backup_dirname'] = $defaults['backup_dirname'];
		}

		// Re-apply schedules to match the new frequencies.
		add_action( 'shutdown', array( 'Simple_DB_Backup_Cron', 'reschedule_all' ) );

		return $clean;
	}

	/* ------------------------------------------------------------------ *
	 * Field + section renderers
	 * ------------------------------------------------------------------ */

	public function render_binaries_section() {
		echo '<p>' . esc_html__( 'Absolute paths to the mysqldump and mysql executables. They are validated before use; backups and restores require them.', 'simple-db-backup' ) . '</p>';
	}

	public function render_backups_section() {
		$dir = Simple_DB_Backup_Filesystem::get_backup_dir();
		echo '<p>' . sprintf(
			/* translators: %s: absolute backup directory path. */
			esc_html__( 'Backups are stored in %s.', 'simple-db-backup' ),
			'<code>' . esc_html( $dir ) . '</code>'
		) . '</p>';
	}

	public function render_schedules_section() {
		echo '<p>' . esc_html__( 'Run these tasks automatically via WP-Cron. Optimize and repair run independently of backups, across all tables. Set to “Never” to disable.', 'simple-db-backup' ) . '</p>';
	}

	/**
	 * @param array $args Field args with 'key' and optional 'placeholder'.
	 */
	public function render_text_field( $args ) {
		$key   = $args['key'];
		$value = (string) self::get( $key, '' );
		printf(
			'<input type="text" class="regular-text code" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' )
		);
	}

	public function render_gzip_field() {
		$checked   = (bool) self::get( 'gzip', false );
		$available = Simple_DB_Backup_Backup::gzip_available();
		printf(
			'<label><input type="checkbox" name="%1$s[gzip]" value="1" %2$s %3$s /> %4$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			disabled( $available, false, false ),
			esc_html__( 'Store backups as .sql.gz', 'simple-db-backup' )
		);
		if ( ! $available ) {
			echo '<p class="description">' . esc_html__( 'Gzip compression is not available on this server.', 'simple-db-backup' ) . '</p>';
		}
	}

	public function render_max_backups_field() {
		printf(
			'<input type="number" min="1" max="500" name="%1$s[max_backups]" value="%2$s" class="small-text" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( (string) self::get( 'max_backups', 10 ) )
		);
		echo '<p class="description">' . esc_html__( 'Older backups beyond this count are deleted automatically.', 'simple-db-backup' ) . '</p>';
	}

	/**
	 * @param array $args Field args with 'key'.
	 */
	public function render_frequency_field( $args ) {
		$key      = $args['key'];
		$current  = (string) self::get( $key, 'never' );
		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']">';
		foreach ( self::frequencies() as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render the Settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( Simple_DB_Backup_Plugin::capability() ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
