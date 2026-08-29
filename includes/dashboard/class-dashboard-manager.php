<?php
/**
 * Dashboard Manager
 *
 * Manages the complete user dashboard experience:
 * - Overview stats (login count, sessions, security score)
 * - Profile view & edit
 * - Avatar management
 * - Password change + 2FA setup wizard
 * - Active sessions
 * - Login activity history
 * - Social connections
 * - Privacy (GDPR data export + account deletion)
 *
 * Tabs are rendered from template files (public/views/dashboard/tab-*.php)
 * with optional theme overrides in {theme}/hikmah-login/dashboard/.
 *
 * @package Hikmah_Login
 * @subpackage Dashboard
 * @since   1.0.0
 */

namespace Hikmah_Login\Dashboard;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;
use Hikmah_Login\Helpers\Validator;
use Hikmah_Login\Database\DB_Manager;
use Hikmah_Login\Auth\Session_Manager;
use Hikmah_Login\Security\Two_Factor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard_Manager {

	use Singleton;
	use Hooks;

	/**
	 * @var DB_Manager
	 */
	private $db;

	/**
	 * Available dashboard tabs.
	 *
	 * @var array
	 */
	private $tabs = [];

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->db = new DB_Manager();
		$this->register_tabs();
		$this->register_hooks();
	}

	/**
	 * =============================================
	 * TABS
	 * =============================================
	 */

	/**
	 * Register default dashboard tabs.
	 */
	private function register_tabs() {

		$this->tabs = [
			'overview' => [
				'label'    => __( 'Overview', 'hikmah-login' ),
				'icon'     => '📊',
				'priority' => 10,
				'template' => 'tab-overview.php',
			],
			'profile'  => [
				'label'    => __( 'Profile', 'hikmah-login' ),
				'icon'     => '👤',
				'priority' => 20,
				'template' => 'tab-profile.php',
			],
			'security' => [
				'label'    => __( 'Security', 'hikmah-login' ),
				'icon'     => '🔐',
				'priority' => 30,
				'template' => 'tab-security.php',
			],
			'sessions' => [
				'label'    => __( 'Sessions', 'hikmah-login' ),
				'icon'     => '💻',
				'priority' => 40,
				'template' => 'tab-sessions.php',
			],
			'social'   => [
				'label'    => __( 'Social', 'hikmah-login' ),
				'icon'     => '🔗',
				'priority' => 50,
				'template' => 'tab-social.php',
				'condition' => [ $this, 'has_social_providers' ],
			],
			'activity' => [
				'label'    => __( 'Activity', 'hikmah-login' ),
				'icon'     => '📋',
				'priority' => 60,
				'template' => 'tab-activity.php',
			],
			'privacy'  => [
				'label'    => __( 'Privacy', 'hikmah-login' ),
				'icon'     => '🛡️',
				'priority' => 100,
				'template' => 'tab-privacy.php',
			],
		];

		/**
		 * Filter dashboard tabs.
		 *
		 * Third-party plugins can add, remove or reorder tabs. Each tab
		 * supports: label, icon, priority, template, condition (callable),
		 * capability (string) and callback (callable returning template vars).
		 *
		 * @since 1.0.0
		 * @param array $tabs Registered tabs (keyed by slug).
		 */
		$this->tabs = apply_filters( 'hikmah_dashboard_tabs', $this->tabs );

		// Sort by priority.
		uasort( $this->tabs, function( $a, $b ) {
			return (int) ( $a['priority'] ?? 50 ) - (int) ( $b['priority'] ?? 50 );
		} );
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {

		$this->add_ajax( 'hikmah_dashboard_update_profile', 'ajax_update_profile' );
		$this->add_ajax( 'hikmah_dashboard_upload_avatar', 'ajax_upload_avatar' );
		$this->add_ajax( 'hikmah_dashboard_remove_avatar', 'ajax_remove_avatar' );
		$this->add_ajax( 'hikmah_dashboard_change_password', 'ajax_change_password' );
		$this->add_ajax( 'hikmah_dashboard_destroy_session', 'ajax_destroy_session' );
		$this->add_ajax( 'hikmah_dashboard_destroy_other_sessions', 'ajax_destroy_other_sessions' );
		$this->add_ajax( 'hikmah_dashboard_unlink_social', 'ajax_unlink_social' );
		$this->add_ajax( 'hikmah_dashboard_delete_account', 'ajax_delete_account' );
		$this->add_ajax( 'hikmah_dashboard_export_data', 'ajax_export_data' );
		$this->add_ajax( 'hikmah_dashboard_load_tab', 'ajax_load_tab' );

		// Privacy export file download (admin-post for logged-in users).
		$this->add_action( 'admin_post_hikmah_dashboard_download_export', 'handle_export_download' );
	}

	/**
	 * Get the tabs visible to the current user.
	 *
	 * @return array
	 */
	public function get_visible_tabs() {

		$visible = [];

		foreach ( $this->tabs as $key => $tab ) {
			if ( isset( $tab['condition'] ) && is_callable( $tab['condition'] ) ) {
				if ( ! call_user_func( $tab['condition'] ) ) {
					continue;
				}
			}

			if ( ! empty( $tab['capability'] ) && ! current_user_can( $tab['capability'] ) ) {
				continue;
			}

			$visible[ $key ] = $tab;
		}

		return $visible;
	}

	/**
	 * Get the resolved tab slugs (keys) as [slug => label] pairs.
	 *
	 * @return array
	 */
	public function get_nav_tabs() {

		$nav = [];

		foreach ( $this->get_visible_tabs() as $key => $tab ) {
			$nav[ $key ] = $tab;
		}

		return $nav;
	}

	/**
	 * Get the current active tab.
	 *
	 * @return string
	 */
	public function get_active_tab() {

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';

		$visible = $this->get_visible_tabs();

		return isset( $visible[ $tab ] ) ? $tab : 'overview';
	}

	/**
	 * Whether any social login provider is enabled.
	 *
	 * The Social module is not part of this phase; guard so the tab
	 * only appears once providers exist.
	 *
	 * @return bool
	 */
	public function has_social_providers() {

		if ( ! class_exists( '\\Hikmah_Login\\Social\\Social_Manager' ) ) {
			return false;
		}

		$social = \Hikmah_Login\Social\Social_Manager::get_instance();

		if ( ! is_callable( [ $social, 'get_enabled_providers' ] ) ) {
			return false;
		}

		return ! empty( $social->get_enabled_providers() );
	}

	/**
	 * =============================================
	 * RENDERING
	 * =============================================
	 */

	/**
	 * Render the dashboard shortcode output.
	 *
	 * @return string
	 */
	public function render() {

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return $this->render_guest_fallback();
		}

		$template = $this->locate_template( 'user-dashboard.php' );

		if ( ! $template ) {
			return '';
		}

		$vars = [
			'user_id'          => $user_id,
			'user'             => wp_get_current_user(),
			'dashboard'        => $this,
			'active_tab'       => $this->get_active_tab(),
			'tabs'             => $this->get_nav_tabs(),
			'current_url'      => $this->get_current_url(),
			'logout_url'       => Helper::get_logout_url(),
		];

		ob_start();
		extract( $vars, EXTR_OVERWRITE ); // phpcs:ignore WordPress.PHP.DontExtract -- Template convention.
		include $template;
		return ob_get_clean();
	}

	/**
	 * Render guest fallback message.
	 *
	 * @return string
	 */
	public function render_guest_fallback() {
		return '<div class="hikmah-login-wrapper"><div class="hikmah-notice hikmah-notice-info"><p>' .
			sprintf(
				wp_kses_post( __( 'Please <a href="%s">log in</a> to view your dashboard.', 'hikmah-login' ) ),
				esc_url( Helper::get_login_url() )
			) .
			'</p></div></div>';
	}

	/**
	 * Render a single tab's content (used for initial render and AJAX).
	 *
	 * @param string $tab_key Tab slug.
	 * @return string
	 */
	public function render_tab( $tab_key ) {

		$visible = $this->get_visible_tabs();

		if ( ! isset( $visible[ $tab_key ] ) ) {
			$tab_key = 'overview';
		}

		$tab  = $visible[ $tab_key ];
		$user = wp_get_current_user();

		$vars = [
			'user_id' => (int) $user->ID,
			'user'    => $user,
		];

		// Per-tab data provider (method on the manager).
		$provider = 'get_' . str_replace( '-', '_', $tab_key ) . '_vars';
		if ( method_exists( $this, $provider ) ) {
			$extra = call_user_func( [ $this, $provider ], $user );
			if ( is_array( $extra ) ) {
				$vars = array_merge( $vars, $extra );
			}
		}

		// Custom callback declared by the tab registration.
		if ( ! empty( $tab['callback'] ) && is_callable( $tab['callback'] ) ) {
			$extra = call_user_func( $tab['callback'], $user );
			if ( is_array( $extra ) ) {
				$vars = array_merge( $vars, $extra );
			}
		}

		// Enforce identity vars.
		$vars['user_id'] = (int) $user->ID;
		$vars['user']    = $user;
		$vars['tab_key'] = $tab_key;

		$template = $this->locate_template( 'dashboard/' . ( $tab['template'] ?? '' ) );

		if ( ! $template ) {
			return '<p class="hikmah-dash-desc">' . esc_html__( 'This section is not available.', 'hikmah-login' ) . '</p>';
		}

		ob_start();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract -- Template convention.
		include $template;
		return ob_get_clean();
	}

	/**
	 * Resolve a dashboard template path with theme override support.
	 *
	 * Overrides live at {theme}/hikmah-login/{$relative}.
	 *
	 * @param string $relative Relative path inside the views dir.
	 * @return string
	 */
	private function locate_template( $relative ) {

		$override = locate_template( 'hikmah-login/' . $relative );

		if ( $override ) {
			return $override;
		}

		$path = HIKMAH_LOGIN_DIR . 'public/views/' . $relative;

		return file_exists( $path ) ? $path : '';
	}

	/**
	 * Get the current page URL.
	 *
	 * @return string
	 */
	private function get_current_url() {
		global $wp;

		if ( ! empty( $wp->request ) ) {
			return home_url( add_query_arg( [], $wp->request ) );
		}

		return home_url( '/' );
	}

	/**
	 * =============================================
	 * DATA PROVIDERS (per-tab vars)
	 * =============================================
	 */

	/**
	 * Overview tab data.
	 *
	 * @param \WP_User $user Current user.
	 * @return array
	 */
	private function get_overview_vars( $user ) {
		return [
			'stats'           => $this->get_overview_stats( $user->ID ),
			'recent_activity' => $this->get_recent_activity( $user->ID, 5 ),
			'recommendations' => $this->get_security_recommendations( $user->ID ),
			'avatar_url'      => $this->get_avatar_url( $user->ID ),
		];
	}

	/**
	 * Profile tab data.
	 *
	 * @param \WP_User $user Current user.
	 * @return array
	 */
	private function get_profile_vars( $user ) {
		return [
			'avatar_id'   => (int) get_user_meta( $user->ID, 'hikmah_avatar_id', true ),
			'avatar_url'  => $this->get_avatar_url( $user->ID ),
		];
	}

	/**
	 * Security tab data.
	 *
	 * @param \WP_User $user Current user.
	 * @return array
	 */
	private function get_security_vars( $user ) {

		$two_factor = Two_Factor::get_instance();

		return [
			'password_changed' => get_user_meta( $user->ID, 'hikmah_password_changed_at', true ),
			// 2FA setup wizard vars (rendered by public/views/2fa-setup.php).
			'two_factor'       => $two_factor,
			'is_enabled'       => $two_factor->is_user_2fa_enabled( $user->ID ),
			'method'           => $two_factor->get_user_2fa_method( $user->ID ),
			'backup_count'     => $two_factor->get_backup_code_count( $user->ID ),
			'needs_setup'      => $two_factor->needs_2fa_setup( $user->ID ),
			'is_admin'         => current_user_can( 'manage_options' ),
			'email_verified'   => Helper::is_email_verified( $user->ID ),
		];
	}

	/**
	 * Sessions tab data.
	 *
	 * @param \WP_User $user Current user.
	 * @return array
	 */
	private function get_sessions_vars( $user ) {
		return [
			'sessions' => $this->get_sessions( $user->ID ),
		];
	}

	/**
	 * Social tab data.
	 *
	 * @param \WP_User $user Current user.
	 * @return array
	 */
	private function get_social_vars( $user ) {

		if ( ! class_exists( '\\Hikmah_Login\\Social\\Social_Manager' ) ) {
			return [ 'providers' => [], 'linked' => [] ];
		}

		$social_manager = \Hikmah_Login\Social\Social_Manager::get_instance();

		$providers = is_callable( [ $social_manager, 'get_enabled_providers' ] )
			? $social_manager->get_enabled_providers()
			: [];

		$linked = [];

		foreach ( $this->db->get_results( 'social_profiles', [ 'where' => [ 'user_id' => $user->ID ] ] ) as $link ) {
			$linked[ $link->provider ] = $link;
		}

		return [
			'providers'          => $providers,
			'linked_providers'   => $linked,
			'social_manager'     => $social_manager,
		];
	}

	/**
	 * Activity tab data.
	 *
	 * @param \WP_User $user Current user.
	 * @return array
	 */
	private function get_activity_vars( $user ) {

		$page = isset( $_GET['hpage'] ) ? max( 1, absint( wp_unslash( $_GET['hpage'] ) ) ) : 1;

		return [
			'logs' => $this->db->get_login_logs( $page, 15, [ 'user_id' => $user->ID ] ),
			'page' => $page,
		];
	}

	/**
	 * Privacy tab data.
	 *
	 * @param \WP_User $user Current user.
	 * @return array
	 */
	private function get_privacy_vars( $user ) {
		return [
			'deletion_enabled' => 'yes' === get_option( 'hikmah_allow_account_deletion', 'no' ),
		];
	}

	/**
	 * Overview: aggregate stats for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_overview_stats( $user_id ) {

		$logs = $this->db->get_login_logs( 1, 200, [ 'user_id' => $user_id ] );

		$logins_30d  = 0;
		$since_30d   = strtotime( '-30 days' );

		foreach ( ( $logs['items'] ?? [] ) as $log ) {
			if ( strtotime( (string) $log->login_at ) >= $since_30d ) {
				$logins_30d++;
			}
		}

		return [
			'success_logins' => (int) get_user_meta( $user_id, 'hikmah_login_count', true ),
			'logins_30d'     => $logins_30d,
			'active_sessions' => count( $this->get_sessions( $user_id ) ),
			'security_score' => $this->get_security_score( $user_id ),
		];
	}

	/**
	 * Overview: latest activity entries.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of entries.
	 * @return array
	 */
	public function get_recent_activity( $user_id, $limit = 5 ) {

		$logs = $this->db->get_login_logs( 1, $limit, [ 'user_id' => $user_id ] );

		$activity = [];

		foreach ( ( $logs['items'] ?? [] ) as $log ) {
			$activity[] = [
				'time'   => $log->login_at,
				'type'   => 'login',
				'status' => $log->status,
				'ip'     => $log->ip_address,
			];
		}

		return $activity;
	}

	/**
	 * Overview: security recommendations checklist.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_security_recommendations( $user_id ) {

		$recommendations = [];

		if ( ! Helper::is_email_verified( $user_id ) ) {
			$recommendations[] = [
				'type'    => 'warning',
				'message' => __( 'Verify your email address to secure your account.', 'hikmah-login' ),
			];
		}

		if ( ! Two_Factor::get_instance()->is_user_2fa_enabled( $user_id ) ) {
			$recommendations[] = [
				'type'    => 'warning',
				'message' => __( 'Enable two-factor authentication for an extra layer of security.', 'hikmah-login' ),
			];
		}

		$password_changed = get_user_meta( $user_id, 'hikmah_password_changed_at', true );

		if ( $password_changed && strtotime( (string) $password_changed ) < strtotime( '-90 days' ) ) {
			$recommendations[] = [
				'type'    => 'info',
				'message' => __( 'Consider changing your password — it was set more than 90 days ago.', 'hikmah-login' ),
			];
		}

		if ( empty( $recommendations ) ) {
			$recommendations[] = [
				'type'    => 'success',
				'message' => __( 'Your account security is up to date. Nice work!', 'hikmah-login' ),
			];
		}

		return $recommendations;
	}

	/**
	 * Calculate a rough security score (0-100).
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_security_score( $user_id ) {

		$score = 0;

		if ( Helper::is_email_verified( $user_id ) ) {
			$score += 40;
		}

		if ( Two_Factor::get_instance()->is_user_2fa_enabled( $user_id ) ) {
			$score += 30;
		}

		$password_changed = get_user_meta( $user_id, 'hikmah_password_changed_at', true );

		if ( $password_changed && strtotime( (string) $password_changed ) >= strtotime( '-90 days' ) ) {
			$score += 20;
		}

		if ( ! empty( $this->get_sessions( $user_id ) ) ) {
			$score += 10;
		}

		return min( 100, $score );
	}

	/**
	 * User avatar URL (plugin-managed upload or gravatar fallback).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function get_avatar_url( $user_id ) {

		$avatar_id = (int) get_user_meta( $user_id, 'hikmah_avatar_id', true );

		if ( $avatar_id ) {
			$url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
			if ( $url ) {
				return $url;
			}
		}

		return get_avatar_url( $user_id, [ 'size' => 128 ] );
	}

	/**
	 * Normalize active session rows into displayable records.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_sessions( $user_id ) {

		$session_manager = Session_Manager::get_instance();

		if ( ! is_callable( [ $session_manager, 'get_active_sessions' ] ) ) {
			return [];
		}

		$rows = $session_manager->get_active_sessions( $user_id );

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return [];
		}

		$current_token = wp_get_session_token();
		$records       = [];

		foreach ( $rows as $row ) {
			$records[] = [
				'device'      => $row->device_type ?? 'undetected',
				'browser'     => $row->browser ?? __( 'Unknown', 'hikmah-login' ),
				'os'          => $row->os ?? __( 'Unknown', 'hikmah-login' ),
				'ip_address'  => $row->ip_address ?? '',
				'user_agent'  => $row->user_agent ?? '',
				'last_active' => $row->last_activity ?? '',
				'login_at'    => $row->login_time ?? '',
				'token_hash'  => hash( 'sha256', (string) ( $row->token ?? '' ) ),
				'is_current'  => isset( $row->token ) && $row->token === $current_token,
			];
		}

		return $records;
	}

	/**
	 * =============================================
	 * AJAX HANDLERS
	 * =============================================
	 */

	/**
	 * AJAX: Update profile.
	 */
	public function ajax_update_profile() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			Helper::send_json( false, __( 'Not logged in.', 'hikmah-login' ), [], 401 );
		}

		$current_user = get_userdata( $user_id );

		$data = [
			'ID'           => $user_id,
			'first_name'   => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
			'last_name'    => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
			'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			'user_url'     => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
		];

		$new_email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

		if ( ! empty( $new_email ) && $new_email !== $current_user->user_email ) {
			if ( ! is_email( $new_email ) ) {
				Helper::send_json( false, __( 'Invalid email address.', 'hikmah-login' ), [], 400 );
			}

			if ( email_exists( $new_email ) ) {
				Helper::send_json( false, __( 'This email is already in use.', 'hikmah-login' ), [], 400 );
			}

			$data['user_email'] = $new_email;
		}

		$result = wp_update_user( $data );

		if ( is_wp_error( $result ) ) {
			Helper::send_json( false, $result->get_error_message(), [], 400 );
		}

		/**
		 * Fires after a user updates their profile from the dashboard.
		 *
		 * @since 1.0.0
		 * @param int   $user_id User ID.
		 * @param array $data    Updated fields.
		 */
		do_action( 'hikmah_dashboard_profile_updated', $user_id, $data );

		Helper::send_json( true, __( 'Profile updated successfully!', 'hikmah-login' ) );
	}

	/**
	 * AJAX: Upload avatar (expects $_FILES['avatar']).
	 */
	public function ajax_upload_avatar() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			Helper::send_json( false, __( 'Not logged in.', 'hikmah-login' ), [], 401 );
		}

		if ( empty( $_FILES['avatar'] ) ) {
			Helper::send_json( false, __( 'No file selected.', 'hikmah-login' ), [], 400 );
		}

		$file = $_FILES['avatar'];

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Validated below.
		$mime     = wp_check_filetype( $file['name'] ?? '', 'image/jpeg,image/png,image/gif,image/webp' );
		$allowed  = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
		$max_size = 2 * 1024 * 1024; // 2MB

		if ( empty( $mime['type'] ) || ! in_array( $mime['type'], $allowed, true ) ) {
			Helper::send_json( false, __( 'Only JPG, PNG, GIF, and WebP images are allowed.', 'hikmah-login' ), [], 400 );
		}

		if ( (int) ( $file['size'] ?? 0 ) > $max_size ) {
			Helper::send_json( false, __( 'Image must be less than 2MB.', 'hikmah-login' ), [], 400 );
		}
		// phpcs:enable

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'avatar', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			Helper::send_json( false, $attachment_id->get_error_message(), [], 400 );
		}

		$previous = (int) get_user_meta( $user_id, 'hikmah_avatar_id', true );

		update_user_meta( $user_id, 'hikmah_avatar_id', $attachment_id );

		if ( $previous && $previous !== $attachment_id && ! empty( get_post( $previous ) ) ) {
			wp_delete_attachment( $previous, true );
		}

		Helper::send_json( true, __( 'Avatar updated!', 'hikmah-login' ), [
			'avatar_url' => $this->get_avatar_url( $user_id ),
		] );
	}

	/**
	 * AJAX: Remove avatar (revert to gravatar).
	 */
	public function ajax_remove_avatar() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			Helper::send_json( false, __( 'Not logged in.', 'hikmah-login' ), [], 401 );
		}

		$avatar_id = (int) get_user_meta( $user_id, 'hikmah_avatar_id', true );

		delete_user_meta( $user_id, 'hikmah_avatar_id' );

		if ( $avatar_id && ! empty( get_post( $avatar_id ) ) ) {
			wp_delete_attachment( $avatar_id, true );
		}

		Helper::send_json( true, __( 'Avatar removed.', 'hikmah-login' ), [
			'avatar_url' => $this->get_avatar_url( $user_id ),
		] );
	}

	/**
	 * AJAX: Change password.
	 */
	public function ajax_change_password() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			Helper::send_json( false, __( 'Not logged in.', 'hikmah-login' ), [], 401 );
		}

		$current  = wp_unslash( $_POST['current_password'] ?? '' );
		$new_pass = wp_unslash( $_POST['new_password'] ?? '' );
		$confirm  = wp_unslash( $_POST['confirm_password'] ?? '' );

		if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			Helper::send_json( false, __( 'Current password is incorrect.', 'hikmah-login' ), [], 401 );
		}

		if ( $new_pass !== $confirm ) {
			Helper::send_json( false, __( 'Passwords do not match.', 'hikmah-login' ), [], 400 );
		}

		$validator = new Validator();
		$validator->password( 'new_password', $new_pass );

		if ( ! $validator->is_valid() ) {
			Helper::send_json( false, $validator->get_first_error(), [], 400 );
		}

		if ( wp_check_password( $new_pass, $user->user_pass, $user_id ) ) {
			Helper::send_json( false, __( 'New password must be different from current.', 'hikmah-login' ), [], 400 );
		}

		wp_set_password( $new_pass, $user_id );
		update_user_meta( $user_id, 'hikmah_password_changed_at', Helper::current_datetime() );

		// Re-login the user with a fresh auth cookie.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		/**
		 * Fires after a user changes their password from the dashboard.
		 *
		 * @since 1.0.0
		 * @param int $user_id User ID.
		 */
		do_action( 'hikmah_dashboard_password_changed', $user_id );

		Helper::send_json( true, __( 'Password changed successfully!', 'hikmah-login' ) );
	}

	/**
	 * AJAX: Destroy a session (by SHA-256 token handle).
	 */
	public function ajax_destroy_session() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		$user_id    = get_current_user_id();
		$token_hash = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

		if ( strlen( $token_hash ) !== 64 || ! ctype_xdigit( $token_hash ) ) {
			Helper::send_json( false, __( 'Invalid session token.', 'hikmah-login' ), [], 400 );
		}

		$session_manager = Session_Manager::get_instance();
		$rows            = $session_manager->get_active_sessions( $user_id );

		if ( ! empty( $rows ) && is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( isset( $row->token ) && hash( 'sha256', $row->token ) === $token_hash ) {
					$session_manager->destroy_session( $row->token, $user_id );
					Helper::send_json( true, __( 'Session revoked.', 'hikmah-login' ) );
				}
			}
		}

		Helper::send_json( false, __( 'Session not found.', 'hikmah-login' ), [], 404 );
	}

	/**
	 * AJAX: Destroy all other sessions.
	 */
	public function ajax_destroy_other_sessions() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		$user_id = get_current_user_id();

		$count = Session_Manager::get_instance()->destroy_other_sessions( $user_id );

		Helper::send_json( true, sprintf(
			/* translators: %d: Number of sessions */
			__( '%d session(s) revoked.', 'hikmah-login' ),
			$count
		) );
	}

	/**
	 * AJAX: Unlink a social connection.
	 */
	public function ajax_unlink_social() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		if ( ! class_exists( '\\Hikmah_Login\\Social\\Social_Manager' ) ) {
			Helper::send_json( false, __( 'Social login is not available.', 'hikmah-login' ), [], 400 );
		}

		$user_id  = get_current_user_id();
		$provider = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );

		$social_manager = \Hikmah_Login\Social\Social_Manager::get_instance();

		if ( ! is_callable( [ $social_manager, 'get_provider' ] ) ) {
			Helper::send_json( false, __( 'Social login is not available.', 'hikmah-login' ), [], 400 );
		}

		$provider_instance = $social_manager->get_provider( $provider );

		if ( ! $provider_instance ) {
			Helper::send_json( false, __( 'Invalid provider.', 'hikmah-login' ), [], 400 );
		}

		if ( ! is_callable( [ $provider_instance, 'unlink_profile' ] ) ) {
			Helper::send_json( false, __( 'Cannot unlink this provider.', 'hikmah-login' ), [], 400 );
		}

		$result = $provider_instance->unlink_profile( $user_id );

		if ( ! $result ) {
			Helper::send_json( false, __( 'Cannot unlink. Set a password first.', 'hikmah-login' ), [], 400 );
		}

		Helper::send_json( true, __( 'Social account disconnected.', 'hikmah-login' ) );
	}

	/**
	 * AJAX: Delete account (GDPR).
	 */
	public function ajax_delete_account() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		if ( 'yes' !== get_option( 'hikmah_allow_account_deletion', 'no' ) ) {
			Helper::send_json( false, __( 'Account deletion is not enabled.', 'hikmah-login' ), [], 403 );
		}

		$user    = wp_get_current_user();
		$user_id = $user->ID;

		$confirm_username = sanitize_text_field( wp_unslash( $_POST['confirm_username'] ?? '' ) );
		$password         = wp_unslash( $_POST['password'] ?? '' );

		if ( $confirm_username !== $user->user_login ) {
			Helper::send_json( false, __( 'Username confirmation does not match.', 'hikmah-login' ), [], 400 );
		}

		if ( ! wp_check_password( $password, $user->user_pass, $user_id ) ) {
			Helper::send_json( false, __( 'Incorrect password.', 'hikmah-login' ), [], 401 );
		}

		if ( user_can( $user, 'manage_options' ) ) {
			Helper::send_json( false, __( 'Administrator accounts cannot be self-deleted.', 'hikmah-login' ), [], 403 );
		}

		/**
		 * Fires before account deletion.
		 *
		 * @since 1.0.0
		 * @param int $user_id User ID being deleted.
		 */
		do_action( 'hikmah_before_account_deletion', $user_id );

		$this->db->delete( 'email_tokens', [ 'user_id' => $user_id ] );
		$this->db->delete( 'two_factor', [ 'user_id' => $user_id ] );
		$this->db->delete( 'social_profiles', [ 'user_id' => $user_id ] );

		delete_user_meta( $user_id, 'hikmah_avatar_id' );
		delete_user_meta( $user_id, 'hikmah_password_changed_at' );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );

		wp_clear_auth_cookie();

		Helper::log( "Account deleted (GDPR): User ID {$user_id}" );

		Helper::send_json( true, __( 'Account deleted.', 'hikmah-login' ), [
			'redirect' => home_url( '/' ),
		] );
	}

	/**
	 * AJAX: Build a GDPR JSON export and stage it for download.
	 */
	public function ajax_export_data() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		$user    = wp_get_current_user();
		$user_id = $user->ID;

		$data = [
			'exported_at'  => Helper::current_datetime(),
			'profile'      => [
				'username'   => $user->user_login,
				'display'    => $user->display_name,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
				'email'      => $user->user_email,
				'website'    => $user->user_url,
				'bio'        => $user->description,
				'registered' => $user->user_registered,
				'meta'       => [
					'avatar'             => (int) get_user_meta( $user_id, 'hikmah_avatar_id', true ),
					'password_changed_at' => get_user_meta( $user_id, 'hikmah_password_changed_at', true ),
				],
			],
			'login_history' => [],
			'sessions'      => [],
			'security'      => [
				'two_factor' => Two_Factor::get_instance()->is_user_2fa_enabled( $user_id ),
				'email'      => Helper::is_email_verified( $user_id ) ? 'verified' : 'unverified',
			],
			'social'        => [],
		];

		$logs = $this->db->get_login_logs( 1, 100, [ 'user_id' => $user_id ] );

		foreach ( ( $logs['items'] ?? [] ) as $log ) {
			$data['login_history'][] = [
				'date'       => $log->login_at,
				'status'     => $log->status,
				'ip'         => $log->ip_address,
				'user_agent' => $log->user_agent,
			];
		}

		foreach ( $this->get_sessions( $user_id ) as $session ) {
			$data['sessions'][] = [
				'device'      => $session['device'],
				'browser'     => $session['browser'],
				'os'          => $session['os'],
				'ip'          => $session['ip_address'],
				'login_at'    => $session['login_at'],
				'last_active' => $session['last_active'],
				'current'     => $session['is_current'],
			];
		}

		if ( class_exists( '\\Hikmah_Login\\Social\\Social_Manager' ) ) {
			foreach ( $this->db->get_results( 'social_profiles', [ 'where' => [ 'user_id' => $user_id ] ] ) as $link ) {
				$data['social'][] = [
					'provider'    => $link->provider,
					'identifier'  => $link->provider_id ?? $link->identifier ?? '',
					'linked_at'   => $link->linked_at ?? '',
				];
			}
		}

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			Helper::send_json( false, __( 'Could not build your data export.', 'hikmah-login' ), [], 500 );
		}

		$upload = wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			Helper::send_json( false, __( 'Storage is not available.', 'hikmah-login' ), [], 500 );
		}

		$dir      = $upload['basedir'] . '/hikmah-exports';
		$filename = 'hikmah-export-' . sanitize_file_name( $user->user_login ) . '-' . gmdate( 'Y-m-d-His' ) . '.json';

		if ( ! wp_mkdir_p( $dir ) ) {
			Helper::send_json( false, __( 'Could not create the export directory.', 'hikmah-login' ), [], 500 );
		}

		$path = $dir . '/' . $filename;

		// Protect the export directory from direct web access.
		if ( ! file_exists( $dir . '/index.php' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
		}

		if ( ! file_put_contents( $path, $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			Helper::send_json( false, __( 'Could not write the export file.', 'hikmah-login' ), [], 500 );
		}

		// Track staged files for the single-use download endpoint.
		$key     = 'hikmah_export_files_' . $user_id;
		$current = (array) get_transient( $key );
		array_unshift( $current, $filename );
		$current = array_slice( array_unique( $current ), 0, 3 );
		set_transient( $key, $current, 15 * MINUTE_IN_SECONDS );

		Helper::log( "GDPR data export: User ID {$user_id} -> {$filename}" );

		Helper::send_json( true, __( 'Your data export is ready.', 'hikmah-login' ), [
			'filename' => $filename,
		] );
	}

	/**
	 * AJAX: Render a tab's content (server-side tab loading).
	 */
	public function ajax_load_tab() {

		check_ajax_referer( 'hikmah_dashboard_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			Helper::send_json( false, __( 'Not logged in.', 'hikmah-login' ), [], 401 );
		}

		$tab_key = sanitize_key( wp_unslash( $_GET['tab'] ?? $_POST['tab'] ?? '' ) );

		if ( empty( $tab_key ) ) {
			$tab_key = $this->get_active_tab();
		}

		$html = $this->render_tab( $tab_key );

		Helper::send_json( true, __( 'Tab loaded.', 'hikmah-login' ), [
			'tab'  => $tab_key,
			'html' => $html,
		] );
	}

	/**
	 * Handle the single-use JSON export download (admin-post).
	 */
	public function handle_export_download() {

		$user_id = get_current_user_id();

		if ( ! check_ajax_referer( 'hikmah_dashboard_nonce', '_wpnonce', false ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hikmah-login' ) );
		}

		$file = isset( $_GET['file'] ) ? basename( sanitize_file_name( wp_unslash( $_GET['file'] ) ) ) : '';

		if ( ! $file ) {
			wp_die( esc_html__( 'Missing export file.', 'hikmah-login' ) );
		}

		$allowed = (array) get_transient( 'hikmah_export_files_' . $user_id );

		if ( ! in_array( $file, $allowed, true ) ) {
			wp_die( esc_html__( 'Export not found or expired.', 'hikmah-login' ) );
		}

		$upload = wp_upload_dir();
		$path   = $upload['basedir'] . '/hikmah-exports/' . $file;

		if ( ! file_exists( $path ) ) {
			delete_transient( 'hikmah_export_files_' . $user_id );
			wp_die( esc_html__( 'Export file no longer exists.', 'hikmah-login' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		header( 'Content-Length: ' . (int) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );

		// Single-use download: remove the file after serving.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		@unlink( $path );

		$allowed = array_values( array_diff( $allowed, [ $file ] ) );
		set_transient( 'hikmah_export_files_' . $user_id, $allowed, 15 * MINUTE_IN_SECONDS );

		exit;
	}
}