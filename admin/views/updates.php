<?php
/**
 * Admin Updates view.
 *
 * GitHub-ready release updater: master toggle, stable/beta channel,
 * optional access token, and a "check now" action. Releases are pulled
 * from the GitHub releases API and surface on the Plugins screen.
 *
 * @package    Hikmah_Login
 * @subpackage Hikmah_Login/admin/views
 */

namespace Hikmah_Login\Admin;

use Hikmah_Login\Updater\Github_Updater;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$settings_saved  = false;
$checked_now     = false;
$status_message  = '';

if ( isset( $_POST['hikmah_updates_settings_submit'] )
    || isset( $_POST['hikmah_updates_check_now'] ) ) {

    if ( ! check_admin_referer( 'hikmah_updates_settings_save', 'hikmah_updates_settings_nonce' )
        || ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'hikmah-login' ) );
    }

    update_option(
        'hikmah_updates_enabled',
        isset( $_POST['hikmah_updates_enabled'] ) && '1' === $_POST['hikmah_updates_enabled'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_updates_channel',
        'beta' === sanitize_text_field( wp_unslash( $_POST['hikmah_updates_channel'] ?? '' ) ) ? 'beta' : 'stable'
    );

    $token = sanitize_text_field( wp_unslash( $_POST['hikmah_updates_token'] ?? '' ) );
    update_option( 'hikmah_updates_token', $token );

    $settings_saved = true;

    if ( isset( $_POST['hikmah_updates_check_now'] ) ) {
        Github_Updater::get_instance()->refresh_cache();
        $checked_now = true;
    }
}

$updater = Github_Updater::get_instance();

$enabled        = 'yes' === get_option( 'hikmah_updates_enabled', 'yes' );
$channel        = get_option( 'hikmah_updates_channel', 'stable' );
$token_saved    = (string) get_option( 'hikmah_updates_token', '' );
$latest         = $updater->get_latest_release();
$cache_timeout  = get_option( '_transient_timeout_' . Github_Updater::CACHE_KEY );

if ( $latest ) {
    $new_version = ltrim( trim( $latest['version'] ), 'vV' );
    $update_available = version_compare( $new_version, HIKMAH_LOGIN_VERSION, '>' );
} else {
    $new_version = '';
    $update_available = false;
}

$version = (int) $cache_timeout - (int) current_time( 'timestamp' );

if ( $checked_now ) {
    if ( $latest ) {
        $status_message = sprintf(
            /* translators: %s: latest version found. */
            __( 'Checked GitHub — latest release: %s', 'hikmah-login' ),
            $new_version
        );
    } else {
        $status_message = __( 'Checked GitHub — no published release found yet.', 'hikmah-login' );
    }
} elseif ( $settings_saved ) {
    $status_message = __( 'Update options saved.', 'hikmah-login' );
}
?>
<div class="wrap hikmah-admin-wrap">

    <div class="hikmah-hero">
        <div class="hikmah-hero__inner">
            <h1 class="hikmah-hero__title"><span>🚀</span> <?php esc_html_e( 'Updates', 'hikmah-login' ); ?></h1>
            <p class="hikmah-hero__subtitle">
                <?php esc_html_e( 'Receive future versions automatically from the GitHub releases of this plugin.', 'hikmah-login' ); ?>
                <a href="https://github.com/proabusaleh/hikmah-login" target="_blank" rel="noopener">github.com/proabusaleh/hikmah-login</a>
            </p>
            <div class="hikmah-hero__actions">
                <a class="hikmah-btn hikmah-btn--light" href="<?php echo esc_url( admin_url( 'admin.php?page=hikmah-login' ) ); ?>">📊 <?php esc_html_e( 'Back to Dashboard', 'hikmah-login' ); ?></a>
                <a class="hikmah-btn hikmah-btn--light" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">🧩 <?php esc_html_e( 'Plugins Screen', 'hikmah-login' ); ?></a>
            </div>
        </div>
    </div>

    <?php if ( $status_message ) : ?>
        <div class="hikmah-toast"><span>✅</span> <?php echo esc_html( $status_message ); ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'hikmah_updates_settings_save', 'hikmah_updates_settings_nonce' ); ?>

        <!-- Status -->
        <div class="hikmah-card">
            <div class="hikmah-card__head">
                <div>
                    <h2 class="hikmah-card__title"><span class="hikmah-icon-chip">📡</span> <?php esc_html_e( 'Release Status', 'hikmah-login' ); ?></h2>
                    <p class="hikmah-card__desc"><?php esc_html_e( 'Active version against the latest release published on GitHub.', 'hikmah-login' ); ?></p>
                </div>
            </div>
            <div class="hikmah-card__body">
                <div class="hikmah-stat-grid">
                    <div class="hikmah-stat-card">
                        <span class="hikmah-stat-card__label"><?php esc_html_e( 'Installed Version', 'hikmah-login' ); ?></span>
                        <span class="hikmah-stat-card__value"><?php echo esc_html( HIKMAH_LOGIN_VERSION ); ?></span>
                    </div>
                    <div class="hikmah-stat-card">
                        <span class="hikmah-stat-card__label"><?php esc_html_e( 'Latest Release', 'hikmah-login' ); ?></span>
                        <?php if ( $latest ) : ?>
                            <span class="hikmah-stat-card__value"><?php echo esc_html( $new_version ); ?></span>
                            <span class="hikmah-badge <?php echo $update_available ? 'hikmah-badge--amber' : 'hikmah-badge--green'; ?>">
                                <?php echo $update_available
                                    ? esc_html__( 'Update available', 'hikmah-login' )
                                    : esc_html__( 'Up to date', 'hikmah-login' ); ?>
                            </span>
                        <?php else : ?>
                            <span class="hikmah-stat-card__value">—</span>
                            <span class="hikmah-badge"><?php esc_html_e( 'No release published yet', 'hikmah-login' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="hikmah-stat-card">
                        <span class="hikmah-stat-card__label"><?php esc_html_e( 'Last Checked', 'hikmah-login' ); ?></span>
                        <span class="hikmah-stat-card__value"><?php echo $version > 0 ? esc_html( human_time_diff( $cache_timeout - $version ) ) . ' ' . esc_html__( 'ago', 'hikmah-login' ) : '—'; ?></span>
                    </div>
                </div>

                <?php if ( $update_available ) : ?>
                    <p class="hikmah-field__desc" style="margin-top:12px;">
                        🔔 <?php echo esc_html( sprintf(
                            /* translators: %s: new version number. */
                            __( 'Version %s is ready. Install it from the Plugins screen or under Update Core.', 'hikmah-login' ),
                            $new_version
                        ) ); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Settings -->
        <div class="hikmah-card">
            <div class="hikmah-card__head">
                <div>
                    <h2 class="hikmah-card__title"><span class="hikmah-icon-chip hikmah-icon-chip--green">⚙️</span> <?php esc_html_e( 'Update Options', 'hikmah-login' ); ?></h2>
                    <p class="hikmah-card__desc"><?php esc_html_e( 'Control how Hikmah Login checks for and receives future updates.', 'hikmah-login' ); ?></p>
                </div>
            </div>
            <div class="hikmah-card__body">
                <div class="hikmah-field">
                    <label class="hikmah-toggle">
                        <input type="checkbox" id="hikmah_updates_enabled" name="hikmah_updates_enabled" value="1" <?php checked( $enabled, true ); ?>>
                        <span class="hikmah-toggle__track"><span class="hikmah-toggle__thumb"></span></span>
                    </label>
                    <span class="hikmah-field__main">
                        <label class="hikmah-field__label" for="hikmah_updates_enabled"><?php esc_html_e( 'Enable Auto-Updates', 'hikmah-login' ); ?></label>
                        <span class="hikmah-field__desc"><?php esc_html_e( 'Shows new releases on the Plugins / Updates screens. On by default.', 'hikmah-login' ); ?></span>
                    </span>
                </div>

                <div class="hikmah-field hikmah-field--stacked">
                    <label class="hikmah-field__label" for="hikmah_updates_channel"><?php esc_html_e( 'Release Channel', 'hikmah-login' ); ?></label>
                    <span class="hikmah-field__control">
                        <select id="hikmah_updates_channel" name="hikmah_updates_channel">
                            <option value="stable" <?php selected( $channel, 'stable' ); ?>><?php esc_html_e( 'Stable — production releases only', 'hikmah-login' ); ?></option>
                            <option value="beta" <?php selected( $channel, 'beta' ); ?>><?php esc_html_e( 'Beta — include pre-releases', 'hikmah-login' ); ?></option>
                        </select>
                        <span class="hikmah-hint"><?php esc_html_e( 'Beta includes pre-release tags, useful for testing ahead of stable.', 'hikmah-login' ); ?></span>
                    </span>
                </div>

                <div class="hikmah-field hikmah-field--stacked">
                    <label class="hikmah-field__label" for="hikmah_updates_token"><?php esc_html_e( 'GitHub Access Token (optional)', 'hikmah-login' ); ?></label>
                    <span class="hikmah-field__control">
                        <input type="password" id="hikmah_updates_token" name="hikmah_updates_token" class="regular-text code" style="width:100%;max-width:480px;" value="<?php echo esc_attr( $token_saved ); ?>" autocomplete="off">
                        <span class="hikmah-hint"><?php esc_html_e( 'Leave blank for a public repository. Add a Fine-grained token to update a private fork or avoid API rate limits.', 'hikmah-login' ); ?></span>
                    </span>
                </div>
            </div>
        </div>

        <div class="hikmah-savebar">
            <span class="hikmah-savebar__hint">💡 <?php esc_html_e( 'Queries to GitHub are cached for 6 hours.', 'hikmah-login' ); ?></span>
            <div>
                <button type="submit" name="hikmah_updates_check_now" class="hikmah-btn hikmah-btn--ghost" value="1">
                    🔄 <?php esc_html_e( 'Check Now', 'hikmah-login' ); ?>
                </button>
                <button type="submit" name="hikmah_updates_settings_submit" class="hikmah-btn hikmah-btn--primary">
                    💾 <?php esc_html_e( 'Save Update Options', 'hikmah-login' ); ?>
                </button>
            </div>
        </div>
    </form>
</div>