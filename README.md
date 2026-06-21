# Simple DB Backup

Safe, modern database backups for WordPress — create, schedule, download, restore, optimize and
repair, from a clean WordPress admin screen.

Simple DB Backup is a modern, security-hardened database tool inspired by the classic
[WP-DBManager](https://github.com/lesterchan/wp-dbmanager). It is an independent reimplementation
that keeps the genuinely useful, non-destructive tooling and leaves out everything that made the
original risky.

## Features

- Create database backups with `mysqldump`, optionally gzip-compressed
- Independent WP-Cron schedules for automatic backup, optimize and repair
- Download, restore and delete backups from the admin
- Read-only database statistics

## Security

- Credentials passed via a temporary `--defaults-extra-file` (chmod 0600), never on the command
  line — no password leak in the process list.
- Processes launched without a shell (no command injection).
- Backup filenames confined to the managed directory (`basename()` + `realpath()` + extension
  allowlist) — no path-traversal download/delete.
- Backups stored in an unguessable `wp-content/uploads/` subdirectory with `.htaccess` / `web.config`
  / index guards and restrictive permissions. Optionally relocatable to any absolute path (validated,
  hardened, with a warning if it sits inside the web root).
- Multisite: restricted to network administrators.

**Deliberately omitted (features WP-DBManager had):** empty/drop tables, arbitrary SQL execution, emailing backups, and
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

Inspired by [WP-DBManager](https://github.com/lesterchan/wp-dbmanager) by Lester 'GaMerZ' Chan.
Simple DB Backup is an independent reimplementation and shares no code with WP-DBManager.

## License

[GPL-2.0-or-later](LICENSE)
