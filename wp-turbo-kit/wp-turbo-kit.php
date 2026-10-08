<?php
/**
 * Plugin Name:       WP Turbo Kit — Media Optimizer
 * Plugin URI:        https://github.com/YOUR_GITHUB_USERNAME/wp-turbo-kit
 * Description:       Convert images to WebP, compress under your target size, bulk optimize, find unused images — with a premium animated dashboard.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Advertidea
 * Author URI:        https://advertidea.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-turbo-kit
 * Domain Path:       /languages
 * Update URI:        https://github.com/SaifAli-Agency00/wp-turbo-kit
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   CONSTANTS
   !! Before releasing: replace YOUR_GITHUB_USERNAME below !!
============================================================ */
define( 'WTK_VERSION',     '1.0.0' );
define( 'WTK_FILE',        __FILE__ );
define( 'WTK_DIR',         plugin_dir_path( __FILE__ ) );
define( 'WTK_URL',         plugin_dir_url( __FILE__ ) );
define( 'WTK_SLUG',        'wp-turbo-kit' );
define( 'WTK_GITHUB_USER', 'SaifAli-Agency00' );   // ← CHANGE THIS
define( 'WTK_GITHUB_REPO', 'wp-turbo-kit' );

/* ============================================================
   AUTOLOADER
============================================================ */
spl_autoload_register( function( $class ) {
    if ( strpos( $class, 'WTK_' ) !== 0 ) return;
    $file = WTK_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
    if ( file_exists( $file ) ) require_once $file;
} );

require_once WTK_DIR . 'admin/class-wtk-admin.php';

/* ============================================================
   ACTIVATION / DEACTIVATION
============================================================ */
register_activation_hook( WTK_FILE,   array( 'WTK_Core', 'activate' ) );
register_deactivation_hook( WTK_FILE, array( 'WTK_Core', 'deactivate' ) );

/* ============================================================
   BOOT
============================================================ */
add_action( 'plugins_loaded', 'wtk_boot' );

function wtk_boot() {
    // Load text domain for translations
    load_plugin_textdomain( 'wp-turbo-kit', false, dirname( plugin_basename( WTK_FILE ) ) . '/languages' );

    // Boot core
    WTK_Core::init();

    // Auto-convert images on upload
    $settings = WTK_Core::get();
    if ( ! empty( $settings['auto_webp'] ) ) {
        add_filter( 'wp_generate_attachment_metadata', array( 'WTK_Converter', 'on_upload' ), 20, 2 );
    }

    // GitHub auto-updater (only in admin, not on every request)
    if ( is_admin() ) {
        new WTK_Updater( WTK_FILE, WTK_GITHUB_USER, WTK_GITHUB_REPO );
    }
}
