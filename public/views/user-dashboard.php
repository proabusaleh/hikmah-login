<?php
/**
 * User Dashboard Template
 *
 * Renders the full dashboard shell: header, sidebar navigation and the
 * active tab's content. Tab bodies live in public/views/dashboard/ and can
 * be overridden from {theme}/hikmah-login/dashboard/.
 *
 * Available template variables (set by Dashboard_Manager::render()):
 *   $user_id, $user, $dashboard (Dashboard_Manager instance),
 *   $active_tab, $tabs, $current_url, $logout_url
 *
 * Override: yourtheme/hikmah-login/user-dashboard.php
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() || ! isset( $user, $dashboard ) ) {
	echo '<div class="hikmah-login-wrapper"><div class="hikmah-notice hikmah-notice-info"><p>' .
		sprintf(
			wp_kses_post( __( 'Please <a href="%s">log in</a> to access your dashboard.', 'hikmah-login' ) ),
			esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() )
		) .
		'</p></div></div>';
	return;
}
?>
<style>
	#hikmah-dashboard{max-width:1100px;margin:0 auto;font-family:var(--hikmah-font)}
	#hikmah-dashboard *{box-sizing:border-box}
	#hikmah-dashboard .hikmah-dash-header{background:linear-gradient(135deg,var(--hikmah-primary,#2563eb),#1e40af);color:#fff;border-radius:var(--hikmah-card-radius,16px);padding:24px;display:flex;align-items:center;gap:16px;margin-bottom:24px}
	#hikmah-dashboard .hikmah-dash-header img{border-radius:50%;border:3px solid rgba(255,255,255,.6);width:64px;height:64px;object-fit:cover}
	#hikmah-dashboard .hikmah-dash-header h2{margin:0;font-size:20px;color:#fff}
	#hikmah-dashboard .hikmah-dash-header p{margin:2px 0 0;opacity:.9;font-size:13px}
	#hikmah-dashboard .hikmah-dash-header .hikmah-dash-actions{margin-left:auto;display:flex;gap:8px}
	#hikmah-dashboard .hikmah-dash-body{display:grid;grid-template-columns:260px 1fr;gap:24px;align-items:start}
	#hikmah-dashboard .hikmah-dash-nav{background:var(--hikmah-surface,#fff);border:1px solid var(--hikmah-border,#e5e7eb);border-radius:var(--hikmah-card-radius,16px);padding:12px;position:sticky;top:20px}
	#hikmah-dashboard .hikmah-dash-nav a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:var(--hikmah-radius-sm,8px);color:var(--hikmah-text,#111827);font-size:14px;text-decoration:none;transition:background .15s}
	#hikmah-dashboard .hikmah-dash-nav a:hover{background:var(--hikmah-primary-light,#eff6ff)}
	#hikmah-dashboard .hikmah-dash-nav a.active{background:var(--hikmah-primary,#2563eb);color:#fff;font-weight:600}
	#hikmah-dashboard .hikmah-dash-nav a .hikmah-nav-icon{width:20px;text-align:center;font-size:16px}
	#hikmah-dashboard .hikmah-dash-nav a small{display:block;font-size:11px;opacity:.7;font-weight:400}
	#hikmah-dashboard .hikmah-dash-content{background:var(--hikmah-surface,#fff);border:1px solid var(--hikmah-border,#e5e7eb);border-radius:var(--hikmah-card-radius,16px);padding:28px;min-height:420px}
	#hikmah-dashboard .hikmah-dash-section h3{margin:0 0 4px;color:var(--hikmah-text,#111827);font-size:18px}
	#hikmah-dashboard .hikmah-dash-desc{margin:0 0 20px;color:var(--hikmah-text-muted,#6b7280);font-size:14px}
	#hikmah-dashboard .hikmah-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px}
	#hikmah-dashboard .hikmah-stat-card{background:var(--hikmah-bg,#f9fafb);border:1px solid var(--hikmah-border,#e5e7eb);border-radius:var(--hikmah-radius,12px);padding:16px}
	#hikmah-dashboard .hikmah-stat-card strong{font-size:26px;display:block;color:var(--hikmah-text,#111827)}
	#hikmah-dashboard .hikmah-stat-card small{color:var(--hikmah-text-muted,#6b7280)}
	#hikmah-dashboard .hikmah-activity-list,.hikmah-rec-list{list-style:none;margin:0;padding:0}
	#hikmah-dashboard .hikmah-activity-list li,.hikmah-rec-list li{display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--hikmah-border,#e5e7eb);font-size:14px}
	#hikmah-dashboard .hikmah-activity-list li:last-child,.hikmah-rec-list li:last-child{border-bottom:0}
	#hikmah-dashboard .hikmah-tag{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600}
	#hikmah-dashboard .hikmah-tag-success{background:#dcfce7;color:#166534}
	#hikmah-dashboard .hikmah-tag-failed{background:#fee2e2;color:#991b1b}
	#hikmah-dashboard .hikmah-tag-warning{background:#fef3c7;color:#92400e}
	#hikmah-dashboard .hikmah-tag-info{background:#e0f2fe;color:#075985}
	@media(max-width:820px){#hikmah-dashboard .hikmah-dash-body{grid-template-columns:1fr}#hikmah-dashboard .hikmah-dash-nav{position:static;display:flex;overflow-x:auto}}
</style>

<div id="hikmah-dashboard" class="hikmah-dashboard-wrap">
	<!-- Header -->
	<header class="hikmah-dash-header">
		<img src="<?php echo esc_url( $dashboard->get_avatar_url( $user->ID ) ); ?>" alt="" width="64" height="64" />
		<div>
			<h2><?php echo esc_html( $user->display_name ); ?></h2>
			<p><?php echo esc_html( $user->user_email ); ?></p>
		</div>
		<div class="hikmah-dash-actions">
			<a href="<?php echo esc_url( $logout_url ); ?>" class="hikmah-btn"
				style="background:rgba(255,255,255,.15);color:#fff;border:none;">
				<?php esc_html_e( 'Log Out', 'hikmah-login' ); ?>
			</a>
		</div>
	</header>

	<div class="hikmah-dash-body">
		<!-- Sidebar navigation -->
		<nav class="hikmah-dash-nav" aria-label="<?php esc_attr_e( 'Dashboard sections', 'hikmah-login' ); ?>">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $current_url ) ); ?>"
					class="<?php echo $key === $active_tab ? 'active' : ''; ?>"
					data-dashboard-tab="<?php echo esc_attr( $key ); ?>">
					<span class="hikmah-nav-icon"><?php echo esc_html( $tab['icon'] ); ?></span>
					<span><?php echo esc_html( $tab['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<!-- Content -->
		<main class="hikmah-dash-content" id="hikmah-dash-content" aria-live="polite">
			<div class="hikmah-dash-content-inner" id="hikmah-dash-content-inner">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered template HTML.
				echo $dashboard->render_tab( $active_tab );
				?>
			</div>
		</main>
	</div>
</div>

<?php
wp_localize_script( 'hikmah-dashboard-script', 'hikmahDashboard', [
	'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
	'nonce'     => wp_create_nonce( 'hikmah_dashboard_nonce' ),
	'userId'    => (int) $user->ID,
	'exportUrl' => admin_url( 'admin-post.php' ) . '?action=hikmah_dashboard_download_export&_wpnonce=' . wp_create_nonce( 'hikmah_dashboard_nonce' ),
	'i18n'      => [
		'confirmSessionRevoke' => __( 'Revoke this session?', 'hikmah-login' ),
		'confirmLogoutAll'     => __( 'Log out from all other devices?', 'hikmah-login' ),
		'confirmUnlinkSocial'  => __( 'Disconnect this social account?', 'hikmah-login' ),
		'confirmDeleteAccount' => __( 'This action is permanent. Are you absolutely sure?', 'hikmah-login' ),
		'confirmExport'        => __( 'Create a JSON export of your account data?', 'hikmah-login' ),
		'saving'               => __( 'Saving…', 'hikmah-login' ),
		'processing'           => __( 'Processing…', 'hikmah-login' ),
		'networkError'         => __( 'Network error. Please try again.', 'hikmah-login' ),
	],
] );