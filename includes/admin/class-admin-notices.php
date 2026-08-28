<?php
/**
 * Admin Notices
 *
 * Manages admin notice display for the plugin.
 * Supports dismissible notices, one-time notices, and persistent notices.
 *
 * @package Hikmah_Login
 * @subpackage Admin
 * @since   1.0.0
 */

namespace Hikmah_Login\Admin;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Notices {

    use Singleton;
    use Hooks;

    /**
     * Notice types.
     */
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR   = 'error';
    const TYPE_WARNING = 'warning';
    const TYPE_INFO    = 'info';

    /**
     * Constructor.
     */
    private function __construct() {
        $this->add_action( 'admin_notices', 'display_notices' );
        $this->add_ajax( 'hikmah_dismiss_notice', 'ajax_dismiss_notice' );
        $this->add_ajax_nopriv( 'hikmah_dismiss_notice', 'ajax_dismiss_notice' );
    }

    /**
     * Add a notice to be displayed.
     *
     * @param string $id          Unique notice ID.
     * @param string $message     Notice message (HTML allowed).
     * @param string $type        Notice type (success, error, warning, info).
     * @param bool   $dismissible Whether the notice can be dismissed.
     * @param bool   $persistent  Whether the notice persists after page reload.
     */
    public function add_notice( $id, $message, $type = self::TYPE_INFO, $dismissible = true, $persistent = false ) {

        $notices = get_transient( 'hikmah_login_admin_notices' );

        if ( ! is_array( $notices ) ) {
            $notices = [];
        }

        $notices[ $id ] = [
            'message'     => $message,
            'type'        => $type,
            'dismissible' => $dismissible,
            'persistent'  => $persistent,
        ];

        set_transient( 'hikmah_login_admin_notices', $notices, HOUR_IN_SECONDS );
    }

    /**
     * Remove a notice.
     *
     * @param string $id Notice ID.
     */
    public function remove_notice( $id ) {

        $notices = get_transient( 'hikmah_login_admin_notices' );

        if ( is_array( $notices ) && isset( $notices[ $id ] ) ) {
            unset( $notices[ $id ] );
            set_transient( 'hikmah_login_admin_notices', $notices, HOUR_IN_SECONDS );
        }
    }

    /**
     * Display all pending notices.
     */
    public function display_notices() {

        // Only show to admins
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notices = get_transient( 'hikmah_login_admin_notices' );

        if ( ! is_array( $notices ) || empty( $notices ) ) {
            return;
        }

        foreach ( $notices as $id => $notice ) {

            // Check if dismissed
            if ( $this->is_dismissed( $id ) ) {
                continue;
            }

            $classes = [
                'notice',
                'notice-' . esc_attr( $notice['type'] ),
                'hikmah-notice',
                'hikmah-notice-' . esc_attr( $id ),
            ];

            if ( $notice['dismissible'] ) {
                $classes[] = 'is-dismissible';
            }

            printf(
                '<div class="%1$s" data-notice-id="%2$s">
                    <p>%3$s</p>
                </div>',
                esc_attr( implode( ' ', $classes ) ),
                esc_attr( $id ),
                wp_kses_post( $notice['message'] )
            );

            // Remove non-persistent notices after display
            if ( ! $notice['persistent'] ) {
                $this->remove_notice( $id );
            }
        }

        // Output dismiss script
        $this->output_dismiss_script();
    }

    /**
     * Check if a notice has been dismissed by the current user.
     *
     * @param string $id Notice ID.
     * @return bool
     */
    private function is_dismissed( $id ) {
        $user_id = get_current_user_id();
        return (bool) get_user_meta( $user_id, "hikmah_dismissed_notice_{$id}", true );
    }

    /**
     * AJAX handler for dismissing notices.
     */
    public function ajax_dismiss_notice() {

        check_ajax_referer( 'hikmah_admin_nonce', 'nonce' );

        $notice_id = sanitize_key( $_POST['notice_id'] ?? '' );

        if ( empty( $notice_id ) ) {
            wp_send_json_error( 'Invalid notice ID.' );
        }

        $user_id = get_current_user_id();
        update_user_meta( $user_id, "hikmah_dismissed_notice_{$notice_id}", true );

        wp_send_json_success();
    }

    /**
     * Output inline JavaScript for notice dismissal.
     */
    private function output_dismiss_script() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.hikmah-notice .notice-dismiss', function() {
                var $notice = $(this).closest('.hikmah-notice');
                var noticeId = $notice.data('notice-id');

                $.post(ajaxurl, {
                    action: 'hikmah_dismiss_notice',
                    nonce: '<?php echo esc_js( wp_create_nonce( 'hikmah_admin_nonce' ) ); ?>',
                    notice_id: noticeId
                });
            });
        });
        </script>
        <?php
    }

    /**
     * =============================================
     * PRE-BUILT NOTICE HELPERS
     * =============================================
     */

    /**
     * Show a welcome notice after activation.
     */
    public function show_welcome_notice() {
        $this->add_notice(
            'welcome',
            sprintf(
                /* translators: %s: Settings page URL */
                __( '🎉 <strong>Hikmah Login</strong> is now active! <a href="%s">Configure your settings</a> to get started.', 'hikmah-login' ),
                admin_url( 'admin.php?page=hikmah-login' )
            ),
            self::TYPE_SUCCESS,
            true,
            false
        );
    }

    /**
     * Show a security warning notice.
     *
     * @param string $message Warning message.
     */
    public function show_security_warning( $message ) {
        $this->add_notice(
            'security_warning',
            '🔒 <strong>Hikmah Login Security:</strong> ' . $message,
            self::TYPE_WARNING,
            true,
            true
        );
    }

    /**
     * Show a migration notice.
     */
    public function show_migration_notice() {
        $this->add_notice(
            'migration_running',
            __( '⚙️ Hikmah Login is updating its database. Please do not deactivate the plugin.', 'hikmah-login' ),
            self::TYPE_INFO,
            false,
            true
        );
    }

    /**
     * Show an SSL warning if site is not HTTPS.
     */
    public function maybe_show_ssl_warning() {
        if ( ! is_ssl() ) {
            $this->show_security_warning(
                __( 'Your site is not using HTTPS. Login credentials may be transmitted insecurely. We strongly recommend enabling SSL.', 'hikmah-login' )
            );
        }
    }
}
