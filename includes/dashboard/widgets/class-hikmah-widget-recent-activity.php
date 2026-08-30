<?php
/**
 * Recent Login Activity Widget
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Widget_Recent_Activity extends Hikmah_Dashboard_Widget {

    protected $id       = 'recent-activity';
    protected $title    = '';
    protected $priority = 30;
    protected $location = 'main';

    public function __construct() {
        $this->title = __('Recent Activity', 'hikmah-login');
        parent::__construct();
    }

    protected function render_widget_actions() {
        ?>
        <a href="<?php echo esc_url(Hikmah_Dashboard::get_dashboard_url('login-history')); ?>"
           class="hikmah-dashboard__widget-action-link">
            <?php esc_html_e('View All', 'hikmah-login'); ?>
        </a>
        <?php
    }

    protected function render_content() {
        global $wpdb;

        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'hikmah_login_logs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            echo '<p class="hikmah-widget-activity__empty">';
            esc_html_e('No activity data available.', 'hikmah-login');
            echo '</p>';
            return;
        }

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE user_id = %d 
                 ORDER BY login_time DESC 
                 LIMIT 5",
                $user_id
            )
        );

        if (empty($logs)) {
            echo '<p class="hikmah-widget-activity__empty">';
            esc_html_e('No recent login activity.', 'hikmah-login');
            echo '</p>';
            return;
        }
        ?>

        <div class="hikmah-widget-activity">
            <table class="hikmah-widget-activity__table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'hikmah-login'); ?></th>
                        <th><?php esc_html_e('IP Address', 'hikmah-login'); ?></th>
                        <th><?php esc_html_e('Status', 'hikmah-login'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td>
                                <?php
                                echo esc_html(
                                    date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        strtotime($log->login_time)
                                    )
                                );
                                ?>
                            </td>
                            <td>
                                <code><?php echo esc_html($this->mask_ip($log->ip_address)); ?></code>
                            </td>
                            <td>
                                <?php
                                $status_class = ($log->status === 'success') ? 'success' : 'failed';
                                $status_label = ($log->status === 'success')
                                    ? __('Success', 'hikmah-login')
                                    : __('Failed', 'hikmah-login');
                                ?>
                                <span class="hikmah-widget-activity__status hikmah-widget-activity__status--<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
    }

    /**
     * Partially mask IP address for privacy
     *
     * @param string $ip IP address.
     * @return string Masked IP.
     */
    private function mask_ip($ip) {
        if (strpos($ip, '.') !== false) {
            // IPv4: show first two octets
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.***. ***';
        }
        // IPv6: show first segment
        $parts = explode(':', $ip);
        return $parts[0] . ':****:****';
    }
}
