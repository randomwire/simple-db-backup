<?php
/**
 * Backups admin screen: create, list, download, restore and delete.
 *
 * @package SimpleDBBackup
 *
 * @var array $backups    List of backups from Simple_DB_Backup_Filesystem::list_backups().
 * @var bool  $can_backup Whether backups can be created on this server.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action_url = admin_url( 'admin-post.php' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Database Backups', 'simple-db-backup' ); ?></h1>

	<?php Simple_DB_Backup_Plugin::render_notices(); ?>

	<h2><?php esc_html_e( 'Create a new backup', 'simple-db-backup' ); ?></h2>
	<?php if ( $can_backup ) : ?>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="action" value="simple_db_backup_create" />
			<?php wp_nonce_field( 'simple_db_backup_create' ); ?>
			<?php submit_button( __( 'Back up now', 'simple-db-backup' ), 'primary', 'submit', false ); ?>
		</form>
	<?php else : ?>
		<p class="description">
			<?php esc_html_e( 'Backups are unavailable: set a valid mysqldump path on the Settings screen and ensure PHP can run external processes.', 'simple-db-backup' ); ?>
		</p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Existing backups', 'simple-db-backup' ); ?></h2>
	<?php if ( empty( $backups ) ) : ?>
		<p><?php esc_html_e( 'No backups yet.', 'simple-db-backup' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'File', 'simple-db-backup' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Size', 'simple-db-backup' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'simple-db-backup' ); ?></th>
					<th scope="col"><?php esc_html_e( 'MD5 Checksum', 'simple-db-backup' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'simple-db-backup' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $backups as $backup ) : ?>
					<?php
					$download_url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'simple_db_backup_download',
								'backup' => $backup['name'],
							),
							$action_url
						),
						'simple_db_backup_download'
					);
					?>
					<tr>
						<td><code><?php echo esc_html( $backup['name'] ); ?></code></td>
						<td><?php echo esc_html( size_format( $backup['size'] ) ); ?></td>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $backup['mtime'] ) ); ?></td>
						<td><code><?php echo esc_html( Simple_DB_Backup_Filesystem::checksum( $backup['path'] ) ); ?></code></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( $download_url ); ?>">
								<?php esc_html_e( 'Download', 'simple-db-backup' ); ?>
							</a>

							<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="display:inline"
								onsubmit="return confirm('<?php echo esc_js( __( 'Restore this backup? This overwrites current data with the backup contents.', 'simple-db-backup' ) ); ?>');">
								<input type="hidden" name="action" value="simple_db_backup_restore" />
								<input type="hidden" name="backup" value="<?php echo esc_attr( $backup['name'] ); ?>" />
								<?php wp_nonce_field( 'simple_db_backup_restore' ); ?>
								<button type="submit" class="button button-small"><?php esc_html_e( 'Restore', 'simple-db-backup' ); ?></button>
							</form>

							<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="display:inline"
								onsubmit="return confirm('<?php echo esc_js( __( 'Delete this backup permanently?', 'simple-db-backup' ) ); ?>');">
								<input type="hidden" name="action" value="simple_db_backup_delete" />
								<input type="hidden" name="backup" value="<?php echo esc_attr( $backup['name'] ); ?>" />
								<?php wp_nonce_field( 'simple_db_backup_delete' ); ?>
								<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'simple-db-backup' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
