# Simple DB Backup

Safe, modern database backups for WordPress — create, schedule, download, restore, optimize and
repair, from a clean WordPress admin screen.

Simple DB Backup is a hardened, trimmed fork of the classic
[WP-DBManager](https://github.com/lesterchan/wp-dbmanager). It keeps the genuinely useful,
non-destructive tooling and removes everything that made the original risky.

## Features

- Create database backups with `mysqldump`, optionally gzip-compressed
- Scheduled automatic backups, optimize and repair via WP-Cron
- Download, restore and delete backups from the admin
- Optimize and repair tables (non-destructive)
- Read-only database statistics

## Security

- Credentials passed via a temporary `--defaults-extra-file` (chmod 0600), never on the command
  line — no password leak in the process list.
- Processes launched without a shell (no command injection).
- Backup filenames confined to the managed directory (`basename()` + `realpath()` + extension
  allowlist) — no path-traversal download/delete.
- Backups stored in an unguessable `wp-content/uploads/` subdirectory with `.htaccess` / `web.config`
  / index guards and restrictive permissions.
- Multisite: restricted to network administrators.

**Removed from the original:** empty/drop tables, arbitrary SQL execution, emailing backups, and
global admin notices.

## Requirements

- WordPress 6.5+
- PHP 8.1+
- `mysqldump` / `mysql` binaries and PHP `proc_open` (for backup/restore)

## Installation

Install the zip from a [GitHub Release](https://github.com/randomwire/simple-db-backup/releases)
via **Plugins → Add New → Upload Plugin**, activate, then set the binary paths under
**DB Backup → Settings**.

Updates are delivered automatically from GitHub Releases.

## Credits

Based on [WP-DBManager](https://github.com/lesterchan/wp-dbmanager) by Lester 'GaMerZ' Chan,
licensed GPL-2.0-or-later.

## License

[GPL-2.0-or-later](LICENSE)
