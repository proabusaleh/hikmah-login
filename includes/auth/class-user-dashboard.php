<?php
/**
 * User Dashboard Manager
 *
 * Manages the complete user dashboard experience:
 * - Profile view & edit
 * - Avatar management
 * - Password change
 * - Active sessions
 * - Login history
 * - Social connections
 * - Account deletion (GDPR)
 * - Dashboard widgets
 *
 * @package Hikmah_Login
 * @subpackage Auth
 * @since   1.0.0
 */

namespace Hikmah_Login\Auth;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;
use Hikmah_Login\Helpers\Validator;
use Hikmah_Login\Database\DB_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class User_Dashboard {

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
     * Register default dashboard tabs.
     */
    private function register_tabs() {

        $this->tabs = [
            'profile'  => [
                'label'    => __( 'Profile', 'hikmah-login' ),
                'icon'     => '👤',
                'priority' => 10,
                'callback' => [ $this, 'render_profile_tab' ],
            ],
            'security' => [
                'label'    => __( 'Security', 'hikmah-login' ),
                'icon'     => '🔐',
                'priority' => 20,
                'callback' => [ $this, 'render_security_tab' ],
            ],
            'sessions' => [
                'label'    => __( 'Sessions', 'hikmah-login' ),
                'icon'     => '💻',
                'priority' => 30,
                'callback' => [ $this, 'render_sessions_tab' ],
            ],
            'history'  => [
                'label'    => __( 'Login History', 'hikmah-login' ),
                'icon'     => '📋',
                'priority' => 40,
                'callback' => [ $this, 'render_history_tab' ],
            ],
            'social'   => [
                'label'    => __( 'Social Accounts', 'hikmah-login' ),
                'icon'     => '🔗',
                'priority' => 50,
                'callback' => [ $this, 'render_social_tab' ],
                'condition' => function() {
                    if ( ! class_exists( '\\Hikmah_Login\\Social\\Social_Manager' ) ) {
                        return false;
                    }
                    $social = \Hikmah_Login\Social\Social_Manager::get_instance();
                    return is_callable( [ $social, 'has_social_login' ] ) && $social->has_social_login();
                },
            ],
            'delete'   => [
                'label'    => __( 'Delete Account', 'hikmah-login' ),
                'icon'     => '⚠️',
                'priority' => 100,
                'callback' => [ $this, 'render_delete_tab' ],
                'condition' => function() {
                    return 'yes' === get_option( 'hikmah_allow_account_deletion', 'no' );
                },
            ],
        ];

        /**
         * Filter dashboard tabs.
         *
         * @since 1.0.0
         * @param array $tabs Registered tabs.
         */
        $this->tabs = apply_filters( 'hikmah_dashboard_tabs', $this->tabs );

        // Sort by priority.
        uasort( $this->tabs, function( $a, $b ) {
            return ( $a['priority'] ?? 50 ) - ( $b['priority'] ?? 50 );
        });
    }

    /**
     * Register hooks.
     */
    private function register_hooks() {

        // AJAX handlers (wp_ajax_hikmah_*)
        $this->add_ajax( 'hikmah_update_profile', 'ajax_update_profile' );
        $this->add_ajax( 'hikmah_change_password', 'ajax_change_password' );
        $this->add_ajax( 'hikmah_upload_avatar', 'ajax_upload_avatar' );
        $this->add_ajax( 'hikmah_delete_session', 'ajax_delete_session' );
        $this->add_ajax( 'hikmah_delete_all_sessions', 'ajax_delete_all_sessions' );
        $this->add_ajax( 'hikmah_delete_account', 'ajax_delete_account' );
        $this->add_ajax( 'hikmah_unlink_social', 'ajax_unlink_social' );
    }

    /**
     * =============================================
     * TAB MANAGEMENT
     * =============================================
     */

    /**
     * Get visible tabs for the current user.
     *
     * @return array
     */
    public function get_visible_tabs() {

        $visible = [];

        foreach ( $this->tabs as $key => $tab ) {
            // Check condition.
            if ( isset( $tab['condition'] ) && is_callable( $tab['condition'] ) ) {
                if ( ! call_user_func( $tab['condition'] ) ) {
                    continue;
                }
            }

            // Check capability.
            if ( ! empty( $tab['capability'] ) && ! current_user_can( $tab['capability'] ) ) {
                continue;
            }

            $visible[ $key ] = $tab;
        }

        return $visible;
    }

    /**
     * Get the current active tab.
     *
     * @return string
     */
    public function get_active_tab() {

        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'profile';

        $visible = $this->get_visible_tabs();

        return isset( $visible[ $tab ] ) ? $tab : 'profile';
    }

    /**
     * =============================================
     * PROFILE TAB
     * =============================================
     */

    /**
     * Render the profile tab content.
     */
    public function render_profile_tab() {

        $user = wp_get_current_user();
        $avatar_id = (int) get_user_meta( $user->ID, 'hikmah_avatar_id', true );
        $avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';
        ?>
        <div class="hikmah-dash-section">
            <h3><?php esc_html_e( 'Profile Information', 'hikmah-login' ); ?></h3>
            <p class="hikmah-dash-desc"><?php esc_html_e( 'Update your personal information.', 'hikmah-login' ); ?></p>

            <!-- Avatar Section -->
            <div class="hikmah-avatar-section" style="display:flex;align-items:center;gap:20px;margin-bottom:24px;padding:16px;background:var(--hikmah-bg);border-radius:var(--hikmah-radius);">
                <?php wp_nonce_field( 'hikmah_profile_action', 'hikmah_profile_nonce', false ); ?>
                <div class="hikmah-avatar-preview" style="position:relative;">
                    <?php echo get_avatar( $user->ID, 80, '', '', [ 'class' => 'hikmah-avatar-img' ] ); ?>
                    <label for="hikmah-avatar-upload" class="hikmah-avatar-edit" style="
                        position:absolute;bottom:0;right:0;background:var(--hikmah-primary);
                        color:#fff;width:24px;height:24px;border-radius:50%;display:flex;
                        align-items:center;justify-content:center;cursor:pointer;font-size:12px;
                    ">✏️</label>
                    <input type="file" id="hikmah-avatar-upload" accept="image/*" style="display:none;">
                </div>
                <div>
                    <strong><?php echo esc_html( $user->display_name ); ?></strong>
                    <br><small style="color:var(--hikmah-text-muted);"><?php echo esc_html( $user->user_email ); ?></small>
                    <br><small style="color:var(--hikmah-text-muted);">
                        <?php
                        printf(
                            /* translators: %s: Registration date */
                            esc_html__( 'Member since %s', 'hikmah-login' ),
                            esc_html( Helper::format_datetime( $user->user_registered, get_option( 'date_format' ) ) )
                        );
                        ?>
                    </small>
                </div>
            </div>

            <!-- Profile Form -->
            <form id="hikmah-profile-form" class="hikmah-dash-form">
                <?php wp_nonce_field( 'hikmah_profile_action', 'hikmah_profile_nonce' ); ?>

                <div class="hikmah-field-row hikmah-name-row">
                    <div class="hikmah-field hikmah-half">
                        <label class="hikmah-label"><?php esc_html_e( 'First Name', 'hikmah-login' ); ?></label>
                        <input type="text" name="first_name" class="hikmah-input"
                               value="<?php echo esc_attr( $user->first_name ); ?>"
                               style="padding-left:12px;">
                    </div>
                    <div class="hikmah-field hikmah-half">
                        <label class="hikmah-label"><?php esc_html_e( 'Last Name', 'hikmah-login' ); ?></label>
                        <input type="text" name="last_name" class="hikmah-input"
                               value="<?php echo esc_attr( $user->last_name ); ?>"
                               style="padding-left:12px;">
                    </div>
                </div>

                <div class="hikmah-field">
                    <label class="hikmah-label"><?php esc_html_e( 'Display Name', 'hikmah-login' ); ?></label>
                    <input type="text" name="display_name" class="hikmah-input"
                           value="<?php echo esc_attr( $user->display_name ); ?>"
                           style="padding-left:12px;">
                </div>

                <div class="hikmah-field">
                    <label class="hikmah-label"><?php esc_html_e( 'Email Address', 'hikmah-login' ); ?></label>
                    <input type="email" name="email" class="hikmah-input"
                           value="<?php echo esc_attr( $user->user_email ); ?>"
                           style="padding-left:12px;">
                    <?php if ( Helper::is_feature_enabled( 'email_verification_required' ) ) : ?>
                        <span class="hikmah-field-hint">
                            <?php if ( Helper::is_email_verified( $user->ID ) ) : ?>
                                ✅ <?php esc_html_e( 'Verified', 'hikmah-login' ); ?>
                            <?php else : ?>
                                ⚠️ <?php esc_html_e( 'Not verified. Changing email will require re-verification.', 'hikmah-login' ); ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="hikmah-field">
                    <label class="hikmah-label"><?php esc_html_e( 'Website', 'hikmah-login' ); ?></label>
                    <input type="url" name="url" class="hikmah-input"
                           value="<?php echo esc_attr( $user->user_url ); ?>"
                           placeholder="https://" style="padding-left:12px;">
                </div>

                <div class="hikmah-field">
                    <label class="hikmah-label"><?php esc_html_e( 'Bio', 'hikmah-login' ); ?></label>
                    <textarea name="description" class="hikmah-input" rows="3"
                              style="padding-left:12px;resize:vertical;"><?php echo esc_textarea( $user->description ); ?></textarea>
                </div>

                <?php
                /**
                 * Fires inside the profile form for custom fields.
                 *
                 * @since 1.0.0
                 * @param \WP_User $user Current user.
                 */
                do_action( 'hikmah_dashboard_profile_fields', $user );
                ?>

                <button type="submit" class="hikmah-btn hikmah-btn-primary">
                    <?php esc_html_e( 'Save Profile', 'hikmah-login' ); ?>
                </button>
                <span class="hikmah-dash-status"></span>
            </form>
        </div>
        <?php
    }

    /**
     * =============================================
     * SECURITY TAB
     * =============================================
     */

    /**
     * Render the security tab content.
     */
    public function render_security_tab() {

        $user = wp_get_current_user();
        $two_factor = \Hikmah_Login\Security\Two_Factor::get_instance();
        $is_2fa_enabled = $two_factor->is_user_2fa_enabled( $user->ID );
        $password_changed = get_user_meta( $user->ID, 'hikmah_password_changed_at', true );
        ?>
        <div class="hikmah-dash-section">
            <h3><?php esc_html_e( 'Security Settings', 'hikmah-login' ); ?></h3>

            <!-- Password Change -->
            <div class="hikmah-dash-card">
                <h4>🔑 <?php esc_html_e( 'Change Password', 'hikmah-login' ); ?></h4>
                <?php if ( $password_changed ) : ?>
                    <p class="hikmah-field-hint">
                        <?php
                        printf(
                            /* translators: %s: Date */
                            esc_html__( 'Last changed: %s', 'hikmah-login' ),
                            esc_html( Helper::format_datetime( $password_changed ) )
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <form id="hikmah-password-form" class="hikmah-dash-form" style="margin-top:12px;">
                    <?php wp_nonce_field( 'hikmah_password_action', 'hikmah_password_nonce' ); ?>

                    <div class="hikmah-field">
                        <label class="hikmah-label"><?php esc_html_e( 'Current Password', 'hikmah-login' ); ?></label>
                        <input type="password" name="current_password" class="hikmah-input" required
                               autocomplete="current-password" style="padding-left:12px;">
                    </div>

                    <div class="hikmah-field">
                        <label class="hikmah-label"><?php esc_html_e( 'New Password', 'hikmah-login' ); ?></label>
                        <input type="password" name="new_password" class="hikmah-input" required
                               minlength="8" autocomplete="new-password" style="padding-left:12px;">
                    </div>

                    <div class="hikmah-field">
                        <label class="hikmah-label"><?php esc_html_e( 'Confirm New Password', 'hikmah-login' ); ?></label>
                        <input type="password" name="confirm_password" class="hikmah-input" required
                               autocomplete="new-password" style="padding-left:12px;">
                    </div>

                    <button type="submit" class="hikmah-btn hikmah-btn-primary">
                        <?php esc_html_e( 'Update Password', 'hikmah-login' ); ?>
                    </button>
                    <span class="hikmah-dash-status"></span>
                </form>
            </div>

            <!-- 2FA Status -->
            <div class="hikmah-dash-card" style="margin-top:20px;">
                <h4>🔐 <?php esc_html_e( 'Two-Factor Authentication', 'hikmah-login' ); ?></h4>
                <div style="display:flex;align-items:center;gap:12px;margin:12px 0;">
                    <span style="font-size:24px;"><?php echo $is_2fa_enabled ? '✅' : '❌'; ?></span>
                    <span>
                        <?php
                        echo $is_2fa_enabled
                            ? esc_html__( '2FA is enabled', 'hikmah-login' )
                            : esc_html__( '2FA is not enabled', 'hikmah-login' );
                        ?>
                    </span>
                </div>
                <?php if ( ! $is_2fa_enabled ) : ?>
                    <p class="hikmah-field-hint"><?php esc_html_e( 'Add an extra layer of security to your account.', 'hikmah-login' ); ?></p>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'profile', Helper::get_dashboard_url() ) ); ?>#hikmah-2fa-setup"
                       class="hikmah-btn hikmah-btn-outline" style="margin-top:8px;">
                        <?php esc_html_e( 'Set Up 2FA', 'hikmah-login' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Email Verification -->
            <div class="hikmah-dash-card" style="margin-top:20px;">
                <h4>📧 <?php esc_html_e( 'Email Verification', 'hikmah-login' ); ?></h4>
                <div style="display:flex;align-items:center;gap:12px;margin:12px 0;">
                    <span style="font-size:24px;">
                        <?php echo Helper::is_email_verified( $user->ID ) ? '✅' : '⚠️'; ?>
                    </span>
                    <span>
                        <?php
                        echo Helper::is_email_verified( $user->ID )
                            ? esc_html__( 'Email verified', 'hikmah-login' )
                            : esc_html__( 'Email not verified', 'hikmah-login' );
                        ?>
                    </span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * =============================================
     * SESSIONS TAB
     * =============================================
     */

    /**
     * Normalize session rows into displayable records.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private function get_session_records( $user_id ) {

        $session_manager = Session_Manager::get_instance();
        $rows = $session_manager->get_active_sessions( $user_id );

        if ( empty( $rows ) || ! is_array( $rows ) ) {
            return [];
        }

        $current_token = wp_get_session_token();
        $records = [];

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
     * Render the sessions tab content.
     */
    public function render_sessions_tab() {

        $user_id = get_current_user_id();
        $sessions = $this->get_session_records( $user_id );
        ?>
        <div class="hikmah-dash-section">
            <h3><?php esc_html_e( 'Active Sessions', 'hikmah-login' ); ?></h3>
            <p class="hikmah-dash-desc">
                <?php
                printf(
                    /* translators: %d: Number of sessions */
                    esc_html__( 'You are currently logged in on %d device(s).', 'hikmah-login' ),
                    count( $sessions )
                );
                ?>
            </p>

            <?php if ( count( $sessions ) > 1 ) : ?>
                <button type="button" class="hikmah-btn hikmah-btn-outline" id="hikmah-logout-all"
                        style="margin-bottom:16px;color:var(--hikmah-error);border-color:var(--hikmah-error);">
                    🚪 <?php esc_html_e( 'Log Out All Other Devices', 'hikmah-login' ); ?>
                </button>
            <?php endif; ?>

            <?php if ( empty( $sessions ) ) : ?>
                <p style="color:var(--hikmah-text-muted);text-align:center;padding:24px;">
                    <?php esc_html_e( 'No active sessions tracked.', 'hikmah-login' ); ?>
                </p>
            <?php else : ?>
                <div class="hikmah-sessions-list">
                    <?php foreach ( $sessions as $session ) : ?>
                        <div class="hikmah-session-card" style="
                            display:flex;align-items:center;justify-content:space-between;
                            padding:12px 16px;background:var(--hikmah-bg);border-radius:var(--hikmah-radius-sm);
                            margin-bottom:8px;border:1px solid var(--hikmah-border);
                            <?php echo $session['is_current'] ? 'border-color:var(--hikmah-primary);background:var(--hikmah-primary-light);' : ''; ?>
                        ">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <span style="font-size:24px;">
                                    <?php
                                    $device_icons = [ 'mobile' => '📱', 'tablet' => '📟', 'desktop' => '💻' ];
                                    echo $device_icons[ $session['device'] ] ?? '💻';
                                    ?>
                                </span>
                                <div>
                                    <strong>
                                        <?php echo esc_html( $session['browser'] ); ?>
                                        <?php if ( $session['is_current'] ) : ?>
                                            <span style="color:var(--hikmah-primary);font-size:11px;">
                                                (<?php esc_html_e( 'Current', 'hikmah-login' ); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </strong>
                                    <br>
                                    <small style="color:var(--hikmah-text-muted);">
                                        <?php echo esc_html( $session['os'] ); ?> &bull;
                                        <?php echo esc_html( $session['ip_address'] ); ?>
                                    </small>
                                    <br>
                                    <small style="color:var(--hikmah-text-light);">
                                        <?php
                                        printf(
                                            /* translators: %s: Time ago */
                                            esc_html__( 'Last active: %s', 'hikmah-login' ),
                                            esc_html( Helper::time_ago( $session['last_active'] ?: $session['login_at'] ) )
                                        );
                                        ?>
                                    </small>
                                </div>
                            </div>

                            <?php if ( ! $session['is_current'] ) : ?>
                                <button type="button" class="hikmah-btn hikmah-session-revoke"
                                        data-token="<?php echo esc_attr( $session['token_hash'] ); ?>"
                                        style="font-size:12px;padding:4px 10px;color:var(--hikmah-error);">
                                    ✕
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * =============================================
     * LOGIN HISTORY TAB
     * =============================================
     */

    /**
     * Render the login history tab.
     */
    public function render_history_tab() {

        $user_id = get_current_user_id();
        $page = isset( $_GET['hpage'] ) ? absint( wp_unslash( $_GET['hpage'] ) ) : 1;

        $logs = $this->db->get_login_logs( $page, 15, [
            'user_id' => $user_id,
        ]);
        ?>
        <div class="hikmah-dash-section">
            <h3><?php esc_html_e( 'Login History', 'hikmah-login' ); ?></h3>
            <p class="hikmah-dash-desc">
                <?php
                printf(
                    /* translators: %d: Total logins */
                    esc_html__( 'Showing your recent login activity (%d total records).', 'hikmah-login' ),
                    (int) $logs['total']
                );
                ?>
            </p>

            <?php if ( empty( $logs['items'] ) ) : ?>
                <p style="color:var(--hikmah-text-muted);text-align:center;padding:24px;">
                    <?php esc_html_e( 'No login history found.', 'hikmah-login' ); ?>
                </p>
            <?php else : ?>
                <table class="hikmah-dash-table" style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--hikmah-border);">
                            <th style="text-align:left;padding:8px;"><?php esc_html_e( 'Date', 'hikmah-login' ); ?></th>
                            <th style="text-align:left;padding:8px;"><?php esc_html_e( 'Status', 'hikmah-login' ); ?></th>
                            <th style="text-align:left;padding:8px;"><?php esc_html_e( 'IP Address', 'hikmah-login' ); ?></th>
                            <th style="text-align:left;padding:8px;"><?php esc_html_e( 'Device', 'hikmah-login' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs['items'] as $log ) : ?>
                            <tr style="border-bottom:1px solid var(--hikmah-border);">
                                <td style="padding:8px;">
                                    <?php echo esc_html( Helper::format_datetime( $log->login_at ) ); ?>
                                </td>
                                <td style="padding:8px;">
                                    <?php
                                    $status_colors = [
                                        'success' => 'var(--hikmah-success)',
                                        'failed'  => 'var(--hikmah-error)',
                                        'blocked' => 'var(--hikmah-warning)',
                                        'locked'  => 'var(--hikmah-primary)',
                                    ];
                                    $color = $status_colors[ $log->status ] ?? 'var(--hikmah-text-muted)';
                                    ?>
                                    <span style="color:<?php echo esc_attr( $color ); ?>;font-weight:600;">
                                        <?php echo esc_html( ucfirst( $log->status ) ); ?>
                                    </span>
                                </td>
                                <td style="padding:8px;">
                                    <code style="font-size:12px;"><?php echo esc_html( $log->ip_address ); ?></code>
                                </td>
                                <td style="padding:8px;font-size:12px;color:var(--hikmah-text-muted);">
                                    <?php echo esc_html( substr( $log->user_agent, 0, 40 ) ); ?>...
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ( $logs['pages'] > 1 ) : ?>
                    <div class="hikmah-pagination" style="margin-top:16px;text-align:center;">
                        <?php for ( $i = 1; $i <= (int) $logs['pages']; $i++ ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'history', 'hpage' => $i ], Helper::get_dashboard_url() ) ); ?>"
                               class="hikmah-btn" style="padding:4px 10px;font-size:12px;<?php echo $i === $page ? 'background:var(--hikmah-primary);color:var(--hikmah-text-invert);' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * =============================================
     * SOCIAL CONNECTIONS TAB
     * =============================================
     */

    /**
     * Render the social connections tab.
     */
    public function render_social_tab() {

        if ( ! class_exists( '\\Hikmah_Login\\Social\\Social_Manager' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        $social_manager = \Hikmah_Login\Social\Social_Manager::get_instance();
        $providers = $social_manager->get_enabled_providers();
        $linked = $this->db->get_results( 'social_profiles', [
            'where' => [ 'user_id' => $user_id ],
        ]);

        $linked_providers = [];
        foreach ( $linked as $link ) {
            $linked_providers[ $link->provider ] = $link;
        }
        ?>
        <div class="hikmah-dash-section">
            <h3><?php esc_html_e( 'Connected Social Accounts', 'hikmah-login' ); ?></h3>
            <p class="hikmah-dash-desc"><?php esc_html_e( 'Manage your social login connections.', 'hikmah-login' ); ?></p>

            <?php foreach ( $providers as $key => $provider ) : ?>
                <div class="hikmah-social-connection" style="
                    display:flex;align-items:center;justify-content:space-between;
                    padding:12px 16px;background:var(--hikmah-bg);border-radius:var(--hikmah-radius-sm);
                    margin-bottom:8px;border:1px solid var(--hikmah-border);
                ">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:24px;">
                            <?php echo 'google' === $key ? '🔵' : '📘'; ?>
                        </span>
                        <div>
                            <strong><?php echo esc_html( $provider->get_provider_name() ); ?></strong>
                            <br>
                            <?php if ( isset( $linked_providers[ $key ] ) ) : ?>
                                <small style="color:var(--hikmah-success);">
                                    ✅ <?php esc_html_e( 'Connected', 'hikmah-login' ); ?>
                                    (<?php echo esc_html( Helper::format_datetime( $linked_providers[ $key ]->linked_at, get_option( 'date_format' ) ) ); ?>)
                                </small>
                            <?php else : ?>
                                <small style="color:var(--hikmah-text-muted);">
                                    <?php esc_html_e( 'Not connected', 'hikmah-login' ); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( isset( $linked_providers[ $key ] ) ) : ?>
                        <button type="button" class="hikmah-btn hikmah-unlink-social"
                                data-provider="<?php echo esc_attr( $key ); ?>"
                                style="font-size:12px;padding:4px 12px;color:var(--hikmah-error);border-color:var(--hikmah-error);">
                            <?php esc_html_e( 'Disconnect', 'hikmah-login' ); ?>
                        </button>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $provider->get_authorization_url() ); ?>"
                           class="hikmah-btn hikmah-btn-outline" style="font-size:12px;padding:4px 12px;">
                            <?php esc_html_e( 'Connect', 'hikmah-login' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * =============================================
     * DELETE ACCOUNT TAB (GDPR)
     * =============================================
     */

    /**
     * Render the account deletion tab.
     */
    public function render_delete_tab() {

        $user = wp_get_current_user();
        ?>
        <div class="hikmah-dash-section">
            <h3 style="color:var(--hikmah-error);">⚠️ <?php esc_html_e( 'Delete Account', 'hikmah-login' ); ?></h3>

            <div class="hikmah-notice hikmah-notice-warning">
                <p>
                    <strong><?php esc_html_e( 'Warning:', 'hikmah-login' ); ?></strong>
                    <?php esc_html_e( 'This action is permanent and cannot be undone. All your data will be deleted.', 'hikmah-login' ); ?>
                </p>
            </div>

            <p><?php esc_html_e( 'Deleting your account will:', 'hikmah-login' ); ?></p>
            <ul style="color:var(--hikmah-text-muted);font-size:14px;line-height:2;">
                <li><?php esc_html_e( 'Permanently delete your profile and personal data', 'hikmah-login' ); ?></li>
                <li><?php esc_html_e( 'Remove all your login history and session data', 'hikmah-login' ); ?></li>
                <li><?php esc_html_e( 'Disconnect all social login connections', 'hikmah-login' ); ?></li>
                <li><?php esc_html_e( 'Delete your 2FA settings and backup codes', 'hikmah-login' ); ?></li>
            </ul>

            <form id="hikmah-delete-form" class="hikmah-dash-form" style="margin-top:20px;padding:20px;background:var(--hikmah-error-bg);border-radius:var(--hikmah-radius);border:1px solid var(--hikmah-error-bg);">
                <?php wp_nonce_field( 'hikmah_delete_action', 'hikmah_delete_nonce' ); ?>

                <div class="hikmah-field">
                    <label class="hikmah-label">
                        <?php
                        printf(
                            /* translators: %s: Username */
                            esc_html__( 'Type "%s" to confirm:', 'hikmah-login' ),
                            '<strong>' . esc_html( $user->user_login ) . '</strong>'
                        );
                        ?>
                    </label>
                    <input type="text" name="confirm_username" class="hikmah-input"
                           required style="padding-left:12px;"
                           data-expected="<?php echo esc_attr( $user->user_login ); ?>">
                </div>

                <div class="hikmah-field">
                    <label class="hikmah-label"><?php esc_html_e( 'Enter your password:', 'hikmah-login' ); ?></label>
                    <input type="password" name="password" class="hikmah-input" required
                           style="padding-left:12px;">
                </div>

                <button type="submit" class="hikmah-btn" id="hikmah-delete-btn"
                        style="background:var(--hikmah-error);color:var(--hikmah-text-invert);" disabled>
                    🗑️ <?php esc_html_e( 'Permanently Delete My Account', 'hikmah-login' ); ?>
                </button>
            </form>
        </div>
        <?php
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

        check_ajax_referer( 'hikmah_profile_action', 'hikmah_profile_nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            Helper::send_json( false, __( 'Not logged in.', 'hikmah-login' ), [], 401 );
        }

        $data = [
            'ID'           => $user_id,
            'first_name'   => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
            'last_name'    => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
            'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
            'user_url'     => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
            'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
        ];

        // Handle email change.
        $new_email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $current_user = get_userdata( $user_id );

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

        do_action( 'hikmah_dashboard_profile_updated', $user_id, $data );

        Helper::send_json( true, __( 'Profile updated successfully!', 'hikmah-login' ) );
    }

    /**
     * AJAX: Change password.
     */
    public function ajax_change_password() {

        check_ajax_referer( 'hikmah_password_action', 'hikmah_password_nonce' );

        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );

        $current  = wp_unslash( $_POST['current_password'] ?? '' );
        $new_pass = wp_unslash( $_POST['new_password'] ?? '' );
        $confirm  = wp_unslash( $_POST['confirm_password'] ?? '' );

        // Verify current password.
        if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
            Helper::send_json( false, __( 'Current password is incorrect.', 'hikmah-login' ), [], 401 );
        }

        // Validate new password.
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

        // Update password (invalidates all existing sessions).
        wp_set_password( $new_pass, $user_id );

        // Track change date.
        update_user_meta( $user_id, 'hikmah_password_changed_at', Helper::current_datetime() );

        // Re-login the user with a fresh auth cookie.
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true, is_ssl() );

        do_action( 'hikmah_dashboard_password_changed', $user_id );

        Helper::send_json( true, __( 'Password changed successfully!', 'hikmah-login' ) );
    }

    /**
     * AJAX: Upload avatar.
     */
    public function ajax_upload_avatar() {

        check_ajax_referer( 'hikmah_profile_action', 'hikmah_profile_nonce' );

        $user_id = get_current_user_id();

        if ( empty( $_FILES['avatar'] ) ) {
            Helper::send_json( false, __( 'No file selected.', 'hikmah-login' ), [], 400 );
        }

        // Validate file.
        $file = $_FILES['avatar'];
        $allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
        $max_size = 2 * 1024 * 1024; // 2MB

        if ( ! in_array( $file['type'], $allowed_types, true ) ) {
            Helper::send_json( false, __( 'Only JPG, PNG, GIF, and WebP images are allowed.', 'hikmah-login' ), [], 400 );
        }

        if ( $file['size'] > $max_size ) {
            Helper::send_json( false, __( 'Image must be less than 2MB.', 'hikmah-login' ), [], 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'avatar', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            Helper::send_json( false, $attachment_id->get_error_message(), [], 400 );
        }

        // Set as user avatar.
        update_user_meta( $user_id, 'hikmah_avatar_id', $attachment_id );

        $url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

        Helper::send_json( true, __( 'Avatar updated!', 'hikmah-login' ), [
            'avatar_url' => $url ? $url : wp_get_attachment_url( $attachment_id ),
        ]);
    }

    /**
     * AJAX: Delete a session.
     */
    public function ajax_delete_session() {

        check_ajax_referer( 'hikmah_login_nonce', 'nonce' );

        $user_id = get_current_user_id();
        $token_hash = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

        if ( strlen( $token_hash ) !== 64 ) {
            Helper::send_json( false, __( 'Invalid session token.', 'hikmah-login' ), [], 400 );
        }

        // Locate the session row that matches the hashed handle.
        $session_manager = Session_Manager::get_instance();
        $rows = $session_manager->get_active_sessions( $user_id );

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
     * AJAX: Delete all other sessions.
     */
    public function ajax_delete_all_sessions() {

        check_ajax_referer( 'hikmah_login_nonce', 'nonce' );

        $user_id = get_current_user_id();
        $session_manager = Session_Manager::get_instance();
        $count = $session_manager->destroy_other_sessions( $user_id );

        Helper::send_json( true, sprintf(
            /* translators: %d: Number of sessions */
            __( '%d session(s) revoked.', 'hikmah-login' ),
            $count
        ));
    }

    /**
     * AJAX: Delete account (GDPR).
     */
    public function ajax_delete_account() {

        check_ajax_referer( 'hikmah_delete_action', 'hikmah_delete_nonce' );

        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );

        $confirm_username = sanitize_text_field( wp_unslash( $_POST['confirm_username'] ?? '' ) );
        $password = wp_unslash( $_POST['password'] ?? '' );

        // Verify username.
        if ( $confirm_username !== $user->user_login ) {
            Helper::send_json( false, __( 'Username confirmation does not match.', 'hikmah-login' ), [], 400 );
        }

        // Verify password.
        if ( ! wp_check_password( $password, $user->user_pass, $user_id ) ) {
            Helper::send_json( false, __( 'Incorrect password.', 'hikmah-login' ), [], 401 );
        }

        // Don't allow admin deletion.
        if ( $user->has_cap( 'manage_options' ) ) {
            Helper::send_json( false, __( 'Administrator accounts cannot be self-deleted.', 'hikmah-login' ), [], 403 );
        }

        /**
         * Fires before account deletion.
         *
         * @since 1.0.0
         * @param int $user_id User ID being deleted.
         */
        do_action( 'hikmah_before_account_deletion', $user_id );

        // Clean up plugin data.
        $this->db->delete( 'email_tokens', [ 'user_id' => $user_id ] );
        $this->db->delete( 'two_factor', [ 'user_id' => $user_id ] );
        $this->db->delete( 'social_profiles', [ 'user_id' => $user_id ] );

        // Clean up plugin user meta.
        delete_user_meta( $user_id, 'hikmah_avatar_id' );
        delete_user_meta( $user_id, 'hikmah_password_changed_at' );

        // Delete user.
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );

        // Clear cookies.
        wp_clear_auth_cookie();

        Helper::log( "Account deleted (GDPR): User ID {$user_id}" );

        Helper::send_json( true, __( 'Account deleted.', 'hikmah-login' ), [
            'redirect' => home_url( '/' ),
        ]);
    }

    /**
     * AJAX: Unlink social account.
     */
    public function ajax_unlink_social() {

        check_ajax_referer( 'hikmah_login_nonce', 'nonce' );

        if ( ! class_exists( '\\Hikmah_Login\\Social\\Social_Manager' ) ) {
            Helper::send_json( false, __( 'Social login is not available.', 'hikmah-login' ), [], 400 );
        }

        $user_id = get_current_user_id();
        $provider = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );

        $social_manager = \Hikmah_Login\Social\Social_Manager::get_instance();
        $provider_instance = $social_manager->get_provider( $provider );

        if ( ! $provider_instance ) {
            Helper::send_json( false, __( 'Invalid provider.', 'hikmah-login' ), [], 400 );
        }

        $result = $provider_instance->unlink_profile( $user_id );

        if ( ! $result ) {
            Helper::send_json( false, __( 'Cannot unlink. Set a password first.', 'hikmah-login' ), [], 400 );
        }

        Helper::send_json( true, __( 'Social account disconnected.', 'hikmah-login' ) );
    }
}