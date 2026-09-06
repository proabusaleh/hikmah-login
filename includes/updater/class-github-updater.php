<?php
/**
 * GitHub Releases Updater
 *
 * Checks the GitHub repository for newer releases and makes them
 * available through the standard WordPress plugins update system
 * (Plugins screen, Update Core, and the plugin info modal).
 *
 * Options:
 *   hikmah_updates_enabled   (yes|no)  Master switch for update checks.
 *   hikmah_updates_channel   (stable|beta)  Release channel to follow.
 *   hikmah_updates_token     (string)  Optional GitHub token for private
 *                                      repos or higher API rate limits.
 *
 * @package Hikmah_Login
 * @subpackage Updater
 * @since   2.0.0
 */

namespace Hikmah_Login\Updater;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Github_Updater {

    use Singleton;
    use Hooks;

    /**
     * GitHub repository (owner/repo).
     */
    const REPO = 'proabusaleh/hikmah-login';

    /**
     * GitHub releases API endpoint.
     */
    const API_URL = 'https://api.github.com/repos/proabusaleh/hikmah-login/releases';

    /**
     * Repository URL for the plugin info modal / homepage.
     */
    const REPO_URL = 'https://github.com/proabusaleh/hikmah-login';

    /**
     * Transient key used to cache the parsed release list.
     */
    const CACHE_KEY = 'hikmah_github_releases';

    /**
     * How long the release list is cached (6 hours).
     */
    const CACHE_TTL = 21600;

    /**
     * Plugin basename (folder/file.php).
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Plugin folder slug.
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * Whether update checks are enabled.
     *
     * @var bool
     */
    private $enabled;

    /**
     * Active release channel ('stable' or 'beta').
     *
     * @var string
     */
    private $channel;

    /**
     * GitHub access token (optional).
     *
     * @var string
     */
    private $token;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->plugin_basename = HIKMAH_LOGIN_BASENAME;
        $this->plugin_slug     = dirname( HIKMAH_LOGIN_BASENAME );
        $this->enabled         = 'yes' === get_option( 'hikmah_updates_enabled', 'yes' );
        $this->channel         = get_option( 'hikmah_updates_channel', 'stable' );
        $this->token           = (string) get_option( 'hikmah_updates_token', '' );

        $this->add_filter( 'pre_set_site_transient_update_plugins', 'inject_update' );
        $this->add_filter( 'plugins_api', 'inject_plugin_info', 10, 3 );
        $this->add_filter( 'upgrader_source_selection', 'fix_plugin_folder', 10, 4 );
        $this->add_filter( 'upgrader_post_install', 'clear_cache_after_install', 10, 3 );
    }

    /**
     * Inject an update entry when a newer release exists.
     *
     * @param object $transient Site update transient.
     * @return object
     */
    public function inject_update( $transient ) {

        if ( ! is_object( $transient ) || ! $this->enabled ) {
            return $transient;
        }

        // Trust an existing response for this plugin.
        if ( isset( $transient->response[ $this->plugin_basename ] ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();

        if ( ! $release ) {
            return $transient;
        }

        $new_version = $this->normalize_version( $release['version'] );

        if ( version_compare( $new_version, HIKMAH_LOGIN_VERSION, '>' ) ) {

            $update = (object) [
                'slug'          => $this->plugin_slug,
                'plugin'        => $this->plugin_basename,
                'new_version'   => $new_version,
                'url'           => $release['html_url'],
                'package'       => $release['zip_url'],
                'tested'        => '6.4',
                'requires_php'  => '7.4',
                'compatibility' => new \stdClass(),
                'icons'         => [],
            ];

            // Short upgrade notice from the release notes.
            $notes = trim( wp_strip_all_tags( (string) $release['notes_text'] ) );
            if ( '' !== $notes ) {
                $update->upgrade_notice = function_exists( 'mb_substr' )
                    ? mb_substr( $notes, 0, 400 )
                    : substr( $notes, 0, 400 );
            }

            $transient->response[ $this->plugin_basename ] = $update;
        }

        return $transient;
    }

    /**
     * Provide plugin information for the "View details" modal.
     *
     * @param mixed  $result Current result.
     * @param string $action Requested action.
     * @param object $args   Request arguments.
     * @return mixed
     */
    public function inject_plugin_info( $result, $action, $args ) {

        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
            return $result;
        }

        $release = $this->get_latest_release();

        $body  = '<h2>' . esc_html__( 'About', 'hikmah-login' ) . '</h2>';
        $body .= '<p>' . esc_html__(
            'Hikmah Login is a complete WordPress login, registration, and authentication system with security, social login, 2FA, and more.',
            'hikmah-login'
        ) . '</p>';
        $body .= '<p><a href="' . esc_url( self::REPO_URL ) . '" target="_blank" rel="noopener">'
            . esc_html__( 'Readme & Documentation', 'hikmah-login' )
            . '</a></p>';

        $changelog = '';
        if ( $release && ! empty( $release['notes_text'] ) ) {
            $changelog = '<h2>' . esc_html__( 'Latest release notes', 'hikmah-login' ) . '</h2>'
                . wpautop( $release['notes_text'] );
        }

        $info = (object) [
            'name'           => 'Hikmah Login',
            'slug'           => $this->plugin_slug,
            'version'        => $release ? $this->normalize_version( $release['version'] ) : HIKMAH_LOGIN_VERSION,
            'author'         => '<a href="https://github.com/proabusaleh" target="_blank" rel="noopener">Abu Saleh</a>',
            'author_profile' => 'https://github.com/proabusaleh',
            'contributors'   => [ 'proabusaleh' => 'Abu Saleh' ],
            'homepage'       => self::REPO_URL,
            'download_link'  => $release ? $release['zip_url'] : self::REPO_URL,
            'requires'       => '5.8',
            'tested'         => '6.4',
            'requires_php'   => '7.4',
            'sections'       => [
                'description' => $body,
                'changelog'   => $changelog,
            ],
            'banners'        => [],
            'short_description' => __( 'Complete login, registration, and authentication system with security, social login, 2FA, and more.', 'hikmah-login' ),
        ];

        if ( $release ) {
            $info->last_updated = $release['date'];
        }

        return $info;
    }

    /**
     * Ensure the extracted update lands in the "hikmah-login" folder.
     *
     * GitHub source zips are named e.g. "proabusaleh-hikmah-login-<sha>",
     * which WordPress would otherwise treat as the plugin folder.
     *
     * @param string $source        Path to extracted source.
     * @param string $remote_source Remote source directory.
     * @param object $upgrader      Upgrader instance.
     * @param array  $hook_extra    Extra arguments.
     * @return string
     */
    public function fix_plugin_folder( $source, $remote_source, $upgrader, $hook_extra ) {

        if ( ! empty( $hook_extra['plugin'] ) && $hook_extra['plugin'] !== $this->plugin_basename ) {
            return $source;
        }

        $target = $remote_source . '/' . $this->plugin_slug;

        if ( $source !== $target ) {

            // Remove any stale partial folder first.
            if ( file_exists( $target ) ) {
                $GLOBALS['wp_filesystem']->delete( $target, true, 'd' );
            }

            if ( $GLOBALS['wp_filesystem']->move( $source, $target ) ) {
                $source = $target;
            }
        }

        return $source;
    }

    /**
     * Drop the release cache after a successful install/update.
     *
     * @param bool  $true        True on success.
     * @param array $hook_extra  Extra arguments.
     * @param array $result      Install result.
     * @return bool
     */
    public function clear_cache_after_install( $true, $hook_extra, $result ) {

        if ( ! empty( $hook_extra['plugin'] ) && $hook_extra['plugin'] === $this->plugin_basename ) {
            delete_transient( self::CACHE_KEY );
        }

        return $true;
    }

    /**
     * Get the latest release object for the active channel.
     *
     * @return array|null Normalized release data or null.
     */
    public function get_latest_release() {

        $this->refresh_cache_if_stale();

        $releases = get_transient( self::CACHE_KEY );

        if ( ! is_array( $releases ) || empty( $releases ) ) {
            return null;
        }

        foreach ( $releases as $release ) {

            // Stable channel skips pre-releases.
            if ( 'stable' === $this->channel && ! empty( $release['prerelease'] ) ) {
                continue;
            }

            return $release;
        }

        return null;
    }

    /**
     * Force a refresh of the cached release list.
     */
    public function refresh_cache() {
        delete_transient( self::CACHE_KEY );
        $this->refresh_cache_if_stale();
    }

    /**
     * Fetch and cache the release list if it is stale or missing.
     */
    private function refresh_cache_if_stale() {

        if ( false !== get_transient( self::CACHE_KEY ) ) {
            return;
        }

        $releases = $this->fetch_releases();

        if ( empty( $releases ) ) {
            // Retry sooner when the API call failed.
            set_transient( self::CACHE_KEY, [], 10 * MINUTE_IN_SECONDS );
            return;
        }

        set_transient( self::CACHE_KEY, $releases, self::CACHE_TTL );
    }

    /**
     * Request the release list from the GitHub API.
     *
     * Results are sorted newest-first by the API; pre-releases are kept
     * for the beta channel and filtered out later for stable.
     *
     * @return array Normalized releases (version, date, notes, zip).
     */
    private function fetch_releases() {

        $response = wp_remote_get( self::API_URL, [
            'timeout'     => 15,
            'headers'     => $this->api_headers(),
            'sslverify'   => true,
            'user-agent'  => 'Hikmah-Login-Updater/' . HIKMAH_LOGIN_VERSION,
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $code = wp_remote_retrieve_response_code( $response );

        // 403 + no token usually means the unauthenticated rate limit ran out.
        if ( 200 !== (int) $code || empty( $this->token ) && 403 === (int) $code ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) ) {
            return [];
        }

        $releases = [];

        foreach ( $body as $item ) {

            $version = $this->parse_version_from_tag( $item['tag_name'] ?? '' );

            if ( '' === $version ) {
                continue;
            }

            $releases[] = [
                'version'    => $version,
                'tag'        => (string) ( $item['tag_name'] ?? '' ),
                'prerelease' => ! empty( $item['prerelease'] ),
                'date'       => (string) ( $item['published_at'] ?? '' ),
                'html_url'   => (string) ( $item['html_url'] ?? self::REPO_URL ),
                'zip_url'    => (string) ( $item['zipball_url'] ?? self::REPO_URL ),
                'notes_html' => (string) ( $item['body'] ?? '' ),
                'notes_text' => trim( wp_strip_all_tags( (string) ( $item['body'] ?? '' ) ) ),
            ];
        }

        return $releases;
    }

    /**
     * Build the headers for the GitHub API request.
     *
     * @return array
     */
    private function api_headers() {

        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        if ( '' !== $this->token ) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $headers;
    }

    /**
     * Extract a version number from a release tag.
     *
     * Accepts "v2.0.0", "2.0.0", etc. Falls back to plain x.y.z matches.
     *
     * @param string $tag Tag name.
     * @return string
     */
    private function parse_version_from_tag( $tag ) {

        $tag = ltrim( trim( (string) $tag ), 'vV' );

        if ( preg_match( '/^(\d+\.\d+\.\d+)(?:[-.+][0-9A-Za-z.-]+)?$/', $tag, $m ) ) {
            return $m[1];
        }

        return '';
    }

    /**
     * Strip a leading "v" so version_compare behaves.
     *
     * @param string $version Version string.
     * @return string
     */
    private function normalize_version( $version ) {
        return ltrim( trim( (string) $version ), 'vV' );
    }
}