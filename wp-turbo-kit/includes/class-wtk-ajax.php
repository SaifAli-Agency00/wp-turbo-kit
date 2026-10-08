<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTK_Ajax {

    public static function register() {
        $actions = [
            'wtk_bulk'         => 'bulk',
            'wtk_scan_unused'  => 'scan_unused',
            'wtk_delete_unused'=> 'delete_unused',
            'wtk_save_settings'=> 'save_settings',
            'wtk_clear_log'    => 'clear_log',
            'wtk_get_stats'    => 'get_stats',
            'wtk_wizard_save'  => 'wizard_save',
        ];
        foreach ($actions as $action => $method) {
            add_action("wp_ajax_{$action}", [__CLASS__, $method]);
        }
    }

    private static function check() {
        check_ajax_referer('wtk_nonce','nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized',403);
    }

    /* ── Bulk process 5 images ────────────────────────────────────── */
    public static function bulk() {
        self::check();
        global $wpdb;

        $ids = $wpdb->get_col(
            "SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON p.ID=m.post_id AND m.meta_key='_wtk_done'
             WHERE p.post_type='attachment' AND p.post_status='inherit'
               AND p.post_mime_type IN('image/jpeg','image/png','image/bmp','image/tiff')
               AND m.meta_id IS NULL
             LIMIT 5"
        );

        $results = [];
        foreach ($ids as $id) {
            $r = WTK_Converter::process_one((int)$id);
            $results[] = [
                'id'   => $id,
                'ok'   => $r['ok'],
                'msg'  => $r['ok']
                    ? '✅ Saved '.WTK_Core::fmt_bytes(max(0,$r['saving'])).' ('.($r['saving_pct']??0).'%)'
                    : '⚠ '.($r['error']??'Skipped'),
                'file' => isset($r['new_path']) ? basename($r['new_path']) : '',
            ];
        }

        $remaining = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON p.ID=m.post_id AND m.meta_key='_wtk_done'
             WHERE p.post_type='attachment' AND p.post_status='inherit'
               AND p.post_mime_type IN('image/jpeg','image/png','image/bmp','image/tiff')
               AND m.meta_id IS NULL"
        );

        wp_send_json_success(['results'=>$results,'remaining'=>$remaining,'stats'=>WTK_Core::get_stats()]);
    }

    /* ── Scan unused images ───────────────────────────────────────── */
    public static function scan_unused() {
        self::check();
        global $wpdb;

        // All image attachment IDs
        $all = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type='attachment' AND post_status='inherit'
               AND post_mime_type LIKE 'image/%'"
        );
        if (empty($all)) { wp_send_json_success(['images'=>[],'count'=>0,'size'=>'0 KB']); return; }

        $used = [];

        // 1. Featured images
        $fids = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_thumbnail_id' AND meta_value!=''");
        foreach ($fids as $id) $used[]=(int)$id;

        // 2. Directly attached (post_parent > 0)
        $aids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_parent>0 AND post_mime_type LIKE 'image/%'");
        foreach ($aids as $id) $used[]=(int)$id;

        // 3. Referenced in post_content
        $contents = $wpdb->get_col("SELECT post_content FROM {$wpdb->posts} WHERE post_status NOT IN('trash','auto-draft') AND post_content!=''");
        foreach ($contents as $content) {
            preg_match_all('/wp-image-(\d+)/',$content,$m);
            foreach ($m[1] as $id) $used[]=(int)$id;
            if (strpos($content,'/wp-content/uploads/')!==false) {
                foreach ($all as $id) {
                    $url = wp_get_attachment_url($id);
                    if ($url && strpos($content,basename($url))!==false) $used[]=(int)$id;
                }
            }
        }

        // 4. Postmeta numeric references (ACF, Elementor, etc.)
        $mvals = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_value REGEXP '^[0-9]+(,[0-9]+)*$' AND meta_value!=''");
        foreach ($mvals as $v) {
            foreach (explode(',',$v) as $id) if(is_numeric($id)&&(int)$id>0) $used[]=(int)$id;
        }

        // 5. WooCommerce gallery
        $wcg = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_product_image_gallery' AND meta_value!=''");
        foreach ($wcg as $v) foreach(explode(',',$v)as $id) if(is_numeric($id)) $used[]=(int)$id;

        $used   = array_unique(array_map('intval',$used));
        $unused = array_diff(array_map('intval',$all),$used);

        $images = []; $total = 0;
        foreach ($unused as $id) {
            $file = get_attached_file($id);
            if (!$file||!file_exists($file)) continue;
            $sz    = filesize($file);
            $total += $sz;
            $images[] = [
                'id'    => $id,
                'file'  => basename($file),
                'thumb' => wp_get_attachment_image_url($id,'thumbnail') ?: wp_get_attachment_url($id),
                'size'  => WTK_Core::fmt_bytes($sz),
                'date'  => get_the_date('M j, Y',$id),
            ];
        }

        wp_send_json_success([
            'images' => $images,
            'count'  => count($images),
            'size'   => WTK_Core::fmt_bytes($total),
        ]);
    }

    /* ── Delete unused ────────────────────────────────────────────── */
    public static function delete_unused() {
        self::check();
        $ids  = isset($_POST['ids']) ? array_map('intval',(array)$_POST['ids']) : [];
        $del  = []; $fail = [];
        foreach ($ids as $id) {
            if ($id>0 && wp_delete_attachment($id,true)) $del[]=$id;
            else $fail[]=$id;
        }
        wp_send_json_success(['deleted'=>count($del),'failed'=>count($fail),'ids'=>$del]);
    }

    /* ── Save settings ────────────────────────────────────────────── */
    public static function save_settings() {
        self::check();
        $new = [
            'auto_webp'      => !empty($_POST['auto_webp'])      ? 1:0,
            'quality'        => min(95,max(30,(int)($_POST['quality']??82))),
            'max_kb'         => min(500,max(10,(int)($_POST['max_kb']??100))),
            'keep_original'  => !empty($_POST['keep_original'])  ? 1:0,
            'convert_thumbs' => !empty($_POST['convert_thumbs']) ? 1:0,
            'skip_small'     => min(100,max(0,(int)($_POST['skip_small']??10))),
        ];
        update_option('wtk_settings',$new);
        wp_send_json_success(['settings'=>$new,'msg'=>'Settings saved!']);
    }

    /* ── Clear log ────────────────────────────────────────────────── */
    public static function clear_log() {
        self::check();
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wtk_log");
        wp_send_json_success();
    }

    /* ── Get live stats ───────────────────────────────────────────── */
    public static function get_stats() {
        self::check();
        wp_send_json_success(WTK_Core::get_stats());
    }

    /* ── Wizard save ──────────────────────────────────────────────── */
    public static function wizard_save() {
        self::check();
        $new = [
            'quality'       => min(95,max(30,(int)($_POST['quality']??82))),
            'max_kb'        => min(500,max(10,(int)($_POST['max_kb']??100))),
            'keep_original' => !empty($_POST['keep_original']) ? 1:0,
            'auto_webp'     => !empty($_POST['auto_webp'])     ? 1:0,
            'convert_thumbs'=> 1,
            'skip_small'    => 10,
        ];
        update_option('wtk_settings',$new);
        update_option('wtk_wizard_done',1);
        wp_send_json_success(['redirect'=> admin_url('admin.php?page=wp-turbo-kit')]);
    }
}

// Extra: reset bulk meta so all images can be re-processed
add_action('wp_ajax_wtk_reset_bulk', function(){
    check_ajax_referer('wtk_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die();
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_wtk_done'");
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_wtk_saved'");
    wp_send_json_success();
});
