<?php
/**
 * Backup engine: create dumps via mysqldump using a temporary defaults-file.
 *
 * @package SimpleDBBackup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates database backups by streaming mysqldump output to a protected file.
 * Credentials are passed through a 0600 --defaults-extra-file, never on argv,
 * and the process is launched with proc_open's array form so no shell is used.
 */
class Simple_DB_Backup_Backup {

	/**
	 * Common locations to probe when auto-detecting a MySQL client binary.
	 *
	 * @var string[]
	 */
	private static $search_paths = array(
		'/usr/bin',
		'/usr/local/bin',
		'/usr/local/mysql/bin',
		'/opt/homebrew/bin',
		'/opt/local/bin',
		'/bin',
	);

	/**
	 * Whether process execution is available (proc_open enabled).
	 *
	 * @return bool
	 */
	public static function proc_available() {
		if ( ! function_exists( 'proc_open' ) ) {
			return false;
		}
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		return ! in_array( 'proc_open', $disabled, true );
	}

	/**
	 * Whether PHP gzip streaming is available.
	 *
	 * @return bool
	 */
	public static function gzip_available() {
		return function_exists( 'gzopen' );
	}

	/**
	 * Validate a configured binary path: must be an existing, regular,
	 * executable file. Closes the command-injection vector (CVE-2014-8334).
	 *
	 * @param string $path Configured path.
	 * @return string|false Canonical path or false.
	 */
	public static function validate_binary( $path ) {
		$path = (string) $path;
		if ( '' === $path ) {
			return false;
		}
		$real = realpath( $path );
		if ( false === $real || ! is_file( $real ) || ! is_executable( $real ) ) {
			return false;
		}
		return $real;
	}

	/**
	 * Best-effort discovery of a binary by name in common locations and on
	 * the system PATH. No shell is used.
	 *
	 * @param string $binary Either 'mysqldump' or 'mysql'.
	 * @return string Detected absolute path or empty string.
	 */
	public static function detect_binary( $binary ) {
		$binary = basename( $binary );

		// Curated common locations first, then each directory on the PATH.
		$dirs = self::$search_paths;
		$path = getenv( 'PATH' );
		if ( is_string( $path ) && '' !== $path ) {
			$dirs = array_merge( $dirs, explode( PATH_SEPARATOR, $path ) );
		}

		foreach ( array_unique( $dirs ) as $dir ) {
			$dir = rtrim( (string) $dir, '/\\' );
			if ( '' === $dir ) {
				continue;
			}
			$candidate = $dir . '/' . $binary;
			if ( is_file( $candidate ) && is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Create a backup.
	 *
	 * @return array{success:bool,message:string,file:string}
	 */
	public static function create() {
		if ( ! self::proc_available() ) {
			return self::error( __( 'Backups require the PHP proc_open function, which is disabled on this server.', 'simple-db-backup' ) );
		}

		$mysqldump = self::validate_binary( Simple_DB_Backup_Settings::get( 'mysqldump_path', '' ) );
		if ( false === $mysqldump ) {
			return self::error( __( 'The mysqldump path is not set or is not a valid executable. Set it on the Settings screen.', 'simple-db-backup' ) );
		}

		if ( ! Simple_DB_Backup_Filesystem::ensure_protected_dir() ) {
			return self::error( __( 'The backup directory could not be created or is not writable.', 'simple-db-backup' ) );
		}

		$gzip = (bool) Simple_DB_Backup_Settings::get( 'gzip', false ) && self::gzip_available();
		$dir  = Simple_DB_Backup_Filesystem::get_backup_dir();
		$name = sprintf(
			'%s_%s_%s.sql%s',
			gmdate( 'Ymd-His' ),
			self::sanitize_db_name_for_filename( DB_NAME ),
			wp_generate_password( 6, false, false ),
			$gzip ? '.gz' : ''
		);
		$target = $dir . '/' . $name;

		$cnf = self::write_defaults_file( $dir );
		if ( false === $cnf ) {
			return self::error( __( 'Could not create the temporary credentials file.', 'simple-db-backup' ) );
		}

		$write_error = false;

		try {
			$command = array(
				$mysqldump,
				'--defaults-extra-file=' . $cnf,
				'--default-character-set=utf8mb4',
				'--single-transaction',
				'--quick',
				'--no-tablespaces',
				'--skip-lock-tables',
				'--skip-comments',
				'--add-drop-table',
				DB_NAME,
			);

			$out = $gzip ? gzopen( $target, 'wb6' ) : fopen( $target, 'wb' );
			if ( false === $out ) {
				$result = array(
					'success' => false,
					'message' => __( 'Could not open the backup file for writing.', 'simple-db-backup' ),
				);
			} else {
				// Track short/failed writes so a full disk can't pass as success.
				$writer = static function ( $chunk ) use ( $out, $gzip, &$write_error ) {
					$written = $gzip ? gzwrite( $out, $chunk ) : fwrite( $out, $chunk );
					if ( false === $written || $written < strlen( $chunk ) ) {
						$write_error = true;
					}
				};

				$result = self::run_process( $command, null, $writer );

				if ( $gzip ) {
					gzclose( $out );
				} else {
					fclose( $out );
				}
			}
		} finally {
			self::remove_file( $cnf );
		}

		if ( $result['success'] && $write_error ) {
			$result = array(
				'success' => false,
				'message' => __( 'Writing the backup file failed (the disk may be full).', 'simple-db-backup' ),
			);
		}

		if ( ! $result['success'] ) {
			self::remove_file( $target );
			return self::error(
				sprintf(
					/* translators: %s: error detail from mysqldump. */
					__( 'Backup failed: %s', 'simple-db-backup' ),
					$result['message']
				)
			);
		}

		@chmod( $target, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		Simple_DB_Backup_Filesystem::enforce_retention( (int) Simple_DB_Backup_Settings::get( 'max_backups', 10 ) );

		return array(
			'success' => true,
			'message' => __( 'Backup created successfully.', 'simple-db-backup' ),
			'file'    => $name,
		);
	}

	/**
	 * Write a temporary [client] defaults-file (0600) for the MySQL binaries.
	 *
	 * @param string $dir Directory to create the file in.
	 * @return string|false Absolute path to the credentials file, or false.
	 */
	public static function write_defaults_file( $dir ) {
		$conn = self::parse_db_host( DB_HOST );

		// WordPress/MySQL treat the literal host "localhost" as a socket
		// connection. The mysql client would otherwise default to
		// /tmp/mysql.sock, which is usually wrong (Local, MAMP, etc.), so ask
		// the live server for the socket it is actually listening on.
		if ( '' === $conn['socket'] && '' === $conn['port'] && self::is_localhost( $conn['host'] ) ) {
			$socket = self::server_socket();
			if ( '' !== $socket ) {
				$conn['socket'] = $socket;
			}
		}

		$lines   = array( '[client]' );
		$lines[] = 'user="' . self::escape_cnf( DB_USER ) . '"';
		$lines[] = 'password="' . self::escape_cnf( DB_PASSWORD ) . '"';
		if ( '' !== $conn['host'] ) {
			$lines[] = 'host="' . self::escape_cnf( $conn['host'] ) . '"';
		}
		if ( '' !== $conn['port'] ) {
			$lines[] = 'port=' . (int) $conn['port'];
		}
		if ( '' !== $conn['socket'] ) {
			$lines[] = 'socket="' . self::escape_cnf( $conn['socket'] ) . '"';
		}

		$path = $dir . '/.sdb-' . wp_generate_password( 16, false, false ) . '.cnf';

		// Create with restrictive permissions before writing any secrets.
		$handle = fopen( $path, 'wb' );
		if ( false === $handle ) {
			return false;
		}
		@chmod( $path, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		fwrite( $handle, implode( "\n", $lines ) . "\n" );
		fclose( $handle );

		return $path;
	}

	/**
	 * Run a process without a shell, concurrently feeding stdin and draining
	 * stdout/stderr so neither side can deadlock on a full pipe buffer.
	 *
	 * @param string[]      $command   Argv array (no shell involved).
	 * @param callable|null $reader    Returns the next stdin chunk, or '' at EOF.
	 *                                 Null closes stdin immediately (no input).
	 * @param callable|null $writer    Receives each stdout chunk. Null discards it.
	 * @return array{success:bool,message:string}
	 */
	public static function run_process( array $command, $reader = null, $writer = null ) {
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( $command, $descriptors, $pipes );
		if ( ! is_resource( $process ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not start the process.', 'simple-db-backup' ),
			);
		}

		foreach ( $pipes as $pipe ) {
			stream_set_blocking( $pipe, false );
		}

		$feed    = is_callable( $reader );
		$pending = '';
		if ( ! $feed ) {
			fclose( $pipes[0] );
		}

		$stderr = '';

		while ( true ) {
			$read = array();
			if ( is_resource( $pipes[1] ) ) {
				$read[] = $pipes[1];
			}
			if ( is_resource( $pipes[2] ) ) {
				$read[] = $pipes[2];
			}

			$write = array();
			if ( is_resource( $pipes[0] ) && ( $feed || '' !== $pending ) ) {
				$write[] = $pipes[0];
			}

			if ( empty( $read ) && empty( $write ) ) {
				break;
			}

			$r = $read;
			$w = $write;
			$e = null;
			if ( false === stream_select( $r, $w, $e, null ) ) {
				break;
			}

			// Feed stdin.
			if ( ! empty( $w ) ) {
				if ( '' === $pending && $feed ) {
					$chunk = call_user_func( $reader );
					if ( false === $chunk || '' === $chunk ) {
						$feed = false;
					} else {
						$pending = $chunk;
					}
				}
				if ( '' !== $pending ) {
					$written = fwrite( $pipes[0], $pending );
					if ( false === $written ) {
						$feed    = false;
						$pending = '';
					} else {
						$pending = substr( $pending, $written );
					}
				}
				if ( ! $feed && '' === $pending ) {
					fclose( $pipes[0] );
				}
			}

			// Drain stdout/stderr.
			foreach ( $r as $stream ) {
				if ( $stream === $pipes[1] ) {
					$chunk = fread( $pipes[1], 65536 );
					if ( '' !== $chunk && false !== $chunk && null !== $writer ) {
						call_user_func( $writer, $chunk );
					}
					if ( feof( $pipes[1] ) ) {
						fclose( $pipes[1] );
					}
				} elseif ( $stream === $pipes[2] ) {
					$chunk = fread( $pipes[2], 65536 );
					if ( '' !== $chunk && false !== $chunk ) {
						$stderr .= $chunk;
					}
					if ( feof( $pipes[2] ) ) {
						fclose( $pipes[2] );
					}
				}
			}
		}

		foreach ( $pipes as $pipe ) {
			if ( is_resource( $pipe ) ) {
				fclose( $pipe );
			}
		}

		$exit_code = proc_close( $process );

		if ( 0 !== $exit_code ) {
			$detail = trim( (string) $stderr );
			if ( '' === $detail ) {
				/* translators: %d: process exit code. */
				$detail = sprintf( __( 'process exited with code %d', 'simple-db-backup' ), $exit_code );
			}
			return array(
				'success' => false,
				'message' => self::redact( $detail ),
			);
		}

		return array( 'success' => true, 'message' => '' );
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Parse a WordPress DB_HOST into host/port/socket parts.
	 *
	 * @param string $db_host Raw DB_HOST value.
	 * @return array{host:string,port:string,socket:string}
	 */
	private static function parse_db_host( $db_host ) {
		$host   = (string) $db_host;
		$port   = '';
		$socket = '';

		// Peel a socket path (":/...") off the right first, if present.
		$socket_pos = strpos( $host, ':/' );
		if ( false !== $socket_pos ) {
			$socket = substr( $host, $socket_pos + 1 );
			$host   = substr( $host, 0, $socket_pos );
		}

		// An IPv6 address contains two or more colons (e.g. ::1, [::1]:3306);
		// IPv4/hostnames have at most one. Pick the matching pattern so we don't
		// mistake an IPv6 address for a socket or chop it at the first colon.
		if ( substr_count( $host, ':' ) > 1 ) {
			$pattern = '#^(?:\[)?(?P<host>[0-9a-fA-F:]+)(?:\]:(?P<port>\d+))?#';
		} else {
			$pattern = '#^(?P<host>[^:/]*)(?::(?P<port>\d+))?#';
		}

		$matches = array();
		if ( preg_match( $pattern, $host, $matches ) ) {
			$host = isset( $matches['host'] ) ? $matches['host'] : '';
			$port = isset( $matches['port'] ) ? $matches['port'] : '';
		}

		return array(
			'host'   => trim( $host ),
			'port'   => $port,
			'socket' => $socket,
		);
	}

	/**
	 * Whether a host string represents a local socket connection.
	 *
	 * @param string $host Host part of DB_HOST.
	 * @return bool
	 */
	private static function is_localhost( $host ) {
		return '' === $host || 0 === strcasecmp( $host, 'localhost' );
	}

	/**
	 * Ask the connected MySQL server for the socket it is listening on.
	 *
	 * @return string Socket path, or '' if unavailable.
	 */
	private static function server_socket() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return '';
		}
		$socket = $wpdb->get_var( 'SELECT @@socket' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_string( $socket ) ? $socket : '';
	}

	/**
	 * Escape a value for use inside a double-quoted my.cnf option.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function escape_cnf( $value ) {
		return str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $value );
	}

	/**
	 * Make a database name safe to embed in a backup filename.
	 *
	 * @param string $name Database name.
	 * @return string
	 */
	private static function sanitize_db_name_for_filename( $name ) {
		$name = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $name );
		return '' === $name ? 'database' : $name;
	}

	/**
	 * Strip anything that looks like the DB password out of an error string.
	 *
	 * @param string $message Error detail.
	 * @return string
	 */
	private static function redact( $message ) {
		if ( defined( 'DB_PASSWORD' ) && '' !== DB_PASSWORD ) {
			$message = str_replace( DB_PASSWORD, '******', $message );
		}
		return $message;
	}

	/**
	 * Delete a file if it exists.
	 *
	 * @param string $path Absolute path.
	 */
	public static function remove_file( $path ) {
		if ( $path && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Build a failure result.
	 *
	 * @param string $message Error message.
	 * @return array{success:bool,message:string,file:string}
	 */
	private static function error( $message ) {
		return array( 'success' => false, 'message' => $message, 'file' => '' );
	}
}
