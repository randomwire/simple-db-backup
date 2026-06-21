# Changelog

## 1.0.0 - 2026-06-21

- Initial release.
- Originates as a hardened, trimmed fork of WP-DBManager by Lester 'GaMerZ' Chan
  (GPL-2.0-or-later).
- Features: manual and scheduled backups (`mysqldump`, optional gzip), restore, download/delete,
  optimize and repair, and a read-only database info screen.
- Security: credentials via temporary 0600 `--defaults-extra-file` (no password on argv); shell-less
  process launch; path-traversal-safe file handling; protected backup directory under uploads;
  network-admin-only on multisite.
- Removed from the original: empty/drop tables, arbitrary SQL execution, backup-by-email, and global
  admin notices.
