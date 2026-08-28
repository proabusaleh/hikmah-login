<?php
/**
 * Custom wp-login.php Template
 *
 * This template replaces the default WordPress login page
 * when the Login Override is active. It provides a fully
 * branded login experience.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get settings
$site_name  = get_bloginfo( 'name' );
$logo_url   = get_option( 'hikmah_login_logo', '' );
$bg_color   = get_option( 'hikmah_login_bg_color', '#f1f1f1' );
$form_width = get_option( 'hikmah_login_form_width', '400' );

// Determine action
$action = isset( $_REQUEST['action'] )
    ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )
    : 'login';

// Get messages
$errors   = [];
$messages = [];

if ( isset( $_GET['loggedout'] ) && 'true' === $_GET['loggedout'] ) {
    $messages[] = __( 'You are now logged out.', 'hikmah-login' );
}

if ( isset( $_GET['registration'] ) && 'disabled' === $_GET['registration'] ) {
    $errors[] = __( 'Registration is currently disabled.', 'hikmah-login' );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( $site_name ); ?> — <?php esc_html_e( 'Login', 'hikmah-login' ); ?></title>

    <?php wp_head(); ?>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body.hikmah-login-page {
            background: <?php echo esc_attr( $bg_color ); ?>;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .hikmah-login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            padding: 40px;
            width: 100%;
            max-width: <?php echo esc_attr( $form_width ); ?>px;
        }

        .hikmah-login-logo {
            text-align: center;
            margin-bottom: 24px;
        }

        .hikmah-login-logo img {
            max-width: 150px;
            max-height: 60px;
        }

        .hikmah-login-logo h1 {
            font-size: 22px;
            color: #1f2937;
            margin-top: 12px;
        }

        .hikmah-login-logo h1 a {
            color: inherit;
            text-decoration: none;
        }

        .hikmah-login-message {
            background: #eff6ff;
            border: 1px solid #3b82f6;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 16px;
            color: #1e40af;
            font-size: 14px;
        }

        .hikmah-login-error {
            background: #fef2f2;
            border: 1px solid #ef4444;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 16px;
            color: #991b1b;
            font-size: 14px;
        }

        .hikmah-login-form .field {
            margin-bottom: 16px;
        }

        .hikmah-login-form label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .hikmah-login-form input[type="text"],
        .hikmah-login-form input[type="password"],
        .hikmah-login-form input[type="email"] {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
            outline: none;
        }

        .hikmah-login-form input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .hikmah-login-form .remember-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .hikmah-login-form .remember-row label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 400;
            cursor: pointer;
        }

        .hikmah-login-form .forgot-link {
            color: #4f46e5;
            text-decoration: none;
        }

        .hikmah-login-form .forgot-link:hover {
            text-decoration: underline;
        }

        .hikmah-login-form .submit-btn {
            width: 100%;
            padding: 12px;
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .hikmah-login-form .submit-btn:hover {
            background: #4338ca;
        }

        .hikmah-login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #6b7280;
        }

        .hikmah-login-footer a {
            color: #4f46e5;
            text-decoration: none;
        }

        .hikmah-login-footer a:hover {
            text-decoration: underline;
        }

        .hikmah-back-to-site {
            text-align: center;
            margin-top: 24px;
        }

        .hikmah-back-to-site a {
            color: #6b7280;
            text-decoration: none;
            font-size: 13px;
        }

        .hikmah-back-to-site a:hover {
            color: #374151;
        }
    </style>

    <?php
    /**
     * Fires in the <head> of the custom login page.
     *
     * @since 1.0.0
     */
    do_action( 'hikmah_login_page_head' );
    ?>
</head>
<body class="hikmah-login-page">

    <div class="hikmah-login-card">

        <!-- Logo -->
        <div class="hikmah-login-logo">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
                </a>
            <?php else : ?>
                <h1><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $site_name ); ?></a></h1>
            <?php endif; ?>
        </div>

        <!-- Messages -->
        <?php foreach ( $messages as $msg ) : ?>
            <div class="hikmah-login-message"><?php echo esc_html( $msg ); ?></div>
        <?php endforeach; ?>

        <?php foreach ( $errors as $err ) : ?>
            <div class="hikmah-login-error"><?php echo esc_html( $err ); ?></div>
        <?php endforeach; ?>

        <?php if ( 'login' === $action ) : ?>
            <!-- Login Form -->
            <form class="hikmah-login-form" method="post" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>">

                <div class="field">
                    <label for="user_login"><?php esc_html_e( 'Username or Email', 'hikmah-login' ); ?></label>
                    <input type="text" name="log" id="user_login" autocomplete="username" required autofocus>
                </div>

                <div class="field">
                    <label for="user_pass"><?php esc_html_e( 'Password', 'hikmah-login' ); ?></label>
                    <input type="password" name="pwd" id="user_pass" autocomplete="current-password" required>
                </div>

                <div class="remember-row">
                    <label>
                        <input type="checkbox" name="rememberme" value="forever">
                        <?php esc_html_e( 'Remember me', 'hikmah-login' ); ?>
                    </label>
                    <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="forgot-link">
                        <?php esc_html_e( 'Forgot Password?', 'hikmah-login' ); ?>
                    </a>
                </div>

                <?php
                /**
                 * Fires inside the wp-login.php form.
                 *
                 * @since 1.0.0
                 */
                do_action( 'hikmah_login_page_form' );
                ?>

                <input type="hidden" name="redirect_to" value="<?php echo esc_url( admin_url() ); ?>">
                <input type="hidden" name="testcookie" value="1">

                <button type="submit" class="submit-btn">
                    <?php esc_html_e( 'Sign In', 'hikmah-login' ); ?>
                </button>
            </form>

            <?php if ( get_option( 'users_can_register' ) ) : ?>
                <div class="hikmah-login-footer">
                    <?php esc_html_e( "Don't have an account?", 'hikmah-login' ); ?>
                    <a href="<?php echo esc_url( wp_registration_url() ); ?>">
                        <?php esc_html_e( 'Register', 'hikmah-login' ); ?>
                    </a>
                </div>
            <?php endif; ?>

        <?php elseif ( 'lostpassword' === $action || 'retrievepassword' === $action ) : ?>
            <!-- Lost Password Form -->
            <form class="hikmah-login-form" method="post" action="<?php echo esc_url( site_url( 'wp-login.php?action=lostpassword', 'login_post' ) ); ?>">
                <p style="font-size:14px;color:#6b7280;margin-bottom:16px;">
                    <?php esc_html_e( 'Enter your email address and we will send you a link to reset your password.', 'hikmah-login' ); ?>
                </p>
                <div class="field">
                    <label for="user_login"><?php esc_html_e( 'Email Address', 'hikmah-login' ); ?></label>
                    <input type="email" name="user_login" id="user_login" required autofocus>
                </div>
                <button type="submit" class="submit-btn">
                    <?php esc_html_e( 'Reset Password', 'hikmah-login' ); ?>
                </button>
            </form>
            <div class="hikmah-login-footer">
                <a href="<?php echo esc_url( wp_login_url() ); ?>">
                    &larr; <?php esc_html_e( 'Back to Login', 'hikmah-login' ); ?>
                </a>
            </div>
        <?php endif; ?>

        <!-- Back to Site -->
        <div class="hikmah-back-to-site">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                &larr; <?php echo esc_html( sprintf( __( 'Back to %s', 'hikmah-login' ), $site_name ) ); ?>
            </a>
        </div>
    </div>

    <?php wp_footer(); ?>

    <?php
    /**
     * Fires at the bottom of the custom login page.
     *
     * @since 1.0.0
     */
    do_action( 'hikmah_login_page_footer' );
    ?>
</body>
</html>
