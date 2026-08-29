<?php
/**
 * Dashboard Activity Tab
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Hikmah_Login\Helpers\Helper;

$logs = isset( $logs ) ? $logs : [ 'items' => [], 'total' => 0, 'pages' => 1 ];
$page = isset( $page ) ? (int) $page : 1;
$dashboard_url = add_query_arg( 'tab', 'activity', Helper::get_dashboard_url() );
?>
<div class="hikmah-dash-section">
	<h3><?php esc_html_e( 'Login History', 'hikmah-login' ); ?></h3>
	<p class="hikmah-dash-desc">
		<?php
		printf(
			/* translators: %d: Total records */
			esc_html__( 'Showing your recent login activity (%d total records).', 'hikmah-login' ),
			(int) $logs['total']
		);
		?>
	</p>

	<?php if ( empty( $logs['items'] ) ) : ?>
		<p style="color:var(--hikmah-text-muted,#6b7280);text-align:center;padding:24px;">
			<?php esc_html_e( 'No login history found.', 'hikmah-login' ); ?>
		</p>
	<?php else : ?>
		<table class="hikmah-dash-table" style="width:100%;border-collapse:collapse;font-size:13px;">
			<thead>
				<tr style="border-bottom:2px solid var(--hikmah-border,#e5e7eb);">
					<th style="text-align:left;padding:8px;"><?php esc_html_e( 'Date', 'hikmah-login' ); ?></th>
					<th style="text-align:left;padding:8px;"><?php esc_html_e( 'Status', 'hikmah-login' ); ?></th>
					<th style="text-align:left;padding:8px;"><?php esc_html_e( 'IP Address', 'hikmah-login' ); ?></th>
					<th style="text-align:left;padding:8px;"><?php esc_html_e( 'Device', 'hikmah-login' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs['items'] as $log ) : ?>
					<tr style="border-bottom:1px solid var(--hikmah-border,#e5e7eb);">
						<td style="padding:8px;"><?php echo esc_html( Helper::format_datetime( $log->login_at ) ); ?></td>
						<td style="padding:8px;">
							<?php
							$status_tags = [
								'success' => 'hikmah-tag-success',
								'failed'  => 'hikmah-tag-failed',
								'blocked' => 'hikmah-tag-warning',
								'locked'  => 'hikmah-tag-info',
							];
							?>
							<span class="hikmah-tag <?php echo esc_attr( $status_tags[ $log->status ] ?? 'hikmah-tag-info' ); ?>">
								<?php echo esc_html( ucfirst( $log->status ) ); ?>
							</span>
						</td>
						<td style="padding:8px;"><code style="font-size:12px;"><?php echo esc_html( $log->ip_address ); ?></code></td>
						<td style="padding:8px;font-size:12px;color:var(--hikmah-text-muted,#6b7280);">
							<?php echo esc_html( substr( (string) $log->user_agent, 0, 40 ) ); ?>...
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $logs['pages'] > 1 ) : ?>
			<div class="hikmah-pagination" style="margin-top:16px;text-align:center;">
				<?php for ( $i = 1; $i <= (int) $logs['pages']; $i++ ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'hpage', $i, $dashboard_url ) ); ?>"
						class="hikmah-btn" style="padding:4px 10px;font-size:12px;<?php echo $i === $page ? 'background:var(--hikmah-primary,#2563eb);color:#fff;' : ''; ?>">
						<?php echo $i; ?>
					</a>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>