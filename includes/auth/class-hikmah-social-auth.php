<?php
/**
 * Social Authentication Engine
 *
 * Implements high-performance Google and Facebook secure OAuth
 * login, registration, and user mapping integrations.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Social_Auth {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('query_vars', [$this, 'add_oauth_query_vars']);
        add_action('template_redirect', [$this, 'process_oauth_callback']);
        add_action('hikmah_social_login_buttons', [$this, 'render_social_buttons']);
    }

    /**
     * Register clean rewrite query parameters for OAuth redirection endpoints
     *
     * @param array $vars Native query variables.
     * @return array Modified query variables.
     */
    public function add_oauth_query_vars($vars) {
        $vars[] = 'hikmah_oauth';
        return $vars;
    }

    /**
     * Generate secure state transient hash to prevent CSRF replay attacks
     *
     * @param string $provider Auth provider identifier.
     * @return string Secure random hash token.
     */
    private function create_state_token($provider) {
        $token = wp_generate_password(24, false);
        set_transient('hikmah_social_' . $provider . '_state_' . $token, 1, 15 * MINUTE_IN_SECONDS);
        return $token;
    }

    /**
     * Verify state token transient value
     *
     * @param string $provider Auth provider identifier.
     * @param string $token    State hash.
     * @return bool True if valid, false otherwise.
     */
    private function verify_state_token($provider, $token) {
        $key = 'hikmah_social_' . $provider . '_state_' . $token;
        $exists = (bool) get_transient($key);
        if ($exists) {
            delete_transient($key);
        }
        return $exists;
    }

    /**
     * Retrieve base dynamic redirect URI
     *
     * @param string $provider Auth provider identifier.
     * @return string Complete absolute URL target.
     */
    private function get_redirect_uri($provider) {
        return add_query_arg('hikmah_oauth', $provider, home_url('/'));
    }

    /**
     * Process OAuth server callback handshake loops
     *
     * @return void
     */
    public function process_oauth_callback() {
        $provider = get_query_var('hikmah_oauth');
        if (empty($provider) || !in_array($provider, ['google', 'facebook'], true)) {
            return;
        }

        $code  = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');

        if (empty($code) || !$this->verify_state_token($provider, $state)) {
            wp_die(esc_html__('State verification check failed. Please try again.', 'hikmah-login'), esc_html__('OAuth Error', 'hikmah-login'), ['response' => 403]);
        }

        $user_profile = [];

        if ($provider === 'google') {
            $user_profile = $this->exchange_google_handshake($code);
        } elseif ($provider === 'facebook') {
            $user_profile = $this->exchange_facebook_handshake($code);
        }

        if (empty($user_profile) || empty($user_profile['id']) || empty($user_profile['email'])) {
            wp_die(esc_html__('Could not retrieve user details from authentication provider.', 'hikmah-login'), esc_html__('OAuth Request Error', 'hikmah-login'), ['response' => 400]);
        }

        $this->authenticate_social_user($provider, $user_profile);
    }

    /**
     * Google Exchange Handshake
     *
     * @param string $code OAuth authorization code.
     * @return array Normalized profile payload.
     */
    private function exchange_google_handshake($code) {
        $client_id     = hikmah_get_option('google_client_id', '');
        $client_secret = hikmah_get_option('google_client_secret', '');

        $response = wp_safe_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $this->get_redirect_uri('google'),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $token_data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($token_data['access_token'])) {
            return [];
        }

        $profile_response = wp_safe_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token_data['access_token'],
            ],
        ]);

        if (is_wp_error($profile_response)) {
            return [];
        }

        $raw_profile = json_decode(wp_remote_retrieve_body($profile_response), true);

        return [
            'id'    => $raw_profile['id'] ?? '',
            'email' => $raw_profile['email'] ?? '',
            'name'  => $raw_profile['name'] ?? '',
        ];
    }

    /**
     * Facebook Exchange Handshake
     *
     * @param string $code OAuth authorization code.
     * @return array Normalized profile payload.
     */
    private function exchange_facebook_handshake($code) {
        $app_id     = hikmah_get_option('facebook_app_id', '');
        $app_secret = hikmah_get_option('facebook_app_secret', '');

        $response = wp_safe_remote_get(add_query_arg([
            'client_id'     => $app_id,
            'redirect_uri'  => $this->get_redirect_uri('facebook'),
            'client_secret' => $app_secret,
            'code'          => $code,
        ], 'https://graph.facebook.com/v15.0/oauth/access_token'));

        if (is_wp_error($response)) {
            return [];
        }

        $token_data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($token_data['access_token'])) {
            return [];
        }

        $profile_response = wp_safe_remote_get(add_query_arg([
            'fields'       => 'id,name,email',
            'access_token' => $token_data['access_token'],
        ], 'https://graph.facebook.com/me'));

        if (is_wp_error($profile_response)) {
            return [];
        }

        $raw_profile = json_decode(wp_remote_retrieve_body($profile_response), true);

        return [
            'id'    => $raw_profile['id'] ?? '',
            'email' => $raw_profile['email'] ?? '',
            'name'  => $raw_profile['name'] ?? '',
        ];
    }

    /**
     * Map, link, and authenticate social credentials into WP accounts
     *
     * @param string $provider Auth provider identifier.
     * @param array  $profile  User profile details.
     * @return void
     */
    private function authenticate_social_user($provider, $profile) {
        $social_id = sanitize_text_field($profile['id']);
        $email     = sanitize_email($profile['email']);

        // 1. Query for an existing user mapped to this specific social ID
        $user_query = new WP_User_Query([
            'meta_key'   => '_hikmah_social_' . $provider . '_id',
            'meta_value' => $social_id,
            'number'     => 1,
        ]);

        $users = $user_query->get_results();
        $user  = !empty($users) ? $users[0] : null;

        // 2. If no social ID map exists, check if email address matches an existing user
        if (!$user) {
            $user = get_user_by('email', $email);
            if ($user) {
                // Link social account ID to existing user
                update_user_meta($user->ID, '_hikmah_social_' . $provider . '_id', $social_id);
                update_user_meta($user->ID, '_hikmah_social_' . $provider . '_connected_at', current_time('mysql'));
            }
        }

        // 3. If user doesn't exist, register a new account on-the-fly (if registration is open)
        if (!$user) {
            if (!get_option('users_can_register')) {
                wp_die(
                    esc_html__('Registration is closed on this website. Please contact site administrators.', 'hikmah-login'),
                    esc_html__('Registration Disabled', 'hikmah-login'),
                    ['response' => 403]
                );
            }

            // Generate clean unique username based on provider name
            $username = strtolower(str_replace(' ', '', $profile['name']));
            if (empty($username) || username_exists($username)) {
                $username = strstr($email, '@', true) . '_' . wp_generate_password(4, false);
            }

            $password = wp_generate_password(18, true, true);
            $user_id  = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                wp_die(esc_html($user_id->get_error_message()), esc_html__('Account Creation Failed', 'hikmah-login'));
            }

            $user = get_userdata($user_id);
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => sanitize_text_field($profile['name']),
            ]);

            // Save social provider mapping details
            update_user_meta($user_id, '_hikmah_social_' . $provider . '_id', $social_id);
            update_user_meta($user_id, '_hikmah_social_' . $provider . '_connected_at', current_time('mysql'));
            update_user_meta($user_id, '_hikmah_password_last_changed', current_time('mysql'));

            wp_new_user_notification($user_id, null, 'user');
        }

        // 4. Perform WordPress authentication
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true, is_ssl());
        do_action('wp_login', $user->user_login, $user);

        // Redirect to dashboard page
        $redirect = hikmah_get_option('login_redirect', home_url());
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Render Social Authentication Buttons on Forms
     *
     * @return void
     */
    public function render_social_buttons() {
        $google_id   = hikmah_get_option('google_client_id', '');
        $facebook_id = hikmah_get_option('facebook_app_id', '');

        if (empty($google_id) && empty($facebook_id)) {
            return;
        }

        echo '<div class="hikmah-social-buttons">';

        if (!empty($google_id)) {
            $google_url = add_query_arg([
                'client_id'     => $google_id,
                'redirect_uri'  => $this->get_redirect_uri('google'),
                'response_type' => 'code',
                'scope'         => 'openid profile email',
                'state'         => $this->create_state_token('google'),
            ], 'https://accounts.google.com/o/oauth2/v2/auth');

            printf(
                '<a href="%1$s" class="hikmah-social-btn hikmah-social-btn--google">
                    <span class="dashicons dashicons-google"></span>
                    %2$s
                </a>',
                esc_url($google_url),
                esc_html__('Continue with Google', 'hikmah-login')
            );
        }

        if (!empty($facebook_id)) {
            $facebook_url = add_query_arg([
                'client_id'    => $facebook_id,
                'redirect_uri' => $this->get_redirect_uri('facebook'),
                'state'        => $this->create_state_token('facebook'),
                'scope'        => 'public_profile,email',
            ], 'https://www.facebook.com/v15.0/dialog/oauth');

            printf(
                '<a href="%1$s" class="hikmah-social-btn hikmah-social-btn--facebook">
                    <span class="dashicons dashicons-facebook"></span>
                    %2$s
                </a>',
                esc_url($facebook_url),
                esc_html__('Continue with Facebook', 'hikmah-login')
            );
        }

        echo '</div>';
    }
}
