# Changelog

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
