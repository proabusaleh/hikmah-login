<?php
/**
 * Register Shortcode
 *
 * Provides the [hikmah_register] shortcode with extensive
 * customization attributes.
 *
 * Usage Examples:
 *   [hikmah_register]
 *   [hikmah_register title="Create Account" redirect="/dashboard/"]
 *   [hikmah_register show_title="false" show_social="false"]
 *   [hikmah_register show_terms="true"]
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

class Register_Shortcode {

    use Singleton;

    /**
     * Constructor.
     */
    private function __construct() {
        add_shortcode( 'hikmah_register', [ $this, 'render' ] );
    }

    /**
     * Render the register shortcode.
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
            'title'          => __( 'Create Account', 'hikmah-login' ),
            'subtitle'       => '',
            'show_title'     => 'true',
            'show_social'    => 'auto',        // auto, true, false
            'show_terms'     => 'auto',        // auto, true, false
            'theme'          => 'light',       // light, dark, auto
            'layout'         => 'default',     // default, compact, wide

            // Behavior
            'redirect_to'    => '',            // URL after registration
            'role'           => '',            // Default role override

            // Customization
            'custom_class'   => '',
            'form_id'        => '',
            'btn_text'       => __( 'Create Account', 'hikmah-login' ),
        ], $atts, $tag );

        // Execute shortcode output through output buffering so hooks can inject content
        ob_start();

        // Pass variables to template
        $template_vars = $this->build_template_vars( $atts );
        $this->render_template( $template_vars );

        $output = ob_get_clean();

        return $output;
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

        // Redirect
        $vars['redirect_to'] = ! empty( $atts['redirect_to'] )
            ? esc_url( $atts['redirect_to'] )
            : '';

        // Show/hide elements
        $vars['show_social'] = $this->resolve_auto(
            $atts['show_social'],
            'yes' === get_option( 'hikmah_google_login_enabled', 'no' )
            || 'yes' === get_option( 'hikmah_facebook_login_enabled', 'no' )
        );
        $vars['show_terms'] = $this->resolve_auto(
            $atts['show_terms'],
            'yes' === get_option( 'hikmah_terms_required', 'no' )
        );

        // Role override
        $vars['role'] = ! empty( $atts['role'] )
            ? sanitize_key( $atts['role'] )
            : '';

        // Theme & Layout
        $vars['theme']  = sanitize_key( $atts['theme'] );
        $vars['layout'] = sanitize_key( $atts['layout'] );

        // Button text
        $vars['btn_text'] = $atts['btn_text'];

        // Form ID
        $vars['form_id'] = ! empty( $atts['form_id'] )
            ? sanitize_html_class( $atts['form_id'] )
            : 'hikmah-register-form-' . wp_rand( 1000, 9999 );

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
     */
    private function render_template( $vars ) {

        // Check for theme override
        $template = $this->locate_template( 'register-form.php' );

        // Extract variables for template
        extract( $vars ); // phpcs:ignore

        if ( file_exists( $template ) ) {
            include $template;
        } else {
            $this->render_fallback_template( $vars );
        }
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
        <div class="hikmah-login-wrapper hikmah-register-wrapper"
             id="<?php echo esc_attr( $vars['form_id'] ); ?>-wrapper">
            <?php if ( $vars['show_title'] ) : ?>
                <h2 class="hikmah-form-title"><?php echo esc_html( $vars['title'] ); ?></h2>
            <?php endif; ?>
            <form id="<?php echo esc_attr( $vars['form_id'] ); ?>"
                  class="hikmah-login-form hikmah-register-form" method="post">
                <?php wp_nonce_field( 'hikmah_register_action', 'hikmah_register_nonce' ); ?>
                <input type="hidden" name="hikmah_action" value="register">
                <div class="hikmah-field">
                    <label class="hikmah-label">
                        <?php esc_html_e( 'Username', 'hikmah-login' ); ?>
                    </label>
                    <input type="text" name="username" class="hikmah-input" required
                           placeholder="<?php esc_attr_e( 'Choose a username', 'hikmah-login' ); ?>">
                </div>
                <div class="hikmah-field">
                    <label class="hikmah-label">
                        <?php esc_html_e( 'Email', 'hikmah-login' ); ?>
                    </label>
                    <input type="email" name="email" class="hikmah-input" required
                           placeholder="<?php esc_attr_e( 'you@example.com', 'hikmah-login' ); ?>">
                </div>
                <div class="hikmah-field">
                    <label class="hikmah-label">
                        <?php esc_html_e( 'Password', 'hikmah-login' ); ?>
                    </label>
                    <input type="password" name="password" class="hikmah-input" required
                           placeholder="<?php esc_attr_e( 'Create a password', 'hikmah-login' ); ?>">
                </div>
                <div class="hikmah-field">
                    <label class="hikmah-label">
                        <?php esc_html_e( 'Confirm Password', 'hikmah-login' ); ?>
                    </label>
                    <input type="password" name="confirm_password" class="hikmah-input" required
                           placeholder="<?php esc_attr_e( 'Confirm password', 'hikmah-login' ); ?>">
                </div>
                <button type="submit" class="hikmah-btn hikmah-btn-primary hikmah-btn-full">
                    <?php echo esc_html( $vars['btn_text'] ); ?>
                </button>
            </form>
            <div class="hikmah-form-footer">
                <p class="hikmah-register-link">
                    <?php esc_html_e( 'Already have an account?', 'hikmah-login' ); ?>
                    <a href="<?php echo esc_url( Helper::get_login_url() ); ?>">
                        <?php esc_html_e( 'Sign In', 'hikmah-login' ); ?>
                    </a>
                </p>
            </div>
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
