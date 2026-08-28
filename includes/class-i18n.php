<?php
/**
 * Internationalization (i18n)
 *
 * Handles loading of translation files for the plugin.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

namespace Hikmah_Login;

use Hikmah_Login\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class I18n {

    use Singleton;

    /**
     * The domain specified for this plugin.
     *
     * @var string
     */
    private $domain = 'hikmah-login';

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'load_script_translations' ], 20 );
    }

    /**
     * Load the plugin text domain for translation.
     */
    public function load_textdomain() {

        // Try to load from WP_LANG_DIR/hikmah-login/ first
        // Then from the plugin's languages/ directory
        load_plugin_textdomain(
            $this->domain,
            false,
            dirname( HIKMAH_LOGIN_BASENAME ) . '/languages/'
        );
    }

    /**
     * Load JavaScript translations.
     *
     * Required for wp.i18n in Gutenberg blocks and JS files.
     */
    public function load_script_translations() {

        // Frontend script translations
        wp_set_script_translations(
            'hikmah-login-script',
            $this->domain,
            HIKMAH_LOGIN_DIR . 'languages/'
        );

        // Admin script translations
        wp_set_script_translations(
            'hikmah-admin-script',
            $this->domain,
            HIKMAH_LOGIN_DIR . 'languages/'
        );

        // Gutenberg block translations
        wp_set_script_translations(
            'hikmah-login-block',
            $this->domain,
            HIKMAH_LOGIN_DIR . 'languages/'
        );
    }

    /**
     * Get all available languages for this plugin.
     *
     * @return array Language code => Language name.
     */
    public function get_available_languages() {

        $languages = [
            'en_US' => 'English (US)',
        ];

        $mo_files = glob( HIKMAH_LOGIN_DIR . 'languages/*.mo' );

        if ( $mo_files ) {
            foreach ( $mo_files as $mo_file ) {
                $locale = basename( $mo_file, '.mo' );
                $locale = str_replace( $this->domain . '-', '', $locale );

                // Get native language name
                require_once ABSPATH . 'wp-admin/includes/translation-install.php';
                $translations = wp_get_available_translations();

                if ( isset( $translations[ $locale ] ) ) {
                    $languages[ $locale ] = $translations[ $locale ]['native_name'];
                } else {
                    $languages[ $locale ] = $locale;
                }
            }
        }

        return $languages;
    }
}
