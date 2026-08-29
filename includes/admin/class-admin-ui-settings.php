<?php
/**
 * Admin UI Settings
 *
 * Phase 12 Custom UI admin settings manager. Persists branding,
 * layout, theme, color, typography, button, and custom-CSS options,
 * generates the dynamic stylesheet consumed on the frontend, and
 * exposes the AJAX endpoints used by the UI builder.
 *
 * @package Hikmah_Login
 * @subpackage Admin
 * @since   1.0.0
 */

namespace Hikmah_Login\Admin;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_UI_Settings {

    use Singleton;
    use Hooks;

    /**
     * Available form layouts.
     */
    const LAYOUTS = [
        'default' => 'Default',
        'compact' => 'Compact',
        'wide'    => 'Wide',
        'split'   => 'Split',
    ];

    /**
     * Theme modes.
     */
    const THEME_MODES = [
        'auto'  => 'Auto (Follow System)',
        'light' => 'Light',
        'dark'  => 'Dark',
    ];

    /**
     * Color presets (slug => ['primary' => hex, 'name' => label]).
     * 'custom' is managed manually.
     */
    const COLOR_PRESETS = [
        'indigo'  => [ 'primary' => '#4f46e5', 'name' => 'Indigo' ],
        'blue'    => [ 'primary' => '#2563eb', 'name' => 'Blue' ],
        'emerald' => [ 'primary' => '#10b981', 'name' => 'Emerald' ],
        'rose'    => [ 'primary' => '#e11d48', 'name' => 'Rose' ],
        'amber'   => [ 'primary' => '#d97706', 'name' => 'Amber' ],
        'violet'  => [ 'primary' => '#7c3aed', 'name' => 'Violet' ],
        'slate'   => [ 'primary' => '#475569', 'name' => 'Slate' ],
        'custom'  => [ 'primary' => '', 'name' => 'Custom' ],
    ];

    /**
     * Button styles.
     */
    const BUTTON_STYLES = [
        'filled'   => 'Filled',
        'outline'  => 'Outline',
        'gradient' => 'Gradient',
    ];

    /**
     * Background types.
     */
    const BG_TYPES = [
        'color'    => 'Solid Color',
        'gradient' => 'Gradient',
        'image'    => 'Image',
    ];

    /**
     * Font family presets (slug => label).
     */
    const FONT_FAMILIES = [
        'system'  => 'System',
        'inter'   => 'Inter',
        'sans'    => 'Sans Serif',
        'serif'   => 'Serif',
        'mono'    => 'Monospace',
        'bengali' => 'Bengali (Hind Siliguri)',
    ];

    /**
     * Option key prefix.
     */
    const OPTION_PREFIX = 'hikmah_ui_';

    /**
     * Dynamic CSS transient key.
     */
    const CSS_TRANSIENT = 'hikmah_dynamic_css';

    /**
     * Constructor.
     */
    private function __construct() {
        // Unified admin-ajax endpoints (wp_ajax_hikmah_*).
        $this->add_ajax( 'hikmah_save_ui_settings', 'ajax_save_settings' );
        $this->add_ajax( 'hikmah_preview_ui', 'ajax_get_preview_data' );
        $this->add_ajax( 'hikmah_upload_logo', 'ajax_upload_logo' );
    }

    /**
     * Get default settings.
     *
     * @return array
     */
    public static function get_defaults() {
        return [
            // Branding.
            'logo_url'        => '',
            'logo_width'      => 140,
            'show_site_name'  => 'yes',
            'favicon_url'     => '',
            // Layout.
            'layout'          => 'default',
            'form_width'      => 420,
            'border_radius'   => 12,
            'show_shadow'     => 'yes',
            // Theme.
            'theme_mode'      => 'auto',
            'show_theme_toggle' => 'yes',
            // Color.
            'color_preset'    => 'indigo',
            'primary_color'   => '#4f46e5',
            'bg_color'        => '#f3f4f6',
            'card_bg_color'   => '#ffffff',
            'text_color'      => '#1f2937',
            'border_color'    => '#d1d5db',
            'input_bg_color'  => '#ffffff',
            // Typography.
            'font_family'     => 'system',
            'title_size'      => 24,
            'body_size'       => 14,
            // Buttons.
            'btn_style'       => 'filled',
            'btn_radius'      => 6,
            'btn_full_width'  => 'yes',
            // Page background.
            'bg_type'         => 'color',
            'bg_gradient'     => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'bg_image'        => '',
            'bg_image_overlay' => 'rgba(0,0,0,0.4)',
            // Custom CSS.
            'custom_css'      => '',
            // Visible elements.
            'show_social'     => 'yes',
            'show_remember'   => 'yes',
            'show_forgot'     => 'yes',
            'show_register'   => 'yes',
            'show_powered_by' => 'no',
        ];
    }

    /**
     * Get all UI settings merged with stored values.
     *
     * @return array
     */
    public function get_settings() {

        $settings = self::get_defaults();

        foreach ( $settings as $key => $default ) {
            $settings[ $key ] = get_option( self::OPTION_PREFIX . $key, $default );
        }

        // Derive primary color from the active preset (unless custom).
        $preset = $settings['color_preset'];
        if ( 'custom' !== $preset && ! empty( self::COLOR_PRESETS[ $preset ]['primary'] ) ) {
            $settings['primary_color'] = self::COLOR_PRESETS[ $preset ]['primary'];
        }

        return $settings;
    }

    /**
     * Sanitize raw UI settings input.
     *
     * @param array $input Raw key/value pairs (keys without prefix).
     * @return array Sanitized settings.
     */
    public function sanitize_ui_settings( $input ) {

        $input    = is_array( $input ) ? $input : [];
        $defaults = self::get_defaults();
        $clean    = [];

        // String fields.
        $string_fields = [ 'logo_url', 'favicon_url' ];
        foreach ( $string_fields as $field ) {
            $clean[ $field ] = isset( $input[ $field ] )
                ? esc_url_raw( wp_unslash( $input[ $field ] ) )
                : $defaults[ $field ];
        }

        // Color fields (hex or rgba/rgb).
        $color_fields = [ 'primary_color', 'bg_color', 'card_bg_color', 'text_color', 'border_color', 'input_bg_color', 'bg_image_overlay' ];
        foreach ( $color_fields as $field ) {
            $value = isset( $input[ $field ] ) ? sanitize_text_field( wp_unslash( $input[ $field ] ) ) : $defaults[ $field ];
            $clean[ $field ] = self::sanitize_color_value( $value ) ?: $defaults[ $field ];
        }

        // Gradient / image (allow function + url() syntax via ksess).
        $clean['bg_gradient'] = isset( $input['bg_gradient'] )
            ? trim( wp_kses_post( wp_unslash( $input['bg_gradient'] ) ) )
            : $defaults['bg_gradient'];
        $clean['bg_image']    = isset( $input['bg_image'] )
            ? esc_url_raw( wp_unslash( $input['bg_image'] ) )
            : $defaults['bg_image'];

        // Custom CSS — strip all tags (style blocks only).
        $clean['custom_css'] = isset( $input['custom_css'] )
            ? wp_strip_all_tags( wp_unslash( $input['custom_css'] ), true )
            : $defaults['custom_css'];

        // Integer fields with sensible clamps.
        $clean['logo_width']    = isset( $input['logo_width'] ) ? absint( $input['logo_width'] ) : $defaults['logo_width'];
        $clean['logo_width']    = max( 40, min( 400, $clean['logo_width'] ) );
        $clean['form_width']    = isset( $input['form_width'] ) ? absint( $input['form_width'] ) : $defaults['form_width'];
        $clean['form_width']    = max( 320, min( 900, $clean['form_width'] ) );
        $clean['border_radius'] = isset( $input['border_radius'] ) ? absint( $input['border_radius'] ) : $defaults['border_radius'];
        $clean['border_radius'] = max( 0, min( 32, $clean['border_radius'] ) );
        $clean['btn_radius']    = isset( $input['btn_radius'] ) ? absint( $input['btn_radius'] ) : $defaults['btn_radius'];
        $clean['btn_radius']    = max( 0, min( 24, $clean['btn_radius'] ) );
        $clean['title_size']    = isset( $input['title_size'] ) ? absint( $input['title_size'] ) : $defaults['title_size'];
        $clean['title_size']    = max( 16, min( 48, $clean['title_size'] ) );
        $clean['body_size']     = isset( $input['body_size'] ) ? absint( $input['body_size'] ) : $defaults['body_size'];
        $clean['body_size']     = max( 12, min( 20, $clean['body_size'] ) );

        // Yes/No toggles.
        $toggle_fields = [ 'show_site_name', 'show_shadow', 'show_theme_toggle', 'btn_full_width', 'show_social', 'show_remember', 'show_forgot', 'show_register', 'show_powered_by' ];
        foreach ( $toggle_fields as $field ) {
            $value          = isset( $input[ $field ] ) ? $input[ $field ] : $defaults[ $field ];
            $clean[ $field ] = 'yes' === $value ? 'yes' : 'no';
        }

        // Enums.
        $clean['layout']       = isset( $input['layout'] ) && isset( self::LAYOUTS[ $input['layout'] ] )
            ? sanitize_key( $input['layout'] )
            : $defaults['layout'];
        $clean['theme_mode']   = isset( $input['theme_mode'] ) && isset( self::THEME_MODES[ $input['theme_mode'] ] )
            ? sanitize_key( $input['theme_mode'] )
            : $defaults['theme_mode'];
        $clean['color_preset'] = isset( $input['color_preset'] ) && isset( self::COLOR_PRESETS[ $input['color_preset'] ] )
            ? sanitize_key( $input['color_preset'] )
            : $defaults['color_preset'];
        $clean['font_family']  = isset( $input['font_family'] ) && isset( self::FONT_FAMILIES[ $input['font_family'] ] )
            ? sanitize_key( $input['font_family'] )
            : $defaults['font_family'];
        $clean['btn_style']    = isset( $input['btn_style'] ) && isset( self::BUTTON_STYLES[ $input['btn_style'] ] )
            ? sanitize_key( $input['btn_style'] )
            : $defaults['btn_style'];
        $clean['bg_type']      = isset( $input['bg_type'] ) && isset( self::BG_TYPES[ $input['bg_type'] ] )
            ? sanitize_key( $input['bg_type'] )
            : $defaults['bg_type'];

        return $clean;
    }

    /**
     * Persist sanitized UI settings.
     *
     * @param array $settings Sanitized settings.
     */
    public function save_ui_settings( $settings ) {

        foreach ( $settings as $key => $value ) {
            update_option( self::OPTION_PREFIX . $key, $value );
        }

        // Sync legacy options consumed by other modules.
        if ( isset( $settings['logo_url'] ) ) {
            update_option( 'hikmah_login_logo', $settings['logo_url'] );
        }
        if ( isset( $settings['form_width'] ) ) {
            update_option( 'hikmah_login_form_width', $settings['form_width'] );
        }
        if ( isset( $settings['bg_color'] ) ) {
            update_option( 'hikmah_login_bg_color', $settings['bg_color'] );
        }
        if ( isset( $settings['custom_css'] ) ) {
            update_option( 'hikmah_custom_css', $settings['custom_css'] );
        }

        delete_transient( self::CSS_TRANSIENT );
    }

    /**
     * Generate the dynamic stylesheet from current settings.
     *
     * @param bool $force Skip the transient cache.
     * @return string
     */
    public function generate_dynamic_css( $force = false ) {

        $cached = get_transient( self::CSS_TRANSIENT );
        if ( false !== $cached && ! $force ) {
            return (string) $cached;
        }

        $s = $this->get_settings();

        $primary = self::validate_hex( $s['primary_color'] ) ? $s['primary_color'] : '#4f46e5';
        $hover   = self::darken_color( $primary, 10 );
        $active  = self::darken_color( $primary, 20 );
        $light   = self::lighten_color( $primary, 30 );
        $radius  = absint( $s['border_radius'] );
        $btn_rad = absint( $s['btn_radius'] );
        $stack   = $this->font_stack( $s['font_family'] );

        $css = '';

        // Theme token overrides.
        $css .= ":root{\n";
        $css .= "--hikmah-primary:{$primary};\n";
        $css .= "--hikmah-primary-hover:{$hover};\n";
        $css .= "--hikmah-primary-active:{$active};\n";
        $css .= "--hikmah-primary-light:{$light};\n";
        $css .= "--hikmah-surface:{$s['card_bg_color']};\n";
        $css .= "--hikmah-input-bg:{$s['input_bg_color']};\n";
        $css .= "--hikmah-text:{$s['text_color']};\n";
        $css .= "--hikmah-border:{$s['border_color']};\n";
        $css .= "--hikmah-border-focus:{$primary};\n";
        $css .= "--hikmah-card-radius:{$radius}px;\n";
        $css .= "--hikmah-radius:{$btn_rad}px;\n";
        $css .= "--hikmah-font:{$stack};\n";

        // Form width only drives the card when using the default layout.
        if ( 'default' === $s['layout'] ) {
            $css .= '--hikmah-form-width:' . absint( $s['form_width'] ) . "px;\n";
        }

        $css .= "}\n";

        // Page background.
        $bg_color = self::validate_hex( $s['bg_color'] ) ? $s['bg_color'] : '#f3f4f6';
        $css     .= "body.hikmah-login-page,.hikmah-login-page{background-color:{$bg_color};}\n";

        if ( 'gradient' === $s['bg_type'] && ! empty( $s['bg_gradient'] ) ) {
            $css .= "body.hikmah-login-page,.hikmah-login-page{background-image:{$s['bg_gradient']};background-size:cover;background-attachment:fixed;background-position:center;}\n";
        } elseif ( 'image' === $s['bg_type'] && ! empty( $s['bg_image'] ) ) {
            $css .= "body.hikmah-login-page{--hikmah-page-bg-image:url('{$s['bg_image']}');}\n";
            if ( ! empty( $s['bg_image_overlay'] ) ) {
                $css .= "body.hikmah-login-page::after{content:'';position:fixed;inset:0;background:{$s['bg_image_overlay']};z-index:0;pointer-events:none;}\n";
            }
        }

        // Card shadow.
        if ( 'no' === $s['show_shadow'] ) {
            $css .= ".hikmah-login-wrapper{box-shadow:none;}\n";
        }

        // Logo width.
        $css .= '.hikmah-form-logo img{max-width:' . absint( $s['logo_width'] ) . "px;}\n";

        // Typography sizes.
        $css .= '.hikmah-form-title{font-size:' . absint( $s['title_size'] ) . "px;}\n";
        $css .= '.hikmah-login-wrapper{font-size:' . absint( $s['body_size'] ) . "px;}\n";

        // Button style.
        if ( 'outline' === $s['btn_style'] ) {
            $css .= ".hikmah-btn-primary{background:transparent;border:1px solid {$primary};color:{$primary};}\n";
            $css .= ".hikmah-btn-primary:hover{background:{$primary};color:#ffffff;}\n";
        } elseif ( 'gradient' === $s['btn_style'] ) {
            $css .= ".hikmah-btn-primary{background-image:linear-gradient(135deg,{$primary} 0%,{$hover} 100%);border-color:transparent;}\n";
            $css .= ".hikmah-btn-primary:hover{filter:brightness(1.08);}\n";
        }

        // Full-width primary button.
        if ( 'yes' === $s['btn_full_width'] ) {
            $css .= ".hikmah-login-wrapper .hikmah-btn-primary{width:100%;}\n";
        }

        // Custom CSS.
        if ( ! empty( $s['custom_css'] ) ) {
            $css .= wp_strip_all_tags( $s['custom_css'] ) . "\n";
        }

        $css = trim( $css );

        if ( ! empty( $css ) ) {
            set_transient( self::CSS_TRANSIENT, $css, HOUR_IN_SECONDS );
        }

        return $css;
    }

    /**
     * AJAX: save UI settings.
     */
    public function ajax_save_settings() {

        check_ajax_referer( 'hikmah_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            Helper::send_json( false, __( 'Permission denied.', 'hikmah-login' ), [], 403 );
        }

        $raw    = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
            ? wp_unslash( $_POST['settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            : [];

        // Partial saves: only overwrite the keys the client actually sent,
        // keeping every other stored setting intact.
        $merged = wp_parse_args( $raw, $this->get_settings() );
        $clean  = $this->sanitize_ui_settings( $merged );

        $this->save_ui_settings( $clean );

        Helper::send_json(
            true,
            __( 'UI settings saved.', 'hikmah-login' ),
            [ 'settings' => $this->get_settings() ]
        );
    }

    /**
     * AJAX: return settings + generated CSS for the live preview.
     */
    public function ajax_get_preview_data() {

        check_ajax_referer( 'hikmah_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            Helper::send_json( false, __( 'Permission denied.', 'hikmah-login' ), [], 403 );
        }

        Helper::send_json(
            true,
            __( 'Preview data loaded.', 'hikmah-login' ),
            [
                'settings' => $this->get_settings(),
                'css'      => $this->generate_dynamic_css( true ),
                'defaults' => self::get_defaults(),
            ]
        );
    }

    /**
     * AJAX: upload a logo image via the media library.
     */
    public function ajax_upload_logo() {

        check_ajax_referer( 'hikmah_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            Helper::send_json( false, __( 'Permission denied.', 'hikmah-login' ), [], 403 );
        }

        if ( empty( $_FILES['logo'] ) || empty( $_FILES['logo']['name'] ) ) {
            Helper::send_json( false, __( 'No file selected.', 'hikmah-login' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'logo', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            Helper::send_json( false, $attachment_id->get_error_message() );
        }

        $url = wp_get_attachment_image_url( $attachment_id, 'large' );

        Helper::send_json(
            true,
            __( 'Logo uploaded.', 'hikmah-login' ),
            [
                'id'  => $attachment_id,
                'url' => $url ? $url : wp_get_attachment_url( $attachment_id ),
            ]
        );
    }

    /**
     * Get the CSS font stack for a font-family slug.
     *
     * @param string $slug Font family slug.
     * @return string
     */
    public function font_stack( $slug ) {

        $stacks = [
            'system'  => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans Bengali', 'Hind Siliguri', sans-serif",
            'inter'   => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Noto Sans Bengali', 'Hind Siliguri', sans-serif",
            'sans'    => "'Segoe UI', system-ui, -apple-system, 'Helvetica Neue', Arial, 'Noto Sans Bengali', 'Hind Siliguri', sans-serif",
            'serif'   => "Georgia, 'Times New Roman', Times, serif",
            'mono'    => "ui-monospace, SFMono-Regular, Menlo, Consolas, 'Courier New', monospace",
            'bengali' => "'Hind Siliguri', 'Noto Sans Bengali', 'SolaimanLipi', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        ];

        return isset( $stacks[ $slug ] ) ? $stacks[ $slug ] : $stacks['system'];
    }

    /**
     * Validate a CSS color value (hex, rgb(), rgba()).
     *
     * @param string $value Color value.
     * @return string|false Valid color or false.
     */
    public static function sanitize_color_value( $value ) {

        $value = trim( (string) $value );

        if ( '' === $value ) {
            return false;
        }

        if ( 0 === strpos( $value, '#' ) ) {
            return sanitize_hex_color( $value );
        }

        if ( preg_match( '/^rgba?\([^)]*\)$/i', $value ) ) {
            return $value;
        }

        return false;
    }

    /**
     * Validate a hex color.
     *
     * @param string $hex Hex color.
     * @return bool
     */
    public static function validate_hex( $hex ) {
        return (bool) sanitize_hex_color( $hex );
    }

    /**
     * Lighten a hex color by a percentage.
     *
     * @param string $hex     Hex color (#rgb or #rrggbb).
     * @param int    $percent 0–100.
     * @return string
     */
    public static function lighten_color( $hex, $percent ) {
        return self::adjust_color( $hex, $percent );
    }

    /**
     * Darken a hex color by a percentage.
     *
     * @param string $hex     Hex color.
     * @param int    $percent 0–100.
     * @return string
     */
    public static function darken_color( $hex, $percent ) {
        return self::adjust_color( $hex, 0 - abs( $percent ) );
    }

    /**
     * Adjust (lighten/darken) a hex color.
     *
     * @param string $hex     Hex color.
     * @param int    $percent Positive = lighter, negative = darker.
     * @return string Adjusted hex color.
     */
    public static function adjust_color( $hex, $percent ) {

        $hex = ltrim( trim( $hex ), '#' );

        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
            return '#' . $hex;
        }

        $percent = max( -100, min( 100, (int) $percent ) );
        $r       = hexdec( substr( $hex, 0, 2 ) );
        $g       = hexdec( substr( $hex, 2, 2 ) );
        $b       = hexdec( substr( $hex, 4, 2 ) );

        $adjust = function ( $channel ) use ( $percent ) {
            if ( 0 === $percent ) {
                return $channel;
            }
            return (int) round( $channel + ( $channel * ( $percent / 100 ) ) );
        };

        $clamp = function ( $channel ) {
            return max( 0, min( 255, $channel ) );
        };

        $r = $clamp( $adjust( $r ) );
        $g = $clamp( $adjust( $g ) );
        $b = $clamp( $adjust( $b ) );

        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }
}