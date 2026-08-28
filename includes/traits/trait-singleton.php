<?php
/**
 * Singleton Trait
 *
 * Ensures only one instance of a class exists.
 * Used across all major classes in Hikmah Login.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

namespace Hikmah_Login\Traits;

// Direct access prevention
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Singleton {

    /**
     * The single instance of the class.
     *
     * @var static|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return static
     */
    public static function get_instance() {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {
        // Do nothing
    }

    /**
     * Prevent unserialization.
     *
     * @throws \Exception
     */
    public function __wakeup() {
        throw new \Exception(
            esc_html__( 'Cannot unserialize a singleton.', 'hikmah-login' )
        );
    }
}
