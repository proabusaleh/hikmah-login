<?php
/**
 * Welcome Email Template
 *
 * Variables available:
 * @var \WP_User $user      User object.
 * @var string   $site_name Blog name.
 * @var array    $data      Registration data.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_name   = $site_name ?? get_bloginfo( 'name' );
$site_url    = home_url( '/' );
$login_url   = \Hikmah_Login\Helpers\Helper::get_login_url();
$logo_url    = get_option( 'hikmah_login_logo', '' );
$brand_color = '#4f46e5';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e( 'Welcome!', 'hikmah-login' ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,<?php echo esc_attr( $brand_color ); ?>,#7c3aed);padding:40px 32px;text-align:center;">
                            <div style="font-size:48px;margin-bottom:12px;">🎉</div>
                            <h1 style="color:#ffffff;margin:0;font-size:26px;">
                                <?php esc_html_e( 'Welcome Aboard!', 'hikmah-login' ); ?>
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px 32px;">
                            <h2 style="color:#1f2937;margin:0 0 16px;font-size:20px;">
                                <?php
                                printf(
                                    /* translators: %s: User display name */
                                    esc_html__( 'Hello %s! 👋', 'hikmah-login' ),
                                    esc_html( $user->display_name )
                                );
                                ?>
                            </h2>

                            <p style="color:#4b5563;font-size:16px;line-height:1.6;margin:0 0 16px;">
                                <?php
                                printf(
                                    /* translators: %s: Site name */
                                    esc_html__( 'Your account at %s has been created successfully. We\'re excited to have you!', 'hikmah-login' ),
                                    '<strong>' . esc_html( $site_name ) . '</strong>'
                                );
                                ?>
                            </p>

                            <!-- Account Details -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb;border-radius:8px;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <h3 style="color:#374151;font-size:14px;margin:0 0 12px;text-transform:uppercase;letter-spacing:0.5px;">
                                            <?php esc_html_e( 'Your Account Details', 'hikmah-login' ); ?>
                                        </h3>
                                        <table role="presentation" width="100%" cellpadding="4" cellspacing="0">
                                            <tr>
                                                <td style="color:#6b7280;font-size:14px;width:120px;">
                                                    <?php esc_html_e( 'Username:', 'hikmah-login' ); ?>
                                                </td>
                                                <td style="color:#1f2937;font-size:14px;font-weight:600;">
                                                    <?php echo esc_html( $user->user_login ); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color:#6b7280;font-size:14px;">
                                                    <?php esc_html_e( 'Email:', 'hikmah-login' ); ?>
                                                </td>
                                                <td style="color:#1f2937;font-size:14px;">
                                                    <?php echo esc_html( $user->user_email ); ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="background-color:<?php echo esc_attr( $brand_color ); ?>;border-radius:8px;">
                                        <a href="<?php echo esc_url( $login_url ); ?>"
                                           style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-weight:600;font-size:16px;">
                                            <?php esc_html_e( 'Log In to Your Account', 'hikmah-login' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color:#6b7280;font-size:14px;line-height:1.6;margin:0;">
                                <?php esc_html_e( 'If you have any questions, feel free to reach out to our support team.', 'hikmah-login' ); ?>
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