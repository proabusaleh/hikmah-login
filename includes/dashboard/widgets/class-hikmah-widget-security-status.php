<?php
/**
 * Security Status Widget
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Widget_Security_Status extends Hikmah_Dashboard_Widget {

    protected $id       = 'security-status';
    protected $title    = '';
    protected $priority = 15;
    protected $location = 'main';

    public function __construct() {
        $this->title = __('Security Status', 'hikmah-login');
        parent::__construct();
    }

    protected function render_content() {
        $user_id = get_current_user_id();
        $checks  = $this->get_security_checks($user_id);
        ?>

        <div class="hikmah-widget-security">
            <ul class="hikmah-widget-security__checklist">
                <?php foreach ($checks as $check) : ?>
                    <li class="hikmah-widget-security__item hikmah-widget-security__item--<?php echo $check['status'] ? 'pass' : 'fail'; ?>">
                        <span class="hikmah-widget-security__icon">
                            <span class="dashicons <?php echo $check['status'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                        </span>
                        <span class="hikmah-widget-security__label">
                            <?php echo esc_html($check['label']); ?>
                        </span>
                        <?php if (!$check['status'] && !empty($check['action_url'])) : ?>
                            <a href="<?php echo esc_url($check['action_url']); ?>"
                               class="hikmah-widget-security__action">
                                <?php echo esc_html($check['action_text']); ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php
    }

    /**
     * Get security check items
     *
     * @param int $user_id User ID.
     * @return array
     */
    private function get_security_checks($user_id) {
        $checks = [];

        // Email verified
        $email_verified = get_user_meta($user_id, '_hikmah_email_verified', true);
        $checks[] = [
            'label'       => __('Email verified', 'hikmah-login'),
            'status'      => (bool) $email_verified,
            'action_url'  => Hikmah_Dashboard::get_dashboard_url('account'),
            'action_text' => __('Verify', 'hikmah-login'),
        ];

        // 2FA enabled
        $twofa = get_user_meta($user_id, '_hikmah_2fa_enabled', true);
        $checks[] = [
            'label'       => __('Two-factor authentication', 'hikmah-login'),
            'status'      => (bool) $twofa,
            'action_url'  => Hikmah_Dashboard::get_dashboard_url('security'),
            'action_text' => __('Enable', 'hikmah-login'),
        ];

        // Password strength / recent change
        $pw_changed = get_user_meta($user_id, '_hikmah_password_last_changed', true);
        $pw_fresh = $pw_changed && ((current_time('timestamp') - $pw_changed) < (90 * DAY_IN_SECONDS));
        $checks[] = [
            'label'       => __('Password recently updated', 'hikmah-login'),
            'status'      => (bool) $pw_fresh,
            'action_url'  => Hikmah_Dashboard::get_dashboard_url('security'),
            'action_text' => __('Update', 'hikmah-login'),
        ];

        // Single session
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $session_count = count($sessions->get_all());
        $checks[] = [
            'label'       => __('No excessive active sessions', 'hikmah-login'),
            'status'      => ($session_count <= 3),
            'action_url'  => Hikmah_Dashboard::get_dashboard_url('sessions'),
            'action_text' => __('Manage', 'hikmah-login'),
        ];

        /**
         * Filter security check items
         *
         * @param array $checks  Security checks.
         * @param int   $user_id User ID.
         */
        return apply_filters('hikmah_dashboard_security_checks', $checks, $user_id);
    }
}
