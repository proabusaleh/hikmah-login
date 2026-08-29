<?php
/**
 * User Dashboard Template
 *
 * Override: yourtheme/hikmah-login/user-dashboard.php
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    ?>
    <div class="hikmah-login-wrapper">
        <div class="hikmah-notice hikmah-notice-info">
            <p>
                <?php
                printf(
                    wp_kses_post( __( 'Please <a href="%s">log in</a> to access your dashboard.', 'hikmah-login' ) ),
                    esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() )
                );
                ?>
            </p>
        </div>
    </div>
    <?php
    return;
}

$dashboard = \Hikmah_Login\Auth\User_Dashboard::get_instance();
$user = wp_get_current_user();
$tabs = $dashboard->get_visible_tabs();
$active_tab = $dashboard->get_active_tab();
?>

<div class="hikmah-dashboard" id="hikmah-dashboard">

    <!-- Dashboard Header -->
    <div class="hikmah-dash-header" style="
        display:flex;align-items:center;justify-content:space-between;
        margin-bottom:24px;flex-wrap:wrap;gap:12px;
    ">
        <div style="display:flex;align-items:center;gap:16px;">
            <?php echo get_avatar( $user->ID, 48, '', '', [ 'class' => 'hikmah-avatar-img' ] ); ?>
            <div>
                <h2 style="margin:0;font-size:20px;">
                    <?php
                    printf(
                        /* translators: %s: User name */
                        esc_html__( 'Welcome, %s!', 'hikmah-login' ),
                        esc_html( $user->display_name )
                    );
                    ?>
                </h2>
                <small style="color:var(--hikmah-text-muted);">
                    <?php echo esc_html( $user->user_email ); ?>
                    &bull;
                    <?php echo esc_html( implode( ', ', $user->roles ) ); ?>
                </small>
            </div>
        </div>
        <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_logout_url() ); ?>"
           class="hikmah-btn hikmah-btn-outline" style="font-size:13px;padding:6px 14px;">
            🚪 <?php esc_html_e( 'Logout', 'hikmah-login' ); ?>
        </a>
    </div>

    <!-- Tab Navigation -->
    <nav class="hikmah-dash-nav" role="tablist" style="
        display:flex;gap:4px;border-bottom:2px solid var(--hikmah-border);
        margin-bottom:24px;overflow-x:auto;
    ">
        <?php foreach ( $tabs as $key => $tab ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'tab', $key, \Hikmah_Login\Helpers\Helper::get_dashboard_url() ) ); ?>"
               class="hikmah-dash-tab"
               role="tab"
               aria-selected="<?php echo $active_tab === $key ? 'true' : 'false'; ?>"
               style="
                   padding:10px 16px;font-size:14px;font-weight:500;
                   text-decoration:none;color:<?php echo $active_tab === $key ? 'var(--hikmah-primary)' : 'var(--hikmah-text-muted)'; ?>;
                   border-bottom:2px solid <?php echo $active_tab === $key ? 'var(--hikmah-primary)' : 'transparent'; ?>;
                   margin-bottom:-2px;white-space:nowrap;transition:all 0.2s;
               ">
                <?php echo esc_html( $tab['icon'] ); ?>
                <?php echo esc_html( $tab['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Tab Content -->
    <div class="hikmah-dash-content" role="tabpanel">
        <?php
        if ( isset( $tabs[ $active_tab ]['callback'] ) && is_callable( $tabs[ $active_tab ]['callback'] ) ) {
            call_user_func( $tabs[ $active_tab ]['callback'] );
        }
        ?>
    </div>

    <?php
    /**
     * Fires at the bottom of the dashboard.
     *
     * @since 1.0.0
     * @param \WP_User $user Current user.
     */
    do_action( 'hikmah_dashboard_footer', $user );
    ?>
</div>

<style>
.hikmah-dashboard {
    max-width: 720px;
    margin: 24px auto;
    padding: 24px;
    background: var(--hikmah-surface);
    border-radius: var(--hikmah-card-radius);
    box-shadow: var(--hikmah-shadow-lg);
    font-family: var(--hikmah-font);
    color: var(--hikmah-text);
}

.hikmah-dash-card {
    padding: 16px;
    background: var(--hikmah-bg);
    border-radius: var(--hikmah-radius);
    border: 1px solid var(--hikmah-border);
}

.hikmah-dash-card h4 {
    margin: 0 0 8px;
    font-size: 16px;
}

.hikmah-dash-desc {
    color: var(--hikmah-text-muted);
    font-size: 14px;
    margin: 0 0 16px;
}

.hikmah-dash-form .hikmah-field {
    margin-bottom: 14px;
}

.hikmah-dash-status {
    display: inline-block;
    margin-left: 12px;
    font-size: 13px;
    color: var(--hikmah-success);
}

.hikmah-avatar-img {
    border-radius: 50%;
    object-fit: cover;
    background: var(--hikmah-bg);
}

.hikmah-dash-tab:hover {
    color: var(--hikmah-primary) !important;
}

@media (max-width: 480px) {
    .hikmah-dashboard {
        margin: 12px;
        padding: 16px;
    }

    .hikmah-dash-nav {
        gap: 0;
    }

    .hikmah-dash-tab {
        padding: 8px 10px !important;
        font-size: 12px !important;
    }
}
</style>