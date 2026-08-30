<?php
/**
 * Admin Settings Panel
 *
 * Implements settings registration, page rendering,
 * and comprehensive sanitization callbacks.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Admin_Settings {

    /**
     * Option group name
     *
     * @var string
     */
    private $option_group = 'hikmah_login_settings_group';

    /**
     * Option name
     *
     * @var string
     */
    private $option_name = 'hikmah_login_options';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add menu item to WordPress dashboard
     *
     * @return void
     */
    public function add_settings_menu() {
        add_options_page(
            __('Hikmah Login Settings', 'hikmah-login'),
            __('Hikmah Login', 'hikmah-login'),
            'manage_options',
            'hikmah-login-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings, sections, and fields
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => $this->get_defaults(),
            ]
        );

        // General Section
        add_settings_section(
            'hikmah_section_general',
            __('General Settings', 'hikmah-login'),
            null,
            'hikmah-login-settings'
        );

        add_settings_field(
            'login_logo',
            __('Login Logo URL', 'hikmah-login'),
            [$this, 'render_field_text_image'],
            'hikmah-login-settings',
            'hikmah_section_general',
            ['key' => 'login_logo', 'desc' => __('Provide an absolute URL for a custom brand logo displayed on login forms.', 'hikmah-login')]
        );

        add_settings_field(
            'login_redirect',
            __('Login Redirect URL', 'hikmah-login'),
            [$this, 'render_field_text'],
            'hikmah-login-settings',
            'hikmah_section_general',
            ['key' => 'login_redirect', 'desc' => __('Default URL to redirect users to after successful authentication.', 'hikmah-login')]
        );

        // Security Section
        add_settings_section(
            'hikmah_section_security',
            __('Security & 2FA Settings', 'hikmah-login'),
            null,
            'hikmah-login-settings'
        );

        add_settings_field(
            'enable_2fa_globally',
            __('Force Two-Factor Authentication', 'hikmah-login'),
            [$this, 'render_field_checkbox'],
            'hikmah-login-settings',
            'hikmah_section_security',
            ['key' => 'enable_2fa_globally', 'label' => __('Require all non-administrator users to set up 2FA.', 'hikmah-login')]
        );

        add_settings_field(
            'limit_attempts',
            __('Limit Login Attempts', 'hikmah-login'),
            [$this, 'render_field_checkbox'],
            'hikmah-login-settings',
            'hikmah_section_security',
            ['key' => 'limit_attempts', 'label' => __('Protect forms against brute force attacks.', 'hikmah-login')]
        );

        add_settings_field(
            'max_login_attempts',
            __('Max Login Attempts Allowed', 'hikmah-login'),
            [$this, 'render_field_number'],
            'hikmah-login-settings',
            'hikmah_section_security',
            ['key' => 'max_login_attempts', 'min' => 1, 'max' => 10, 'desc' => __('Number of allowable failures before blocking user temporary IP access.', 'hikmah-login')]
        );

        add_settings_field(
            'lockout_duration',
            __('Lockout Duration (Minutes)', 'hikmah-login'),
            [$this, 'render_field_number'],
            'hikmah-login-settings',
            'hikmah_section_security',
            ['key' => 'lockout_duration', 'min' => 5, 'max' => 1440, 'desc' => __('Minutes blocked IP addresses remain locked out of registration interfaces.', 'hikmah-login')]
        );

        // GDPR Section
        add_settings_section(
            'hikmah_section_gdpr',
            __('GDPR Data Compliance', 'hikmah-login'),
            null,
            'hikmah-login-settings'
        );

        add_settings_field(
            'allow_self_deletion',
            __('Allow Self-Deletion', 'hikmah-login'),
            [$this, 'render_field_checkbox'],
            'hikmah-login-settings',
            'hikmah_section_gdpr',
            ['key' => 'allow_self_deletion', 'label' => __('Enable front-end user self-deletion mechanisms (Right to Erasure).', 'hikmah-login')]
        );

        add_settings_field(
            'delete_account_reassign_to',
            __('Reassign Deleted User Content', 'hikmah-login'),
            [$this, 'render_field_user_select'],
            'hikmah-login-settings',
            'hikmah_section_gdpr',
            ['key' => 'delete_account_reassign_to', 'desc' => __('User account to which posts and media should be attributed after user self-deletes.', 'hikmah-login')]
        );
    }

    /**
     * Settings defaults values
     *
     * @return array
     */
    private function get_defaults() {
        return [
            'login_logo'                  => '',
            'login_redirect'              => '',
            'enable_2fa_globally'         => '0',
            'limit_attempts'              => '1',
            'max_login_attempts'          => 5,
            'lockout_duration'            => 60,
            'allow_self_deletion'         => '1',
            'delete_account_reassign_to'  => '0',
        ];
    }

    /**
     * Clean and sanitize option arrays
     *
     * @param array $input Original array raw inputs.
     * @return array Cleaned values.
     */
    public function sanitize_settings($input) {
        $output = $this->get_defaults();

        if (isset($input['login_logo'])) {
            $output['login_logo'] = esc_url_raw(trim($input['login_logo']));
        }

        if (isset($input['login_redirect'])) {
            $output['login_redirect'] = esc_url_raw(trim($input['login_redirect']));
        }

        $output['enable_2fa_globally'] = isset($input['enable_2fa_globally']) ? '1' : '0';
        $output['limit_attempts']      = isset($input['limit_attempts']) ? '1' : '0';

        if (isset($input['max_login_attempts'])) {
            $val = intval($input['max_login_attempts']);
            $output['max_login_attempts'] = ($val >= 1 && $val <= 20) ? $val : 5;
        }

        if (isset($input['lockout_duration'])) {
            $val = intval($input['lockout_duration']);
            $output['lockout_duration'] = ($val >= 5 && $val <= 10080) ? $val : 60;
        }

        $output['allow_self_deletion'] = isset($input['allow_self_deletion']) ? '1' : '0';

        if (isset($input['delete_account_reassign_to'])) {
            $user_id = intval($input['delete_account_reassign_to']);
            $output['delete_account_reassign_to'] = (get_userdata($user_id) || $user_id === 0) ? $user_id : 0;
        }

        return $output;
    }

    // ─────────────────────────────────────────────
    // RENDER FIELDS
    // ─────────────────────────────────────────────

    public function render_field_text($args) {
        $options = get_option($this->option_name, $this->get_defaults());
        $value = esc_attr($options[$args['key']] ?? '');
        printf(
            '<input type="text" name="%1$s[%2$s]" id="%2$s" value="%3$s" class="regular-text" />',
            esc_attr($this->option_name),
            esc_attr($args['key']),
            $value
        );
        if (isset($args['desc'])) {
            printf('<p class="description">%s</p>', esc_html($args['desc']));
        }
    }

    public function render_field_text_image($args) {
        $options = get_option($this->option_name, $this->get_defaults());
        $value = esc_attr($options[$args['key']] ?? '');
        printf(
            '<input type="text" name="%1$s[%2$s]" id="%2$s" value="%3$s" class="regular-text" />',
            esc_attr($this->option_name),
            esc_attr($args['key']),
            $value
        );
        if ($value) {
            printf('<div style="margin-top:10px;"><img src="%s" style="max-height:80px; width:auto; border:1px solid #ccd0d4; padding:5px; background:#fff;" /></div>', $value);
        }
        if (isset($args['desc'])) {
            printf('<p class="description">%s</p>', esc_html($args['desc']));
        }
    }

    public function render_field_checkbox($args) {
        $options = get_option($this->option_name, $this->get_defaults());
        $checked = isset($options[$args['key']]) && $options[$args['key']] === '1' ? 'checked="checked"' : '';
        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" id="%2$s" value="1" %3$s /> %4$s</label>',
            esc_attr($this->option_name),
            esc_attr($args['key']),
            $checked,
            esc_html($args['label'] ?? '')
        );
    }

    public function render_field_number($args) {
        $options = get_option($this->option_name, $this->get_defaults());
        $value = intval($options[$args['key']] ?? 0);
        printf(
            '<input type="number" name="%1$s[%2$s]" id="%2$s" value="%3$d" min="%4$d" max="%5$d" class="small-text" />',
            esc_attr($this->option_name),
            esc_attr($args['key']),
            $value,
            intval($args['min'] ?? 0),
            intval($args['max'] ?? 100)
        );
        if (isset($args['desc'])) {
            printf('<span class="description" style="margin-left: 10px;">%s</span>', esc_html($args['desc']));
        }
    }

    public function render_field_user_select($args) {
        $options = get_option($this->option_name, $this->get_defaults());
        $selected = intval($options[$args['key']] ?? 0);
        
        $admins = get_users(['role' => 'administrator', 'fields' => ['ID', 'user_login', 'user_email']]);
        
        printf('<select name="%1$s[%2$s]" id="%2$s">', esc_attr($this->option_name), esc_attr($args['key']));
        printf('<option value="0" %s>%s</option>', selected($selected, 0, false), esc_html__('— Select Administrator —', 'hikmah-login'));
        
        foreach ($admins as $admin) {
            printf(
                '<option value="%1$d" %2$s>%3$s (%4$s)</option>',
                intval($admin->ID),
                selected($selected, $admin->ID, false),
                esc_html($admin->user_login),
                esc_html($admin->user_email)
            );
        }
        echo '</select>';
        if (isset($args['desc'])) {
            printf('<p class="description">%s</p>', esc_html($args['desc']));
        }
    }

    /**
     * Output options page wrapper
     *
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post" style="max-width: 800px; background: #fff; padding: 20px 30px; margin-top: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('hikmah-login-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
