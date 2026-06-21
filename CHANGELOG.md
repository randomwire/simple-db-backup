# Changelog

## 1.0.1 - 2026-06-21

- Connections: parse IPv6 `DB_HOST` values correctly (`::1`, `[::1]:3306`, bracketed with port).
- Maintenance: `get_tables()` returns base tables only (views excluded); optimize/repair inspect
  the result and report real per-table errors instead of always claiming success.
- Internal: backup-directory migration runs via an `update_option` hook (once per real change)
  rather than as a side effect in `sanitize()`.
- Docs: corrected attribution — Simple DB Backup is an independent reimplementation inspired by
  WP-DBManager and shares no code with it.

## 1.0.0 - 2026-06-21

- Initial release.
- Independent, security-hardened reimplementation inspired by WP-DBManager by Lester 'GaMerZ'
  Chan; shares no code with the original.
- Features: manual and scheduled backups (`mysqldump`, optional gzip), independent optimize and
  repair schedules, restore, download/delete, and a read-only database info screen.
- Security: credentials via temporary 0600 `--defaults-extra-file` (no password on argv); shell-less
  process launch; path-traversal-safe file handling; protected backup directory under uploads;
  network-admin-only on multisite.
- Deliberately omitted (features WP-DBManager had): empty/drop tables, arbitrary SQL execution,
  backup-by-email, and global admin notices.
