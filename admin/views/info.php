<?php
/**
 * Database Info admin screen: read-only table statistics.
 *
 * @package SimpleDBBackup
 *
 * @var array  $rows           Rows of {name, rows_count, size} from information_schema.
 * @var string $server_version MySQL/MariaDB server version.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows        = is_array( $rows ) ? $rows : array();
$total_size  = 0;
$total_rows  = 0;
foreach ( $rows as $row ) {
	$total_size += (int) $row->size;
	$total_rows += (int) $row->rows_count;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Database Information', 'simple-db-backup' ); ?></h1>

	<?php Simple_DB_Backup_Plugin::render_notices(); ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Database name', 'simple-db-backup' ); ?></th>
			<td><code><?php echo esc_html( DB_NAME ); ?></code></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Server version', 'simple-db-backup' ); ?></th>
			<td><?php echo esc_html( $server_version ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Tables', 'simple-db-backup' ); ?></th>
			<td><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Total size', 'simple-db-backup' ); ?></th>
			<td><?php echo esc_html( size_format( $total_size ) ); ?></td>
		</tr>
	</table>

	<table class="wp-list-table widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Table', 'simple-db-backup' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Rows', 'simple-db-backup' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Size', 'simple-db-backup' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $row->name ); ?></code></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row->rows_count ) ); ?></td>
					<td><?php echo esc_html( size_format( (int) $row->size ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
