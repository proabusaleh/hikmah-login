<?php
/**
 * Account Summary Widget
 *
 * Shows key account information at a glance.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Widget_Account_Summary extends Hikmah_Dashboard_Widget {

    protected $id       = 'account-summary';
    protected $title    = '';
    protected $priority = 10;
    protected $location = 'full';

    public function __construct() {
        $this->title = __('Account Summary', 'hikmah-login');
        parent::__construct();
    }

    /**
     * Render account summary content
     *
     * @return void
     */
    protected function render_content() {
        $user = wp_get_current_user();
        $dashboard = Hikmah_Dashboard::get_instance();

        // Gather stats
        $stats = $this->get_user_stats($user->ID);
        ?>

        <div class="hikmah-widget-summary">

            <div class="hikmah-widget-summary__cards">

                <!-- Profile Completion -->
                <div class="hikmah-widget-summary__card">
                    <div class="hikmah-widget-summary__card-icon hikmah-widget-summary__card-icon--profile">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="hikmah-widget-summary__card-content">
                        <span class="hikmah-widget-summary__card-value">
                            <?php echo esc_html($stats['profile_completion']); ?>%
                        </span>
                        <span class="hikmah-widget-summary__card-label">
                            <?php esc_html_e('Profile Complete', 'hikmah-login'); ?>
                        </span>
                    </div>
                    <div class="hikmah-widget-summary__progress">
                        <div class="hikmah-widget-summary__progress-bar"
                             style="width: <?php echo esc_attr($stats['profile_completion']); ?>%"></div>
                    </div>
                </div>

                <!-- Security Score -->
                <div class="hikmah-widget-summary__card">
                    <div class="hikmah-widget-summary__card-icon hikmah-widget-summary__card-icon--security">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="hikmah-widget-summary__card-content">
                        <span class="hikmah-widget-summary__card-value">
                            <?php echo esc_html($stats['security_score']); ?>/100
                        </span>
                        <span class="hikmah-widget-summary__card-label">
                            <?php esc_html_e('Security Score', 'hikmah-login'); ?>
                        </span>
                    </div>
                </div>

                <!-- Active Sessions -->
                <div class="hikmah-widget-summary__card">
                    <div class="hikmah-widget-summary__card-icon hikmah-widget-summary__card-icon--sessions">
                        <span class="dashicons dashicons-laptop"></span>
                    </div>
                    <div class="hikmah-widget-summary__card-content">
                        <span class="hikmah-widget-summary__card-value">
                            <?php echo esc_html($stats['active_sessions']); ?>
                        </span>
                        <span class="hikmah-widget-summary__card-label">
                            <?php esc_html_e('Active Sessions', 'hikmah-login'); ?>
                        </span>
                    </div>
                </div>

                <!-- Last Login -->
                <div class="hikmah-widget-summary__card">
                    <div class="hikmah-widget-summary__card-icon hikmah-widget-summary__card-icon--login">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="hikmah-widget-summary__card-content">
                        <span class="hikmah-widget-summary__card-value">
                            <?php echo esc_html($stats['last_login']); ?>
                        </span>
                        <span class="hikmah-widget-summary__card-label">
                            <?php esc_html_e('Last Login', 'hikmah-login'); ?>
                        </span>
                    </div>
                </div>

            </div>

        </div>

        <?php
    }

    /**
     * Get user statistics
     *
     * @param int $user_id User ID.
     * @return array User stats.
     */
    private function get_user_stats($user_id) {
        $user = get_userdata($user_id);

        // Profile completion
        $profile_fields = [
            'first_name', 'last_name', 'description',
            'user_url',
        ];
        $filled = 0;
        foreach ($profile_fields as $field) {
            if (!empty($user->$field)) {
                $filled++;
            }
        }
        // Check avatar
        $custom_avatar = get_user_meta($user_id, '_hikmah_custom_avatar', true);
        if (!empty($custom_avatar)) {
            $filled++;
        }
        $total_fields = count($profile_fields) + 1; // +1 for avatar
        $profile_completion = round(($filled / $total_fields) * 100);

        // Security score
        $security_score = $this->calculate_security_score($user_id);

        // Active sessions
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $all_sessions = $sessions->get_all();
        $active_sessions = count($all_sessions);

        // Last login
        $last_login_timestamp = get_user_meta($user_id, '_hikmah_last_login', true);
        if ($last_login_timestamp) {
            $last_login = human_time_diff($last_login_timestamp, current_time('timestamp'));
            $last_login .= ' ' . __('ago', 'hikmah-login');
        } else {
            $last_login = __('N/A', 'hikmah-login');
        }

        return [
            'profile_completion' => $profile_completion,
            'security_score'     => $security_score,
            'active_sessions'    => $active_sessions,
            'last_login'         => $last_login,
        ];
    }

    /**
     * Calculate user security score
     *
     * @param int $user_id User ID.
     * @return int Score out of 100.
     */
    private function calculate_security_score($user_id) {
        $score = 0;

        // Strong password (assume yes if recently changed) +20
        $password_changed = get_user_meta($user_id, '_hikmah_password_last_changed', true);
        if ($password_changed) {
            $days_since = (current_time('timestamp') - $password_changed) / DAY_IN_SECONDS;
            if ($days_since < 90) {
                $score += 20;
            } else {
                $score += 10;
            }
        } else {
            $score += 10; // Default
        }

        // 2FA enabled +30
        $twofa_enabled = get_user_meta($user_id, '_hikmah_2fa_enabled', true);
        if ($twofa_enabled) {
            $score += 30;
        }

        // Email verified +20
        $email_verified = get_user_meta($user_id, '_hikmah_email_verified', true);
        if ($email_verified) {
            $score += 20;
        }

        // Only 1 active session +10
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $session_count = count($sessions->get_all());
        if ($session_count <= 1) {
            $score += 10;
        } elseif ($session_count <= 3) {
            $score += 5;
        }

        // No failed login attempts recently +10
        $failed_attempts = get_user_meta($user_id, '_hikmah_failed_attempts', true);
        if (empty($failed_attempts) || intval($failed_attempts) === 0) {
            $score += 10;
        }

        // Profile complete +10
        $user = get_userdata($user_id);
        if (!empty($user->first_name) && !empty($user->last_name)) {
            $score += 10;
        }

        return min(100, $score);
    }
}
