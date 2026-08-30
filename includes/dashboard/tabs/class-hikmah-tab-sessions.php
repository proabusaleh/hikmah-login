<?php
/**
 * Dashboard Sessions Tab
 *
 * Manages active user sessions with device info and
 * ability to revoke individual or all sessions.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Tab_Sessions {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('hikmah_dashboard_ajax_destroy_session', [$this, 'ajax_destroy_session']);
        add_action('hikmah_dashboard_ajax_destroy_all_sessions', [$this, 'ajax_destroy_all_sessions']);
    }

    /**
     * Render sessions tab
     *
     * @return void
     */
    public function render() {
        $user_id = get_current_user_id();
        $sessions = $this->get_user_sessions($user_id);
        $current_token = $this->get_current_session_token();
        ?>

        <div class="hikmah-tab-sessions">

            <div class="hikmah-tab-sessions__header">
                <p class="hikmah-tab-sessions__description">
                    <?php esc_html_e('These are the devices that are currently logged into your account. You can revoke any session that you do not recognize.', 'hikmah-login'); ?>
                </p>

                <?php if (count($sessions) > 1) : ?>
                    <button type="button"
                            class="hikmah-btn hikmah-btn--danger hikmah-btn--sm"
                            id="hikmah-destroy-all-sessions">
                        <span class="dashicons dashicons-no"></span>
                        <?php esc_html_e('Log Out All Other Sessions', 'hikmah-login'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($sessions)) : ?>
                <div class="hikmah-tab-sessions__empty">
                    <p><?php esc_html_e('No active sessions found.', 'hikmah-login'); ?></p>
                </div>
            <?php else : ?>

                <div class="hikmah-tab-sessions__list" id="hikmah-sessions-list">

                    <?php foreach ($sessions as $token_hash => $session) : ?>
                        <?php
                        $is_current = ($token_hash === $current_token);
                        $device_info = $this->parse_user_agent($session['ua'] ?? '');
                        $login_time = isset($session['login']) ? $session['login'] : ($session['expiration'] - (2 * DAY_IN_SECONDS));
                        $expiration = $session['expiration'] ?? 0;
                        $ip = $session['ip'] ?? __('Unknown', 'hikmah-login');
                        ?>

                        <div class="hikmah-tab-sessions__item <?php echo $is_current ? 'hikmah-tab-sessions__item--current' : ''; ?>"
                             data-token="<?php echo esc_attr($token_hash); ?>">

                            <div class="hikmah-tab-sessions__device-icon">
                                <span class="dashicons <?php echo esc_attr($device_info['icon']); ?>"></span>
                            </div>

                            <div class="hikmah-tab-sessions__details">
                                <div class="hikmah-tab-sessions__device-name">
                                    <?php echo esc_html($device_info['browser']); ?>
                                    <?php esc_html_e('on', 'hikmah-login'); ?>
                                    <?php echo esc_html($device_info['os']); ?>

                                    <?php if ($is_current) : ?>
                                        <span class="hikmah-tab-sessions__current-badge">
                                            <?php esc_html_e('Current Session', 'hikmah-login'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="hikmah-tab-sessions__meta">
                                    <span class="hikmah-tab-sessions__ip">
                                        <span class="dashicons dashicons-location"></span>
                                        <?php echo esc_html($this->mask_ip($ip)); ?>
                                    </span>

                                    <span class="hikmah-tab-sessions__time">
                                        <span class="dashicons dashicons-clock"></span>
                                        <?php
                                        printf(
                                            /* translators: %s: human-readable time */
                                            esc_html__('Active %s ago', 'hikmah-login'),
                                            esc_html(human_time_diff($login_time, current_time('timestamp')))
                                        );
                                        ?>
                                    </span>

                                    <?php if ($expiration) : ?>
                                        <span class="hikmah-tab-sessions__expires">
                                            <span class="dashicons dashicons-calendar-alt"></span>
                                            <?php
                                            printf(
                                                /* translators: %s: expiration date */
                                                esc_html__('Expires %s', 'hikmah-login'),
                                                esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expiration))
                                            );
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="hikmah-tab-sessions__actions">
                                <?php if (!$is_current) : ?>
                                    <button type="button"
                                            class="hikmah-btn hikmah-btn--outline hikmah-btn--sm hikmah-btn--danger hikmah-destroy-session"
                                            data-token="<?php echo esc_attr($token_hash); ?>">
                                        <?php esc_html_e('Revoke', 'hikmah-login'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

        <?php
    }

    /**
     * Get all sessions for a user
     *
     * @param int $user_id User ID.
     * @return array Sessions array.
     */
    private function get_user_sessions($user_id) {
        $manager = WP_Session_Tokens::get_instance($user_id);
        $sessions = $manager->get_all();

        // Filter out expired sessions
        $now = time();
        foreach ($sessions as $token => $session) {
            if (isset($session['expiration']) && $session['expiration'] < $now) {
                unset($sessions[$token]);
            }
        }

        return $sessions;
    }

    /**
     * Get the current session token hash
     *
     * @return string Token hash.
     */
    private function get_current_session_token() {
        $cookie = wp_parse_auth_cookie('', 'logged_in');
        if (!$cookie) {
            return '';
        }
        return wp_hash($cookie['token']);
    }

    /**
     * Parse user agent string to extract device info
     *
     * @param string $ua User agent string.
     * @return array Device information.
     */
    private function parse_user_agent($ua) {
        $info = [
            'browser' => __('Unknown Browser', 'hikmah-login'),
            'os'      => __('Unknown OS', 'hikmah-login'),
            'icon'    => 'dashicons-desktop',
        ];

        if (empty($ua)) {
            return $info;
        }

        // Detect browser
        $browsers = [
            'Firefox'   => '/Firefox\/[\d.]+/',
            'Chrome'    => '/Chrome\/[\d.]+/',
            'Safari'    => '/Safari\/[\d.]+/',
            'Edge'      => '/Edg\/[\d.]+/',
            'Opera'     => '/OPR\/[\d.]+/',
            'IE'        => '/MSIE\s[\d.]+|Trident/',
        ];

        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                $info['browser'] = $name;
                break;
            }
        }

        // Detect OS
        $os_patterns = [
            'Windows 11' => '/Windows NT 10.*Build.*(2[2-9]|[3-9]\d)\d{3}/',
            'Windows 10' => '/Windows NT 10/',
            'Windows 8'  => '/Windows NT 6\.[23]/',
            'Windows 7'  => '/Windows NT 6\.1/',
            'macOS'      => '/Mac OS X/',
            'Linux'      => '/Linux(?!.*Android)/',
            'Android'    => '/Android/',
            'iOS'        => '/iPhone|iPad|iPod/',
            'Chrome OS'  => '/CrOS/',
        ];

        foreach ($os_patterns as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                $info['os'] = $name;
                break;
            }
        }

        // Device icon
        if (preg_match('/Mobile|Android|iPhone|iPod/', $ua)) {
            $info['icon'] = 'dashicons-smartphone';
        } elseif (preg_match('/iPad|Tablet/', $ua)) {
            $info['icon'] = 'dashicons-tablet';
        }

        return $info;
    }

    /**
     * Partially mask IP address
     *
     * @param string $ip IP address.
     * @return string Masked IP.
     */
    private function mask_ip($ip) {
        if (strpos($ip, '.') !== false) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.***. ***';
        }
        return substr($ip, 0, 8) . '****';
    }

    /**
     * AJAX: Destroy a specific session
     *
     * @return void
     */
    public function ajax_destroy_session() {
        $user_id = get_current_user_id();
        $token   = sanitize_text_field($_POST['token'] ?? '');

        if (empty($token)) {
            wp_send_json_error(['message' => __('Invalid session token.', 'hikmah-login')]);
        }

        // Prevent destroying current session
        $current_token = $this->get_current_session_token();
        if ($token === $current_token) {
            wp_send_json_error(['message' => __('Cannot revoke current session. Use logout instead.', 'hikmah-login')]);
        }

        $manager = WP_Session_Tokens::get_instance($user_id);

        // Verify the session exists
        $all_sessions = $manager->get_all();
        if (!isset($all_sessions[$token])) {
            wp_send_json_error(['message' => __('Session not found.', 'hikmah-login')]);
        }

        // Destroy the session
        // WordPress doesn't have a direct method to destroy by hash,
        // so we need to use the token verifier approach
        $manager->destroy($token);

        /**
         * Action after a session is destroyed
         *
         * @param int    $user_id User ID.
         * @param string $token   Session token hash.
         */
        do_action('hikmah_session_destroyed', $user_id, $token);

        wp_send_json_success([
            'message'         => __('Session revoked successfully.', 'hikmah-login'),
            'remaining_count' => count($manager->get_all()),
        ]);
    }

    /**
     * AJAX: Destroy all sessions except current
     *
     * @return void
     */
    public function ajax_destroy_all_sessions() {
        $user_id = get_current_user_id();

        $manager = WP_Session_Tokens::get_instance($user_id);
        $current_token = wp_get_session_token();

        // Destroy all except current
        $manager->destroy_others($current_token);

        /**
         * Action after all other sessions are destroyed
         *
         * @param int $user_id User ID.
         */
        do_action('hikmah_all_sessions_destroyed', $user_id);

        wp_send_json_success([
            'message' => __('All other sessions have been logged out.', 'hikmah-login'),
        ]);
    }
}
