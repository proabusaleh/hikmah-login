<?php
/**
 * Dashboard Account Tab
 *
 * Handles account deletion, data export (GDPR compliance),
 * and account-level settings.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Tab_Account {

    /**
     * Constructor
     */
    public function __construct() {
        // Form Fallbacks
        add_action('hikmah_dashboard_process_delete_account', [$this, 'process_delete_account']);
        add_action('hikmah_dashboard_process_export_data', [$this, 'process_export_data']);

        // AJAX Handlers
        add_action('wp_ajax_hikmah_dashboard_request_data_export', [$this, 'ajax_request_export']);
        add_action('wp_ajax_hikmah_dashboard_confirm_delete_account', [$this, 'ajax_confirm_delete']);

        // Secure File Download Handler
        add_action('init', [$this, 'process_download_export']);
    }

    /**
     * Render account tab
     *
     * @return void
     */
    public function render() {
        $user = wp_get_current_user();
        ?>

        <div class="hikmah-tab-account">

            <!-- Account Info Section -->
            <?php $this->render_account_info($user); ?>

            <!-- Data Export Section (GDPR) -->
            <?php $this->render_data_export_section($user); ?>

            <!-- Account Deletion Section (GDPR) -->
            <?php $this->render_delete_section($user); ?>

            <?php
            /**
             * Action to add custom account sections
             *
             * @param WP_User $user Current user.
             */
            do_action('hikmah_dashboard_account_sections', $user);
            ?>

        </div>

        <?php
    }

    /**
     * Render account information section
     *
     * @param WP_User $user Current user.
     * @return void
     */
    private function render_account_info($user) {
        ?>

        <div class="hikmah-tab-account__section">
            <div class="hikmah-tab-account__section-header">
                <h4 class="hikmah-tab-account__section-title">
                    <span class="dashicons dashicons-id-alt"></span>
                    <?php esc_html_e('Account Information', 'hikmah-login'); ?>
                </h4>
            </div>

            <div class="hikmah-tab-account__section-body">
                <table class="hikmah-tab-account__info-table">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e('Username', 'hikmah-login'); ?></th>
                            <td>
                                <code><?php echo esc_html($user->user_login); ?></code>
                                <span class="hikmah-form-description">
                                    <?php esc_html_e('Username cannot be changed.', 'hikmah-login'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Email', 'hikmah-login'); ?></th>
                            <td><?php echo esc_html($user->user_email); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Role', 'hikmah-login'); ?></th>
                            <td>
                                <?php
                                $roles = array_map(function ($role) {
                                    $wp_roles = wp_roles();
                                    return isset($wp_roles->role_names[$role])
                                        ? translate_user_role($wp_roles->role_names[$role])
                                        : ucfirst($role);
                                }, $user->roles);
                                echo esc_html(implode(', ', $roles));
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Registered', 'hikmah-login'); ?></th>
                            <td>
                                <?php
                                echo esc_html(date_i18n(
                                    get_option('date_format') . ' ' . get_option('time_format'),
                                    strtotime($user->user_registered)
                                ));
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('User ID', 'hikmah-login'); ?></th>
                            <td><code><?php echo esc_html($user->ID); ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
    }

    /**
     * Render data export section (GDPR Article 20 - Right to Data Portability)
     *
     * @param WP_User $user Current user.
     * @return void
     */
    private function render_data_export_section($user) {
        $pending_export = get_user_meta($user->ID, '_hikmah_data_export_requested', true);
        ?>

        <div class="hikmah-tab-account__section">
            <div class="hikmah-tab-account__section-header">
                <h4 class="hikmah-tab-account__section-title">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export Your Data', 'hikmah-login'); ?>
                </h4>
            </div>

            <div class="hikmah-tab-account__section-body">
                <p class="hikmah-tab-account__description">
                    <?php esc_html_e(
                        'You can request an export of your personal data. This includes your profile information, login history, and any other data associated with your account.',
                        'hikmah-login'
                    ); ?>
                </p>

                <?php if ($pending_export) : ?>
                    <div class="hikmah-tab-account__pending-notice">
                        <span class="dashicons dashicons-clock"></span>
                        <?php esc_html_e('A data export request is currently being processed. You will receive an email when it is ready.', 'hikmah-login'); ?>
                    </div>
                <?php else : ?>

                    <div class="hikmah-tab-account__export-options">
                        <h5><?php esc_html_e('Export includes:', 'hikmah-login'); ?></h5>
                        <ul class="hikmah-tab-account__export-list">
                            <li>
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e('Profile information', 'hikmah-login'); ?>
                            </li>
                            <li>
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e('Login history', 'hikmah-login'); ?>
                            </li>
                            <li>
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e('Connected social accounts', 'hikmah-login'); ?>
                            </li>
                            <li>
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e('Security settings', 'hikmah-login'); ?>
                            </li>
                        </ul>

                        <div class="hikmah-tab-account__export-format">
                            <label class="hikmah-form-label" for="hikmah-export-format">
                                <?php esc_html_e('Export format:', 'hikmah-login'); ?>
                            </label>
                            <select id="hikmah-export-format" class="hikmah-form-select hikmah-form-select--sm">
                                <option value="json">JSON</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>

                        <button type="button"
                                class="hikmah-btn hikmah-btn--secondary"
                                id="hikmah-request-export">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Request Data Export', 'hikmah-login'); ?>
                        </button>
                    </div>

                <?php endif; ?>
            </div>
        </div>

        <?php
    }

    /**
     * Render account deletion section (GDPR Article 17 - Right to Erasure)
     *
     * @param WP_User $user Current user.
     * @return void
     */
    private function render_delete_section($user) {
        $is_admin = in_array('administrator', $user->roles, true);
        ?>

        <div class="hikmah-tab-account__section hikmah-tab-account__section--danger">
            <div class="hikmah-tab-account__section-header">
                <h4 class="hikmah-tab-account__section-title hikmah-tab-account__section-title--danger">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Delete Account', 'hikmah-login'); ?>
                </h4>
            </div>

            <div class="hikmah-tab-account__section-body">

                <?php if ($is_admin) : ?>

                    <div class="hikmah-tab-account__admin-warning">
                        <span class="dashicons dashicons-shield"></span>
                        <p>
                            <?php esc_html_e(
                                'Administrator accounts cannot be deleted from the front-end dashboard for security reasons. Please use the WordPress admin panel.',
                                'hikmah-login'
                            ); ?>
                        </p>
                    </div>

                <?php else : ?>

                    <div class="hikmah-tab-account__danger-zone">
                        <p class="hikmah-tab-account__danger-description">
                            <?php esc_html_e(
                                'Permanently delete your account and all associated data. This action cannot be undone.',
                                'hikmah-login'
                            ); ?>
                        </p>

                        <div class="hikmah-tab-account__delete-consequences">
                            <h5><?php esc_html_e('What happens when you delete your account:', 'hikmah-login'); ?></h5>
                            <ul>
                                <li>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Your profile and personal information will be permanently removed', 'hikmah-login'); ?>
                                </li>
                                <li>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Your login history will be erased', 'hikmah-login'); ?>
                                </li>
                                <li>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('All connected social accounts will be disconnected', 'hikmah-login'); ?>
                                </li>
                                <li>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('You will be immediately logged out', 'hikmah-login'); ?>
                                </li>
                                <?php
                                /**
                                 * Action to add custom deletion consequence items
                                 *
                                 * @param WP_User $user Current user.
                                 */
                                do_action('hikmah_delete_account_consequences', $user);
                                ?>
                            </ul>
                        </div>

                        <button type="button"
                                class="hikmah-btn hikmah-btn--danger"
                                id="hikmah-delete-account-trigger">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Delete My Account', 'hikmah-login'); ?>
                        </button>

                        <!-- Delete Confirmation Modal -->
                        <div class="hikmah-modal" id="hikmah-delete-modal" style="display:none;">
                            <div class="hikmah-modal__overlay"></div>
                            <div class="hikmah-modal__content hikmah-modal__content--danger">

                                <div class="hikmah-modal__header">
                                    <h4>
                                        <span class="dashicons dashicons-warning"></span>
                                        <?php esc_html_e('Confirm Account Deletion', 'hikmah-login'); ?>
                                    </h4>
                                    <button type="button" class="hikmah-modal__close" id="hikmah-delete-modal-close">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </button>
                                </div>

                                <div class="hikmah-modal__body">
                                    <p>
                                        <?php esc_html_e('This is permanent and cannot be reversed. Please enter your password to confirm.', 'hikmah-login'); ?>
                                    </p>

                                    <div class="hikmah-form-group">
                                        <label for="hikmah-delete-password" class="hikmah-form-label">
                                            <?php esc_html_e('Your Password', 'hikmah-login'); ?>
                                        </label>
                                        <input type="password"
                                               id="hikmah-delete-password"
                                               class="hikmah-form-input"
                                               placeholder="<?php esc_attr_e('Enter your password', 'hikmah-login'); ?>"
                                               autocomplete="current-password" />
                                    </div>

                                    <div class="hikmah-form-group">
                                        <label for="hikmah-delete-confirm-text" class="hikmah-form-label">
                                            <?php
                                            printf(
                                                /* translators: %s: the confirmation text to type */
                                                esc_html__('Type %s to confirm:', 'hikmah-login'),
                                                '<strong>DELETE</strong>'
                                            );
                                            ?>
                                        </label>
                                        <input type="text"
                                               id="hikmah-delete-confirm-text"
                                               class="hikmah-form-input"
                                               placeholder="DELETE"
                                               autocomplete="off" />
                                    </div>

                                    <div class="hikmah-form-group">
                                        <label class="hikmah-form-checkbox">
                                            <input type="checkbox" id="hikmah-delete-understand" />
                                            <span class="hikmah-form-checkbox__label">
                                                <?php esc_html_e('I understand that this action is permanent and all my data will be deleted.', 'hikmah-login'); ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="hikmah-modal__footer">
                                    <button type="button"
                                            class="hikmah-btn hikmah-btn--secondary"
                                            id="hikmah-delete-cancel">
                                        <?php esc_html_e('Cancel', 'hikmah-login'); ?>
                                    </button>
                                    <button type="button"
                                            class="hikmah-btn hikmah-btn--danger"
                                            id="hikmah-delete-confirm"
                                            disabled>
                                        <span class="hikmah-btn__text">
                                            <?php esc_html_e('Permanently Delete Account', 'hikmah-login'); ?>
                                        </span>
                                        <span class="hikmah-btn__loading" style="display:none;">
                                            <span class="hikmah-spinner"></span>
                                            <?php esc_html_e('Deleting...', 'hikmah-login'); ?>
                                        </span>
                                    </button>
                                </div>

                            </div>
                        </div>

                    </div>

                <?php endif; ?>

            </div>
        </div>

        <?php
    }

    /**
     * AJAX: Request data export
     *
     * @return void
     */
    public function ajax_request_export() {
        check_ajax_referer('hikmah_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'hikmah-login')]);
        }

        $user_id = get_current_user_id();
        $format  = sanitize_key($_POST['format'] ?? 'json');

        if (!in_array($format, ['json', 'csv'], true)) {
            $format = 'json';
        }

        // Gather user data
        $export_data = $this->collect_user_data($user_id, $format);

        if ($format === 'json') {
            $content = wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $filename = 'hikmah-data-export-' . $user_id . '-' . current_time('Y-m-d') . '.json';
            $mime = 'application/json';
        } else {
            $content = $this->convert_to_csv($export_data);
            $filename = 'hikmah-data-export-' . $user_id . '-' . current_time('Y-m-d') . '.csv';
            $mime = 'text/csv';
        }

        // Save to temp file
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/hikmah-exports/';

        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
            // Add protection files
            file_put_contents($export_dir . '.htaccess', 'deny from all');
            file_put_contents($export_dir . 'index.php', '<?php // Silence is golden');
        }

        $filepath = $export_dir . $filename;
        file_put_contents($filepath, $content);

        // Generate a temporary download token
        $download_token = wp_generate_password(32, false);
        set_transient('hikmah_export_' . $download_token, [
            'user_id'  => $user_id,
            'filepath' => $filepath,
            'filename' => $filename,
            'mime'     => $mime,
        ], HOUR_IN_SECONDS);

        $download_url = add_query_arg([
            'hikmah_download_export' => $download_token,
            '_wpnonce'               => wp_create_nonce('hikmah_download_export'),
        ], home_url());

        /**
         * Action after data export is generated
         *
         * @param int    $user_id  User ID.
         * @param string $format   Export format.
         * @param string $filepath File path.
         */
        do_action('hikmah_data_export_generated', $user_id, $format, $filepath);

        wp_send_json_success([
            'download_url' => $download_url,
            'message'      => __('Your data export is ready for download.', 'hikmah-login'),
        ]);
    }

    /**
     * Intercept and process secure download tokens on init
     *
     * @return void
     */
    public function process_download_export() {
        if (!isset($_GET['hikmah_download_export'])) {
            return;
        }

        $token = sanitize_key($_GET['hikmah_download_export']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'hikmah_download_export')) {
            wp_die(esc_html__('Security check failed.', 'hikmah-login'), esc_html__('Unauthorized', 'hikmah-login'), ['response' => 403]);
        }

        if (!is_user_logged_in()) {
            wp_die(esc_html__('Please log in to download this export.', 'hikmah-login'), esc_html__('Unauthorized', 'hikmah-login'), ['response' => 403]);
        }

        $data = get_transient('hikmah_export_' . $token);

        if (!$data || !is_array($data)) {
            wp_die(esc_html__('This download token has expired or is invalid. Please request a new export.', 'hikmah-login'), esc_html__('Expired Link', 'hikmah-login'), ['response' => 410]);
        }

        // Verify that the downloader is the owner of the data
        if (get_current_user_id() !== (int) $data['user_id']) {
            wp_die(esc_html__('You do not have permission to download this file.', 'hikmah-login'), esc_html__('Unauthorized Access', 'hikmah-login'), ['response' => 403]);
        }

        if (!file_exists($data['filepath'])) {
            wp_die(esc_html__('Export file could not be found. Please request a new export.', 'hikmah-login'), esc_html__('File Not Found', 'hikmah-login'), ['response' => 404]);
        }

        // Delete temporary database transient immediately
        delete_transient('hikmah_export_' . $token);

        // Stream the file down securely
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $data['mime']);
        header('Content-Disposition: attachment; filename="' . basename($data['filename']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($data['filepath']));

        // Read file securely and avoid script buffer overhead
        readfile($data['filepath']);

        // Clean up physical file on server immediately for high security
        @unlink($data['filepath']);
        exit;
    }

    /**
     * Collect all user data for export
     *
     * @param int    $user_id User ID.
     * @param string $format  Export format.
     * @return array Collected data.
     */
    private function collect_user_data($user_id, $format = 'json') {
        $user = get_userdata($user_id);

        $data = [
            'profile' => [
                'username'     => $user->user_login,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'nickname'     => $user->nickname,
                'description'  => $user->description,
                'website'      => $user->user_url,
                'registered'   => $user->user_registered,
                'roles'        => $user->roles,
            ],
            'security' => [
                '2fa_enabled'          => (bool) get_user_meta($user_id, '_hikmah_2fa_enabled', true),
                'email_verified'       => (bool) get_user_meta($user_id, '_hikmah_email_verified', true),
                'password_last_changed' => get_user_meta($user_id, '_hikmah_password_last_changed', true),
            ],
            'social_connections' => [],
            'login_history'     => [],
        ];

        // Social connections
        $providers = ['google', 'facebook'];
        foreach ($providers as $provider) {
            $social_id = get_user_meta($user_id, '_hikmah_social_' . $provider . '_id', true);
            if ($social_id) {
                $data['social_connections'][] = [
                    'provider'     => $provider,
                    'connected_at' => get_user_meta($user_id, '_hikmah_social_' . $provider . '_connected_at', true),
                ];
            }
        }

        // Login history
        global $wpdb;
        $table = $wpdb->prefix . 'hikmah_login_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            $logs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT login_time, ip_address, status, login_method, user_agent 
                     FROM {$table} WHERE user_id = %d ORDER BY login_time DESC LIMIT 1000",
                    $user_id
                ),
                ARRAY_A
            );
            $data['login_history'] = $logs ?: [];
        }

        // Custom user meta
        $custom_meta = [];
        $all_meta = get_user_meta($user_id);
        foreach ($all_meta as $key => $values) {
            if (strpos($key, '_hikmah_') === 0) {
                // Skip sensitive crypt keys
                $skip_keys = ['_hikmah_2fa_secret', '_hikmah_2fa_backup_codes', '_hikmah_2fa_temp_secret'];
                if (!in_array($key, $skip_keys, true)) {
                    $custom_meta[$key] = $values[0] ?? '';
                }
            }
        }
        $data['plugin_metadata'] = $custom_meta;

        /**
         * Filter export data before generation
         *
         * @param array $data    Export data.
         * @param int   $user_id User ID.
         */
        return apply_filters('hikmah_export_data', $data, $user_id);
    }

    /**
     * Convert data array to CSV format
     *
     * @param array $data Data to convert.
     * @return string CSV content.
     */
    private function convert_to_csv($data) {
        $output = fopen('php://temp', 'r+');

        // Profile section
        fputcsv($output, ['=== Profile ===']);
        fputcsv($output, ['Field', 'Value']);
        foreach ($data['profile'] as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            fputcsv($output, [$key, $value]);
        }

        fputcsv($output, []); // Empty row

        // Security section
        fputcsv($output, ['=== Security ===']);
        fputcsv($output, ['Field', 'Value']);
        foreach ($data['security'] as $key => $value) {
            fputcsv($output, [$key, is_bool($value) ? ($value ? 'Yes' : 'No') : $value]);
        }

        fputcsv($output, []);

        // Login History
        if (!empty($data['login_history'])) {
            fputcsv($output, ['=== Login History ===']);
            fputcsv($output, array_keys($data['login_history'][0]));
            foreach ($data['login_history'] as $row) {
                fputcsv($output, array_values($row));
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        // Add BOM for UTF-8 compatibility with Excel
        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * AJAX: Confirm and process account deletion
     *
     * @return void
     */
    public function ajax_confirm_delete() {
        check_ajax_referer('hikmah_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'hikmah-login')]);
        }

        $user_id  = get_current_user_id();
        $user     = get_userdata($user_id);
        $password = $_POST['password'] ?? '';

        // Block administrators from front-end suicide deletion
        if (in_array('administrator', $user->roles, true)) {
            wp_send_json_error([
                'message' => __('Administrator accounts cannot be deleted from here.', 'hikmah-login'),
            ]);
        }

        // Verify password confirmation
        if (!wp_check_password($password, $user->user_pass, $user_id)) {
            wp_send_json_error([
                'message' => __('Incorrect password.', 'hikmah-login'),
            ]);
        }

        /**
         * Action before account deletion
         *
         * @param int     $user_id User ID.
         * @param WP_User $user    User object.
         */
        do_action('hikmah_before_account_deletion', $user_id, $user);

        // Delete plugin database login logs
        global $wpdb;
        $table = $wpdb->prefix . 'hikmah_login_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            $wpdb->delete($table, ['user_id' => $user_id], ['%d']);
        }

        // Delete custom uploaded profile avatars
        $avatar_id = get_user_meta($user_id, '_hikmah_custom_avatar', true);
        if ($avatar_id) {
            wp_delete_attachment($avatar_id, true);
        }

        // Delete all plugin metadata matching namespace prefix
        $all_meta = get_user_meta($user_id);
        foreach ($all_meta as $key => $values) {
            if (strpos($key, '_hikmah_') === 0) {
                delete_user_meta($user_id, $key);
            }
        }

        // Purge export files left on system
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/hikmah-exports/';
        $export_files = glob($export_dir . '*-' . $user_id . '-*');
        if ($export_files) {
            foreach ($export_files as $file) {
                @unlink($file);
            }
        }

        // Configure dynamic post deletion assignment
        $reassign_to = hikmah_get_option('delete_account_reassign_to', 0);
        if (empty($reassign_to)) {
            $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ids']);
            $reassign_to = !empty($admins) ? $admins[0] : null;
        }

        // Trace deletion event before session invalidation
        if (class_exists('Hikmah_Login_Logger')) {
            Hikmah_Login_Logger::log([
                'event'   => 'account_deleted',
                'user_id' => $user_id,
                'email'   => $user->user_email,
                'ip'      => hikmah_get_client_ip(),
            ]);
        }

        // Invalidate and purge session active sessions
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();

        // Delete the core WordPress user account
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = wp_delete_user($user_id, $reassign_to);

        if (!$deleted) {
            wp_send_json_error([
                'message' => __('Failed to delete account. Please contact support.', 'hikmah-login'),
            ]);
        }

        /**
         * Action after account is deleted
         *
         * @param int    $user_id User ID (now deleted).
         * @param string $email   User email.
         */
        do_action('hikmah_account_deleted', $user_id, $user->user_email);

        // Send email receipt verifying deletion
        $this->send_deletion_confirmation_email($user->user_email, $user->display_name);

        wp_send_json_success([
            'message'      => __('Your account has been permanently deleted.', 'hikmah-login'),
            'redirect_url' => home_url(),
        ]);
    }

    /**
     * Send account deletion confirmation email
     *
     * @param string $email        User email.
     * @param string $display_name User display name.
     * @return void
     */
    private function send_deletion_confirmation_email($email, $display_name) {
        $site_name = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Account Deleted', 'hikmah-login'),
            $site_name
        );

        $message = sprintf(
            /* translators: 1: user name, 2: site name */
            __(
                "Hello %1\$s,\n\n" .
                "This email confirms that your account on %2\$s has been permanently deleted.\n\n" .
                "All your personal data has been removed from our system.\n\n" .
                "If you did not request this deletion, please contact us immediately.\n\n" .
                "Thank you,\n%2\$s",
                'hikmah-login'
            ),
            $display_name,
            $site_name
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Process data export form submission (non-AJAX fallback)
     *
     * @return void
     */
    public function process_export_data() {
        if (!wp_verify_nonce($_POST['_hikmah_export_nonce'] ?? '', 'hikmah_export_data')) {
            return;
        }

        $user_id = get_current_user_id();
        $format  = sanitize_key($_POST['export_format'] ?? 'json');

        $data = $this->collect_user_data($user_id, $format);

        if ($format === 'json') {
            $content = wp_json_encode($data, JSON_PRETTY_PRINT);
            $filename = 'data-export.json';
            header('Content-Type: application/json');
        } else {
            $content = $this->convert_to_csv($data);
            $filename = 'data-export.csv';
            header('Content-Type: text/csv');
        }

        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    /**
     * Process account deletion form submission (non-AJAX fallback)
     *
     * @return void
     */
    public function process_delete_account() {
        Hikmah_Dashboard::set_notice('info', __('Please use the delete button on the Account page.', 'hikmah-login'));
    }
}
