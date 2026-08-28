<?php
/**
 * Validator Class
 *
 * Comprehensive input validation for all user-submitted data.
 * Used in Login, Registration, Password Reset, and Profile forms.
 *
 * @package Hikmah_Login
 * @subpackage Helpers
 * @since   1.0.0
 */

namespace Hikmah_Login\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Validator {

    /**
     * Validation errors collected during validation.
     *
     * @var array
     */
    private $errors = [];

    /**
     * Get all validation errors.
     *
     * @return array Associative array of field => error messages.
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Check if validation passed (no errors).
     *
     * @return bool
     */
    public function is_valid() {
        return empty( $this->errors );
    }

    /**
     * Add an error for a specific field.
     *
     * @param string $field   Field name.
     * @param string $message Error message.
     */
    private function add_error( $field, $message ) {
        if ( ! isset( $this->errors[ $field ] ) ) {
            $this->errors[ $field ] = [];
        }
        $this->errors[ $field ][] = $message;
    }

    /**
     * Get the first error message (for simple display).
     *
     * @return string
     */
    public function get_first_error() {
        if ( empty( $this->errors ) ) {
            return '';
        }
        $first_field = reset( $this->errors );
        return $first_field[0];
    }

    /**
     * Get all errors as a flat array of strings.
     *
     * @return array
     */
    public function get_all_error_messages() {
        $messages = [];
        foreach ( $this->errors as $field_errors ) {
            foreach ( $field_errors as $error ) {
                $messages[] = $error;
            }
        }
        return $messages;
    }

    /**
     * =============================================
     * FIELD VALIDATION METHODS
     * =============================================
     */

    /**
     * Validate that a field is not empty.
     *
     * @param string $field Field name.
     * @param mixed  $value Field value.
     * @param string $label Human-readable field label.
     * @return self
     */
    public function required( $field, $value, $label = '' ) {

        $label = $label ?: $field;

        if ( is_string( $value ) ) {
            $value = trim( $value );
        }

        if ( empty( $value ) && '0' !== (string) $value ) {
            $this->add_error(
                $field,
                sprintf(
                    /* translators: %s: Field label */
                    __( '%s is required.', 'hikmah-login' ),
                    $label
                )
            );
        }

        return $this;
    }

    /**
     * Validate an email address.
     *
     * @param string $field Field name.
     * @param string $value Email value.
     * @param bool   $check_exists Also check if email exists in WP.
     * @return self
     */
    public function email( $field, $value, $check_exists = false ) {

        $value = trim( $value );

        if ( empty( $value ) ) {
            return $this; // Use required() for empty check
        }

        if ( ! is_email( $value ) ) {
            $this->add_error(
                $field,
                __( 'Please enter a valid email address.', 'hikmah-login' )
            );
            return $this;
        }

        // Check for disposable email domains
        if ( $this->is_disposable_email( $value ) ) {
            $this->add_error(
                $field,
                __( 'Disposable email addresses are not allowed.', 'hikmah-login' )
            );
            return $this;
        }

        // Check if email already exists
        if ( $check_exists && email_exists( $value ) ) {
            $this->add_error(
                $field,
                __( 'This email address is already registered.', 'hikmah-login' )
            );
        }

        return $this;
    }

    /**
     * Validate a username.
     *
     * @param string $field        Field name.
     * @param string $value        Username value.
     * @param bool   $check_exists Check if username exists in WP.
     * @param int    $min_length   Minimum length.
     * @param int    $max_length   Maximum length.
     * @return self
     */
    public function username( $field, $value, $check_exists = false, $min_length = 3, $max_length = 60 ) {

        $value = trim( $value );

        if ( empty( $value ) ) {
            return $this;
        }

        // Length check
        $length = mb_strlen( $value );

        if ( $length < $min_length ) {
            $this->add_error(
                $field,
                sprintf(
                    /* translators: %d: Minimum length */
                    __( 'Username must be at least %d characters long.', 'hikmah-login' ),
                    $min_length
                )
            );
            return $this;
        }

        if ( $length > $max_length ) {
            $this->add_error(
                $field,
                sprintf(
                    /* translators: %d: Maximum length */
                    __( 'Username cannot exceed %d characters.', 'hikmah-login' ),
                    $max_length
                )
            );
            return $this;
        }

        // WordPress username validation
        $sanitized = sanitize_user( $value, true );

        if ( $sanitized !== $value ) {
            $this->add_error(
                $field,
                __( 'Username can only contain letters, numbers, spaces, underscores, hyphens, periods, and @ symbols.', 'hikmah-login' )
            );
            return $this;
        }

        // Check reserved usernames
        $reserved = $this->get_reserved_usernames();
        if ( in_array( strtolower( $value ), $reserved, true ) ) {
            $this->add_error(
                $field,
                __( 'This username is reserved and cannot be used.', 'hikmah-login' )
            );
            return $this;
        }

        // Check if username exists
        if ( $check_exists && username_exists( $value ) ) {
            $this->add_error(
                $field,
                __( 'This username is already taken.', 'hikmah-login' )
            );
        }

        return $this;
    }

    /**
     * Validate a password.
     *
     * @param string $field      Field name.
     * @param string $value      Password value.
     * @param array  $rules      Custom rules override.
     * @return self
     */
    public function password( $field, $value, $rules = [] ) {

        if ( empty( $value ) ) {
            return $this;
        }

        // Default rules (can be overridden)
        $defaults = [
            'min_length'   => 8,
            'max_length'   => 128,
            'require_upper' => true,
            'require_lower' => true,
            'require_number' => true,
            'require_special' => false,
        ];

        $rules = wp_parse_args( $rules, $defaults );

        // Length
        $length = mb_strlen( $value );

        if ( $length < $rules['min_length'] ) {
            $this->add_error(
                $field,
                sprintf(
                    /* translators: %d: Minimum length */
                    __( 'Password must be at least %d characters long.', 'hikmah-login' ),
                    $rules['min_length']
                )
            );
        }

        if ( $length > $rules['max_length'] ) {
            $this->add_error(
                $field,
                sprintf(
                    /* translators: %d: Maximum length */
                    __( 'Password cannot exceed %d characters.', 'hikmah-login' ),
                    $rules['max_length']
                )
            );
        }

        // Uppercase
        if ( $rules['require_upper'] && ! preg_match( '/[A-Z]/', $value ) ) {
            $this->add_error(
                $field,
                __( 'Password must contain at least one uppercase letter.', 'hikmah-login' )
            );
        }

        // Lowercase
        if ( $rules['require_lower'] && ! preg_match( '/[a-z]/', $value ) ) {
            $this->add_error(
                $field,
                __( 'Password must contain at least one lowercase letter.', 'hikmah-login' )
            );
        }

        // Number
        if ( $rules['require_number'] && ! preg_match( '/[0-9]/', $value ) ) {
            $this->add_error(
                $field,
                __( 'Password must contain at least one number.', 'hikmah-login' )
            );
        }

        // Special character
        if ( $rules['require_special'] && ! preg_match( '/[^A-Za-z0-9]/', $value ) ) {
            $this->add_error(
                $field,
                __( 'Password must contain at least one special character.', 'hikmah-login' )
            );
        }

        // Check against common passwords
        if ( $this->is_common_password( $value ) ) {
            $this->add_error(
                $field,
                __( 'This password is too common. Please choose a stronger password.', 'hikmah-login' )
            );
        }

        return $this;
    }

    /**
     * Validate password confirmation match.
     *
     * @param string $field    Confirm field name.
     * @param string $value    Confirm password value.
     * @param string $password Original password value.
     * @return self
     */
    public function password_confirm( $field, $value, $password ) {

        if ( empty( $value ) ) {
            return $this;
        }

        if ( $value !== $password ) {
            $this->add_error(
                $field,
                __( 'Passwords do not match.', 'hikmah-login' )
            );
        }

        return $this;
    }

    /**
     * Validate string length.
     *
     * @param string $field Field name.
     * @param string $value Value.
     * @param int    $min   Minimum length.
     * @param int    $max   Maximum length.
     * @param string $label Field label.
     * @return self
     */
    public function length( $field, $value, $min = 0, $max = 0, $label = '' ) {

        $value = trim( $value );
        $label = $label ?: $field;

        if ( empty( $value ) ) {
            return $this;
        }

        $length = mb_strlen( $value );

        if ( $min > 0 && $length < $min ) {
            $this->add_error(
                $field,
                sprintf(
                    /* translators: 1: Field label 2: Minimum length */
                    __( '%1$s must be at least %2$d characters.', 'hikmah-login' ),
                    $label,
                    $min
                )
            );
        }

        if ( $max > 0 && $length > $max ) {
            $this->add_error(
                $field,
                sprintf(
                    /* translators: 1: Field label 2: Maximum length */
                    __( '%1$s cannot exceed %2$d characters.', 'hikmah-login' ),
                    $label,
                    $max
                )
            );
        }

        return $this;
    }

    /**
     * Validate that a value is in an allowed list.
     *
     * @param string $field   Field name.
     * @param mixed  $value   Value.
     * @param array  $allowed Allowed values.
     * @param string $label   Field label.
     * @return self
     */
    public function in_list( $field, $value, $allowed, $label = '' ) {

        $label = $label ?: $field;

        if ( ! in_array( $value, $allowed, true ) ) {
            $this->add_error(
                $field,
                sprintf(
                    /* translators: %s: Field label */
                    __( 'Invalid value for %s.', 'hikmah-login' ),
                    $label
                )
            );
        }

        return $this;
    }

    /**
     * Validate a URL.
     *
     * @param string $field Field name.
     * @param string $value URL value.
     * @return self
     */
    public function url( $field, $value ) {

        $value = trim( $value );

        if ( empty( $value ) ) {
            return $this;
        }

        if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
            $this->add_error(
                $field,
                __( 'Please enter a valid URL.', 'hikmah-login' )
            );
        }

        return $this;
    }

    /**
     * Validate terms acceptance (checkbox).
     *
     * @param string $field Field name.
     * @param mixed  $value Checkbox value.
     * @return self
     */
    public function terms_accepted( $field, $value ) {

        if ( empty( $value ) || $value !== 'on' && $value !== '1' && $value !== 'yes' ) {
            $this->add_error(
                $field,
                __( 'You must accept the Terms and Conditions.', 'hikmah-login' )
            );
        }

        return $this;
    }

    /**
     * Validate honeypot field (anti-spam).
     *
     * @param string $field Field name.
     * @param string $value Should be empty.
     * @return self
     */
    public function honeypot( $field, $value ) {

        if ( ! empty( $value ) ) {
            // Don't reveal that it's a honeypot — generic error
            $this->add_error(
                'form',
                __( 'Form submission failed. Please try again.', 'hikmah-login' )
            );
        }

        return $this;
    }

    /**
     * =============================================
     * COMPOSITE VALIDATION METHODS
     * =============================================
     */

    /**
     * Validate a complete login form.
     *
     * @param array $data Form data.
     * @return self
     */
    public function validate_login( $data ) {

        $this->required( 'username', $data['username'] ?? '', __( 'Username or Email', 'hikmah-login' ) );
        $this->required( 'password', $data['password'] ?? '', __( 'Password', 'hikmah-login' ) );

        // Honeypot
        if ( isset( $data['website_url'] ) ) {
            $this->honeypot( 'website_url', $data['website_url'] );
        }

        /**
         * Allow third-party validation on login.
         *
         * @param Validator $validator Validator instance.
         * @param array     $data      Form data.
         */
        do_action( 'hikmah_login_validate_login', $this, $data );

        return $this;
    }

    /**
     * Validate a complete registration form.
     *
     * @param array $data Form data.
     * @return self
     */
    public function validate_registration( $data ) {

        $this->required( 'username', $data['username'] ?? '', __( 'Username', 'hikmah-login' ) );
        $this->username( 'username', $data['username'] ?? '', true );

        $this->required( 'email', $data['email'] ?? '', __( 'Email', 'hikmah-login' ) );
        $this->email( 'email', $data['email'] ?? '', true );

        $this->required( 'password', $data['password'] ?? '', __( 'Password', 'hikmah-login' ) );
        $this->password( 'password', $data['password'] ?? '' );

        $this->required( 'confirm_password', $data['confirm_password'] ?? '', __( 'Confirm Password', 'hikmah-login' ) );
        $this->password_confirm( 'confirm_password', $data['confirm_password'] ?? '', $data['password'] ?? '' );

        // Optional fields
        if ( ! empty( $data['first_name'] ) ) {
            $this->length( 'first_name', $data['first_name'], 1, 50, __( 'First Name', 'hikmah-login' ) );
        }

        if ( ! empty( $data['last_name'] ) ) {
            $this->length( 'last_name', $data['last_name'], 1, 50, __( 'Last Name', 'hikmah-login' ) );
        }

        // Terms
        if ( Helper::is_feature_enabled( 'terms_required' ) ) {
            $this->terms_accepted( 'terms', $data['terms'] ?? '' );
        }

        // Honeypot
        if ( isset( $data['website_url'] ) ) {
            $this->honeypot( 'website_url', $data['website_url'] );
        }

        /**
         * Allow third-party validation on registration.
         *
         * @param Validator $validator Validator instance.
         * @param array     $data      Form data.
         */
        do_action( 'hikmah_login_validate_registration', $this, $data );

        return $this;
    }

    /**
     * Validate forgot password form.
     *
     * @param array $data Form data.
     * @return self
     */
    public function validate_forgot_password( $data ) {

        $this->required( 'user_login', $data['user_login'] ?? '', __( 'Email or Username', 'hikmah-login' ) );

        $user_login = trim( $data['user_login'] ?? '' );

        if ( ! empty( $user_login ) ) {
            if ( is_email( $user_login ) ) {
                $this->email( 'user_login', $user_login );
            }
        }

        return $this;
    }

    /**
     * =============================================
     * INTERNAL HELPER METHODS
     * =============================================
     */

    /**
     * Check if email is from a disposable domain.
     *
     * @param string $email Email address.
     * @return bool
     */
    private function is_disposable_email( $email ) {

        $domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );

        // Common disposable email domains
        $disposable_domains = [
            'mailinator.com', 'guerrillamail.com', 'tempmail.com',
            'throwaway.email', 'yopmail.com', 'sharklasers.com',
            'guerrillamailblock.com', 'grr.la', 'dispostable.com',
            'trashmail.com', 'fakeinbox.com', 'tempail.com',
            'maildrop.cc', 'discard.email', 'temp-mail.org',
            '10minutemail.com', 'minutemail.com', 'emailondeck.com',
        ];

        /**
         * Filter disposable email domains.
         *
         * @param array $disposable_domains List of domains.
         */
        $disposable_domains = apply_filters(
            'hikmah_login_disposable_email_domains',
            $disposable_domains
        );

        return in_array( $domain, $disposable_domains, true );
    }

    /**
     * Check if password is in common passwords list.
     *
     * @param string $password Plain password.
     * @return bool
     */
    private function is_common_password( $password ) {

        $common = [
            'password', '123456', '12345678', 'qwerty', 'abc123',
            'monkey', '1234567', 'letmein', 'trustno1', 'dragon',
            'baseball', 'iloveyou', 'master', 'sunshine', 'ashley',
            'bailey', 'passw0rd', 'shadow', '123123', '654321',
            'superman', 'qazwsx', 'michael', 'football', 'password1',
            'password123', 'welcome', 'hello', 'charlie', 'donald',
        ];

        return in_array( strtolower( $password ), $common, true );
    }

    /**
     * Get list of reserved usernames.
     *
     * @return array
     */
    private function get_reserved_usernames() {

        $reserved = [
            'admin', 'administrator', 'root', 'webmaster', 'support',
            'info', 'contact', 'help', 'moderator', 'mod',
            'system', 'null', 'undefined', 'test', 'guest',
            'superadmin', 'editor', 'author', 'contributor', 'subscriber',
        ];

        /**
         * Filter reserved usernames.
         *
         * @param array $reserved Reserved usernames.
         */
        return apply_filters( 'hikmah_login_reserved_usernames', $reserved );
    }
}
