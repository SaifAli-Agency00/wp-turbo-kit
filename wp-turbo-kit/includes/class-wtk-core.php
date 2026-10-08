<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTK_Core {

    /* ── Default Settings ─────────────────────────────────────────── */
    public static function defaults() {
        return [
            'quality'        => 82,
            'max_kb'         => 100,
            'keep_original'  => 1,
            'auto_webp'      => 1,
            'convert_thumbs' => 1,
            'skip_small'     => 10,   // skip images under 10KB
        ];
    }

    public static function get( $key = null ) {
        $s = get_option( 'wtk_settings', self::defaults() );
        return $key ? ( $s[ $key ] ?? ( self::defaults()[ $key ] ?? null ) ) : $s;
    }

    /* ── Activation ───────────────────────────────────────────────── */
    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Optimization log table
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wtk_log (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            attachment_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            file_name     VARCHAR(255)        NOT NULL DEFAULT '',
            original_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            new_size      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            status        TINYINT(1)          NOT NULL DEFAULT 1,
            created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id)
        ) $charset" );

        // First-time: mark setup wizard needed
        if ( ! get_option( 'wtk_settings' ) ) {
            update_option( 'wtk_wizard_done', 0 );
            update_option( 'wtk_settings', self::defaults() );
        }

        update_option( 'wtk_version', WTK_VERSION );

        // Schedule weekly cleanup of old log entries (keep last 1000)
        if ( ! wp_next_scheduled( 'wtk_cleanup' ) ) {
            wp_schedule_event( time(), 'weekly', 'wtk_cleanup' );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'wtk_cleanup' );
    }

    /* ── Boot ─────────────────────────────────────────────────────── */
    public static function init() {
        // Auto-convert on upload
        if ( self::get('auto_webp') ) {
            add_filter( 'wp_generate_attachment_metadata', [ 'WTK_Converter', 'on_upload' ], 20, 2 );
        }

        // Scheduled cleanup
        add_action( 'wtk_cleanup', [ __CLASS__, 'cleanup_log' ] );
    }

    /* ── Helpers ──────────────────────────────────────────────────── */
    public static function log( $aid, $name, $orig, $new, $status = 1 ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'wtk_log', [
            'attachment_id' => (int) $aid,
            'file_name'     => sanitize_text_field( $name ),
            'original_size' => (int) $orig,
            'new_size'      => (int) $new,
            'status'        => (int) $status,
        ], [ '%d', '%s', '%d', '%d', '%d' ] );
    }

    public static function cleanup_log() {
        global $wpdb;
        $keep = 1000;
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wtk_log" );
        if ( $count > $keep ) {
            $wpdb->query( "DELETE FROM {$wpdb->prefix}wtk_log ORDER BY id ASC LIMIT " . ( $count - $keep ) );
        }
    }

    public static function server_caps() {
        return [
            'imagick'    => extension_loaded('imagick'),
            'gd'         => extension_loaded('gd') && function_exists('imagewebp'),
            'can_convert'=> extension_loaded('imagick') || ( extension_loaded('gd') && function_exists('imagewebp') ),
            'php'        => PHP_VERSION,
            'memory'     => ini_get('memory_limit'),
            'max_exec'   => ini_get('max_execution_time'),
        ];
    }

    public static function get_stats() {
        global $wpdb;
        $row = $wpdb->get_row( "SELECT COUNT(*) total, SUM(original_size) orig, SUM(new_size) news FROM {$wpdb->prefix}wtk_log WHERE status=1" );
        $pending = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON p.ID=m.post_id AND m.meta_key='_wtk_done'
             WHERE p.post_type='attachment' AND p.post_status='inherit'
               AND p.post_mime_type IN('image/jpeg','image/png','image/bmp','image/tiff')
               AND m.meta_id IS NULL"
        );
        $saved = $row ? max(0,(int)$row->orig-(int)$row->news) : 0;
        return [
            'total'   => $row ? (int)$row->total : 0,
            'saved'   => $saved,
            'saved_f' => self::fmt_bytes($saved),
            'pending' => $pending,
        ];
    }

    public static function fmt_bytes( $b ) {
        if ($b <= 0)        return '0 B';
        if ($b >= 1073741824) return round($b/1073741824,2).' GB';
        if ($b >= 1048576)  return round($b/1048576,1).' MB';
        if ($b >= 1024)     return round($b/1024,1).' KB';
        return $b.' B';
    }
}
