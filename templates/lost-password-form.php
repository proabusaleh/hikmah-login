<?php
/**
 * Template: Front-End Lost Password Recovery Form
 *
 * Clean, minimal interface to request security key links for recovery.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="hikmah-auth-card">
    <div class="hikmah-auth-card__header">
        <?php
        $logo_url = hikmah_get_option('login_logo', '');
        if (!empty($logo_url)) {
            printf('<img class="hikmah-auth-card__logo" src="%s" alt="%s" />', esc_url($logo_url), esc_attr(get_bloginfo('name')));
        } else {
            printf('<h2 class="hikmah-auth-card__title">%s</h2>', esc_html__('Recover Password', 'hikmah-login'));
        }
        ?>
        <p class="hikmah-auth-card__subtitle"><?php esc_html_e('Enter your registered credentials to request a recovery link.', 'hikmah-login'); ?></p>
    </div>

    <div class="hikmah-auth-card__body">

        <!-- Outer authentication form container -->
        <form id="hikmah-lostpassword-form" class="hikmah-auth-form" method="post" action="<?php echo esc_url(wp_lostpassword_url()); ?>">
            
            <?php wp_nonce_field('hikmah_lostpassword_action', 'hikmah_lostpassword_nonce'); ?>

            <!-- Username or Email Field -->
            <div class="hikmah-form-group">
                <label for="hikmah-lost-user-login" class="hikmah-form-label"><?php esc_html_e('Username or Email', 'hikmah-login'); ?></label>
                <div class="hikmah-form-input-wrapper">
                    <span class="hikmah-form-icon dashicons dashicons-email"></span>
                    <input type="text" 
                           name="user_login" 
                           id="hikmah-lost-user-login" 
                           class="hikmah-form-input" 
                           placeholder="<?php esc_attr_e('Enter username or registered email', 'hikmah-login'); ?>" 
                           required 
                           autocomplete="username" />
                </div>
                <p class="hikmah-form-description">
                    <?php esc_html_e('A password reset link will be sent to your registered email address.', 'hikmah-login'); ?>
                </p>
            </div>

            <!-- Submit Button Trigger -->
            <button type="submit" class="hikmah-btn hikmah-btn--primary hikmah-btn--block" id="hikmah-lostpassword-submit">
                <span class="hikmah-btn__text"><?php esc_html_e('Request Reset Link', 'hikmah-login'); ?></span>
                <span class="hikmah-btn__loading" style="display:none;">
                    <span class="hikmah-spinner"></span>
                    <?php esc_html_e('Sending link...', 'hikmah-login'); ?>
                </span>
            </button>

        </form>

    </div>

    <div class="hikmah-auth-card__footer">
        <p><a href="<?php echo esc_url(wp_login_url()); ?>"><?php esc_html_e('Back to Login', 'hikmah-login'); ?></a></p>
    </div>
</div>
