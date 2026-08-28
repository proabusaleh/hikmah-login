<?php
/**
 * Login Shortcode
 *
 * Provides the [hikmah_login] shortcode with extensive
 * customization attributes.
 *
 * Usage Examples:
 *   [hikmah_login]
 *   [hikmah_login title="Welcome Back" redirect="/dashboard/"]
 *   [hikmah_login show_title="false" show_social="false"]
 *   [hikmah_login redirect_to="/my-account/" logo="123"]
 *
 * @package Hikmah_Login
 * @subpackage Shortcodes
 * @since   1.0.0
 */

namespace Hikmah_Login\Shortcodes;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Login_Shortcode {

    use Singleton;

    /**
     * Constructor.
     */
    private function __construct() {
        add_shortcode( 'hikmah_login', [ $this, 'render' ] );
    }

    /**
     * Render the login shortcode.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Enclosed content (if any).
     * @param string $tag     Shortcode tag.
     * @return string Rendered HTML.
     */
    public function render( $atts = [], $content = '', $tag = '' ) {

        // Parse attributes with defaults
        $atts = shortcode_atts( [
            // Appearance
            'title'          => __( 'Sign In', 'hikmah-login' ),
            'subtitle'       => '',
            'show_title'     => 'true',
            'logo'           => '',           // Attachment ID or URL
            'logo_width'     => '120',
            'theme'          => 'light',       // light, dark, auto
            'layout'         => 'default',     // default, compact, wide

            // Behavior
            'redirect_to'    => '',            // URL after login
            'redirect_guest' => '',            // URL for non-logged-in (if already logged in)
            'show_register'  => 'auto',        // auto, true, false
            'show_forgot'    => 'true',
            'show_social'    => 'auto',        // auto, true, false
            'show_remember'  => 'true',

            // Customization
            'bg_color'       => '',
            'btn_color'      => '',
            'btn_text'       => __( 'Sign In', 'hikmah-login' ),
            'custom_class'   => '',
            'form_id'        => '',

            // Fields
            'username_label' => __( 'Username or Email', 'hikmah-login' ),
            'password_label' => __( 'Password', 'hikmah-login' ),
            'username_placeholder' => __( 'Enter your username or email', 'hikmah-login' ),
            'password_placeholder' => __( 'Enter your password', 'hikmah-login' ),

            // Logged-in behavior
            'logged_in_text' => '',            // Custom text for logged-in users
            'logged_in_redirect' => '',        // Redirect if already logged in
        ], $atts, $tag );

        // Handle already logged-in users
        if ( is_user_logged_in() ) {
            return $this->render_logged_in( $atts );
        }

        // Build template variables
        $template_vars = $this->build_template_vars( $atts );

        // Render the template
        return $this->render_template( $template_vars );
    }

    /**
     * Render logged-in state.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML.
     */
    private function render_logged_in( $atts ) {

        $user = wp_get_current_user();

        // Custom redirect
        if ( ! empty( $atts['logged_in_redirect'] ) ) {
            $redirect_url = esc_url( $atts['logged_in_redirect'] );
            return '<script>window.location.href="' . $redirect_url . '";</script>' .
                   '<p>' . esc_html__( 'Redirecting...', 'hikmah-login' ) . '</p>';
        }

        // Custom text
        if ( ! empty( $atts['logged_in_text'] ) ) {
            return '<div class="hikmah-logged-in-notice">' .
                   wp_kses_post( $atts['logged_in_text'] ) .
                   '</div>';
        }

        // Default logged-in message
        ob_start();
        ?>
        <div class="hikmah-logged-in-notice">
            <div class="hikmah-notice hikmah-notice-info">
                <p>
                    <?php
                    printf(
                        /* translators: 1: User name 2: Dashboard link 3: Logout link */
                        esc_html__( 'Welcome back, %1$s! Visit your %2$s or %3$s.', 'hikmah-login' ),
                        '<strong>' . esc_html( $user->display_name ) . '</strong>',
                        '<a href="' . esc_url( Helper::get_dashboard_url() ) . '">' .
                            esc_html__( 'Dashboard', 'hikmah-login' ) . '</a>',
                        '<a href="' . esc_url( Helper::get_logout_url() ) . '">' .
                            esc_html__( 'Logout', 'hikmah-login' ) . '</a>'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Build template variables from attributes.
     *
     * @param array $atts Parsed attributes.
     * @return array Template variables.
     */
    private function build_template_vars( $atts ) {

        $vars = [];

        // Title
        $vars['show_title'] = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
        $vars['title']      = $atts['title'];
        $vars['subtitle']   = $atts['subtitle'];

        // Logo
        if ( ! empty( $atts['logo'] ) ) {
            if ( is_numeric( $atts['logo'] ) ) {
                $vars['logo_url'] = wp_get_attachment_url( absint( $atts['logo'] ) );
            } else {
                $vars['logo_url'] = esc_url( $atts['logo'] );
            }
        } else {
            $vars['logo_url'] = get_option( 'hikmah_login_logo', '' );
        }
        $vars['logo_width'] = absint( $atts['logo_width'] );

        // Redirect
        $vars['redirect_to'] = ! empty( $atts['redirect_to'] )
            ? esc_url( $atts['redirect_to'] )
            : '';

        // Show/hide elements
        $vars['show_register'] = $this->resolve_auto(
            $atts['show_register'],
            'yes' === get_option( 'hikmah_registration_enabled', 'yes' )
        );
        $vars['show_forgot'] = filter_var( $atts['show_forgot'], FILTER_VALIDATE_BOOLEAN );
        $vars['show_social'] = $this->resolve_auto(
            $atts['show_social'],
            'yes' === get_option( 'hikmah_google_login_enabled', 'no' )
            || 'yes' === get_option( 'hikmah_facebook_login_enabled', 'no' )
        );
        $vars['show_remember'] = filter_var( $atts['show_remember'], FILTER_VALIDATE_BOOLEAN );

        // Theme & Layout
        $vars['theme']  = sanitize_key( $atts['theme'] );
        $vars['layout'] = sanitize_key( $atts['layout'] );

        // Custom styles
        $vars['bg_color']  = sanitize_hex_color( $atts['bg_color'] );
        $vars['btn_color'] = sanitize_hex_color( $atts['btn_color'] );

        // Labels
        $vars['username_label']       = $atts['username_label'];
        $vars['password_label']       = $atts['password_label'];
        $vars['username_placeholder'] = $atts['username_placeholder'];
        $vars['password_placeholder'] = $atts['password_placeholder'];
        $vars['btn_text']             = $atts['btn_text'];

        // Form ID
        $vars['form_id'] = ! empty( $atts['form_id'] )
            ? sanitize_html_class( $atts['form_id'] )
            : 'hikmah-login-form-' . wp_rand( 1000, 9999 );

        // Custom class
        $vars['custom_class'] = sanitize_html_class( $atts['custom_class'] );

        // CAPTCHA
        $vars['captcha_enabled'] = Helper::is_feature_enabled( 'captcha_enabled' );
        $vars['captcha_type']    = get_option( 'hikmah_captcha_type', 'recaptcha_v2' );
        $vars['site_key']        = get_option( 'hikmah_recaptcha_site_key', '' );

        // Social
        $vars['google_enabled']   = 'yes' === get_option( 'hikmah_google_login_enabled', 'no' );
        $vars['facebook_enabled'] = 'yes' === get_option( 'hikmah_facebook_login_enabled', 'no' );

        return $vars;
    }

    /**
     * Render the template with variables.
     *
     * @param array $vars Template variables.
     * @return string Rendered HTML.
     */
    private function render_template( $vars ) {

        // Check for theme override
        $template = $this->locate_template( 'login-form.php' );

        ob_start();

        // Extract variables for template
        extract( $vars ); // phpcs:ignore

        // Add custom CSS variables if needed
        if ( ! empty( $vars['bg_color'] ) || ! empty( $vars['btn_color'] ) ) {
            echo '<style>';
            if ( ! empty( $vars['bg_color'] ) ) {
                echo '#' . esc_attr( $vars['form_id'] ) . '-wrapper { background: ' . esc_attr( $vars['bg_color'] ) . '; }';
            }
            if ( ! empty( $vars['btn_color'] ) ) {
                echo '#' . esc_attr( $vars['form_id'] ) . ' .hikmah-btn-primary { background: ' . esc_attr( $vars['btn_color'] ) . '; }';
            }
            echo '</style>';
        }

        if ( file_exists( $template ) ) {
            include $template;
        } else {
            // Fallback inline template
            $this->render_fallback_template( $vars );
        }

        return ob_get_clean();
    }

    /**
     * Locate template file (theme override support).
     *
     * @param string $template_name Template filename.
     * @return string Full path to template.
     */
    private function locate_template( $template_name ) {

        // Check theme first
        $theme_template = locate_template( 'hikmah-login/' . $template_name );

        if ( $theme_template ) {
            return $theme_template;
        }

        // Fallback to plugin template
        return HIKMAH_LOGIN_DIR . 'public/views/' . $template_name;
    }

    /**
     * Fallback template if no file exists.
     *
     * @param array $vars Template variables.
     */
    private function render_fallback_template( $vars ) {
        ?>
        <div class="hikmah-login-wrapper" id="<?php echo esc_attr( $vars['form_id'] ); ?>-wrapper">
            <?php if ( $vars['show_title'] ) : ?>
                <h2 class="hikmah-form-title"><?php echo esc_html( $vars['title'] ); ?></h2>
            <?php endif; ?>
            <form id="<?php echo esc_attr( $vars['form_id'] ); ?>" class="hikmah-login-form" method="post">
                <?php wp_nonce_field( 'hikmah_login_action', 'hikmah_login_nonce' ); ?>
                <input type="hidden" name="hikmah_action" value="login">
                <div class="hikmah-field">
                    <label class="hikmah-label"><?php echo esc_html( $vars['username_label'] ); ?></label>
                    <input type="text" name="username" class="hikmah-input" required
                           placeholder="<?php echo esc_attr( $vars['username_placeholder'] ); ?>">
                </div>
                <div class="hikmah-field">
                    <label class="hikmah-label"><?php echo esc_html( $vars['password_label'] ); ?></label>
                    <input type="password" name="password" class="hikmah-input" required
                           placeholder="<?php echo esc_attr( $vars['password_placeholder'] ); ?>">
                </div>
                <button type="submit" class="hikmah-btn hikmah-btn-primary hikmah-btn-full">
                    <?php echo esc_html( $vars['btn_text'] ); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Resolve "auto" boolean values.
     *
     * @param string $value   Attribute value (auto, true, false).
     * @param bool   $default Default when "auto".
     * @return bool
     */
    private function resolve_auto( $value, $default ) {
        if ( 'auto' === $value ) {
            return $default;
        }
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }
}
