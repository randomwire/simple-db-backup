<?php
/**
 * Maintenance admin screen: optimize / repair selected tables.
 *
 * @package SimpleDBBackup
 *
 * @var string[] $tables All table names in the current database.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Database Maintenance', 'simple-db-backup' ); ?></h1>

	<?php Simple_DB_Backup_Plugin::render_notices(); ?>

	<p><?php esc_html_e( 'Select tables, then optimize to reclaim space or repair to fix corruption. Both operations are non-destructive.', 'simple-db-backup' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="simple_db_backup_maintenance" />
		<?php wp_nonce_field( 'simple_db_backup_maintenance' ); ?>

		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<td class="check-column">
						<input type="checkbox" onclick="var c=this.checked;document.querySelectorAll('input[name=\'tables[]\']').forEach(function(b){b.checked=c;});" />
					</td>
					<th scope="col"><?php esc_html_e( 'Table', 'simple-db-backup' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tables as $table ) : ?>
					<tr>
						<th scope="row" class="check-column">
							<input type="checkbox" name="tables[]" value="<?php echo esc_attr( $table ); ?>" checked="checked" />
						</th>
						<td><code><?php echo esc_html( $table ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" name="operation" value="optimize" class="button button-primary"><?php esc_html_e( 'Optimize selected', 'simple-db-backup' ); ?></button>
			<button type="submit" name="operation" value="repair" class="button"><?php esc_html_e( 'Repair selected', 'simple-db-backup' ); ?></button>
		</p>
	</form>
</div>
