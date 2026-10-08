<?php
if (!defined('ABSPATH')) exit;
$s     = WTK_Core::get();
$caps  = WTK_Core::server_caps();
$stats = WTK_Core::get_stats();
global $wpdb;
$logs  = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wtk_log ORDER BY created_at DESC LIMIT 50");
?>
<div class="wtk-wrap" id="wtk-app">

    <!-- ══ TOPBAR ═══════════════════════════════════════════════════ -->
    <div class="wtk-topbar">
        <div class="wtk-topbar-left">
            <span class="wtk-logo">⚡</span>
            <div>
                <h1>WP Turbo Kit</h1>
                <span class="wtk-version">v<?php echo WTK_VERSION ?> — Media Optimizer</span>
            </div>
        </div>
        <div class="wtk-topbar-right">
            <span class="wtk-server-pill <?php echo $caps['imagick']?'green':($caps['gd']?'yellow':'red') ?>">
                <?php echo $caps['imagick']?'⚡ Imagick':($caps['gd']?'✓ GD':'❌ No Library') ?>
            </span>
            <a href="<?php echo admin_url('admin.php?page=wp-turbo-kit&wtk_reset_wizard=1') ?>" class="wtk-topbar-link">Re-run Setup</a>
        </div>
    </div>

    <!-- ══ STAT CARDS ════════════════════════════════════════════════ -->
    <div class="wtk-stat-row">
        <div class="wtk-stat-card" data-color="purple">
            <div class="wtk-stat-icon">🖼</div>
            <div class="wtk-stat-body">
                <span class="wtk-stat-num" id="js-stat-total"><?php echo number_format($stats['total']) ?></span>
                <span class="wtk-stat-label">Images Optimized</span>
            </div>
            <div class="wtk-stat-ring"></div>
        </div>
        <div class="wtk-stat-card" data-color="green">
            <div class="wtk-stat-icon">💾</div>
            <div class="wtk-stat-body">
                <span class="wtk-stat-num" id="js-stat-saved"><?php echo $stats['saved_f'] ?></span>
                <span class="wtk-stat-label">Space Saved</span>
            </div>
            <div class="wtk-stat-ring"></div>
        </div>
        <div class="wtk-stat-card" data-color="blue">
            <div class="wtk-stat-icon">⏳</div>
            <div class="wtk-stat-body">
                <span class="wtk-stat-num" id="js-stat-pending"><?php echo number_format($stats['pending']) ?></span>
                <span class="wtk-stat-label">Images Pending</span>
            </div>
            <div class="wtk-stat-ring"></div>
        </div>
        <div class="wtk-stat-card" data-color="orange">
            <div class="wtk-stat-icon">⚙️</div>
            <div class="wtk-stat-body">
                <span class="wtk-stat-num"><?php echo $s['quality'] ?>%</span>
                <span class="wtk-stat-label">Current Quality</span>
            </div>
            <div class="wtk-stat-ring"></div>
        </div>
    </div>

    <!-- ══ MAIN GRID ══════════════════════════════════════════════════ -->
    <div class="wtk-grid">

        <!-- ── LEFT COLUMN ─────────────────────────────────────── -->
        <div class="wtk-col-main">

            <!-- BULK OPTIMIZER -->
            <div class="wtk-panel" id="panel-bulk">
                <div class="wtk-panel-head">
                    <h2><span class="wtk-panel-icon">🚀</span> Bulk Image Optimizer</h2>
                    <span class="wtk-badge" id="js-pending-badge"><?php echo $stats['pending'] ?> pending</span>
                </div>
                <div class="wtk-panel-body">
                    <?php if (!$caps['can_convert']): ?>
                    <div class="wtk-alert error">
                        ❌ No image processing library found. Please enable <strong>GD with WebP</strong> or <strong>Imagick</strong> from your hosting control panel.
                    </div>
                    <?php else: ?>

                    <p class="wtk-panel-desc">Converts all JPG/PNG images to WebP and compresses them under <strong><?php echo $s['max_kb'] ?>KB</strong>. Processes 5 images at a time to avoid timeouts.</p>

                    <!-- Progress -->
                    <div id="js-bulk-prog" style="display:none">
                        <div class="wtk-progress-track">
                            <div class="wtk-progress-fill" id="js-prog-fill"></div>
                        </div>
                        <div class="wtk-prog-meta">
                            <span id="js-prog-txt">Starting…</span>
                            <span id="js-prog-pct">0%</span>
                        </div>
                    </div>

                    <!-- Log -->
                    <div id="js-bulk-log" class="wtk-bulk-log" style="display:none"></div>

                    <!-- Actions -->
                    <div class="wtk-bulk-actions">
                        <button id="js-bulk-start" class="wtk-btn primary large">
                            <span class="btn-icon">🚀</span>
                            Start Optimization
                            <span class="btn-sub" id="js-bulk-sub"><?php echo $stats['pending'] ?> images</span>
                        </button>
                        <button id="js-bulk-stop" class="wtk-btn ghost" style="display:none">
                            ⏹ Stop
                        </button>
                        <button id="js-bulk-reset" class="wtk-btn ghost small" title="Reset optimization status to re-process all images">
                            ↺ Reset All
                        </button>
                    </div>
                    <div id="js-bulk-done"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- UNUSED IMAGES -->
            <div class="wtk-panel" id="panel-unused">
                <div class="wtk-panel-head">
                    <h2><span class="wtk-panel-icon">🔍</span> Unused Images</h2>
                    <span class="wtk-badge orange" id="js-unused-count-badge">Not scanned</span>
                </div>
                <div class="wtk-panel-body">
                    <p class="wtk-panel-desc">Scans posts, pages, widgets, ACF fields, WooCommerce galleries, and option values to find images not referenced anywhere.</p>

                    <button id="js-scan-btn" class="wtk-btn secondary">
                        <span class="btn-icon">🔍</span> Scan for Unused Images
                    </button>

                    <div id="js-scan-loading" class="wtk-scan-loading" style="display:none">
                        <div class="wtk-scan-dots"><span></span><span></span><span></span></div>
                        <p>Scanning database… this may take a moment on large sites.</p>
                    </div>

                    <div id="js-unused-result" style="display:none">
                        <div id="js-unused-info" class="wtk-unused-info"></div>
                        <div id="js-unused-grid" class="wtk-unused-grid"></div>
                        <div id="js-unused-actions" class="wtk-unused-actions" style="display:none">
                            <div class="wtk-unused-select-row">
                                <label class="wtk-checkbox-label">
                                    <input type="checkbox" id="js-sel-all"> Select All
                                </label>
                                <span id="js-sel-count" style="color:#888;font-size:12px"></span>
                            </div>
                            <div class="wtk-unused-btn-row">
                                <button id="js-del-sel" class="wtk-btn danger" disabled>🗑 Delete Selected</button>
                                <button id="js-del-all" class="wtk-btn danger outline">🗑 Delete ALL</button>
                            </div>
                            <div id="js-del-msg" class="wtk-del-msg"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LOG -->
            <div class="wtk-panel" id="panel-log">
                <div class="wtk-panel-head">
                    <h2><span class="wtk-panel-icon">📋</span> Optimization Log</h2>
                    <button id="js-clear-log" class="wtk-btn ghost small">🗑 Clear</button>
                </div>
                <div class="wtk-panel-body wtk-log-wrap">
                <?php if (empty($logs)): ?>
                    <div class="wtk-empty-log">
                        <span>📭</span>
                        <p>No images optimized yet.<br>Run the Bulk Optimizer or upload a new image.</p>
                    </div>
                <?php else: ?>
                    <table class="wtk-log-table">
                        <thead><tr><th>File</th><th>Before</th><th>After</th><th>Saved</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach($logs as $row):
                            $sv  = max(0,(int)$row->original_size-(int)$row->new_size);
                            $pct = $row->original_size>0 ? round($sv/$row->original_size*100,1):0;
                        ?>
                        <tr class="<?php echo $row->status?'':'wtk-log-skip' ?>">
                            <td class="wtk-log-file" title="<?php echo esc_attr($row->file_name) ?>"><?php echo esc_html($row->file_name) ?></td>
                            <td><?php echo WTK_Core::fmt_bytes($row->original_size) ?></td>
                            <td><?php echo WTK_Core::fmt_bytes($row->new_size) ?></td>
                            <td><span class="wtk-log-saved">-<?php echo $pct ?>%</span> <?php echo WTK_Core::fmt_bytes($sv) ?></td>
                            <td class="wtk-log-date"><?php echo esc_html(date('M j, g:i a',strtotime($row->created_at))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                </div>
            </div>

        </div><!-- .wtk-col-main -->

        <!-- ── RIGHT COLUMN ────────────────────────────────────── -->
        <div class="wtk-col-side">

            <!-- SETTINGS -->
            <div class="wtk-panel" id="panel-settings">
                <div class="wtk-panel-head">
                    <h2><span class="wtk-panel-icon">⚙️</span> Settings</h2>
                </div>
                <div class="wtk-panel-body">
                <form id="js-settings-form">

                    <div class="wtk-field">
                        <label>Image Quality</label>
                        <div class="wtk-range-wrap">
                            <input type="range" name="quality" min="30" max="95" value="<?php echo $s['quality'] ?>" id="js-quality">
                            <span id="js-q-display"><?php echo $s['quality'] ?>%</span>
                        </div>
                        <p class="wtk-field-hint">75–85 recommended</p>
                    </div>

                    <div class="wtk-field">
                        <label>Max File Size</label>
                        <div class="wtk-input-group">
                            <input type="number" name="max_kb" min="10" max="500" value="<?php echo $s['max_kb'] ?>">
                            <span>KB</span>
                        </div>
                        <p class="wtk-field-hint">Quality reduces until file fits</p>
                    </div>

                    <div class="wtk-field">
                        <label>Skip Files Under</label>
                        <div class="wtk-input-group">
                            <input type="number" name="skip_small" min="0" max="100" value="<?php echo $s['skip_small'] ?? 10 ?>">
                            <span>KB</span>
                        </div>
                        <p class="wtk-field-hint">Tiny images not worth converting</p>
                    </div>

                    <div class="wtk-divider"></div>

                    <div class="wtk-toggle-row">
                        <div>
                            <strong>Auto-Convert Uploads</strong>
                            <p>New uploads auto-convert to WebP</p>
                        </div>
                        <label class="wtk-toggle">
                            <input type="checkbox" name="auto_webp" <?php echo !empty($s['auto_webp'])?'checked':'' ?>>
                            <span class="wtk-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="wtk-toggle-row">
                        <div>
                            <strong>Convert Thumbnails</strong>
                            <p>Also convert all registered sizes</p>
                        </div>
                        <label class="wtk-toggle">
                            <input type="checkbox" name="convert_thumbs" <?php echo !empty($s['convert_thumbs'])?'checked':'' ?>>
                            <span class="wtk-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="wtk-toggle-row">
                        <div>
                            <strong>Keep Originals</strong>
                            <p>Keep JPG/PNG alongside WebP</p>
                        </div>
                        <label class="wtk-toggle">
                            <input type="checkbox" name="keep_original" <?php echo !empty($s['keep_original'])?'checked':'' ?>>
                            <span class="wtk-toggle-slider"></span>
                        </label>
                    </div>

                    <button type="submit" class="wtk-btn primary full" style="margin-top:16px">
                        💾 Save Settings
                    </button>
                    <div id="js-settings-msg" class="wtk-settings-msg"></div>
                </form>
                </div>
            </div>

            <!-- SERVER INFO -->
            <div class="wtk-panel" id="panel-server">
                <div class="wtk-panel-head">
                    <h2><span class="wtk-panel-icon">🖥</span> Server Info</h2>
                </div>
                <div class="wtk-panel-body">
                    <div class="wtk-server-list">
                        <div class="wtk-server-item <?php echo $caps['imagick']?'ok':'na' ?>">
                            <span><?php echo $caps['imagick']?'✅':'—' ?> Imagick</span>
                            <span><?php echo $caps['imagick']?'Active':'Not installed' ?></span>
                        </div>
                        <div class="wtk-server-item <?php echo $caps['gd']?'ok':'fail' ?>">
                            <span><?php echo $caps['gd']?'✅':'❌' ?> GD + WebP</span>
                            <span><?php echo $caps['gd']?'Active':'Not available' ?></span>
                        </div>
                        <div class="wtk-server-item ok">
                            <span>🐘 PHP</span>
                            <span><?php echo $caps['php'] ?></span>
                        </div>
                        <div class="wtk-server-item ok">
                            <span>💾 Memory</span>
                            <span><?php echo $caps['memory'] ?></span>
                        </div>
                        <div class="wtk-server-item ok">
                            <span>⏱ Max Exec</span>
                            <span><?php echo $caps['max_exec'] ?>s</span>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- .wtk-col-side -->
    </div><!-- .wtk-grid -->

</div><!-- .wtk-wrap -->

<?php
// Allow wizard reset
if (isset($_GET['wtk_reset_wizard'])) {
    update_option('wtk_wizard_done',0);
    wp_redirect(admin_url('admin.php?page=wp-turbo-kit'));
    exit;
}
?>
