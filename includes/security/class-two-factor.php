<?php
/**
 * Two-Factor Authentication Manager
 *
 * Central 2FA orchestrator: email OTP, TOTP (authenticator), hashed
 * single-use backup codes, setup wizard shortcode/view, and admin
 * enforcement.
 *
 * AJAX endpoints are registered through the unified Ajax_Controller
 * (action: hikmah_ajax) to keep nonce/rate-limit handling consistent
 * with the rest of the plugin.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

namespace Hikmah_Login\Security;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;
use Hikmah_Login\Database\DB_Manager;
use Hikmah_Login\Auth\Auth_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Two_Factor {

	use Singleton;
	use Hooks;

	/**
	 * Supported authentication methods.
	 */
	const METHOD_EMAIL          = 'email';
	const METHOD_AUTHENTICATOR  = 'authenticator';
	const METHOD_SMS            = 'sms';

	/**
	 * Database manager instance.
	 *
	 * @var DB_Manager
	 */
	private $db;

	/**
	 * OTP validity (seconds).
	 *
	 * @var int
	 */
	private $otp_lifetime = 600;

	/**
	 * OTP rate limit: max sends per window.
	 *
	 * @var int
	 */
	private $otp_max_per_window = 3;

	/**
	 * OTP rate limit window (seconds).
	 *
	 * @var int
	 */
	private $otp_window = 900;

	/**
	 * Constructor.
	 *
	 * Initializes the database manager and registers hooks unless the
	 * 2FA feature is disabled.
	 */
	private function __construct() {
		$this->db = new DB_Manager();

		if ( ! Helper::is_feature_enabled( '2fa_enabled' ) ) {
			return;
		}

		$this->register_hooks();
	}

	/**
	 * Register all hooks.
	 */
	private function register_hooks() {
		$this->add_action( 'hikmah_ajax_register_actions', 'register_ajax_actions' );
		$this->add_action( 'show_user_profile', 'render_setup_wizard' );
		$this->add_action( 'admin_notices', 'render_admin_2fa_notice' );
		$this->add_action( 'wp_login', 'check_2fa_setup_required', 10, 2 );
	}

	/**
	 * =============================================
	 * AJAX ACTION REGISTRATION
	 * =============================================
	 */

	/**
	 * Register 2FA actions with the unified Ajax_Controller.
	 *
	 * @param \Hikmah_Login\Ajax\Ajax_Controller $controller Controller instance.
	 */
	public function register_ajax_actions( $controller ) {

		$controller->register_action( '2fa_setup', [
			'handler'    => [ $this, 'ajax_setup_2fa' ],
			'auth'       => 'logged_in',
			'nonce'      => 'hikmah_login_nonce',
			'rate_limit' => 10,
			'window'     => 60,
		]);

		$controller->register_action( '2fa_verify_setup', [
			'handler'    => [ $this, 'ajax_verify_setup' ],
			'auth'       => 'logged_in',
			'nonce'      => 'hikmah_login_nonce',
			'rate_limit' => 10,
			'window'     => 60,
		]);

		$controller->register_action( '2fa_disable', [
			'handler'    => [ $this, 'ajax_disable_2fa' ],
			'auth'       => 'logged_in',
			'nonce'      => 'hikmah_login_nonce',
			'rate_limit' => 5,
			'window'     => 60,
		]);

		$controller->register_action( '2fa_generate_backup', [
			'handler'    => [ $this, 'ajax_generate_backup_codes' ],
			'auth'       => 'logged_in',
			'nonce'      => 'hikmah_login_nonce',
			'rate_limit' => 3,
			'window'     => 60,
		]);

		$controller->register_action( '2fa_send_login_otp', [
			'handler'    => [ $this, 'ajax_send_login_otp' ],
			'auth'       => 'both',
			'nonce'      => 'hikmah_login_nonce',
			'rate_limit' => 3,
			'window'     => 900,
		]);
	}

	/**
	 * =============================================
	 * STATE & READERS
	 * =============================================
	 */

	/**
	 * Check if 2FA is enabled for a user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_user_2fa_enabled( $user_id ) {
		$settings = $this->db->get_2fa_settings( $user_id );
		return $settings && '1' === (string) $settings->is_enabled;
	}

	/**
	 * Get a user's 2FA method.
	 *
	 * @param int $user_id User ID.
	 * @return string Method slug or empty string.
	 */
	public function get_user_2fa_method( $user_id ) {
		$settings = $this->db->get_2fa_settings( $user_id );
		return $settings ? $settings->method : '';
	}

	/**
	 * Count remaining backup codes for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_backup_code_count( $user_id ) {
		$settings = $this->db->get_2fa_settings( $user_id );

		if ( ! $settings || empty( $settings->backup_codes ) ) {
			return 0;
		}

		$codes = json_decode( $settings->backup_codes, true );
		return is_array( $codes ) ? count( $codes ) : 0;
	}

	/**
	 * Whether 2FA is required for a user.
	 *
	 * @param \WP_User $user User object.
	 * @return bool
	 */
	public function is_2fa_required_for_user( $user ) {
		return $this->is_user_2fa_enabled( $user->ID );
	}

	/**
	 * Whether the user still needs to complete 2FA setup (flag set after login).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function needs_2fa_setup( $user_id ) {
		return (bool) get_transient( "hikmah_2fa_setup_needed_{$user_id}" )
			&& ! $this->is_user_2fa_enabled( $user_id );
	}

	/**
	 * =============================================
	 * EMAIL OTP
	 * =============================================
	 */

	/**
	 * Send a one-time passcode to the user's email.
	 *
	 * Honors rate limiting (3 sends / 15 minutes) and stores a hash of
	 * the OTP in a short-lived transient (single use).
	 *
	 * @param int $user_id User ID.
	 * @return bool True if the mail was accepted.
	 */
	public function send_email_otp( $user_id ) {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		if ( ! $this->check_otp_rate_limit( $user_id ) ) {
			return false;
		}

		$otp = Helper::generate_otp( 6 );

		set_transient(
			"hikmah_2fa_otp_hash_{$user_id}",
			Helper::hash_token( $otp ),
			$this->otp_lifetime
		);

		$sent = $this->send_otp_email( $user, $otp );

		if ( ! $sent ) {
			delete_transient( "hikmah_2fa_otp_hash_{$user_id}" );
		}

		return $sent;
	}

	/**
	 * Verify an email OTP (single use).
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    User-provided code.
	 * @return bool
	 */
	public function verify_email_otp( $user_id, $code ) {

		$hash = get_transient( "hikmah_2fa_otp_hash_{$user_id}" );

		if ( ! $hash ) {
			return false;
		}

		$valid = Helper::verify_token( $code, $hash );

		// Single use: always consume.
		delete_transient( "hikmah_2fa_otp_hash_{$user_id}" );

		return $valid;
	}

	/**
	 * Enforce the email OTP rate limit.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if the send is allowed.
	 */
	private function check_otp_rate_limit( $user_id ) {

		$key = "hikmah_2fa_otp_{$user_id}";
		$data = get_transient( $key );

		if ( ! is_array( $data ) ) {
			$data = [ 'count' => 0, 'first' => time() ];
		}

		if ( $data['count'] >= $this->otp_max_per_window ) {
			return false;
		}

		$data['count']++;

		set_transient( $key, $data, $this->otp_window );

		return true;
	}

	/**
	 * Send the OTP email.
	 *
	 * @param \WP_User $user User object.
	 * @param string   $otp  6-digit code.
	 * @return bool
	 */
	private function send_otp_email( $user, $otp ) {

		$site_name = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: Site name */
			__( '[%s] Your Verification Code', 'hikmah-login' ),
			$site_name
		);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_option( 'hikmah_email_from_name', $site_name )
			       . ' <' . get_option( 'hikmah_email_from_address', get_option( 'admin_email' ) ) . '>',
		];

		$message = $this->get_otp_email_html( $user, $otp, $site_name );

		/**
		 * Filter OTP email arguments.
		 *
		 * @since 1.0.0
		 * @param array    $args Email arguments.
		 * @param \WP_User $user User object.
		 */
		$args = apply_filters( 'hikmah_2fa_email_args', [
			'to'      => $user->user_email,
			'subject' => $subject,
			'message' => $message,
			'headers' => $headers,
		], $user );

		$sent = wp_mail( $args['to'], $args['subject'], $args['message'], $args['headers'] );

		if ( $sent ) {
			Helper::log( "2FA email OTP sent: User ID {$user->ID}" );
		}

		return $sent;
	}

	/**
	 * Build the OTP email HTML.
	 *
	 * @param \WP_User $user      User object.
	 * @param string   $otp       Verification code.
	 * @param string   $site_name Site name.
	 * @return string
	 */
	private function get_otp_email_html( $user, $otp, $site_name ) {

		$expiry = (int) floor( $this->otp_lifetime / 60 );

		$html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#1f2937">';
		$html .= '<h2 style="margin:0 0 8px;color:#111827">Two-Factor Verification</h2>';
		$html .= '<p style="margin:0 0 20px;font-size:14px;line-height:1.6">';
		$html .= sprintf(
			/* translators: 1: Display name, 2: Site name */
			esc_html__( 'Hi %1$s, use the code below to complete your sign-in to %2$s.', 'hikmah-login' ),
			esc_html( $user->display_name ),
			esc_html( $site_name )
		);
		$html .= '</p>';
		$html .= '<div style="background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:20px;text-align:center">';
		$html .= '<span style="font-size:28px;font-weight:bold;letter-spacing:6px;color:#111827">' . esc_html( $otp ) . '</span>';
		$html .= '</div>';
		$html .= '<p style="margin:20px 0 0;font-size:13px;color:#6b7280">';
		$html .= sprintf(
			/* translators: %d: Minutes */
			esc_html__( 'This code expires in %d minutes. If you did not request it, you can safely ignore this email.', 'hikmah-login' ),
			$expiry
		);
		$html .= '</p></div>';

		return $html;
	}

	/**
	 * =============================================
	 * TOTP (AUTHENTICATOR APP)
	 * =============================================
	 */

	/**
	 * Generate a new Base32 TOTP secret.
	 *
	 * @param int $length Length in bytes (default 20 = 160 bits).
	 * @return string Base32 secret.
	 */
	public function generate_totp_secret( $length = 20 ) {

		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$secret   = '';

		for ( $i = 0; $i < ceil( $length * 8 / 5 ); $i++ ) {
			$secret .= $alphabet[ wp_rand( 0, 31 ) ];
		}

		return $secret;
	}

	/**
	 * Build the otpauth:// provisioning URI for QR codes.
	 *
	 * @param string $user_login User login.
	 * @param string $secret     Base32 secret.
	 * @return string
	 */
	public function get_totp_uri( $user_login, $secret ) {

		$issuer   = get_bloginfo( 'name' );
		$label    = rawurlencode( $issuer ) . ':' . rawurlencode( $user_login );
		$query    = http_build_query( [
			'secret'    => $secret,
			'issuer'    => $issuer,
			'algorithm' => 'SHA1',
			'digits'    => 6,
			'period'    => 30,
		] );

		return 'otpauth://totp/' . $label . '?' . $query;
	}

	/**
	 * Build a Google Charts QR code URL for a provisioning URI.
	 *
	 * @param string $otpauth_uri Provisioning URI.
	 * @return string
	 */
	public function get_qr_code_url( $otpauth_uri ) {
		return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . rawurlencode( $otpauth_uri );
	}

	/**
	 * Verify a TOTP code (RFC 6238, SHA1, 6 digits, ±30s window).
	 *
	 * @param string $secret Base32 secret.
	 * @param string $code   User-provided code.
	 * @return bool
	 */
	public function verify_totp( $secret, $code ) {

		$time_step = floor( time() / 30 );

		for ( $i = -1; $i <= 1; $i++ ) {
			$expected = $this->calculate_totp( $secret, $time_step + $i );
			if ( hash_equals( $expected, $code ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Calculate a TOTP code for a given time step.
	 *
	 * @param string $secret    Base32 secret.
	 * @param int    $time_step Unix time divided by period.
	 * @return string 6-digit code.
	 */
	private function calculate_totp( $secret, $time_step ) {

		$key = $this->base32_decode( $secret );

		if ( empty( $key ) ) {
			return '000000';
		}

		$binary_time = pack( 'N*', 0, $time_step );
		$hash        = hash_hmac( 'sha1', $binary_time, $key, true );
		$offset      = ord( $hash[19] ) & 0xf;
		$code        = (
			( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 ) |
			( ( ord( $hash[ $offset + 1 ] ) & 0xff ) << 16 ) |
			( ( ord( $hash[ $offset + 2 ] ) & 0xff ) << 8 )  |
			( ord( $hash[ $offset + 3 ] ) & 0xff )
		) % 1000000;

		return str_pad( (string) $code, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Decode a Base32 string (RFC 4648, alphabet A-Z2-7).
	 *
	 * @param string $base32 Base32 string.
	 * @return string Binary secret.
	 */
	private function base32_decode( $base32 ) {

		$base32 = strtoupper( rtrim( (string) $base32, '=' ) );
		$base32 = str_replace( ' ', '', $base32 );

		if ( '' === $base32 || preg_match( '/[^A-Z2-7]/', $base32 ) ) {
			return '';
		}

		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$bits     = '';
		$binary   = '';

		$len = strlen( $base32 );
		for ( $i = 0; $i < $len; $i++ ) {
			$bits .= str_pad( decbin( strpos( $alphabet, $base32[ $i ] ) ), 5, '0', STR_PAD_LEFT );
		}

		$bit_len = strlen( $bits );
		for ( $i = 0; $i + 8 <= $bit_len; $i += 8 ) {
			$binary .= chr( bindec( substr( $bits, $i, 8 ) ) );
		}

		return $binary;
	}

	/**
	 * =============================================
	 * BACKUP CODES
	 * =============================================
	 */

	/**
	 * Generate a set of backup codes.
	 *
	 * @param int $count Number of codes (default 10).
	 * @return array Plaintext codes.
	 */
	public function generate_backup_codes( $count = 10 ) {

		$codes   = [];
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

		for ( $i = 0; $i < $count; $i++ ) {
			$code = '';
			for ( $j = 0; $j < 8; $j++ ) {
				$code .= $alphabet[ wp_rand( 0, strlen( $alphabet ) - 1 ) ];
			}
			$codes[] = $code;
		}

		return $codes;
	}

	/**
	 * Store hashed backup codes for a user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $plain   Plaintext codes.
	 * @return bool
	 */
	public function save_backup_codes( $user_id, $plain ) {

		$hashed = array_map( function ( $code ) {
			return Helper::hash_token( $code );
		}, $plain );

		$settings = $this->db->get_2fa_settings( $user_id );

		if ( ! $settings ) {
			return false;
		}

		return $this->db->save_2fa_settings(
			$user_id,
			$settings->secret_key,
			$settings->method,
			wp_json_encode( array_values( $hashed ) )
		);
	}

	/**
	 * Verify and consume a backup code (single use).
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    User-provided code.
	 * @return bool
	 */
	public function verify_backup_code( $user_id, $code ) {

		$settings = $this->db->get_2fa_settings( $user_id );

		if ( ! $settings || empty( $settings->backup_codes ) ) {
			return false;
		}

		$hashes = json_decode( $settings->backup_codes, true );

		if ( ! is_array( $hashes ) ) {
			return false;
		}

		foreach ( $hashes as $i => $hash ) {
			if ( Helper::verify_token( $code, $hash ) ) {
				unset( $hashes[ $i ] );
				$this->db->save_2fa_settings(
					$user_id,
					$settings->secret_key,
					$settings->method,
					wp_json_encode( array_values( $hashes ) )
				);

				return true;
			}
		}

		return false;
	}

	/**
	 * =============================================
	 * ENABLE / DISABLE
	 * =============================================
	 */

	/**
	 * Enable 2FA for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $method  2FA method.
	 * @param string $secret  TOTP secret (authenticator) or empty string.
	 * @return array|false Plaintext backup codes on success, false on failure.
	 */
	public function enable_2fa( $user_id, $method, $secret = '' ) {

		if ( ! in_array( $method, $this->get_supported_methods(), true ) ) {
			return false;
		}

		$backup_codes = $this->generate_backup_codes( 10 );

		$this->db->save_2fa_settings(
			$user_id,
			$secret,
			$method,
			wp_json_encode( array_map( static function ( $code ) {
				return Helper::hash_token( $code );
			}, $backup_codes ) )
		);

		$this->db->toggle_2fa( $user_id, true );

		// Clear "setup required" flag.
		delete_transient( "hikmah_2fa_setup_needed_{$user_id}" );

		Helper::log( "2FA enabled: User ID {$user_id} (method: {$method})" );

		/**
		 * Fires when 2FA is enabled for a user.
		 *
		 * @since 1.0.0
		 * @param int    $user_id      User ID.
		 * @param string $method       2FA method.
		 * @param array  $backup_codes Plaintext backup codes.
		 */
		do_action( 'hikmah_2fa_enabled', $user_id, $method, $backup_codes );

		return $backup_codes;
	}

	/**
	 * Disable 2FA for a user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function disable_2fa( $user_id ) {

		$deleted = $this->db->delete( 'two_factor', [ 'user_id' => $user_id ], [ '%d' ] );

		if ( false === $deleted && ! $this->db->get_2fa_settings( $user_id ) ) {
			// No row to delete; treated as success.
			$deleted = true;
		}

		delete_transient( "hikmah_2fa_otp_hash_{$user_id}" );
		delete_transient( "hikmah_2fa_otp_{$user_id}" );
		delete_transient( "hikmah_2fa_setup_needed_{$user_id}" );

		Helper::log( "2FA disabled: User ID {$user_id}" );

		/**
		 * Fires when 2FA is disabled for a user.
		 *
		 * @since 1.0.0
		 * @param int $user_id User ID.
		 */
		do_action( 'hikmah_2fa_disabled', $user_id );

		return true;
	}

	/**
	 * Verify a login challenge code for a user.
	 *
	 * Dispatches to the user's 2FA method; accepts backup codes for any method.
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    Code provided during login.
	 * @return bool
	 */
	public function verify_login_code( $user_id, $code ) {

		$settings = $this->db->get_2fa_settings( $user_id );

		if ( ! $settings || '1' !== (string) $settings->is_enabled ) {
			return false;
		}

		// Backup codes always accepted (any method).
		if ( $this->verify_backup_code( $user_id, $code ) ) {
			return true;
		}

		switch ( $settings->method ) {
			case self::METHOD_EMAIL:
				return $this->verify_email_otp( $user_id, $code );

			case self::METHOD_AUTHENTICATOR:
				return $this->verify_totp( $settings->secret_key, $code );

			case self::METHOD_SMS:
				return false;

			default:
				return false;
		}
	}

	/**
	 * List supported methods.
	 *
	 * @return array
	 */
	public function get_supported_methods() {
		return [ self::METHOD_EMAIL, self::METHOD_AUTHENTICATOR ];
	}

	/**
	 * =============================================
	 * AJAX HANDLERS
	 * =============================================
	 */

	/**
	 * Start 2FA setup for the current user.
	 *
	 * Generates the TOTP secret (authenticator) and sends the OTP email
	 * (email method), then returns everything the wizard needs.
	 */
	public function ajax_setup_2fa() {

		$user_id = get_current_user_id();
		$method  = isset( $_POST['method'] )
			? sanitize_key( wp_unslash( $_POST['method'] ) )
			: '';

		if ( ! in_array( $method, $this->get_supported_methods(), true ) ) {
			Helper::send_json( false, __( 'Invalid 2FA method.', 'hikmah-login' ), [], 400 );
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			Helper::send_json( false, __( 'User not found.', 'hikmah-login' ), [], 404 );
		}

		$data = [ 'method' => $method, 'expires_in' => $this->otp_lifetime ];

		if ( self::METHOD_AUTHENTICATOR === $method ) {
			$secret = $this->generate_totp_secret();
			set_transient( "hikmah_2fa_setup_{$user_id}", $secret, $this->otp_lifetime );

			$uri = $this->get_totp_uri( $user->user_login, $secret );

			$data['secret']        = $secret;
			$data['qr_code_url']   = $this->get_qr_code_url( $uri );
			$data['provisioning_uri'] = $uri;
		}

		if ( self::METHOD_EMAIL === $method ) {
			$this->send_email_otp( $user_id );
		}

		Helper::send_json( true, __( 'Setup started. Enter the verification code.', 'hikmah-login' ), $data );
	}

	/**
	 * Verify the setup code and enable 2FA.
	 */
	public function ajax_verify_setup() {

		$user_id = get_current_user_id();
		$method  = isset( $_POST['method'] )
			? sanitize_key( wp_unslash( $_POST['method'] ) )
			: '';
		$code    = isset( $_POST['code'] )
			? sanitize_text_field( wp_unslash( $_POST['code'] ) )
			: '';

		if ( ! in_array( $method, $this->get_supported_methods(), true ) || empty( $code ) ) {
			Helper::send_json( false, __( 'Invalid verification request.', 'hikmah-login' ), [], 400 );
		}

		$secret = '';

		if ( self::METHOD_AUTHENTICATOR === $method ) {
			// Keep the generated secret in a local variable (transient is consumed).
			$secret = (string) get_transient( "hikmah_2fa_setup_{$user_id}" );

			if ( empty( $secret ) ) {
				Helper::send_json( false, __( 'Setup session expired. Please start again.', 'hikmah-login' ), [], 400 );
			}

			if ( ! $this->verify_totp( $secret, $code ) ) {
				Helper::send_json( false, __( 'Invalid code. Check that your device time is correct.', 'hikmah-login' ), [ 'field' => 'code' ], 400 );
			}

			delete_transient( "hikmah_2fa_setup_{$user_id}" );
		} else {
			if ( ! $this->verify_email_otp( $user_id, $code ) ) {
				Helper::send_json( false, __( 'Invalid or expired code.', 'hikmah-login' ), [ 'field' => 'code' ], 400 );
			}
		}

		$codes = $this->enable_2fa( $user_id, $method, $secret );

		Helper::send_json( true, __( 'Two-factor authentication enabled successfully.', 'hikmah-login' ), [
			'backup_codes' => $codes ? $codes : [],
		]);
	}

	/**
	 * Disable 2FA (password confirmation required).
	 */
	public function ajax_disable_2fa() {

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			Helper::send_json( false, __( 'User not found.', 'hikmah-login' ), [], 404 );
		}

		$password = isset( $_POST['password'] )
			? (string) wp_unslash( $_POST['password'] )
			: '';

		if ( empty( $password ) || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			Helper::send_json( false, __( 'Incorrect password. Two-factor authentication was not disabled.', 'hikmah-login' ), [ 'field' => 'password' ], 403 );
		}

		$this->disable_2fa( $user_id );

		Helper::send_json( true, __( 'Two-factor authentication disabled.', 'hikmah-login' ) );
	}

	/**
	 * Regenerate backup codes for the current user.
	 */
	public function ajax_generate_backup_codes() {

		$user_id = get_current_user_id();

		// Security: only allowed when 2FA is enabled.
		if ( ! $this->is_user_2fa_enabled( $user_id ) ) {
			Helper::send_json( false, __( 'Two-factor authentication is not enabled.', 'hikmah-login' ), [], 403 );
		}

		$plain = $this->generate_backup_codes( 10 );
		$this->save_backup_codes( $user_id, $plain );

		Helper::send_json( true, __( 'New backup codes generated. Old codes are no longer valid.', 'hikmah-login' ), [
			'backup_codes' => $plain,
		]);
	}

	/**
	 * (Re)send the login challenge OTP.
	 *
	 * Only allowed for users in a pending_2fa auth state with email 2FA.
	 */
	public function ajax_send_login_otp() {

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			Helper::send_json( false, __( 'Invalid request.', 'hikmah-login' ), [], 400 );
		}

// Prevent unsolicited email floods to arbitrary addresses.
	if ( self::METHOD_EMAIL !== $this->get_user_2fa_method( $user_id ) ) {
		Helper::send_json( false, __( 'Email verification is not enabled for this account.', 'hikmah-login' ), [], 403 );
	}

	// Allowed when the target is the logged-in user mid-setup, or when a
	// login challenge for that user is pending.
	$is_self = is_user_logged_in() && (int) $user_id === get_current_user_id();

	$auth  = Auth_Manager::get_instance();
	$state = $auth->get_auth_state( $user_id );
	$is_pending = $state && 'pending_2fa' === $state['state'];

	if ( ! $is_self && ! $is_pending ) {
		Helper::send_json( false, __( 'Login session expired. Please sign in again.', 'hikmah-login' ), [], 401 );
	}

		if ( ! $this->send_email_otp( $user_id ) ) {
			Helper::send_json( false, __( 'Too many codes sent. Please wait a few minutes and try again.', 'hikmah-login' ), [], 429 );
		}

		Helper::send_json( true, __( 'A new code has been sent to your email.', 'hikmah-login' ), [
			'expires_in' => $this->otp_lifetime,
		]);
	}

	/**
	 * =============================================
	 * ADMIN ENFORCEMENT & SETUP FLAG
	 * =============================================
	 */

	/**
	 * Set a "setup required" transient (1 hour) after login when an
	 * administrator has 2FA disabled.
	 *
	 * @param string    $user_login User login.
	 * @param \WP_User  $user       User object.
	 */
	public function check_2fa_setup_required( $user_login, $user = null ) {

		if ( ! $user || ! ( $user instanceof \WP_User ) ) {
			return;
		}

		if ( $this->is_user_2fa_enabled( $user->ID ) ) {
			return;
		}

		if ( ! user_can( $user, 'manage_options' ) ) {
			return;
		}

		set_transient( "hikmah_2fa_setup_needed_{$user->ID}", 1, HOUR_IN_SECONDS );
	}

	/**
	 * Render an admin notice asking administrators to configure 2FA.
	 *
	 * Skips the personal profile screen and AJAX requests.
	 */
	public function render_admin_2fa_notice() {

		if ( ! is_user_logged_in() || wp_doing_ajax() ) {
			return;
		}

		$user = wp_get_current_user();

		if ( ! $user || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && in_array( $screen->id, [ 'profile', 'user-edit' ], true ) ) {
			return;
		}

		if ( $this->is_user_2fa_enabled( $user->ID ) ) {
			return;
		}

		echo '<div class="notice notice-warning hikmah-2fa-admin-notice"><p>';
		printf(
			/* translators: %s: Profile URL */
			wp_kses_post(
				sprintf(
					__( 'Two-factor authentication is recommended for administrators. <a href="%s">Configure it now</a>.', 'hikmah-login' ),
					esc_url( admin_url( 'profile.php#hikmah-2fa-setup' ) )
				)
			)
		);
		echo '</p></div>';
	}

	/**
	 * =============================================
	 * RENDERING (SETUP WIZARD)
	 * =============================================
	 */

	/**
	 * Render the 2FA setup wizard on the user's own profile page.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public function render_setup_wizard( $user ) {

		// Only render on the current user's OWN profile.
		if ( ! is_user_logged_in() || (int) $user->ID !== get_current_user_id() ) {
			return;
		}

		$this->render_setup_markup();
	}

	/**
	 * Render the 2FA setup markup.
	 *
	 * Echoes directly; wrap in output buffering for shortcode use.
	 */
	public function render_setup_markup() {

		if ( ! Helper::is_feature_enabled( '2fa_enabled' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		$instance = Two_Factor::get_instance();

		$vars = [
			'user_id'        => $user_id,
			'user'           => $user,
			'two_factor'     => $instance,
			'is_enabled'     => $instance->is_user_2fa_enabled( $user_id ),
			'method'         => $instance->get_user_2fa_method( $user_id ),
			'backup_count'   => $instance->get_backup_code_count( $user_id ),
			'needs_setup'    => $instance->needs_2fa_setup( $user_id ),
			'is_admin'       => current_user_can( 'manage_options' ),
		];

		$template = HIKMAH_LOGIN_DIR . 'public/views/2fa-setup.php';

		if ( file_exists( $template ) ) {
			extract( $vars, EXTR_OVERWRITE ); // phpcs:ignore WordPress.PHP.DontExtract -- Template convention.
			include $template;
		}
	}

	/**
	 * Whether 2FA as a feature is enabled.
	 *
	 * @return bool
	 */
	public function is_feature_enabled() {
		return Helper::is_feature_enabled( '2fa_enabled' );
	}
}