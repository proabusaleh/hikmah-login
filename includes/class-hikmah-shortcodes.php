<?php
/**
 * Plugin Shortcodes & Template Router
 *
 * Registers shortcodes and handles safe theme-overrideable template rendering.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Shortcodes {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('hikmah_login_form', [$this, 'render_login_form']);
        add_shortcode('hikmah_registration_form', [$this, 'render_registration_form']);
        add_shortcode('hikmah_lost_password_form', [$this, 'render_lost_password_form']);
        add_shortcode('hikmah_dashboard', [$this, 'render_dashboard']);
    }

    /**
     * Enqueue authentication specific frontend assets
     *
     * @return void
     */
    private function enqueue_auth_assets() {
        wp_enqueue_style('hikmah-auth-style');
        wp_enqueue_script('hikmah-auth-script');
    }

    /**
     * Enqueue dashboard specific frontend assets
     *
     * @return void
     */
    private function enqueue_dashboard_assets() {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('hikmah-dashboard-style');
        wp_enqueue_script('hikmah-dashboard-script');

        // Enqueue third-party cropping engine for avatar uploads
        wp_enqueue_style('cropper-css', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css', [], '1.5.13');
        wp_enqueue_script('cropper-js', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js', [], '1.5.13', true);
    }

    /**
     * Render the shortcode template fallback
     *
     * @param string $template_name Filename of target template.
     * @param array  $args          Data variables parsed into local scope.
     * @return string               Rendered HTML string buffer.
     */
    public function get_template_html($template_name, $args = []) {
        if (!empty($args) && is_array($args)) {
            extract($args); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }

        ob_start();

        // Check for theme/child-theme level overrides first
        $template = locate_template('hikmah-login/' . $template_name . '.php');

        if (!$template) {
            $template = HIKMAH_LOGIN_DIR . 'templates/' . $template_name . '.php';
        }

        if (file_exists($template)) {
            include $template;
        } else {
            printf(
                /* translators: %s: template file path */
                esc_html__('Template file %s not found.', 'hikmah-login'),
                '<code>' . esc_html($template_name) . '</code>'
            );
        }

        return ob_get_clean();
    }

    /**
     * Shortcode: [hikmah_login_form]
     *
     * @return string Form markup.
     */
    public function render_login_form() {
        if (is_user_logged_in()) {
            return sprintf(
                '<p class="hikmah-notice">%s <a href="%s">%s</a></p>',
                esc_html__('You are already signed in.', 'hikmah-login'),
                esc_url(hikmah_get_option('login_redirect', home_url())),
                esc_html__('Go to Dashboard', 'hikmah-login')
            );
        }

        $this->enqueue_auth_assets();
        return $this->get_template_html('login-form');
    }

    /**
     * Shortcode: [hikmah_registration_form]
     *
     * @return string Registration markup.
     */
    public function render_registration_form() {
        if (is_user_logged_in()) {
            return sprintf(
                '<p class="hikmah-notice">%s <a href="%s">%s</a></p>',
                esc_html__('You are already registered and signed in.', 'hikmah-login'),
                esc_url(hikmah_get_option('login_redirect', home_url())),
                esc_html__('Go to Dashboard', 'hikmah-login')
            );
        }

        if (!get_option('users_can_register')) {
            return sprintf(
                '<div class="hikmah-auth-alert hikmah-auth-alert--error"><span class="dashicons dashicons-warning"></span><p>%s</p></div>',
                esc_html__('User registration is currently closed.', 'hikmah-login')
            );
        }

        $this->enqueue_auth_assets();
        return $this->get_template_html('register-form');
    }

    /**
     * Shortcode: [hikmah_lost_password_form]
     *
     * @return string Lost password markup.
     */
    public function render_lost_password_form() {
        if (is_user_logged_in()) {
            return '';
        }

        $this->enqueue_auth_assets();
        return $this->get_template_html('lost-password-form');
    }

    /**
     * Shortcode: [hikmah_dashboard]
     *
     * @return string Dashboard markup.
     */
    public function render_dashboard() {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());
            return sprintf(
                '<div class="hikmah-auth-alert hikmah-auth-alert--error">
                    <span class="dashicons dashicons-warning"></span>
                    <p>%1$s <a href="%2$s" style="font-weight:700; text-decoration:underline;">%3$s</a></p>
                </div>',
                esc_html__('Please authenticate to view your personal dashboard.', 'hikmah-login'),
                esc_url($login_url),
                esc_html__('Log in here', 'hikmah-login')
            );
        }

        $this->enqueue_dashboard_assets();
        return $this->get_template_html('dashboard');
    }
}
