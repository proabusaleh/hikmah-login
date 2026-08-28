<?php
/**
 * Email Verification Template
 *
 * Variables available:
 * @var \WP_User $user       User object.
 * @var string   $verify_url Verification URL with token.
 * @var string   $site_name  Blog name.
 *
 * Override: yourtheme/hikmah-login/emails/verification-email.php
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_name  = $site_name ?? get_bloginfo( 'name' );
$site_url   = home_url( '/' );
$logo_url   = get_option( 'hikmah_login_logo', '' );
$brand_color = '#4f46e5';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e( 'Verify Your Email', 'hikmah-login' ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color:<?php echo esc_attr( $brand_color ); ?>;padding:32px;text-align:center;">
                            <?php if ( ! empty( $logo_url ) ) : ?>
                                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="max-height:40px;">
                            <?php else : ?>
                                <h1 style="color:#ffffff;margin:0;font-size:24px;"><?php echo esc_html( $site_name ); ?></h1>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px 32px;">
                            <h2 style="color:#1f2937;margin:0 0 16px;font-size:22px;">
                                <?php esc_html_e( 'Verify Your Email Address', 'hikmah-login' ); ?>
                            </h2>

                            <p style="color:#4b5563;font-size:16px;line-height:1.6;margin:0 0 8px;">
                                <?php
                                printf(
                                    /* translators: %s: User display name */
                                    esc_html__( 'Hi %s,', 'hikmah-login' ),
                                    esc_html( $user->display_name )
                                );
                                ?>
                            </p>

                            <p style="color:#4b5563;font-size:16px;line-height:1.6;margin:0 0 24px;">
                                <?php esc_html_e( 'Thank you for creating an account! Please verify your email address by clicking the button below:', 'hikmah-login' ); ?>
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="background-color:<?php echo esc_attr( $brand_color ); ?>;border-radius:8px;">
                                        <a href="<?php echo esc_url( $verify_url ); ?>"
                                           style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-weight:600;font-size:16px;">
                                            <?php esc_html_e( 'Verify My Email', 'hikmah-login' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Fallback Link -->
                            <p style="color:#9ca3af;font-size:13px;line-height:1.5;margin:0 0 8px;">
                                <?php esc_html_e( 'If the button doesn\'t work, copy and paste this link into your browser:', 'hikmah-login' ); ?>
                            </p>
                            <p style="color:#4f46e5;font-size:12px;word-break:break-all;margin:0 0 24px;">
                                <?php echo esc_url( $verify_url ); ?>
                            </p>

                            <!-- Expiry Notice -->
                            <div style="background-color:#fef3c7;border-radius:6px;padding:12px 16px;margin-bottom:24px;">
                                <p style="color:#92400e;font-size:13px;margin:0;">
                                    ⏰ <?php esc_html_e( 'This link will expire in 24 hours.', 'hikmah-login' ); ?>
                                </p>
                            </div>

                            <p style="color:#9ca3af;font-size:13px;margin:0;">
                                <?php esc_html_e( 'If you did not create an account, please ignore this email. No action is needed.', 'hikmah-login' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f9fafb;padding:24px 32px;text-align:center;border-top:1px solid #e5e7eb;">
                            <p style="color:#9ca3af;font-size:12px;margin:0 0 8px;">
                                <?php
                                printf(
                                    /* translators: %s: Site name */
                                    esc_html__( 'This email was sent by %s', 'hikmah-login' ),
                                    esc_html( $site_name )
                                );
                                ?>
                            </p>
                            <p style="color:#9ca3af;font-size:12px;margin:0;">
                                <a href="<?php echo esc_url( $site_url ); ?>" style="color:#6b7280;text-decoration:none;">
                                    <?php echo esc_html( $site_url ); ?>
                                </a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>