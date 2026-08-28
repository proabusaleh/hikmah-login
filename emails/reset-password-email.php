<?php
/**
 * Reset Password Email Template
 *
 * Variables available:
 * @var \WP_User $user      User object.
 * @var string   $reset_url Password reset URL.
 * @var string   $site_name Blog name.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_name   = $site_name ?? get_bloginfo( 'name' );
$site_url    = home_url( '/' );
$brand_color = '#4f46e5';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e( 'Reset Your Password', 'hikmah-login' ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color:#ef4444;padding:32px;text-align:center;">
                            <div style="font-size:36px;margin-bottom:8px;">🔐</div>
                            <h1 style="color:#ffffff;margin:0;font-size:22px;">
                                <?php esc_html_e( 'Password Reset Request', 'hikmah-login' ); ?>
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px 32px;">
                            <p style="color:#4b5563;font-size:16px;line-height:1.6;margin:0 0 8px;">
                                <?php
                                printf(
                                    esc_html__( 'Hi %s,', 'hikmah-login' ),
                                    esc_html( $user->display_name )
                                );
                                ?>
                            </p>

                            <p style="color:#4b5563;font-size:16px;line-height:1.6;margin:0 0 24px;">
                                <?php esc_html_e( 'We received a request to reset the password for your account. Click the button below to set a new password:', 'hikmah-login' ); ?>
                            </p>

                            <!-- CTA -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="background-color:<?php echo esc_attr( $brand_color ); ?>;border-radius:8px;">
                                        <a href="<?php echo esc_url( $reset_url ); ?>"
                                           style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-weight:600;font-size:16px;">
                                            <?php esc_html_e( 'Reset My Password', 'hikmah-login' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security Notice -->
                            <div style="background-color:#fef2f2;border-radius:6px;padding:12px 16px;margin-bottom:24px;">
                                <p style="color:#991b1b;font-size:13px;margin:0;">
                                    🔒 <?php esc_html_e( 'If you did not request a password reset, please ignore this email. Your password will remain unchanged.', 'hikmah-login' ); ?>
                                </p>
                            </div>

                            <p style="color:#9ca3af;font-size:13px;margin:0 0 4px;">
                                <?php esc_html_e( 'This link expires in 1 hour.', 'hikmah-login' ); ?>
                            </p>
                            <p style="color:#9ca3af;font-size:12px;word-break:break-all;margin:0;">
                                <?php echo esc_url( $reset_url ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f9fafb;padding:24px 32px;text-align:center;border-top:1px solid #e5e7eb;">
                            <p style="color:#9ca3af;font-size:12px;margin:0;">
                                <?php echo esc_html( $site_name ); ?> &bull;
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