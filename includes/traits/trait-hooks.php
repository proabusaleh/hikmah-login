<?php
/**
 * Hooks Trait
 *
 * Provides convenient methods for registering
 * WordPress actions and filters.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

namespace Hikmah_Login\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Hooks {

    /**
     * Register an action hook.
     *
     * @param string $hook     Hook name.
     * @param string $method   Method name in this class.
     * @param int    $priority Priority (default 10).
     * @param int    $args     Number of arguments (default 1).
     */
    protected function add_action( $hook, $method, $priority = 10, $args = 1 ) {
        add_action( $hook, [ $this, $method ], $priority, $args );
    }

    /**
     * Register a filter hook.
     *
     * @param string $hook     Hook name.
     * @param string $method   Method name in this class.
     * @param int    $priority Priority (default 10).
     * @param int    $args     Number of arguments (default 1).
     */
    protected function add_filter( $hook, $method, $priority = 10, $args = 1 ) {
        add_filter( $hook, [ $this, $method ], $priority, $args );
    }

    /**
     * Register an AJAX action (for logged-in users).
     *
     * @param string $action AJAX action name.
     * @param string $method Method name in this class.
     */
    protected function add_ajax( $action, $method ) {
        add_action( "wp_ajax_{$action}", [ $this, $method ] );
    }

    /**
     * Register an AJAX action (for non-logged-in users).
     *
     * @param string $action AJAX action name.
     * @param string $method Method name in this class.
     */
    protected function add_ajax_nopriv( $action, $method ) {
        add_action( "wp_ajax_nopriv_{$action}", [ $this, $method ] );
    }

    /**
     * Register an AJAX action for both logged-in
     * and non-logged-in users.
     *
     * @param string $action AJAX action name.
     * @param string $method Method name in this class.
     */
    protected function add_ajax_both( $action, $method ) {
        $this->add_ajax( $action, $method );
        $this->add_ajax_nopriv( $action, $method );
    }
}
