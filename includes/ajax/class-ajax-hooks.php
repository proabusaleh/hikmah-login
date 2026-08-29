<?php
/**
 * AJAX Hooks Registry
 *
 * Provides a clean API for third-party developers to extend
 * the AJAX system without modifying core files.
 *
 * @package Hikmah_Login
 * @subpackage Ajax
 * @since   1.0.0
 *
 * Usage Examples:
 *
 * // Register a custom AJAX action
 * add_action('hikmah_ajax_register_actions', function($controller) {
 *     $controller->register_action('custom_action', [
 *         'handler'    => 'my_custom_handler',
 *         'auth'       => 'logged_in',
 *         'nonce'      => 'my_nonce',
 *         'rate_limit' => 10,
 *         'window'     => 60,
 *     ]);
 * });
 *
 * // Modify response before sending
 * add_filter('hikmah_ajax_response', function($response, $status) {
 *     $response['custom_field'] = 'value';
 *     return $response;
 * }, 10, 2);
 *
 * // Run code before/after any AJAX action
 * add_action('hikmah_ajax_before_dispatch', function($action, $config) {
 *     error_log("AJAX action starting: {$action}");
 * }, 10, 2);
 */

namespace Hikmah_Login\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax_Hooks {

    /**
     * All available hooks with documentation.
     *
     * @return array
     */
    public static function get_documentation() {

        return [
            // ── Actions ──
            'hikmah_ajax_register_actions' => [
                'type'        => 'action',
                'description' => 'Register custom AJAX actions with the controller.',
                'parameters'  => [
                    '$controller' => 'Ajax_Controller instance',
                ],
                'example'     => 'add_action("hikmah_ajax_register_actions", function($c) { $c->register_action(...); });',
            ],

            'hikmah_ajax_before_dispatch' => [
                'type'        => 'action',
                'description' => 'Fires before any AJAX handler is called.',
                'parameters'  => [
                    '$action' => 'Action name (string)',
                    '$config' => 'Action configuration (array)',
                ],
            ],

            'hikmah_ajax_after_dispatch' => [
                'type'        => 'action',
                'description' => 'Fires after any AJAX handler completes.',
                'parameters'  => [
                    '$action' => 'Action name (string)',
                    '$result' => 'Handler return value (mixed)',
                ],
            ],

            // ── Filters ──
            'hikmah_ajax_response' => [
                'type'        => 'filter',
                'description' => 'Modify the complete AJAX response before sending.',
                'parameters'  => [
                    '$response' => 'Response array',
                    '$status'   => 'HTTP status code (int)',
                ],
            ],

            'hikmah_ajax_response_meta' => [
                'type'        => 'filter',
                'description' => 'Modify the meta section of AJAX responses.',
                'parameters'  => [
                    '$meta' => 'Meta data array',
                ],
            ],

            // ── Existing Auth Hooks (also relevant) ──
            'hikmah_login_auth_response' => [
                'type'        => 'filter',
                'description' => 'Modify auth response (login/logout/2FA).',
                'parameters'  => [
                    '$response' => 'Auth response array',
                ],
            ],

            'hikmah_login_validate_login' => [
                'type'        => 'action',
                'description' => 'Add custom validation rules to login.',
                'parameters'  => [
                    '$validator' => 'Validator instance',
                    '$data'      => 'Form data array',
                ],
            ],

            'hikmah_register_validate' => [
                'type'        => 'action',
                'description' => 'Add custom validation rules to registration.',
                'parameters'  => [
                    '$validator' => 'Validator instance',
                    '$data'      => 'Form data array',
                ],
            ],

            'hikmah_login_after_login' => [
                'type'        => 'action',
                'description' => 'Fires after successful login.',
                'parameters'  => [
                    '$user' => 'WP_User object',
                ],
            ],

            'hikmah_login_after_logout' => [
                'type'        => 'action',
                'description' => 'Fires after logout.',
                'parameters'  => [
                    '$user_id' => 'User ID (int)',
                ],
            ],

            'hikmah_register_success' => [
                'type'        => 'action',
                'description' => 'Fires after successful registration.',
                'parameters'  => [
                    '$user_id' => 'New user ID (int)',
                    '$data'    => 'Registration data (array)',
                ],
            ],

            'hikmah_password_reset_complete' => [
                'type'        => 'action',
                'description' => 'Fires after password is reset.',
                'parameters'  => [
                    '$user' => 'WP_User object',
                ],
            ],

            'hikmah_email_verified' => [
                'type'        => 'action',
                'description' => 'Fires after email is verified.',
                'parameters'  => [
                    '$user_id' => 'User ID (int)',
                ],
            ],
        ];
    }

    /**
     * Print hook documentation (for admin debug page).
     */
    public static function print_documentation() {

        $hooks = self::get_documentation();

        echo '<div class="hikmah-hooks-docs">';
        echo '<h2>Hikmah Login — AJAX Hooks Reference</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>
            <th>Hook Name</th>
            <th>Type</th>
            <th>Description</th>
            <th>Parameters</th>
        </tr></thead>';
        echo '<tbody>';

        foreach ( $hooks as $name => $info ) {
            $params = '';
            if ( ! empty( $info['parameters'] ) ) {
                $params = '<ul>';
                foreach ( $info['parameters'] as $param => $desc ) {
                    $params .= "<li><code>{$param}</code> — {$desc}</li>";
                }
                $params .= '</ul>';
            }

            printf(
                '<tr>
                    <td><code>%s</code></td>
                    <td><span class="hikmah-badge hikmah-badge-%s">%s</span></td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                esc_html( $name ),
                esc_attr( $info['type'] ),
                esc_html( strtoupper( $info['type'] ) ),
                esc_html( $info['description'] ),
                $params
            );
        }

        echo '</tbody></table></div>';
    }
}
