<?php
/**
 * Admin UI Settings Page with Live Preview
 *
 * Phase 12 Customizer: settings panel + live preview iframe.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) || ! current_user_can( 'manage_options' ) ) {
    exit;
}

$ui       = \Hikmah_Login\Admin\Admin_UI_Settings::get_instance();
$settings = $ui->get_settings();
$bg_type  = $settings['bg_type'];
?>

<div class="wrap hikmah-admin-wrap">
    <h1>🎨 <?php esc_html_e( 'Login Page Customizer', 'hikmah-login' ); ?></h1>

    <div class="hikmah-customizer" style="display:grid;grid-template-columns:380px 1fr;gap:24px;margin-top:20px;">

        <!-- Settings Panel -->
        <div class="hikmah-settings-panel" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);max-height:80vh;overflow-y:auto;">

            <!-- Branding -->
            <h3>🏷️ <?php esc_html_e( 'Branding', 'hikmah-login' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Logo', 'hikmah-login' ); ?></label></th>
                    <td>
                        <div id="hikmah-logo-preview" style="margin-bottom:8px;">
                            <?php if ( ! empty( $settings['logo_url'] ) ) : ?>
                                <img src="<?php echo esc_url( $settings['logo_url'] ); ?>" style="max-height:50px;">
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button" id="hikmah-upload-logo">
                            <?php esc_html_e( 'Upload Logo', 'hikmah-login' ); ?>
                        </button>
                        <button type="button" class="button" id="hikmah-remove-logo">
                            <?php esc_html_e( 'Remove', 'hikmah-login' ); ?>
                        </button>
                        <input type="hidden" name="logo_url" id="hikmah-logo-url" value="<?php echo esc_attr( $settings['logo_url'] ); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="hikmah-logo-width"><?php esc_html_e( 'Logo Width', 'hikmah-login' ); ?></label></th>
                    <td>
                        <input type="range" id="hikmah-logo-width" name="logo_width"
                               min="60" max="300" value="<?php echo esc_attr( $settings['logo_width'] ); ?>">
                        <span id="hikmah-logo-width-val"><?php echo esc_html( $settings['logo_width'] ); ?>px</span>
                    </td>
                </tr>
            </table>

            <!-- Layout -->
            <h3>📐 <?php esc_html_e( 'Layout', 'hikmah-login' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Form Layout', 'hikmah-login' ); ?></label></th>
                    <td>
                        <select name="layout" id="hikmah-layout">
                            <?php foreach ( \Hikmah_Login\Admin\Admin_UI_Settings::LAYOUTS as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['layout'], $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Theme Mode', 'hikmah-login' ); ?></label></th>
                    <td>
                        <select name="theme_mode" id="hikmah-theme-mode">
                            <?php foreach ( \Hikmah_Login\Admin\Admin_UI_Settings::THEME_MODES as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['theme_mode'], $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <!-- Colors -->
            <h3>🎨 <?php esc_html_e( 'Colors', 'hikmah-login' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Color Preset', 'hikmah-login' ); ?></label></th>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php foreach ( \Hikmah_Login\Admin\Admin_UI_Settings::COLOR_PRESETS as $key => $preset ) : ?>
                                <?php if ( 'custom' === $key ) { continue; } ?>
                                <button type="button" class="hikmah-color-swatch"
                                        data-color="<?php echo esc_attr( $preset['primary'] ); ?>"
                                        data-preset="<?php echo esc_attr( $key ); ?>"
                                        style="width:28px;height:28px;border-radius:50%;border:2px solid <?php echo $settings['color_preset'] === $key ? '#333' : 'transparent'; ?>;background:<?php echo esc_attr( $preset['primary'] ); ?>;cursor:pointer;"
                                        title="<?php echo esc_attr( $preset['name'] ); ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="color_preset" id="hikmah-color-preset" value="<?php echo esc_attr( $settings['color_preset'] ); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="hikmah-primary-color"><?php esc_html_e( 'Primary Color', 'hikmah-login' ); ?></label></th>
                    <td><input type="color" id="hikmah-primary-color" name="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="hikmah-bg-color"><?php esc_html_e( 'Page Background', 'hikmah-login' ); ?></label></th>
                    <td><input type="color" id="hikmah-bg-color" name="bg_color" value="<?php echo esc_attr( $settings['bg_color'] ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="hikmah-card-bg"><?php esc_html_e( 'Card Background', 'hikmah-login' ); ?></label></th>
                    <td><input type="color" id="hikmah-card-bg" name="card_bg_color" value="<?php echo esc_attr( $settings['card_bg_color'] ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="hikmah-text-color"><?php esc_html_e( 'Text Color', 'hikmah-login' ); ?></label></th>
                    <td><input type="color" id="hikmah-text-color" name="text_color" value="<?php echo esc_attr( $settings['text_color'] ); ?>"></td>
                </tr>
            </table>

            <!-- Background Type -->
            <h3>🖼️ <?php esc_html_e( 'Background', 'hikmah-login' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Background Type', 'hikmah-login' ); ?></label></th>
                    <td>
                        <select name="bg_type" id="hikmah-bg-type">
                            <option value="color" <?php selected( $settings['bg_type'], 'color' ); ?>><?php esc_html_e( 'Solid Color', 'hikmah-login' ); ?></option>
                            <option value="gradient" <?php selected( $settings['bg_type'], 'gradient' ); ?>><?php esc_html_e( 'Gradient', 'hikmah-login' ); ?></option>
                            <option value="image" <?php selected( $settings['bg_type'], 'image' ); ?>><?php esc_html_e( 'Image', 'hikmah-login' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr class="hikmah-bg-gradient-row" <?php echo 'gradient' === $bg_type ? '' : 'style="display:none"'; ?>>
                    <th><label for="hikmah-bg-gradient"><?php esc_html_e( 'Gradient CSS', 'hikmah-login' ); ?></label></th>
                    <td>
                        <input type="text" id="hikmah-bg-gradient" name="bg_gradient" class="large-text code" value="<?php echo esc_attr( $settings['bg_gradient'] ); ?>">
                    </td>
                </tr>
                <tr class="hikmah-bg-image-row" <?php echo 'image' === $bg_type ? '' : 'style="display:none"'; ?>>
                    <th><label><?php esc_html_e( 'Background Image', 'hikmah-login' ); ?></label></th>
                    <td>
                        <div id="hikmah-bg-image-preview" style="margin-bottom:8px;">
                            <?php if ( ! empty( $settings['bg_image'] ) ) : ?>
                                <img src="<?php echo esc_url( $settings['bg_image'] ); ?>" style="max-height:60px;max-width:180px;">
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button" id="hikmah-upload-bg">
                            <?php esc_html_e( 'Upload Image', 'hikmah-login' ); ?>
                        </button>
                        <button type="button" class="button" id="hikmah-remove-bg">
                            <?php esc_html_e( 'Remove', 'hikmah-login' ); ?>
                        </button>
                        <input type="hidden" name="bg_image" id="hikmah-bg-image" value="<?php echo esc_attr( $settings['bg_image'] ); ?>">
                    </td>
                </tr>
            </table>

            <!-- Buttons -->
            <h3>🔘 <?php esc_html_e( 'Buttons', 'hikmah-login' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Button Style', 'hikmah-login' ); ?></label></th>
                    <td>
                        <select name="btn_style" id="hikmah-btn-style">
                            <option value="filled" <?php selected( $settings['btn_style'], 'filled' ); ?>><?php esc_html_e( 'Filled', 'hikmah-login' ); ?></option>
                            <option value="outline" <?php selected( $settings['btn_style'], 'outline' ); ?>><?php esc_html_e( 'Outline', 'hikmah-login' ); ?></option>
                            <option value="gradient" <?php selected( $settings['btn_style'], 'gradient' ); ?>><?php esc_html_e( 'Gradient', 'hikmah-login' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Border Radius', 'hikmah-login' ); ?></label></th>
                    <td>
                        <input type="range" name="btn_radius" min="0" max="24" value="<?php echo esc_attr( $settings['btn_radius'] ); ?>">
                        <span><?php echo esc_html( $settings['btn_radius'] ); ?>px</span>
                    </td>
                </tr>
            </table>

            <!-- Custom CSS -->
            <h3>💻 <?php esc_html_e( 'Custom CSS', 'hikmah-login' ); ?></h3>
            <textarea name="custom_css" id="hikmah-custom-css" rows="8"
                      style="width:100%;font-family:monospace;font-size:12px;"
                      placeholder="/* Add your custom CSS here */"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>

            <!-- Save Button -->
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;">
                <button type="button" class="button button-primary button-large" id="hikmah-save-ui">
                    💾 <?php esc_html_e( 'Save Changes', 'hikmah-login' ); ?>
                </button>
                <span id="hikmah-save-status" style="margin-left:12px;color:#10b981;"></span>
            </div>
        </div>

        <!-- Live Preview Panel -->
        <div class="hikmah-preview-panel" style="position:relative;background:#f3f4f6;padding:24px;border-radius:8px;min-height:600px;display:flex;align-items:center;justify-content:center;">
            <h3 style="position:absolute;top:8px;left:16px;color:#6b7280;font-size:13px;">
                👁️ <?php esc_html_e( 'Live Preview', 'hikmah-login' ); ?>
            </h3>
            <iframe id="hikmah-preview-iframe"
                    src="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() ); ?>"
                    style="width:100%;max-width:480px;height:580px;border:none;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.15);background:#fff;">
            </iframe>
        </div>
    </div>
</div>