<?php
/**
 * Dashboard Connected Accounts Tab
 *
 * Manages social login connections (Google, Facebook).
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Tab_Connected_Accounts {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('hikmah_dashboard_ajax_disconnect_social', [$this, 'ajax_disconnect']);
    }

    /**
     * Render connected accounts tab
     *
     * @return void
     */
    public function render() {
        $user_id  = get_current_user_id();
        $providers = $this->get_social_providers();
        ?>

        <div class="hikmah-tab-connected">

            <p class="hikmah-tab-connected__description">
                <?php esc_html_e('Connect your social accounts for faster login. You can disconnect them anytime.', 'hikmah-login'); ?>
            </p>

            <div class="hikmah-tab-connected__list">

                <?php foreach ($providers as $provider) : ?>
                    <?php
                    $is_connected = $this->is_provider_connected($user_id, $provider['id']);
                    $connected_data = $is_connected ? $this->get_connection_data($user_id, $provider['id']) : null;
                    ?>

                    <div class="hikmah-tab-connected__item hikmah-tab-connected__item--<?php echo $is_connected ? 'connected' : 'disconnected'; ?>"
                         data-provider="<?php echo esc_attr($provider['id']); ?>">

                        <div class="hikmah-tab-connected__provider-info">
                            <div class="hikmah-tab-connected__icon hikmah-tab-connected__icon--<?php echo esc_attr($provider['id']); ?>">
                                <?php echo $provider['svg_icon']; // Already escaped SVG ?>
                            </div>
                            <div class="hikmah-tab-connected__details">
                                <h5 class="hikmah-tab-connected__name">
                                    <?php echo esc_html($provider['name']); ?>
                                </h5>
                                <?php if ($is_connected && $connected_data) : ?>
                                    <p class="hikmah-tab-connected__email">
                                        <?php echo esc_html($connected_data['email'] ?? ''); ?>
                                    </p>
                                    <p class="hikmah-tab-connected__connected-date">
                                        <?php
                                        printf(
                                            /* translators: %s: connection date */
                                            esc_html__('Connected on %s', 'hikmah-login'),
                                            esc_html(date_i18n(
                                                get_option('date_format'),
                                                strtotime($connected_data['connected_at'] ?? '')
                                            ))
                                        );
                                        ?>
                                    </p>
                                <?php else : ?>
                                    <p class="hikmah-tab-connected__not-connected">
                                        <?php esc_html_e('Not connected', 'hikmah-login'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="hikmah-tab-connected__action">
                            <?php if ($is_connected) : ?>
                                <button type="button"
                                        class="hikmah-btn hikmah-btn--outline hikmah-btn--sm hikmah-btn--danger hikmah-disconnect-social"
                                        data-provider="<?php echo esc_attr($provider['id']); ?>">
                                    <?php esc_html_e('Disconnect', 'hikmah-login'); ?>
                                </button>
                            <?php elseif ($provider['enabled']) : ?>
                                <a href="<?php echo esc_url($this->get_connect_url($provider['id'])); ?>"
                                   class="hikmah-btn hikmah-btn--social hikmah-btn--sm hikmah-btn--<?php echo esc_attr($provider['id']); ?>">
                                    <?php esc_html_e('Connect', 'hikmah-login'); ?>
                                </a>
                            <?php else : ?>
                                <span class="hikmah-tab-connected__unavailable">
                                    <?php esc_html_e('Not available', 'hikmah-login'); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="hikmah-tab-connected__notice">
                <span class="dashicons dashicons-info"></span>
                <p>
                    <?php esc_html_e('Disconnecting a social account will not delete your account. You can still login with your email and password.', 'hikmah-login'); ?>
                </p>
            </div>

        </div>

        <?php
    }

    /**
     * Get available social providers
     *
     * @return array
     */
    private function get_social_providers() {
        $providers = [
            [
                'id'       => 'google',
                'name'     => __('Google', 'hikmah-login'),
                'enabled'  => (bool) hikmah_get_option('google_login_enabled', false),
                'svg_icon' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>',
            ],
            [
                'id'       => 'facebook',
                'name'     => __('Facebook', 'hikmah-login'),
                'enabled'  => (bool) hikmah_get_option('facebook_login_enabled', false),
                'svg_icon' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            ],
        ];

        /**
         * Filter social providers list
         *
         * @param array $providers Social provider configurations.
         */
        return apply_filters('hikmah_social_providers', $providers);
    }

    /**
     * Check if a provider is connected for a user
     *
     * @param int    $user_id  User ID.
     * @param string $provider Provider ID.
     * @return bool
     */
    private function is_provider_connected($user_id, $provider) {
        $social_id = get_user_meta($user_id, '_hikmah_social_' . $provider . '_id', true);
        return !empty($social_id);
    }

    /**
     * Get connection data for a provider
     *
     * @param int    $user_id  User ID.
     * @param string $provider Provider ID.
     * @return array|null
     */
    private function get_connection_data($user_id, $provider) {
        return [
            'email'        => get_user_meta($user_id, '_hikmah_social_' . $provider . '_email', true),
            'connected_at' => get_user_meta($user_id, '_hikmah_social_' . $provider . '_connected_at', true),
            'name'         => get_user_meta($user_id, '_hikmah_social_' . $provider . '_name', true),
        ];
    }

    /**
     * Get the URL to connect a social provider
     *
     * @param string $provider Provider ID.
     * @return string Connect URL.
     */
    private function get_connect_url($provider) {
        return add_query_arg([
            'hikmah_social_connect' => $provider,
            'redirect_to'           => Hikmah_Dashboard::get_dashboard_url('connected-accounts'),
            '_wpnonce'              => wp_create_nonce('hikmah_social_connect_' . $provider),
        ], home_url());
    }

    /**
     * AJAX: Disconnect social provider
     *
     * @return void
     */
    public function ajax_disconnect() {
        $user_id  = get_current_user_id();
        $provider = sanitize_key($_POST['provider'] ?? '');

        $allowed_providers = ['google', 'facebook'];
        if (!in_array($provider, $allowed_providers, true)) {
            wp_send_json_error(['message' => __('Invalid provider.', 'hikmah-login')]);
        }

        // Check that user has a password set (don't lock them out)
        $user = get_userdata($user_id);
        $has_password = !empty($user->user_pass) && $user->user_pass !== '';

        // Count connected providers
        $connected_count = 0;
        foreach ($allowed_providers as $p) {
            if ($this->is_provider_connected($user_id, $p)) {
                $connected_count++;
            }
        }

        // If this is the only login method and no password, prevent disconnect
        if (!$has_password && $connected_count <= 1) {
            wp_send_json_error([
                'message' => __('Cannot disconnect. Please set a password first, otherwise you will be locked out of your account.', 'hikmah-login'),
            ]);
        }

        // Remove social connection data
        delete_user_meta($user_id, '_hikmah_social_' . $provider . '_id');
        delete_user_meta($user_id, '_hikmah_social_' . $provider . '_email');
        delete_user_meta($user_id, '_hikmah_social_' . $provider . '_name');
        delete_user_meta($user_id, '_hikmah_social_' . $provider . '_connected_at');
        delete_user_meta($user_id, '_hikmah_social_' . $provider . '_token');

        /**
         * Action after social account is disconnected
         *
         * @param int    $user_id  User ID.
         * @param string $provider Provider ID.
         */
        do_action('hikmah_social_disconnected', $user_id, $provider);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %s: provider name */
                __('%s account disconnected successfully.', 'hikmah-login'),
                ucfirst($provider)
            ),
        ]);
    }
}
