=== Simple DB Backup ===
Contributors: randomwire
Donate link: https://ko-fi.com/randomwire
Tags: database, backup, restore, optimize, repair
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe, modern database backups for WordPress — create, schedule, download, restore, optimize and repair.

== Description ==

Simple DB Backup is a modern, security-hardened database tool inspired by the classic WP-DBManager.
It is an independent reimplementation that keeps the genuinely useful, non-destructive tooling and
leaves out everything that made the original risky.

**What it does:**

* Create database backups with `mysqldump` (optionally gzip-compressed)
* Schedule automatic backups, optimize and repair via WP-Cron (each on its own schedule)
* Download, restore and delete backups from the admin
* View read-only database statistics

**Security by design:**

* Database credentials are passed to `mysqldump`/`mysql` through a temporary, 0600
  `--defaults-extra-file` — never on the command line, so the password is not exposed in the
  process list.
* External processes are launched without a shell, closing command-injection vectors.
* Every backup filename is confined to the managed directory with `basename()` + `realpath()`
  checks and an extension allowlist, preventing path-traversal file download/delete.
* Backups are stored in an unguessable directory under `wp-content/uploads/` protected by
  `.htaccess`, `web.config` and an index file, with restrictive file permissions.
* On multisite, only network administrators can use the plugin.

**Deliberately omitted (features WP-DBManager had):** emptying/dropping tables, running arbitrary SQL,
emailing backups, and global admin notices.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/simple-db-backup/`, or install the zip via
   Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to **DB Backup > Settings** and confirm the `mysqldump` and `mysql` paths (auto-detected
   where possible).
4. Use **DB Backup > Backups** to create your first backup.

== Frequently Asked Questions ==

= Does it need mysqldump? =

Yes. Backups and restores shell out to `mysqldump`/`mysql`, so PHP must be able to run external
processes (`proc_open`) and the binaries must be present. The plugin tells you on-screen if either
is unavailable.

= Where are backups stored? =

By default, in an unguessable folder under `wp-content/uploads/`, protected from direct web access.
You can set a custom absolute path on the Settings screen — ideally outside the web root. Changing
the path moves your existing backups to the new location.

= Can it drop tables or run custom SQL? =

No. Those capabilities were intentionally removed. The plugin only performs safe, non-destructive
operations plus backup/restore.

== Changelog ==

= 1.0.1 =
* Connections: support IPv6 database hosts (e.g. ::1, [::1]:3306).
* Maintenance: optimize/repair only base tables (skip views) and report real errors instead of
  always reporting success.
* Internal: backup-directory migration now runs once per change via an update_option hook.
* Docs: clarified that this is an independent reimplementation inspired by WP-DBManager (shares
  no code with it).

= 1.0.0 =
* Initial release.
* Independent, security-hardened reimplementation inspired by WP-DBManager: backup, scheduled
  backup/optimize/repair, restore, download/delete, and a read-only database info screen.

== Upgrade Notice ==

= 1.0.1 =
Adds IPv6 database host support and more reliable optimize/repair. Recommended for all users.

= 1.0.0 =
First release.

== Credits ==

Inspired by [WP-DBManager](https://github.com/lesterchan/wp-dbmanager) by Lester 'GaMerZ' Chan.
Simple DB Backup is an independent reimplementation — it shares no code with WP-DBManager — that
keeps the useful, non-destructive features, leaves out the risky ones, and is hardened against the
kinds of vulnerabilities that affected the original.
