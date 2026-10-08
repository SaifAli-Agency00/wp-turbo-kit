<?php if (!defined('ABSPATH')) exit; ?>
<div class="wtk-wizard-overlay" id="wtk-wizard">

    <div class="wtk-wizard-box">

        <!-- Logo / Header -->
        <div class="wtk-wiz-header">
            <div class="wtk-wiz-logo">⚡</div>
            <h1>Welcome to <span>WP Turbo Kit</span></h1>
            <p>Let's set up your image optimizer in 3 quick steps.</p>
        </div>

        <!-- Steps indicator -->
        <div class="wtk-wiz-steps">
            <div class="wtk-wiz-step active" data-step="1"><span>1</span> Server Check</div>
            <div class="wtk-wiz-step"        data-step="2"><span>2</span> Configure</div>
            <div class="wtk-wiz-step"        data-step="3"><span>3</span> Ready!</div>
        </div>

        <!-- STEP 1: Server Check -->
        <div class="wtk-wiz-panel active" id="wiz-step-1">
            <h2>🖥 Checking your server…</h2>
            <div class="wtk-wiz-checks" id="wiz-checks">
                <div class="wtk-check-item" id="chk-imagick">
                    <span class="chk-spinner">⏳</span>
                    <span class="chk-label">Imagick Extension</span>
                    <span class="chk-result"></span>
                </div>
                <div class="wtk-check-item" id="chk-gd">
                    <span class="chk-spinner">⏳</span>
                    <span class="chk-label">GD + WebP Support</span>
                    <span class="chk-result"></span>
                </div>
                <div class="wtk-check-item" id="chk-php">
                    <span class="chk-spinner">⏳</span>
                    <span class="chk-label">PHP Version</span>
                    <span class="chk-result"></span>
                </div>
                <div class="wtk-check-item" id="chk-memory">
                    <span class="chk-spinner">⏳</span>
                    <span class="chk-label">Memory Limit</span>
                    <span class="chk-result"></span>
                </div>
                <div class="wtk-check-item" id="chk-write">
                    <span class="chk-spinner">⏳</span>
                    <span class="chk-label">Upload Directory Writable</span>
                    <span class="chk-result"></span>
                </div>
            </div>
            <div id="wiz-check-msg" style="display:none;margin-top:16px"></div>
            <div class="wtk-wiz-foot">
                <button class="wtk-wiz-btn primary" id="wiz-next-1" disabled>Continue →</button>
            </div>
        </div>

        <!-- STEP 2: Configure -->
        <div class="wtk-wiz-panel" id="wiz-step-2">
            <h2>⚙️ Configure your settings</h2>
            <div class="wtk-wiz-form">

                <div class="wtk-wiz-field">
                    <label>Image Quality</label>
                    <div class="wtk-range-wrap">
                        <input type="range" id="wiz-quality" name="quality" min="30" max="95" value="82">
                        <span id="wiz-q-val">82%</span>
                    </div>
                    <p class="wtk-hint">75–85 is the sweet spot — great quality, small file.</p>
                </div>

                <div class="wtk-wiz-field">
                    <label>Target Max Size per Image</label>
                    <div class="wtk-wiz-inline">
                        <input type="number" id="wiz-maxkb" name="max_kb" min="10" max="500" value="100">
                        <span>KB</span>
                    </div>
                    <p class="wtk-hint">Plugin will reduce quality until the file is under this limit.</p>
                </div>

                <div class="wtk-wiz-toggle-row">
                    <div class="wtk-wiz-toggle-item">
                        <div>
                            <strong>Auto-Convert New Uploads</strong>
                            <p>Convert JPG/PNG to WebP when you upload</p>
                        </div>
                        <label class="wtk-toggle">
                            <input type="checkbox" id="wiz-auto" name="auto_webp" checked>
                            <span class="wtk-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="wtk-wiz-toggle-item">
                        <div>
                            <strong>Keep Original Files</strong>
                            <p>Keep JPG/PNG alongside the new WebP</p>
                        </div>
                        <label class="wtk-toggle">
                            <input type="checkbox" id="wiz-keep" name="keep_original" checked>
                            <span class="wtk-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="wtk-wiz-foot">
                <button class="wtk-wiz-btn ghost" id="wiz-back-2">← Back</button>
                <button class="wtk-wiz-btn primary" id="wiz-next-2">Save & Continue →</button>
            </div>
        </div>

        <!-- STEP 3: Done -->
        <div class="wtk-wiz-panel" id="wiz-step-3">
            <div class="wtk-wiz-success">
                <div class="wtk-success-icon">🎉</div>
                <h2>You're all set!</h2>
                <p>WP Turbo Kit is configured and ready to optimize your images.</p>
                <div class="wtk-wiz-recap" id="wiz-recap"></div>
                <button class="wtk-wiz-btn primary large" id="wiz-go-dashboard">Open Dashboard →</button>
            </div>
        </div>

    </div><!-- .wtk-wizard-box -->
</div><!-- .wtk-wizard-overlay -->
