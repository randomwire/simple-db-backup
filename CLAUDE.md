# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Simple DB Backup is a hardened, trimmed fork of WP-DBManager. It performs database backups (manual
and scheduled via WP-Cron), restore, download/delete of backups, non-destructive optimize/repair,
and shows read-only database info. Destructive features from the original (empty/drop tables,
arbitrary SQL, backup email, global admin notices) are intentionally absent.

GitHub-only plugin: it uses the workspace's standard GitHub-Releases auto-updater
(`includes/updater.php` + vendored Plugin Update Checker), `build.sh`, and the release workflow.

## Testing

Requires a real WordPress environment (PHP 8.1+/WP 6.5+) with `mysqldump`/`mysql` available:

1. Activate; confirm a protected backup dir is created under `wp-content/uploads/`.
2. **DB Backup → Settings**: verify/auto-detect binary paths; set the backup/optimize/repair schedules and retention.
3. **Backups**: create a backup; download it; restore it; delete it.
4. Confirm optimize/repair schedules register via `wp_get_scheduled_event()` and run over all tables.
5. Confirm no global admin notices appear; feedback shows only on the plugin's screens.

## Architecture

Bootstrapped from `simple-db-backup.php`; all logic lives in `includes/` classes, with admin
markup in `admin/views/`.

- `Simple_DB_Backup_Plugin` — loader: menus, `admin_post_*` handlers, contextual notices, the
  `capability()` gate (`manage_network_options` on multisite, else `manage_options`).
- `Simple_DB_Backup_Settings` — single serialized option `simple_db_backup_options`, Settings API
  page, defaults, and the random backup directory name.
- `Simple_DB_Backup_Filesystem` — backup dir creation/hardening and `resolve_backup_path()`, which
  confines every filename to the managed dir.
- `Simple_DB_Backup_Backup` — `mysqldump` engine; credentials via temp 0600 `--defaults-extra-file`;
  shell-less `proc_open` array form; gzip streaming; retention pruning; binary validation.
- `Simple_DB_Backup_Restore` — imports a managed backup via `mysql` (gunzip on the fly).
- `Simple_DB_Backup_Manage` — download (streamed) and delete.
- `Simple_DB_Backup_Maintenance` — OPTIMIZE/REPAIR over tables validated against the live list;
  invoked from the cron handlers (no standalone admin page).
- `Simple_DB_Backup_Cron` — independent backup, optimize and repair schedules + handlers; adds a
  monthly recurrence. Optimize/repair run over all tables, decoupled from backups.

## Key implementation details

- **Never** put the DB password on argv; always use `write_defaults_file()`.
- **Never** add an `admin_notices` hook; use `Simple_DB_Backup_Plugin::render_notices()` inside views.
- Every state-changing action: `check_admin_referer()` + capability re-check in the handler.
- Any filename from a request must pass through `Simple_DB_Backup_Filesystem::resolve_backup_path()`
  before use.
- Keep the version in sync across the `Version:` header, `SIMPLE_DB_BACKUP_VERSION`, `readme.txt`,
  and `CHANGELOG.md`.
