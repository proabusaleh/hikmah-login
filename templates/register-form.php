<?php
/**
 * Template: Standard Dynamic Registration Form
 *
 * Front-end sign-up template with automated password strength checking,
 * profile details, and customizable hooks.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if open registration is active in core settings
if (!get_option('users_can_register')) {
    echo '<div class="hikmah-auth-alert hikmah-auth-alert--error">';
    echo '<span class="dashicons dashicons-warning"></span>';
    echo '<p>' . esc_html__('User registration is currently disabled.', 'hikmah-login') . '</p>';
    echo '</div>';
    return;
}
?>

<div class="hikmah-auth-card">
    <div class="hikmah-auth-card__header">
        <?php
        $logo_url = hikmah_get_option('login_logo', '');
        if (!empty($logo_url)) {
            printf('<img class="hikmah-auth-card__logo" src="%s" alt="%s" />', esc_url($logo_url), esc_attr(get_bloginfo('name')));
        } else {
            printf('<h2 class="hikmah-auth-card__title">%s</h2>', esc_html__('Create Account', 'hikmah-login'));
        }
        ?>
        <p class="hikmah-auth-card__subtitle"><?php esc_html_e('Set up your personal workspace portal instantly', 'hikmah-login'); ?></p>
    </div>

    <div class="hikmah-auth-card__body">

        <form id="hikmah-registration-form" class="hikmah-auth-form" method="post" action="">
            
            <?php wp_nonce_field('hikmah_register_action', 'hikmah_register_nonce'); ?>

            <!-- Username Field -->
            <div class="hikmah-form-group">
                <label for="hikmah-reg-username" class="hikmah-form-label"><?php esc_html_e('Username', 'hikmah-login'); ?></label>
                <div class="hikmah-form-input-wrapper">
                    <span class="hikmah-form-icon dashicons dashicons-admin-users"></span>
                    <input type="text" 
                           name="user_login" 
                           id="hikmah-reg-username" 
                           class="hikmah-form-input" 
                           placeholder="<?php esc_attr_e('Choose a unique username', 'hikmah-login'); ?>" 
                           required 
                           autocomplete="username" />
                </div>
            </div>

            <!-- Email Field -->
            <div class="hikmah-form-group">
                <label for="hikmah-reg-email" class="hikmah-form-label"><?php esc_html_e('Email Address', 'hikmah-login'); ?></label>
                <div class="hikmah-form-input-wrapper">
                    <span class="hikmah-form-icon dashicons dashicons-email"></span>
                    <input type="email" 
                           name="user_email" 
                           id="hikmah-reg-email" 
                           class="hikmah-form-input" 
                           placeholder="you@example.com" 
                           required 
                           autocomplete="email" />
                </div>
            </div>

            <!-- Password Field -->
            <div class="hikmah-form-group">
                <label for="hikmah_new_password" class="hikmah-form-label"><?php esc_html_e('Password', 'hikmah-login'); ?></label>
                <div class="hikmah-form-input-wrapper">
                    <span class="hikmah-form-icon dashicons dashicons-lock"></span>
                    <input type="password" 
                           name="user_pass" 
                           id="hikmah_new_password" 
                           class="hikmah-form-input" 
                           placeholder="<?php esc_attr_e('Generate a secure password', 'hikmah-login'); ?>" 
                           required 
                           autocomplete="new-password" />
                    <button type="button" class="hikmah-password-toggle" data-target="hikmah_new_password" aria-label="<?php esc_attr_e('Show password', 'hikmah-login'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>

                <!-- Dynamic password strength meter component -->
                <div class="hikmah-password-strength-container">
                    <div class="hikmah-password-strength">
                        <div class="hikmah-password-strength__fill" id="hikmah-password-strength-fill"></div>
                    </div>
                    <span class="hikmah-password-strength__text" id="hikmah-password-strength-text"></span>
                </div>

                <!-- Password Rules List -->
                <ul class="hikmah-password-requirements">
                    <li data-rule="length" class="hikmah-password-requirements__item">
                        <span class="dashicons dashicons-minus"></span> <?php esc_html_e('At least 8 characters long', 'hikmah-login'); ?>
                    </li>
                    <li data-rule="uppercase" class="hikmah-password-requirements__item">
                        <span class="dashicons dashicons-minus"></span> <?php esc_html_e('One uppercase letter', 'hikmah-login'); ?>
                    </li>
                    <li data-rule="number" class="hikmah-password-requirements__item">
                        <span class="dashicons dashicons-minus"></span> <?php esc_html_e('One numeric character', 'hikmah-login'); ?>
                    </li>
                    <li data-rule="special" class="hikmah-password-requirements__item">
                        <span class="dashicons dashicons-minus"></span> <?php esc_html_e('One non-alphanumeric special character', 'hikmah-login'); ?>
                    </li>
                </ul>
            </div>

            <!-- Custom Registration Action Fields for Extensibility -->
            <?php do_action('hikmah_register_form_fields'); ?>

            <!-- Terms and Privacy Checkbox -->
            <div class="hikmah-form-group">
                <label class="hikmah-form-checkbox">
                    <input type="checkbox" name="hikmah_terms" id="hikmah-terms" required />
                    <span class="hikmah-form-checkbox__label">
                        <?php
                        printf(
                            /* translators: 1: Terms of Service URL, 2: Privacy Policy URL */
                            esc_html__('I accept the %1$s and have read the %2$s.', 'hikmah-login'),
                            '<a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank">' . esc_html__('Terms of Service', 'hikmah-login') . '</a>',
                            '<a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank">' . esc_html__('Privacy Policy', 'hikmah-login') . '</a>'
                        );
                        ?>
                    </span>
                </label>
            </div>

            <!-- Submit Button Trigger -->
            <button type="submit" class="hikmah-btn hikmah-btn--primary hikmah-btn--block" id="hikmah-register-submit">
                <span class="hikmah-btn__text"><?php esc_html_e('Sign Up', 'hikmah-login'); ?></span>
                <span class="hikmah-btn__loading" style="display:none;">
                    <span class="hikmah-spinner"></span>
                    <?php esc_html_e('Creating account...', 'hikmah-login'); ?>
                </span>
            </button>

        </form>

        <!-- Social Authentication Hooks -->
        <?php if (has_action('hikmah_social_login_buttons')) : ?>
            <div class="hikmah-auth-divider">
                <span><?php esc_html_e('Or register with', 'hikmah-login'); ?></span>
            </div>
            <div class="hikmah-social-login-grid">
                <?php do_action('hikmah_social_login_buttons'); ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="hikmah-auth-card__footer">
        <p><?php esc_html_e('Already have an account?', 'hikmah-login'); ?> <a href="<?php echo esc_url(wp_login_url()); ?>"><?php esc_html_e('Login here', 'hikmah-login'); ?></a></p>
    </div>
</div>
