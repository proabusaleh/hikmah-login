<?php
/**
 * Autoloader
 *
 * PSR-4 style autoloader for Hikmah Login plugin.
 * Automatically loads class files based on namespace.
 *
 * Namespace mapping:
 *   Hikmah_Login\            → includes/
 *   Hikmah_Login\Auth\       → includes/auth/
 *   Hikmah_Login\Ajax\       → includes/ajax/
 *   Hikmah_Login\Security\   → includes/security/
 *   Hikmah_Login\Social\     → includes/social/
 *   Hikmah_Login\Traits\     → includes/traits/
 *   ... etc.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hikmah_Login_Autoloader {

    /**
     * The base namespace.
     *
     * @var string
     */
    private $namespace = 'Hikmah_Login';

    /**
     * The base directory for includes.
     *
     * @var string
     */
    private $base_dir;

    /**
     * Namespace to directory mapping.
     *
     * @var array
     */
    private $namespace_map = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->base_dir = HIKMAH_LOGIN_DIR . 'includes/';
        $this->setup_namespace_map();
    }

    /**
     * Setup namespace to directory mapping.
     */
    private function setup_namespace_map() {
        $this->namespace_map = [
            'Hikmah_Login\\Traits'     => $this->base_dir . 'traits/',
            'Hikmah_Login\\Auth'       => $this->base_dir . 'auth/',
            'Hikmah_Login\\Dashboard'  => $this->base_dir . 'dashboard/',
            'Hikmah_Login\\Ajax'       => $this->base_dir . 'ajax/',
            'Hikmah_Login\\Security'   => $this->base_dir . 'security/',
            'Hikmah_Login\\Social'     => $this->base_dir . 'social/',
            'Hikmah_Login\\Redirect'   => $this->base_dir . 'redirect/',
            'Hikmah_Login\\Shortcodes' => $this->base_dir . 'shortcodes/',
            'Hikmah_Login\\Blocks'     => $this->base_dir . 'blocks/',
            'Hikmah_Login\\RestApi'    => $this->base_dir . 'rest-api/',
            'Hikmah_Login\\Admin'      => $this->base_dir . 'admin/',
            'Hikmah_Login\\Database'   => $this->base_dir . 'database/',
            'Hikmah_Login\\Helpers'    => $this->base_dir . 'helpers/',
            'Hikmah_Login\\Updater'    => $this->base_dir . 'updater/',
            'Hikmah_Login'             => $this->base_dir,
        ];
    }

    /**
     * Register the autoloader.
     */
    public function register() {
        spl_autoload_register( [ $this, 'autoload' ] );
    }

    /**
     * Autoload callback.
     *
     * @param string $class_name Fully qualified class name.
     */
    public function autoload( $class_name ) {

        // Only handle our namespace
        if ( strpos( $class_name, $this->namespace ) !== 0 ) {
            return;
        }

        // Try each namespace mapping (longest match first)
        foreach ( $this->namespace_map as $namespace => $directory ) {
            if ( strpos( $class_name, $namespace ) === 0 ) {
                $this->load_class( $class_name, $namespace, $directory );
                return;
            }
        }
    }

    /**
     * Load a class file.
     *
     * @param string $class_name Full class name.
     * @param string $namespace  Matched namespace.
     * @param string $directory  Corresponding directory.
     */
    private function load_class( $class_name, $namespace, $directory ) {

        // Remove the namespace prefix
        $relative_class = substr( $class_name, strlen( $namespace ) );

        // Remove leading backslash
        $relative_class = ltrim( $relative_class, '\\' );

        // Convert to filename
        $filename = $this->class_to_filename( $relative_class, $directory );

        // Build full file path
        $file = $directory . $filename;

        // Load if exists
        if ( file_exists( $file ) ) {
            require_once $file;
        } elseif ( HIKMAH_LOGIN_DEBUG ) {
            // Log missing files in debug mode
            error_log(
                sprintf(
                    'Hikmah Login Autoloader: File not found — %s (Class: %s)',
                    $file,
                    $class_name
                )
            );
        }
    }

    /**
     * Convert class name to WordPress-style filename.
     *
     * Examples:
     *   'Activator'       → 'class-activator.php'
     *   'Hikmah_Login'    → 'class-hikmah-login.php'
     *   'Brute_Force'     → 'class-brute-force.php'
     *   'Singleton'(trait) → 'trait-singleton.php'
     *
     * @param string $class_name Relative class name.
     * @param string $directory  Resolved directory for this namespace.
     * @return string Filename.
     */
    private function class_to_filename( $class_name, $directory = '' ) {

        // Convert underscores and camelCase to hyphens
        $filename = str_replace( '_', '-', $class_name );
        $filename = strtolower( $filename );

        // Remove leading hyphen if any
        $filename = ltrim( $filename, '-' );

        // Determine prefix (trait or class)
        $prefix = 'class';
        if ( strpos( strtolower( $class_name ), 'trait' ) !== false
            || $this->is_trait_directory( $directory ) ) {
            $prefix = 'trait';
        }

        return $prefix . '-' . $filename . '.php';
    }

    /**
     * Check if loading from the traits directory.
     *
     * Traits live in the Hikmah_Login\Traits namespace,
     * which maps to the includes/traits/ directory.
     *
     * @param string $directory Resolved directory for this namespace.
     * @return bool
     */
    private function is_trait_directory( $directory ) {
        return rtrim( $directory, '/' ) === rtrim( $this->base_dir, '/' ) . '/traits';
    }
}
