<?php
/**
 * Sanitizer Class
 *
 * Input sanitization for all user-submitted data.
 * Ensures all data is clean before database storage or processing.
 *
 * @package Hikmah_Login
 * @subpackage Helpers
 * @since   1.0.0
 */

namespace Hikmah_Login\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sanitizer {

    /**
     * =============================================
     * BASIC SANITIZATION
     * =============================================
     */

    /**
     * Sanitize a plain text field.
     *
     * Removes HTML tags, extra whitespace, and special characters.
     *
     * @param string $value Raw input.
     * @return string Sanitized text.
     */
    public static function text( $value ) {
        return sanitize_text_field( $value );
    }

    /**
     * Sanitize a textarea field.
     *
     * @param string $value Raw input.
     * @return string Sanitized textarea.
     */
    public static function textarea( $value ) {
        return sanitize_textarea_field( $value );
    }

    /**
     * Sanitize an email address.
     *
     * @param string $value Raw email.
     * @return string Sanitized email or empty string.
     */
    public static function email( $value ) {
        return sanitize_email( trim( $value ) );
    }

    /**
     * Sanitize a URL.
     *
     * @param string $value Raw URL.
     * @return string Sanitized URL.
     */
    public static function url( $value ) {
        return esc_url_raw( trim( $value ) );
    }

    /**
     * Sanitize a username.
     *
     * @param string $value Raw username.
     * @return string Sanitized username.
     */
    public static function username( $value ) {
        return sanitize_user( trim( $value ), false );
    }

    /**
     * Sanitize a key/slug.
     *
     * @param string $value Raw key.
     * @return string Sanitized key.
     */
    public static function key( $value ) {
        return sanitize_key( $value );
    }

    /**
     * Sanitize a title.
     *
     * @param string $value Raw title.
     * @return string Sanitized title.
     */
    public static function title( $value ) {
        return sanitize_title( $value );
    }

    /**
     * Sanitize a file name.
     *
     * @param string $value Raw filename.
     * @return string Sanitized filename.
     */
    public static function filename( $value ) {
        return sanitize_file_name( $value );
    }

    /**
     * Sanitize an integer.
     *
     * @param mixed $value Raw value.
     * @return int
     */
    public static function int( $value ) {
        return absint( $value );
    }

    /**
     * Sanitize a float/decimal.
     *
     * @param mixed $value Raw value.
     * @return float
     */
    public static function float( $value ) {
        return floatval( $value );
    }

    /**
     * Sanitize a boolean value.
     *
     * @param mixed $value Raw value.
     * @return bool
     */
    public static function bool( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Sanitize a hex color code.
     *
     * @param string $value Raw color.
     * @return string Sanitized hex color.
     */
    public static function hex_color( $value ) {
        return sanitize_hex_color( $value );
    }

    /**
     * Sanitize a class name.
     *
     * @param string $value Raw class name.
     * @return string
     */
    public static function html_class( $value ) {
        return sanitize_html_class( $value );
    }

    /**
     * Sanitize a MIME type.
     *
     * @param string $value Raw MIME type.
     * @return string
     */
    public static function mime_type( $value ) {
        return sanitize_mime_type( $value );
    }

    /**
     * =============================================
     * HTML SANITIZATION
     * =============================================
     */

    /**
     * Sanitize HTML with allowed tags (for rich text).
     *
     * @param string $value        Raw HTML.
     * @param array  $allowed_tags Custom allowed tags.
     * @return string Sanitized HTML.
     */
    public static function html( $value, $allowed_tags = [] ) {

        if ( empty( $allowed_tags ) ) {
            $allowed_tags = [
                'p'      => [],
                'br'     => [],
                'strong' => [],
                'em'     => [],
                'a'      => [
                    'href'   => true,
                    'title'  => true,
                    'target' => true,
                    'rel'    => true,
                ],
                'ul'     => [],
                'ol'     => [],
                'li'     => [],
                'span'   => [ 'class' => true ],
            ];
        }

        return wp_kses( $value, $allowed_tags );
    }

    /**
     * Strip ALL HTML tags.
     *
     * @param string $value Raw input.
     * @return string Plain text.
     */
    public static function strip_html( $value ) {
        return wp_strip_all_tags( $value );
    }

    /**
     * =============================================
     * COMPOSITE SANITIZATION
     * =============================================
     */

    /**
     * Sanitize login form data.
     *
     * @param array $data Raw form data.
     * @return array Sanitized data.
     */
    public static function sanitize_login_data( $data ) {

        $sanitized = [];

        $sanitized['username'] = self::text( $data['username'] ?? '' );
        $sanitized['password'] = $data['password'] ?? ''; // Password is NOT sanitized
        $sanitized['remember'] = self::bool( $data['remember'] ?? false );
        $sanitized['redirect'] = self::url( $data['redirect'] ?? '' );

        return $sanitized;
    }

    /**
     * Sanitize registration form data.
     *
     * @param array $data Raw form data.
     * @return array Sanitized data.
     */
    public static function sanitize_registration_data( $data ) {

        $sanitized = [];

        $sanitized['username']         = self::username( $data['username'] ?? '' );
        $sanitized['email']            = self::email( $data['email'] ?? '' );
        $sanitized['password']         = $data['password'] ?? ''; // Not sanitized
        $sanitized['confirm_password'] = $data['confirm_password'] ?? '';
        $sanitized['first_name']       = self::text( $data['first_name'] ?? '' );
        $sanitized['last_name']        = self::text( $data['last_name'] ?? '' );
        $sanitized['display_name']     = self::text( $data['display_name'] ?? '' );
        $sanitized['website']          = self::url( $data['website'] ?? '' );
        $sanitized['bio']              = self::textarea( $data['bio'] ?? '' );
        $sanitized['terms']            = self::key( $data['terms'] ?? '' );

        // Custom fields
        if ( isset( $data['custom_fields'] ) && is_array( $data['custom_fields'] ) ) {
            $sanitized['custom_fields'] = self::sanitize_array( $data['custom_fields'] );
        }

        return $sanitized;
    }

    /**
     * Sanitize forgot password form data.
     *
     * @param array $data Raw form data.
     * @return array Sanitized data.
     */
    public static function sanitize_forgot_password_data( $data ) {

        $sanitized = [];

        $sanitized['user_login'] = self::text( $data['user_login'] ?? '' );

        return $sanitized;
    }

    /**
     * Sanitize reset password form data.
     *
     * @param array $data Raw form data.
     * @return array Sanitized data.
     */
    public static function sanitize_reset_password_data( $data ) {

        $sanitized = [];

        $sanitized['password']         = $data['password'] ?? '';
        $sanitized['confirm_password'] = $data['confirm_password'] ?? '';
        $sanitized['token']            = self::key( $data['token'] ?? '' );
        $sanitized['user_id']          = self::int( $data['user_id'] ?? 0 );

        return $sanitized;
    }

    /**
     * Sanitize profile update data.
     *
     * @param array $data Raw form data.
     * @return array Sanitized data.
     */
    public static function sanitize_profile_data( $data ) {

        $sanitized = [];

        $sanitized['first_name']   = self::text( $data['first_name'] ?? '' );
        $sanitized['last_name']    = self::text( $data['last_name'] ?? '' );
        $sanitized['display_name'] = self::text( $data['display_name'] ?? '' );
        $sanitized['nickname']     = self::text( $data['nickname'] ?? '' );
        $sanitized['description']  = self::textarea( $data['description'] ?? '' );
        $sanitized['url']          = self::url( $data['url'] ?? '' );

        // Email change requires extra validation
        if ( isset( $data['email'] ) ) {
            $sanitized['email'] = self::email( $data['email'] );
        }

        return $sanitized;
    }

    /**
     * =============================================
     * UTILITY SANITIZATION
     * =============================================
     */

    /**
     * Recursively sanitize an array of strings.
     *
     * @param array $data Raw array.
     * @return array Sanitized array.
     */
    public static function sanitize_array( $data ) {

        $sanitized = [];

        foreach ( $data as $key => $value ) {
            $clean_key = self::key( $key );

            if ( is_array( $value ) ) {
                $sanitized[ $clean_key ] = self::sanitize_array( $value );
            } elseif ( is_string( $value ) ) {
                $sanitized[ $clean_key ] = self::text( $value );
            } elseif ( is_int( $value ) ) {
                $sanitized[ $clean_key ] = self::int( $value );
            } elseif ( is_bool( $value ) ) {
                $sanitized[ $clean_key ] = self::bool( $value );
            } else {
                $sanitized[ $clean_key ] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize an IP address.
     *
     * @param string $ip Raw IP.
     * @return string Valid IP or '0.0.0.0'.
     */
    public static function ip( $ip ) {

        $ip = trim( $ip );

        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
            return $ip;
        }

        return '0.0.0.0';
    }

    /**
     * Sanitize a user agent string.
     *
     * @param string $ua Raw user agent.
     * @return string Sanitized user agent (max 500 chars).
     */
    public static function user_agent( $ua ) {
        return substr( self::text( $ua ), 0, 500 );
    }

    /**
     * Sanitize a redirect URL (must be local).
     *
     * @param string $url Raw redirect URL.
     * @return string Safe local URL or home URL.
     */
    public static function redirect_url( $url ) {

        $url = self::url( $url );

        if ( empty( $url ) ) {
            return home_url( '/' );
        }

        // Only allow local redirects
        if ( ! wp_validate_redirect( $url, false ) ) {
            return home_url( '/' );
        }

        return $url;
    }

    /**
     * Sanitize settings data from admin form.
     *
     * @param array $data Raw settings data.
     * @return array Sanitized settings.
     */
    public static function sanitize_settings( $data ) {

        $sanitized = [];

        // String settings
        $string_fields = [
            'login_redirect_url', 'logout_redirect_url',
            'default_user_role', 'captcha_type',
            'recaptcha_site_key', 'recaptcha_secret_key',
            'google_client_id', 'google_client_secret',
            'facebook_app_id', 'facebook_app_secret',
            'login_logo', 'login_bg_color',
            'email_from_name', 'email_from_address',
            'custom_css',
        ];

        foreach ( $string_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                if ( strpos( $field, 'url' ) !== false ) {
                    $sanitized[ $field ] = self::url( $data[ $field ] );
                } elseif ( strpos( $field, 'color' ) !== false ) {
                    $sanitized[ $field ] = self::hex_color( $data[ $field ] );
                } elseif ( strpos( $field, 'email' ) !== false ) {
                    $sanitized[ $field ] = self::email( $data[ $field ] );
                } elseif ( $field === 'custom_css' ) {
                    $sanitized[ $field ] = wp_strip_all_tags( $data[ $field ] );
                } else {
                    $sanitized[ $field ] = self::text( $data[ $field ] );
                }
            }
        }

        // Boolean/yes-no settings
        $bool_fields = [
            'login_enabled', 'registration_enabled',
            'email_verification_required', 'captcha_enabled',
            '2fa_enabled', 'google_login_enabled',
            'facebook_login_enabled', 'login_logging_enabled',
            'terms_required',
        ];

        foreach ( $bool_fields as $field ) {
            $sanitized[ $field ] = isset( $data[ $field ] ) && $data[ $field ] === 'yes'
                ? 'yes'
                : 'no';
        }

        // Integer settings
        $int_fields = [
            'max_login_attempts', 'lockout_duration',
            'login_form_width', 'log_retention_days',
        ];

        foreach ( $int_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitized[ $field ] = self::int( $data[ $field ] );
            }
        }

        return $sanitized;
    }
}
