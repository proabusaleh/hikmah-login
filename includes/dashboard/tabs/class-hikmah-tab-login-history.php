<?php
/**
 * Dashboard Login History Tab
 *
 * Displays paginated login history with filtering.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Tab_Login_History {

    /**
     * Items per page
     *
     * @var int
     */
    private $per_page = 20;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('hikmah_dashboard_ajax_load_login_history', [$this, 'ajax_load_history']);
    }

    /**
     * Render login history tab
     *
     * @return void
     */
    public function render() {
        $user_id     = get_current_user_id();
        $current_page = max(1, intval($_GET['hpage'] ?? 1));
        $filter       = sanitize_key($_GET['filter'] ?? 'all');

        $result = $this->get_login_history($user_id, $current_page, $filter);
        ?>

        <div class="hikmah-tab-login-history">

            <!-- Filters -->
            <div class="hikmah-tab-login-history__filters">
                <?php
                $filter_options = [
                    'all'     => __('All', 'hikmah-login'),
                    'success' => __('Successful', 'hikmah-login'),
                    'failed'  => __('Failed', 'hikmah-login'),
                ];

                foreach ($filter_options as $value => $label) :
                    $is_active = ($filter === $value);
                    $filter_url = add_query_arg(['filter' => $value, 'hpage' => 1]);
                ?>
                    <a href="<?php echo esc_url($filter_url); ?>"
                       class="hikmah-tab-login-history__filter <?php echo $is_active ? 'hikmah-tab-login-history__filter--active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                        <?php if (isset($result['counts'][$value])) : ?>
                            <span class="hikmah-tab-login-history__filter-count">
                                (<?php echo esc_html($result['counts'][$value]); ?>)
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($result['logs'])) : ?>
                <div class="hikmah-tab-login-history__empty">
                    <span class="dashicons dashicons-backup"></span>
                    <p><?php esc_html_e('No login history found.', 'hikmah-login'); ?></p>
                </div>
            <?php else : ?>

                <!-- History Table -->
                <div class="hikmah-tab-login-history__table-wrapper">
                    <table class="hikmah-tab-login-history__table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date & Time', 'hikmah-login'); ?></th>
                                <th><?php esc_html_e('IP Address', 'hikmah-login'); ?></th>
                                <th><?php esc_html_e('Browser', 'hikmah-login'); ?></th>
                                <th><?php esc_html_e('Location', 'hikmah-login'); ?></th>
                                <th><?php esc_html_e('Status', 'hikmah-login'); ?></th>
                                <th><?php esc_html_e('Method', 'hikmah-login'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['logs'] as $log) : ?>
                                <?php
                                $device = $this->parse_user_agent($log->user_agent ?? '');
                                $status_class = ($log->status === 'success') ? 'success' : 'failed';
                                ?>
                                <tr class="hikmah-tab-login-history__row hikmah-tab-login-history__row--<?php echo esc_attr($status_class); ?>">
                                    <td>
                                        <div class="hikmah-tab-login-history__datetime">
                                            <span class="hikmah-tab-login-history__date">
                                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($log->login_time))); ?>
                                            </span>
                                            <span class="hikmah-tab-login-history__time">
                                                <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($log->login_time))); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="hikmah-tab-login-history__ip">
                                            <?php echo esc_html($this->mask_ip($log->ip_address)); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="dashicons <?php echo esc_attr($device['icon']); ?>"></span>
                                        <?php echo esc_html($device['browser']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $location = $this->get_ip_location($log->ip_address);
                                        echo esc_html($location ?: __('Unknown', 'hikmah-login'));
                                        ?>
                                    </td>
                                    <td>
                                        <span class="hikmah-tab-login-history__status hikmah-tab-login-history__status--<?php echo esc_attr($status_class); ?>">
                                            <span class="dashicons <?php echo $log->status === 'success' ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                                            <?php
                                            echo $log->status === 'success'
                                                ? esc_html__('Success', 'hikmah-login')
                                                : esc_html__('Failed', 'hikmah-login');
                                            ?>
                                        </span>
                                        <?php if ($log->status !== 'success' && !empty($log->failure_reason)) : ?>
                                            <span class="hikmah-tab-login-history__reason">
                                                <?php echo esc_html($log->failure_reason); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $method = $log->login_method ?? 'standard';
                                        $method_labels = [
                                            'standard' => __('Password', 'hikmah-login'),
                                            'google'   => __('Google', 'hikmah-login'),
                                            'facebook' => __('Facebook', 'hikmah-login'),
                                            'ajax'     => __('AJAX', 'hikmah-login'),
                                            'api'      => __('API', 'hikmah-login'),
                                        ];
                                        echo esc_html($method_labels[$method] ?? ucfirst($method));
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($result['total_pages'] > 1) : ?>
                    <div class="hikmah-tab-login-history__pagination">
                        <?php
                        $pagination_args = [
                            'base'      => add_query_arg('hpage', '%#%'),
                            'format'    => '',
                            'current'   => $current_page,
                            'total'     => $result['total_pages'],
                            'prev_text' => '&laquo; ' . __('Previous', 'hikmah-login'),
                            'next_text' => __('Next', 'hikmah-login') . ' &raquo;',
                            'type'      => 'list',
                        ];

                        echo wp_kses_post(paginate_links($pagination_args));
                        ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>

        <?php
    }

    /**
     * Get login history from database
     *
     * @param int    $user_id      User ID.
     * @param int    $current_page Current page number.
     * @param string $filter       Status filter (all, success, failed).
     * @return array Results with logs, total_pages, and counts.
     */
    private function get_login_history($user_id, $current_page, $filter) {
        global $wpdb;

        $table  = $wpdb->prefix . 'hikmah_login_logs';
        $offset = ($current_page - 1) * $this->per_page;

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return ['logs' => [], 'total_pages' => 0, 'counts' => []];
        }

        // Build WHERE clause
        $where = $wpdb->prepare("WHERE user_id = %d", $user_id);
        if ($filter !== 'all') {
            $where .= $wpdb->prepare(" AND status = %s", $filter);
        }

        // Get counts
        $counts = [];
        $counts['all'] = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id)
        );
        $counts['success'] = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'success'", $user_id)
        );
        $counts['failed'] = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status != 'success'", $user_id)
        );

        // Get total for current filter
        $total = $counts[$filter] ?? $counts['all'];
        $total_pages = ceil($total / $this->per_page);

        // Get logs
        $logs = $wpdb->get_results(
            "SELECT * FROM {$table} {$where} ORDER BY login_time DESC LIMIT {$this->per_page} OFFSET {$offset}"
        );

        return [
            'logs'        => $logs ?: [],
            'total_pages' => $total_pages,
            'counts'      => $counts,
        ];
    }

    /**
     * Parse user agent string
     *
     * @param string $ua User agent.
     * @return array Browser and icon info.
     */
    private function parse_user_agent($ua) {
        $info = [
            'browser' => __('Unknown', 'hikmah-login'),
            'icon'    => 'dashicons-desktop',
        ];

        if (empty($ua)) {
            return $info;
        }

        $browsers = [
            'Firefox' => '/Firefox/',
            'Chrome'  => '/Chrome/',
            'Safari'  => '/Safari/',
            'Edge'    => '/Edg/',
            'Opera'   => '/OPR/',
            'IE'      => '/MSIE|Trident/',
        ];

        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                $info['browser'] = $name;
                break;
            }
        }

        if (preg_match('/Mobile|Android|iPhone/', $ua)) {
            $info['icon'] = 'dashicons-smartphone';
        }

        return $info;
    }

    /**
     * Get approximate location from IP (basic implementation)
     *
     * @param string $ip IP address.
     * @return string Location or empty string.
     */
    private function get_ip_location($ip) {
        // Check cache first
        $cache_key = 'hikmah_ip_loc_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Skip local IPs
        if (in_array($ip, ['127.0.0.1', '::1']) || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
            return __('Local', 'hikmah-login');
        }

        // Use free IP geolocation API (with rate limiting consideration)
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=country,city", [
            'timeout' => 3,
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['city']) && !empty($body['country'])) {
            $location = $body['city'] . ', ' . $body['country'];
        } elseif (!empty($body['country'])) {
            $location = $body['country'];
        } else {
            $location = '';
        }

        // Cache for 7 days
        set_transient($cache_key, $location, 7 * DAY_IN_SECONDS);

        return $location;
    }

    /**
     * Mask IP address
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
     * AJAX: Load login history page
     *
     * @return void
     */
    public function ajax_load_history() {
        $user_id = get_current_user_id();
        $page    = max(1, intval($_POST['page'] ?? 1));
        $filter  = sanitize_key($_POST['filter'] ?? 'all');

        $result = $this->get_login_history($user_id, $page, $filter);

        ob_start();
        // Render table rows
        foreach ($result['logs'] as $log) {
            $device = $this->parse_user_agent($log->user_agent ?? '');
            $status_class = ($log->status === 'success') ? 'success' : 'failed';
            ?>
            <tr class="hikmah-tab-login-history__row hikmah-tab-login-history__row--<?php echo esc_attr($status_class); ?>">
                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->login_time))); ?></td>
                <td><code><?php echo esc_html($this->mask_ip($log->ip_address)); ?></code></td>
                <td><?php echo esc_html($device['browser']); ?></td>
                <td><?php echo esc_html($this->get_ip_location($log->ip_address) ?: __('Unknown', 'hikmah-login')); ?></td>
                <td>
                    <span class="hikmah-tab-login-history__status hikmah-tab-login-history__status--<?php echo esc_attr($status_class); ?>">
                        <?php echo $log->status === 'success' ? esc_html__('Success', 'hikmah-login') : esc_html__('Failed', 'hikmah-login'); ?>
                    </span>
                </td>
                <td><?php echo esc_html(ucfirst($log->login_method ?? 'standard')); ?></td>
            </tr>
            <?php
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html'        => $html,
            'total_pages' => $result['total_pages'],
            'current_page' => $page,
        ]);
    }
}
