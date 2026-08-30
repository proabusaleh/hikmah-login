<?php
/**
 * Template: Dashboard Outer Framework
 *
 * Renders the responsive dashboard layout including the sidebar navigation,
 * dynamic notification areas, and the main content viewport.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure the user is logged in before rendering anything
if (!is_user_logged_in()) {
    echo '<div class="hikmah-error-notice">' . esc_html__('You must be logged in to view this page.', 'hikmah-login') . '</div>';
    return;
}

$current_user = wp_get_current_user();
$current_tab  = sanitize_key($_GET['tab'] ?? 'profile');
$tabs         = apply_filters('hikmah_dashboard_tabs', [
    'profile'   => [
        'label' => __('My Profile', 'hikmah-login'),
        'icon'  => 'admin-users',
    ],
    'security'  => [
        'label' => __('Security & 2FA', 'hikmah-login'),
        'icon'  => 'shield',
    ],
    'sessions'  => [
        'label' => __('Active Sessions', 'hikmah-login'),
        'icon'  => 'desktop',
    ],
    'connected' => [
        'label' => __('Connected Accounts', 'hikmah-login'),
        'icon'  => 'share',
    ],
    'account'   => [
        'label' => __('Account Settings', 'hikmah-login'),
        'icon'  => 'admin-generic',
    ],
]);

// Fallback to default tab if key is unregistered
if (!array_key_exists($current_tab, $tabs)) {
    $current_tab = 'profile';
}
?>

<div class="hikmah-dashboard">

    <!-- Mobile Navigation Header -->
    <div class="hikmah-dashboard__mobile-header">
        <span class="hikmah-dashboard__mobile-title">
            <?php echo esc_html($tabs[$current_tab]['label']); ?>
        </span>
        <button type="button" class="hikmah-dashboard__mobile-nav-toggle" aria-label="<?php esc_attr_e('Toggle Menu', 'hikmah-login'); ?>">
            <span class="dashicons dashicons-menu"></span>
        </button>
    </div>

    <!-- Dashboard Sidebar Panel -->
    <aside class="hikmah-dashboard__sidebar">
        <div class="hikmah-dashboard__sidebar-logo">
            <?php
            $logo_url = hikmah_get_option('login_logo', '');
            if (!empty($logo_url)) {
                printf('<img src="%s" alt="%s" />', esc_url($logo_url), esc_attr(get_bloginfo('name')));
            } else {
                printf('<h3>%s</h3>', esc_html(get_bloginfo('name')));
            }
            ?>
        </div>

        <ul class="hikmah-dashboard__menu">
            <?php foreach ($tabs as $tab_id => $tab_args) : ?>
                <?php
                $is_active = ($current_tab === $tab_id);
                $tab_url   = add_query_arg('tab', $tab_id, get_permalink());
                $classes   = 'hikmah-dashboard__menu-item';
                if ($is_active) {
                    $classes .= ' hikmah-dashboard__menu-item--active';
                }
                ?>
                <li class="<?php echo esc_attr($classes); ?>">
                    <a href="<?php echo esc_url($tab_url); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($tab_args['icon']); ?>"></span>
                        <span class="hikmah-dashboard__menu-label"><?php echo esc_html($tab_args['label']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <li class="hikmah-dashboard__menu-item hikmah-dashboard__menu-item--logout">
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>">
                    <span class="dashicons dashicons-logout"></span>
                    <span><?php esc_html_e('Log Out', 'hikmah-login'); ?></span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Viewport Content Area -->
    <main class="hikmah-dashboard__content">

        <!-- Global AJAX & Session Notifications wrapper -->
        <div class="hikmah-dashboard__notices-wrapper">
            <?php
            // Output fallback URL/redirect notices stored in session transients
            if (isset($_GET['notice'])) {
                $notice_type = sanitize_key($_GET['notice_type'] ?? 'info');
                $message     = sanitize_text_field($_GET['notice_msg'] ?? '');
                if ($message) {
                    printf(
                        '<div class="hikmah-dashboard__notice hikmah-dashboard__notice--%1$s">
                            <div class="hikmah-dashboard__notice-content">
                                <span class="dashicons %2$s"></span>
                                <p>%3$s</p>
                            </div>
                            <button type="button" class="hikmah-dashboard__notice-dismiss"><span class="dashicons dashicons-no-alt"></span></button>
                        </div>',
                        esc_attr($notice_type),
                        esc_attr($notice_type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning'),
                        esc_html($message)
                    );
                }
            }
            ?>
        </div>

        <!-- Dynamic Tab Context Injector -->
        <div class="hikmah-dashboard__tab-body">
            <?php
            /**
             * Fires inside the dashboard main viewport to load current tab contents.
             * 
             * @param string $current_tab Active tab slug identifier.
             * @param WP_User $current_user Current logged-in user object.
             */
            do_action('hikmah_dashboard_render_tab_' . $current_tab, $current_user);
            ?>
        </div>

    </main>

</div>
