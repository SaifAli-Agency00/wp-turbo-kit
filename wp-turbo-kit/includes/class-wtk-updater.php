<?php
/**
 * WTK_Updater — GitHub-based auto-update for WP Turbo Kit
 *
 * How it works:
 * 1. WordPress checks for updates every 12 hours via transients
 * 2. This class hooks into that check and queries GitHub Releases API
 * 3. If a newer tag exists, it shows the update notice in WP admin
 * 4. User clicks "Update Now" — WordPress downloads the ZIP from GitHub
 * 5. Done — fully automatic, just like WordPress.org plugins
 *
 * Setup: Set your GitHub username and repo below.
 *        Create a GitHub Release with a ZIP asset named "wp-turbo-kit.zip"
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WTK_Updater {

    private $plugin_file;
    private $plugin_slug;
    private $plugin_version;
    private $github_user;
    private $github_repo;
    private $github_api_url;
    private $cache_key;
    private $cache_ttl = 43200; // 12 hours

    public function __construct( $plugin_file, $github_user, $github_repo ) {
        $this->plugin_file    = $plugin_file;
        $this->plugin_slug    = plugin_basename( $plugin_file );
        $this->plugin_version = WTK_VERSION;
        $this->github_user    = $github_user;
        $this->github_repo    = $github_repo;
        $this->github_api_url = "https://api.github.com/repos/{$github_user}/{$github_repo}/releases/latest";
        $this->cache_key      = 'wtk_github_update_' . md5( $this->github_api_url );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_post_install',                 [ $this, 'after_install' ], 10, 3 );
        add_action( 'admin_notices',                         [ $this, 'maybe_show_update_notice' ] );
    }

    /* ── Fetch latest release from GitHub ──────────────────────────── */
    private function get_github_release() {
        $cached = get_transient( $this->cache_key );
        if ( $cached !== false ) return $cached;

        $response = wp_remote_get( $this->github_api_url, [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WP-Turbo-Kit/' . $this->plugin_version . '; ' . get_bloginfo('url'),
            ],
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            // Cache failure for 1 hour to avoid hammering API
            set_transient( $this->cache_key, null, 3600 );
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $body->tag_name ) ) {
            set_transient( $this->cache_key, null, 3600 );
            return null;
        }

        // Find the plugin ZIP asset
        $zip_url = null;
        if ( ! empty( $body->assets ) ) {
            foreach ( $body->assets as $asset ) {
                if ( strpos( $asset->name, '.zip' ) !== false ) {
                    $zip_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // Fallback to zipball URL (GitHub auto-generated ZIP of the repo)
        if ( ! $zip_url ) {
            $zip_url = $body->zipball_url;
        }

        $release = (object) [
            'tag'         => ltrim( $body->tag_name, 'v' ),
            'version'     => ltrim( $body->tag_name, 'v' ),
            'zip_url'     => $zip_url,
            'description' => $body->body ?? '',
            'published'   => $body->published_at ?? '',
            'url'         => $body->html_url ?? '',
        ];

        set_transient( $this->cache_key, $release, $this->cache_ttl );
        return $release;
    }

    /* ── Hook into WP update check ─────────────────────────────────── */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = $this->get_github_release();
        if ( ! $release ) return $transient;

        if ( version_compare( $this->plugin_version, $release->version, '<' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'id'            => $this->plugin_slug,
                'slug'          => dirname( $this->plugin_slug ),
                'plugin'        => $this->plugin_slug,
                'new_version'   => $release->version,
                'url'           => $release->url,
                'package'       => $release->zip_url,
                'icons'         => [],
                'banners'       => [],
                'banners_rtl'   => [],
                'tested'        => '6.7',
                'requires_php'  => '7.4',
                'compatibility' => new stdClass(),
            ];
        }

        return $transient;
    }

    /* ── Plugin info popup (when user clicks "View version details") ── */
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) return $result;

        $release = $this->get_github_release();
        if ( ! $release ) return $result;

        return (object) [
            'name'          => 'WP Turbo Kit — Media Optimizer',
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => $release->version,
            'author'        => '<a href="https://advertidea.com">Advertidea</a>',
            'homepage'      => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
            'requires'      => '5.8',
            'tested'        => '6.7',
            'requires_php'  => '7.4',
            'last_updated'  => $release->published,
            'sections'      => [
                'description' => 'WebP image conversion, compression, bulk optimizer, and unused image finder with a premium animated dashboard.',
                'changelog'   => nl2br( esc_html( $release->description ) ) ?: '<p>See <a href="' . esc_url($release->url) . '" target="_blank">GitHub release</a> for details.</p>',
            ],
            'download_link' => $release->zip_url,
        ];
    }

    /* ── After install: rename folder correctly ─────────────────────── */
    public function after_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $response;
        }

        global $wp_filesystem;
        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_slug );

        // GitHub ZIPs extract with a different folder name — rename it
        if ( isset( $result['destination'] ) && $result['destination'] !== $plugin_folder ) {
            $wp_filesystem->move( $result['destination'], $plugin_folder );
            $result['destination'] = $plugin_folder;
        }

        // Re-activate plugin
        activate_plugin( $this->plugin_slug );

        return $result;
    }

    /* ── Manual update notice in admin ─────────────────────────────── */
    public function maybe_show_update_notice() {
        if ( ! current_user_can( 'update_plugins' ) ) return;

        $release = $this->get_github_release();
        if ( ! $release ) return;
        if ( ! version_compare( $this->plugin_version, $release->version, '<' ) ) return;

        // Only show on plugin's own page
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'toplevel_page_wp-turbo-kit' ) return;

        printf(
            '<div class="notice notice-warning is-dismissible"><p>
                <strong>WP Turbo Kit:</strong> Version <strong>%s</strong> is available.
                <a href="%s">Update now</a> or <a href="%s" target="_blank">view release notes</a>.
            </p></div>',
            esc_html( $release->version ),
            esc_url( wp_nonce_url( admin_url( 'update.php?action=upgrade-plugin&plugin=' . urlencode( $this->plugin_slug ) ), 'upgrade-plugin_' . $this->plugin_slug ) ),
            esc_url( $release->url )
        );
    }

    /* ── Clear update cache (call when needed) ──────────────────────── */
    public static function clear_cache() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wtk_github_update_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wtk_github_update_%'" );
    }
}
