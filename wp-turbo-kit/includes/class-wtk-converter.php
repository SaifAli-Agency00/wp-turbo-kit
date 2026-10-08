<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTK_Converter {

    /* ── Main convert function ────────────────────────────────────── */
    public static function convert( $path ) {
        if ( ! file_exists( $path ) ) return ['ok'=>false,'error'=>'File not found'];

        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        if ( $ext === 'webp' ) return ['ok'=>false,'error'=>'Already WebP'];

        $skip = ['svg','gif','ico','bmp']; // skip these
        if ( in_array($ext,$skip) ) return ['ok'=>false,'error'=>'Skipped type'];

        $s       = WTK_Core::get();
        $quality = (int)($s['quality'] ?? 82);
        $max     = (int)($s['max_kb']  ?? 100) * 1024;
        $orig    = filesize($path);

        // Skip very small files
        $skip_kb = (int)($s['skip_small'] ?? 10) * 1024;
        if ($orig < $skip_kb) return ['ok'=>false,'error'=>'File too small, skipped'];

        $out = preg_replace( '/\.'.preg_quote($ext,'/').'$/i', '.webp', $path );

        // Try Imagick → GD
        $ok = false;
        if ( extension_loaded('imagick') )
            $ok = self::via_imagick($path,$out,$quality,$max);
        if (!$ok && extension_loaded('gd') && function_exists('imagewebp'))
            $ok = self::via_gd($path,$out,$quality,$max,$ext);

        if (!$ok) return ['ok'=>false,'error'=>'Conversion failed (no GD/Imagick or unsupported format)'];

        $new = filesize($out);

        // If WebP ended up larger — discard it
        if ($new >= $orig) {
            @unlink($out);
            return ['ok'=>false,'error'=>'WebP larger than original'];
        }

        // Delete original if not keeping
        if ( empty($s['keep_original']) && $out !== $path ) {
            @unlink($path);
        }

        return [
            'ok'        => true,
            'new_path'  => $out,
            'orig_size' => $orig,
            'new_size'  => $new,
            'saving'    => $orig - $new,
            'saving_pct'=> round(($orig-$new)/$orig*100,1),
        ];
    }

    /* ── Imagick ──────────────────────────────────────────────────── */
    private static function via_imagick($src,$dest,$quality,$max) {
        try {
            $im = new Imagick($src);
            $im->setImageFormat('webp');
            $im->stripImage();
            $im->autoOrient();

            // Try to hit target size
            $q = $quality;
            do {
                $im->setImageCompressionQuality($q);
                $blob = $im->getImagesBlob();
                if (strlen($blob) <= $max || $q <= 30) break;
                $q -= 5;
            } while ($q >= 30);

            $ok = (bool) file_put_contents($dest,$blob);
            $im->destroy();
            return $ok;
        } catch (Exception $e) { return false; }
    }

    /* ── GD ───────────────────────────────────────────────────────── */
    private static function via_gd($src,$dest,$quality,$max,$ext) {
        $im = false;
        switch ($ext) {
            case 'jpg': case 'jpeg': $im = @imagecreatefromjpeg($src); break;
            case 'png':
                $im = @imagecreatefrompng($src);
                if ($im) {
                    // Preserve transparency
                    $c = imagecreatetruecolor(imagesx($im),imagesy($im));
                    imagealphablending($c,false);
                    imagesavealpha($c,true);
                    imagefill($c,0,0,imagecolorallocatealpha($c,0,0,0,127));
                    imagecopy($c,$im,0,0,0,0,imagesx($im),imagesy($im));
                    imagedestroy($im); $im=$c;
                }
                break;
            case 'tiff': case 'tif':
                if (extension_loaded('imagick')) { return false; } // handled above
                break;
        }
        if (!$im) return false;

        $q = $quality; $blob = '';
        do {
            ob_start();
            imagewebp($im,null,$q);
            $blob = ob_get_clean();
            if (strlen($blob)<=$max||$q<=30) break;
            $q-=5;
        } while ($q>=30);

        imagedestroy($im);
        return $blob ? (bool)file_put_contents($dest,$blob) : false;
    }

    /* ── On Upload hook ───────────────────────────────────────────── */
    public static function on_upload($meta,$id) {
        $mime = get_post_mime_type($id);
        $allowed = ['image/jpeg','image/png','image/tiff'];
        if (!in_array($mime,$allowed)) return $meta;

        $file = get_attached_file($id);
        if (!$file||!file_exists($file)) return $meta;

        $r = self::convert($file);
        if (!$r['ok']) return $meta;

        // Convert thumbnail sizes
        $s = WTK_Core::get();
        if (!empty($s['convert_thumbs']) && !empty($meta['sizes'])) {
            $dir = dirname($file);
            foreach ($meta['sizes'] as $sz => $data) {
                $t = $dir.'/'.$data['file'];
                if (!file_exists($t)) continue;
                $tr = self::convert($t);
                if ($tr['ok']) {
                    $meta['sizes'][$sz]['file']      = basename($tr['new_path']);
                    $meta['sizes'][$sz]['mime-type'] = 'image/webp';
                }
            }
        }

        // Update WP attachment references
        if ($r['new_path'] !== $file) {
            $base = trailingslashit(wp_upload_dir()['basedir']);
            $rel  = str_replace($base,'',$r['new_path']);
            $meta['file'] = $rel;
            update_post_meta($id,'_wp_attached_file',$rel);
            wp_update_post(['ID'=>$id,'post_mime_type'=>'image/webp']);
        }

        update_post_meta($id,'_wtk_done',1);
        update_post_meta($id,'_wtk_saved',$r['saving']);
        WTK_Core::log($id,basename($file),$r['orig_size'],$r['new_size']);
        return $meta;
    }

    /* ── Bulk: process one attachment ─────────────────────────────── */
    public static function process_one($id) {
        $file = get_attached_file($id);
        if (!$file||!file_exists($file)) {
            update_post_meta($id,'_wtk_done',1);
            return ['ok'=>false,'error'=>'File not found on disk'];
        }

        $r = self::convert($file);
        if (!$r['ok']) {
            update_post_meta($id,'_wtk_done',1);
            return $r;
        }

        // Update meta + thumbnails
        $meta = wp_get_attachment_metadata($id);
        $s    = WTK_Core::get();
        if (!empty($s['convert_thumbs'])&&!empty($meta['sizes'])) {
            $dir = dirname($file);
            foreach ($meta['sizes'] as $sz => $data) {
                $t = $dir.'/'.$data['file'];
                if (!file_exists($t)) continue;
                $tr = self::convert($t);
                if ($tr['ok']) {
                    $meta['sizes'][$sz]['file']      = basename($tr['new_path']);
                    $meta['sizes'][$sz]['mime-type'] = 'image/webp';
                }
            }
        }
        if ($r['new_path']!==$file) {
            $base = trailingslashit(wp_upload_dir()['basedir']);
            $rel  = str_replace($base,'',$r['new_path']);
            if ($meta) { $meta['file']=$rel; wp_update_attachment_metadata($id,$meta); }
            update_post_meta($id,'_wp_attached_file',$rel);
            wp_update_post(['ID'=>$id,'post_mime_type'=>'image/webp']);
        }

        update_post_meta($id,'_wtk_done',1);
        update_post_meta($id,'_wtk_saved',$r['saving']);
        WTK_Core::log($id,basename($file),$r['orig_size'],$r['new_size']);
        return $r;
    }
}
