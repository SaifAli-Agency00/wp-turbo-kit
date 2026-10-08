<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTK_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        WTK_Ajax::register();
    }

    public function menu() {
        add_menu_page(
            'WP Turbo Kit',
            '⚡ Turbo Kit',
            'manage_options',
            'wp-turbo-kit',
            [ $this, 'render' ],
            'dashicons-performance',
            58
        );
    }

    public function assets( $hook ) {
        if ( strpos($hook,'wp-turbo-kit') === false ) return;
        wp_enqueue_style(  'wtk', WTK_URL.'assets/admin.css', [], WTK_VERSION );
        wp_enqueue_script( 'wtk', WTK_URL.'assets/admin.js',  ['jquery'], WTK_VERSION, true );
        wp_localize_script( 'wtk', 'WTK', [
            'ajax'    => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wtk_nonce'),
            'caps'    => WTK_Core::server_caps(),
            'stats'   => WTK_Core::get_stats(),
            'wizard'  => (int) get_option('wtk_wizard_done',0),
            'settings'=> WTK_Core::get(),
        ]);
    }

    public function render() {
        // Show wizard on first install
        if ( ! get_option('wtk_wizard_done',0) ) {
            include WTK_DIR . 'admin/views/wizard.php';
            return;
        }
        include WTK_DIR . 'admin/views/dashboard.php';
    }
}

new WTK_Admin();
