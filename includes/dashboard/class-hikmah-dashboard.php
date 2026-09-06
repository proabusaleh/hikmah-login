<?php
/**
 * Hikmah Dashboard Core
 * 
 * Main dashboard controller that handles template loading,
 * tab registration, widget management, and routing.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Dashboard {

    /**
     * Singleton instance
     *
     * @var Hikmah_Dashboard|null
     */
    private static $instance = null;

    /**
     * Registered dashboard tabs
     *
     * @var array
     */
    private $tabs = [];

    /**
     * Registered dashboard widgets
     *
     * @var array
     */
    private $widgets = [];

    /**
     * Current active tab slug
     *
     * @var string
     */
    private $current_tab = '';

    /**
     * Dashboard page slug
     *
     * @var string
     */
    private $page_slug = 'hikmah-dashboard';

    /**
     * Template path
     *
     * @var string
     */
    private $template_path = '';

    /**
     * Get singleton instance
     *
     * @return Hikmah_Dashboard
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->template_path = HIKMAH_LOGIN_PATH . 'templates/dashboard/';
        
        $this->init_hooks();
        $this->register_default_tabs();
    }

    /**
     * Initialize all hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Shortcode registration
        add_shortcode('hikmah_dashboard', [$this, 'render_dashboard_shortcode']);

        // Enqueue dashboard assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);

        // AJAX handlers for dashboard actions
        add_action('wp_ajax_hikmah_dashboard_action', [$this, 'handle_ajax_action']);

        // Process form submissions
        add_action('init', [$this, 'process_form_submissions']);

        // Allow other plugins/themes to register tabs early
        add_action('init', [$this, 'allow_external_tab_registration'], 20);

        // Dashboard page body class
        add_filter('body_class', [$this, 'add_body_class']);
    }

    /**
     * Register all default dashboard tabs
     *
     * @return void
     */
    private function register_default_tabs() {
        // Overview / Home tab
        $this->register_tab([
            'slug'     => 'overview',
            'title'    => __('Overview', 'hikmah-login'),
            'icon'     => 'dashicons-dashboard',
            'callback' => [$this, 'render_tab_overview'],
            'priority' => 10,
            'cap'      => 'read',
        ]);

        // Profile tab
        $this->register_tab([
            'slug'     => 'profile',
            'title'    => __('Profile', 'hikmah-login'),
            'icon'     => 'dashicons-admin-users',
            'callback' => [$this, 'render_tab_profile'],
            'priority' => 20,
            'cap'      => 'read',
        ]);

        // Security tab
        $this->register_tab([
            'slug'     => 'security',
            'title'    => __('Security', 'hikmah-login'),
            'icon'     => 'dashicons-shield',
            'callback' => [$this, 'render_tab_security'],
            'priority' => 30,
            'cap'      => 'read',
        ]);

        // Sessions tab
        $this->register_tab([
            'slug'     => 'sessions',
            'title'    => __('Sessions', 'hikmah-login'),
            'icon'     => 'dashicons-laptop',
            'callback' => [$this, 'render_tab_sessions'],
            'priority' => 40,
            'cap'      => 'read',
        ]);

        // Login History tab
        $this->register_tab([
            'slug'     => 'login-history',
            'title'    => __('Login History', 'hikmah-login'),
            'icon'     => 'dashicons-backup',
            'callback' => [$this, 'render_tab_login_history'],
            'priority' => 50,
            'cap'      => 'read',
        ]);

        // Connected Accounts tab
        $this->register_tab([
            'slug'     => 'connected-accounts',
            'title'    => __('Connected Accounts', 'hikmah-login'),
            'icon'     => 'dashicons-share',
            'callback' => [$this, 'render_tab_connected_accounts'],
            'priority' => 60,
            'cap'      => 'read',
        ]);

        // Account tab (deletion, export, GDPR)
        $this->register_tab([
            'slug'     => 'account',
            'title'    => __('Account', 'hikmah-login'),
            'icon'     => 'dashicons-admin-settings',
            'callback' => [$this, 'render_tab_account'],
            'priority' => 100,
            'cap'      => 'read',
        ]);
    }

    /**
     * Register a dashboard tab
     *
     * @param array $args {
     *     Tab configuration.
     *
     *     @type string   $slug     Unique tab identifier.
     *     @type string   $title    Display title.
     *     @type string   $icon     Dashicon class or SVG.
     *     @type callable $callback Render callback function.
     *     @type int      $priority Sort order (lower = first).
     *     @type string   $cap      Required capability.
     *     @type string   $badge    Optional badge text/count.
     * }
     * @return bool True if registered successfully.
     */
    public function register_tab($args) {
        $defaults = [
            'slug'     => '',
            'title'    => '',
            'icon'     => 'dashicons-admin-generic',
            'callback' => null,
            'priority' => 50,
            'cap'      => 'read',
            'badge'    => '',
        ];

        $tab = wp_parse_args($args, $defaults);

        // Validate required fields
        if (empty($tab['slug']) || empty($tab['title']) || !is_callable($tab['callback'])) {
            return false;
        }

        // Sanitize slug
        $tab['slug'] = sanitize_key($tab['slug']);

        /**
         * Filter tab arguments before registration
         *
         * @param array  $tab  Tab configuration array.
         * @param string $slug Tab slug.
         */
        $tab = apply_filters('hikmah_dashboard_register_tab', $tab, $tab['slug']);

        $this->tabs[$tab['slug']] = $tab;

        return true;
    }

    /**
     * Unregister a dashboard tab
     *
     * @param string $slug Tab slug to remove.
     * @return bool
     */
    public function unregister_tab($slug) {
        $slug = sanitize_key($slug);

        if (isset($this->tabs[$slug])) {
            unset($this->tabs[$slug]);
            return true;
        }

        return false;
    }

    /**
     * Get all registered tabs sorted by priority
     *
     * @return array Sorted tabs array.
     */
    public function get_tabs() {
        $tabs = $this->tabs;

        // Remove tabs user doesn't have capability for
        $user = wp_get_current_user();
        foreach ($tabs as $slug => $tab) {
            if (!empty($tab['cap']) && !current_user_can($tab['cap'])) {
                unset($tabs[$slug]);
            }
        }

        /**
         * Filter all dashboard tabs
         *
         * @param array   $tabs All registered tabs.
         * @param WP_User $user Current user object.
         */
        $tabs = apply_filters('hikmah_dashboard_tabs', $tabs, $user);

        // Sort by priority
        uasort($tabs, function ($a, $b) {
            return ($a['priority'] ?? 50) - ($b['priority'] ?? 50);
        });

        return $tabs;
    }

    /**
     * Get the current active tab
     *
     * @return string Current tab slug.
     */
    public function get_current_tab() {
        if (!empty($this->current_tab)) {
            return $this->current_tab;
        }

        $tabs = $this->get_tabs();

        // Check URL parameter
        $requested_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';

        if (!empty($requested_tab) && isset($tabs[$requested_tab])) {
            $this->current_tab = $requested_tab;
        } else {
            // Default to first tab
            $tab_keys = array_keys($tabs);
            $this->current_tab = !empty($tab_keys) ? $tab_keys[0] : 'overview';
        }

        return $this->current_tab;
    }

    /**
     * Allow external plugins/themes to register tabs
     *
     * @return void
     */
    public function allow_external_tab_registration() {
        /**
         * Action to register custom dashboard tabs
         *
         * @param Hikmah_Dashboard $dashboard Dashboard instance.
         */
        do_action('hikmah_dashboard_register_tabs', $this);
    }

    /**
     * Render the dashboard shortcode
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content.
     * @return string HTML output.
     */
    public function render_dashboard_shortcode($atts = [], $content = '') {
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_required_message();
        }

        $atts = shortcode_atts([
            'default_tab' => 'overview',
            'show_header' => 'yes',
            'show_sidebar' => 'yes',
            'theme'        => '',
        ], $atts, 'hikmah_dashboard');

        // Set default tab from shortcode attribute
        if (empty($_GET['tab'])) {
            $this->current_tab = sanitize_key($atts['default_tab']);
        }

        ob_start();

        /**
         * Action before dashboard renders
         *
         * @param array $atts Shortcode attributes.
         */
        do_action('hikmah_before_dashboard', $atts);

        $this->render_dashboard($atts);

        /**
         * Action after dashboard renders
         *
         * @param array $atts Shortcode attributes.
         */
        do_action('hikmah_after_dashboard', $atts);

        return ob_get_clean();
    }

    /**
     * Render the complete dashboard
     *
     * @param array $atts Configuration attributes.
     * @return void
     */
    private function render_dashboard($atts) {
        $user        = wp_get_current_user();
        $tabs        = $this->get_tabs();
        $current_tab = $this->get_current_tab();
        $show_header = ($atts['show_header'] === 'yes');
        $show_sidebar = ($atts['show_sidebar'] === 'yes');
        $theme_class = !empty($atts['theme']) ? 'hikmah-dashboard--' . sanitize_html_class($atts['theme']) : '';

        $dashboard_classes = [
            'hikmah-dashboard',
            $theme_class,
        ];

        /**
         * Filter dashboard wrapper CSS classes
         *
         * @param array $dashboard_classes CSS classes.
         * @param array $atts             Shortcode attributes.
         */
        $dashboard_classes = apply_filters('hikmah_dashboard_classes', $dashboard_classes, $atts);
        ?>

        <div class="<?php echo esc_attr(implode(' ', array_filter($dashboard_classes))); ?>"
             data-current-tab="<?php echo esc_attr($current_tab); ?>">

            <?php if ($show_header) : ?>
                <?php $this->render_dashboard_header($user); ?>
            <?php endif; ?>

            <div class="hikmah-dashboard__body">

                <?php if ($show_sidebar) : ?>
                    <aside class="hikmah-dashboard__sidebar">
                        <?php $this->render_sidebar_navigation($tabs, $current_tab); ?>
                    </aside>
                <?php endif; ?>

                <main class="hikmah-dashboard__content">
                    <?php $this->render_notices(); ?>
                    <?php $this->render_tab_content($current_tab); ?>
                </main>

            </div>

            <?php $this->render_dashboard_footer(); ?>

        </div>

        <?php
    }

    /**
     * Render dashboard header with user info
     *
     * @param WP_User $user Current user.
     * @return void
     */
    private function render_dashboard_header($user) {
        $avatar_url = $this->get_user_avatar_url($user->ID, 80);
        $display_name = $user->display_name ?: $user->user_login;
        $member_since = date_i18n(get_option('date_format'), strtotime($user->user_registered));
        ?>

        <header class="hikmah-dashboard__header">
            <div class="hikmah-dashboard__header-inner">

                <div class="hikmah-dashboard__user-info">
                    <div class="hikmah-dashboard__avatar">
                        <img src="<?php echo esc_url($avatar_url); ?>"
                             alt="<?php echo esc_attr($display_name); ?>"
                             class="hikmah-dashboard__avatar-img" />
                    </div>
                    <div class="hikmah-dashboard__user-meta">
                        <h2 class="hikmah-dashboard__user-name">
                            <?php
                            printf(
                                /* translators: %s: user display name */
                                esc_html__('Welcome, %s', 'hikmah-login'),
                                esc_html($display_name)
                            );
                            ?>
                        </h2>
                        <p class="hikmah-dashboard__user-email">
                            <?php echo esc_html($user->user_email); ?>
                        </p>
                        <p class="hikmah-dashboard__member-since">
                            <?php
                            printf(
                                /* translators: %s: registration date */
                                esc_html__('Member since %s', 'hikmah-login'),
                                esc_html($member_since)
                            );
                            ?>
                        </p>
                    </div>
                </div>

                <div class="hikmah-dashboard__header-actions">
                    <?php
                    /**
                     * Action to add custom header buttons/actions
                     *
                     * @param WP_User $user Current user.
                     */
                    do_action('hikmah_dashboard_header_actions', $user);
                    ?>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>"
                       class="hikmah-btn hikmah-btn--outline hikmah-btn--sm">
                        <span class="dashicons dashicons-exit"></span>
                        <?php esc_html_e('Logout', 'hikmah-login'); ?>
                    </a>
                </div>

            </div>
        </header>

        <?php
    }

    /**
     * Render sidebar navigation
     *
     * @param array  $tabs        All registered tabs.
     * @param string $current_tab Currently active tab.
     * @return void
     */
    private function render_sidebar_navigation($tabs, $current_tab) {
        ?>
        <nav class="hikmah-dashboard__nav" role="navigation"
             aria-label="<?php esc_attr_e('Dashboard Navigation', 'hikmah-login'); ?>">

            <ul class="hikmah-dashboard__nav-list">
                <?php foreach ($tabs as $slug => $tab) : ?>
                    <?php
                    $is_active = ($slug === $current_tab);
                    $tab_url = add_query_arg('tab', $slug);
                    $item_classes = [
                        'hikmah-dashboard__nav-item',
                        $is_active ? 'hikmah-dashboard__nav-item--active' : '',
                    ];
                    ?>
                    <li class="<?php echo esc_attr(implode(' ', array_filter($item_classes))); ?>">
                        <a href="<?php echo esc_url($tab_url); ?>"
                           class="hikmah-dashboard__nav-link"
                           <?php echo $is_active ? 'aria-current="page"' : ''; ?>>

                            <?php if (!empty($tab['icon'])) : ?>
                                <span class="hikmah-dashboard__nav-icon <?php echo esc_attr($tab['icon']); ?>"></span>
                            <?php endif; ?>

                            <span class="hikmah-dashboard__nav-text">
                                <?php echo esc_html($tab['title']); ?>
                            </span>

                            <?php if (!empty($tab['badge'])) : ?>
                                <span class="hikmah-dashboard__nav-badge">
                                    <?php echo esc_html($tab['badge']); ?>
                                </span>
                            <?php endif; ?>

                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php
            /**
             * Action after sidebar navigation items
             */
            do_action('hikmah_dashboard_after_nav');
            ?>

        </nav>
        <?php
    }

    /**
     * Render current tab content
     *
     * @param string $current_tab Tab slug to render.
     * @return void
     */
    private function render_tab_content($current_tab) {
        $tabs = $this->get_tabs();

        if (!isset($tabs[$current_tab])) {
            echo '<div class="hikmah-dashboard__tab-not-found">';
            esc_html_e('Tab not found.', 'hikmah-login');
            echo '</div>';
            return;
        }

        $tab = $tabs[$current_tab];

        /**
         * Action before tab content renders
         *
         * @param string $current_tab Tab slug.
         * @param array  $tab         Tab configuration.
         */
        do_action('hikmah_before_dashboard_tab', $current_tab, $tab);

        echo '<div class="hikmah-dashboard__tab-panel" id="hikmah-tab-' . esc_attr($current_tab) . '">';

        // Tab title
        echo '<div class="hikmah-dashboard__tab-header">';
        echo '<h3 class="hikmah-dashboard__tab-title">' . esc_html($tab['title']) . '</h3>';

        /**
         * Action for tab-specific header actions
         *
         * @param string $current_tab Tab slug.
         */
        do_action('hikmah_dashboard_tab_actions_' . $current_tab);

        echo '</div>';

        // Render tab content via callback
        if (is_callable($tab['callback'])) {
            call_user_func($tab['callback']);
        }

        echo '</div>';

        /**
         * Action after tab content renders
         *
         * @param string $current_tab Tab slug.
         * @param array  $tab         Tab configuration.
         */
        do_action('hikmah_after_dashboard_tab', $current_tab, $tab);
    }

    /**
     * Render dashboard footer
     *
     * @return void
     */
    private function render_dashboard_footer() {
        ?>
        <footer class="hikmah-dashboard__footer">
            <?php
            /**
             * Action for dashboard footer content
             */
            do_action('hikmah_dashboard_footer');
            ?>
        </footer>
        <?php
    }

    /**
     * Render notice messages (success, error, info)
     *
     * @return void
     */
    private function render_notices() {
        // Check for URL-based notices
        $notice_type = isset($_GET['notice']) ? sanitize_key($_GET['notice']) : '';
        $notice_msg  = isset($_GET['message']) ? sanitize_text_field(urldecode($_GET['message'])) : '';

        // Check session-based notices
        if (empty($notice_type) && isset($_SESSION['hikmah_dashboard_notice'])) {
            $notice_type = sanitize_key($_SESSION['hikmah_dashboard_notice']['type'] ?? '');
            $notice_msg  = sanitize_text_field($_SESSION['hikmah_dashboard_notice']['message'] ?? '');
            unset($_SESSION['hikmah_dashboard_notice']);
        }

        if (empty($notice_type) || empty($notice_msg)) {
            return;
        }

        $allowed_types = ['success', 'error', 'warning', 'info'];
        if (!in_array($notice_type, $allowed_types, true)) {
            $notice_type = 'info';
        }

        $icons = [
            'success' => 'dashicons-yes-alt',
            'error'   => 'dashicons-dismiss',
            'warning' => 'dashicons-warning',
            'info'    => 'dashicons-info',
        ];

        ?>
        <div class="hikmah-dashboard__notice hikmah-dashboard__notice--<?php echo esc_attr($notice_type); ?>"
             role="alert">
            <span class="dashicons <?php echo esc_attr($icons[$notice_type]); ?>"></span>
            <span class="hikmah-dashboard__notice-text"><?php echo esc_html($notice_msg); ?></span>
            <button type="button" class="hikmah-dashboard__notice-dismiss" aria-label="<?php esc_attr_e('Dismiss', 'hikmah-login'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <?php
    }

    /**
     * Render login required message for non-logged-in users
     *
     * @return string HTML output.
     */
    private function render_login_required_message() {
        $login_url = hikmah_get_login_url();

        ob_start();
        ?>
        <div class="hikmah-dashboard__login-required">
            <div class="hikmah-dashboard__login-required-inner">
                <span class="dashicons dashicons-lock"></span>
                <h3><?php esc_html_e('Login Required', 'hikmah-login'); ?></h3>
                <p><?php esc_html_e('You must be logged in to access your dashboard.', 'hikmah-login'); ?></p>
                <a href="<?php echo esc_url($login_url); ?>" class="hikmah-btn hikmah-btn--primary">
                    <?php esc_html_e('Login Now', 'hikmah-login'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get user avatar URL with custom avatar support
     *
     * @param int $user_id User ID.
     * @param int $size    Avatar size in pixels.
     * @return string Avatar URL.
     */
    public function get_user_avatar_url($user_id, $size = 96) {
        // Check for custom uploaded avatar first
        $custom_avatar = get_user_meta($user_id, '_hikmah_custom_avatar', true);

        if (!empty($custom_avatar) && is_numeric($custom_avatar)) {
            $avatar_url = wp_get_attachment_image_url($custom_avatar, [$size, $size]);
            if ($avatar_url) {
                return $avatar_url;
            }
        }

        // Fallback to Gravatar
        return get_avatar_url($user_id, ['size' => $size]);
    }

    /**
     * Enqueue dashboard-specific assets
     *
     * @return void
     */
    public function enqueue_dashboard_assets() {
        // Only load on pages containing our shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'hikmah_dashboard')) {
            return;
        }

        // Dashboard CSS
        wp_enqueue_style(
            'hikmah-dashboard',
            HIKMAH_LOGIN_URL . 'assets/css/dashboard.css',
            ['hikmah-login-styles'],
            HIKMAH_LOGIN_VERSION
        );

        // Dashboard JS
        wp_enqueue_script(
            'hikmah-dashboard',
            HIKMAH_LOGIN_URL . 'assets/js/dashboard.js',
            ['jquery'],
            HIKMAH_LOGIN_VERSION,
            true
        );

        // Cropper.js for avatar
        wp_enqueue_style(
            'cropperjs',
            HIKMAH_LOGIN_URL . 'assets/vendor/cropper/cropper.min.css',
            [],
            '1.6.1'
        );
        wp_enqueue_script(
            'cropperjs',
            HIKMAH_LOGIN_URL . 'assets/vendor/cropper/cropper.min.js',
            [],
            '1.6.1',
            true
        );

        // WordPress media uploader
        wp_enqueue_media();

        // Localize dashboard data
        wp_localize_script('hikmah-dashboard', 'hikmahDashboard', [
            'ajaxUrl'     => \Hikmah_Login\Helpers\Helper::relative_url(admin_url('admin-ajax.php')),
            'nonce'       => wp_create_nonce('hikmah_dashboard_nonce'),
            'currentTab'  => $this->get_current_tab(),
            'userId'      => get_current_user_id(),
            'i18n'        => [
                'confirmDelete'   => __('Are you sure you want to delete your account? This action cannot be undone.', 'hikmah-login'),
                'confirmLogout'   => __('Are you sure you want to end this session?', 'hikmah-login'),
                'saving'          => __('Saving...', 'hikmah-login'),
                'saved'           => __('Saved successfully!', 'hikmah-login'),
                'error'           => __('An error occurred. Please try again.', 'hikmah-login'),
                'uploadAvatar'    => __('Select or Upload Avatar', 'hikmah-login'),
                'cropAvatar'      => __('Crop Avatar', 'hikmah-login'),
                'passwordWeak'    => __('Weak', 'hikmah-login'),
                'passwordMedium'  => __('Medium', 'hikmah-login'),
                'passwordStrong'  => __('Strong', 'hikmah-login'),
            ],
        ]);
    }

    /**
     * Add body class when on dashboard page
     *
     * @param array $classes Body CSS classes.
     * @return array Modified classes.
     */
    public function add_body_class($classes) {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'hikmah_dashboard')) {
            $classes[] = 'hikmah-dashboard-page';
            $classes[] = 'hikmah-dashboard-tab--' . $this->get_current_tab();
        }
        return $classes;
    }

    /**
     * Handle AJAX dashboard actions
     *
     * @return void
     */
    public function handle_ajax_action() {
        // Verify nonce
        if (!check_ajax_referer('hikmah_dashboard_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'hikmah-login'),
            ], 403);
        }

        // Must be logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in.', 'hikmah-login'),
            ], 401);
        }

        $action_type = isset($_POST['action_type']) ? sanitize_key($_POST['action_type']) : '';

        /**
         * Action to handle custom AJAX dashboard actions
         *
         * @param string $action_type The action being performed.
         */
        do_action('hikmah_dashboard_ajax_' . $action_type);

        // If no handler caught it
        wp_send_json_error([
            'message' => __('Unknown action.', 'hikmah-login'),
        ], 400);
    }

    /**
     * Process non-AJAX form submissions
     *
     * @return void
     */
    public function process_form_submissions() {
        if (!isset($_POST['hikmah_dashboard_action'])) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        $action = sanitize_key($_POST['hikmah_dashboard_action']);

        /**
         * Action to process dashboard form submissions
         *
         * @param string $action The form action identifier.
         */
        do_action('hikmah_dashboard_process_' . $action);
    }

    /**
     * Placeholder: Render Overview tab
     *
     * @return void
     */
    public function render_tab_overview() {
        // Will be implemented in Step 2
        echo '<p>' . esc_html__('Overview content loading...', 'hikmah-login') . '</p>';
    }

    /**
     * Placeholder: Render Profile tab
     *
     * @return void
     */
    public function render_tab_profile() {
        // Will be implemented in Step 2
    }

    /**
     * Placeholder: Render Security tab
     *
     * @return void
     */
    public function render_tab_security() {
        // Will be implemented in Step 3
    }

    /**
     * Placeholder: Render Sessions tab
     *
     * @return void
     */
    public function render_tab_sessions() {
        // Will be implemented in Step 4
    }

    /**
     * Placeholder: Render Login History tab
     *
     * @return void
     */
    public function render_tab_login_history() {
        // Will be implemented in Step 4
    }

    /**
     * Placeholder: Render Connected Accounts tab
     *
     * @return void
     */
    public function render_tab_connected_accounts() {
        // Will be implemented in Step 4
    }

    /**
     * Placeholder: Render Account tab
     *
     * @return void
     */
    public function render_tab_account() {
        // Will be implemented in Step 5
    }

    /**
     * Set a dashboard notice (session-based)
     *
     * @param string $type    Notice type: success, error, warning, info.
     * @param string $message Notice message.
     * @return void
     */
    public static function set_notice($type, $message) {
        if (!session_id()) {
            session_start();
        }

        $_SESSION['hikmah_dashboard_notice'] = [
            'type'    => sanitize_key($type),
            'message' => sanitize_text_field($message),
        ];
    }

    /**
     * Get dashboard page URL with optional tab
     *
     * @param string $tab  Tab slug.
     * @param array  $args Additional query args.
     * @return string Dashboard URL.
     */
    public static function get_dashboard_url($tab = '', $args = []) {
        $page_id = hikmah_get_option('dashboard_page_id', 0);

        if ($page_id) {
            $url = get_permalink($page_id);
        } else {
            $url = home_url('/dashboard/');
        }

        if (!empty($tab)) {
            $args['tab'] = $tab;
        }

        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }

        return $url;
    }
}
