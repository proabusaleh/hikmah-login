<?php
/**
 * Quick Actions Widget
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Widget_Quick_Actions extends Hikmah_Dashboard_Widget {

    protected $id       = 'quick-actions';
    protected $title    = '';
    protected $priority = 20;
    protected $location = 'sidebar';

    public function __construct() {
        $this->title = __('Quick Actions', 'hikmah-login');
        parent::__construct();
    }

    protected function render_content() {
        $actions = $this->get_actions();
        ?>

        <ul class="hikmah-widget-actions__list">
            <?php foreach ($actions as $action) : ?>
                <li class="hikmah-widget-actions__item">
                    <a href="<?php echo esc_url($action['url']); ?>"
                       class="hikmah-widget-actions__link">
                        <span class="dashicons <?php echo esc_attr($action['icon']); ?>"></span>
                        <span><?php echo esc_html($action['label']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php
    }

    /**
     * Get quick action links
     *
     * @return array
     */
    private function get_actions() {
        $actions = [
            [
                'label' => __('Edit Profile', 'hikmah-login'),
                'url'   => Hikmah_Dashboard::get_dashboard_url('profile'),
                'icon'  => 'dashicons-edit',
            ],
            [
                'label' => __('Change Password', 'hikmah-login'),
                'url'   => Hikmah_Dashboard::get_dashboard_url('security'),
                'icon'  => 'dashicons-lock',
            ],
            [
                'label' => __('Manage Sessions', 'hikmah-login'),
                'url'   => Hikmah_Dashboard::get_dashboard_url('sessions'),
                'icon'  => 'dashicons-laptop',
            ],
            [
                'label' => __('Login History', 'hikmah-login'),
                'url'   => Hikmah_Dashboard::get_dashboard_url('login-history'),
                'icon'  => 'dashicons-backup',
            ],
        ];

        // Check if 2FA is not enabled, suggest enabling
        $twofa_enabled = get_user_meta(get_current_user_id(), '_hikmah_2fa_enabled', true);
        if (!$twofa_enabled) {
            array_splice($actions, 2, 0, [[
                'label' => __('Enable 2FA', 'hikmah-login'),
                'url'   => Hikmah_Dashboard::get_dashboard_url('security'),
                'icon'  => 'dashicons-shield-alt',
            ]]);
        }

        /**
         * Filter quick action items
         *
         * @param array $actions Quick action links.
         */
        return apply_filters('hikmah_dashboard_quick_actions', $actions);
    }
}
