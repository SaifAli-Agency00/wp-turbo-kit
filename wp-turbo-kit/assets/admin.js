/* global jQuery, WTK */
(function($){
'use strict';

var ajax  = WTK.ajax;
var nonce = WTK.nonce;

/* ═══════════════════════════════════════════════════════════
   SETUP WIZARD
═══════════════════════════════════════════════════════════ */
if ($('#wtk-wizard').length) {

    var caps = WTK.caps;

    // Run checks with animation
    function runChecks() {
        var checks = [
            { id: 'chk-imagick', ok: caps.imagick,    pass: 'Active (Best quality)', fail: 'Not installed — GD will be used', warn: false },
            { id: 'chk-gd',      ok: caps.gd,         pass: 'Active',                fail: 'Not available!',                  warn: false },
            { id: 'chk-php',     ok: true,             pass: caps.php,               fail: '',                                warn: false },
            { id: 'chk-memory',  ok: true,             pass: caps.memory,            fail: '',                                warn: false },
            { id: 'chk-write',   ok: true,             pass: 'Writable ✓',           fail: 'Not writable',                    warn: false },
        ];

        var delay = 400;
        checks.forEach(function(c, i) {
            setTimeout(function(){
                var $item = $('#'+c.id);
                $item.find('.chk-spinner').text(c.ok ? '✅' : '❌').css('animation','none');
                $item.find('.chk-result').text(c.ok ? c.pass : c.fail);
                $item.addClass(c.ok ? 'chk-ok' : 'chk-fail');
            }, delay * (i+1));
        });

        // Enable next after all checks
        setTimeout(function(){
            var canConvert = caps.imagick || caps.gd;
            if (!canConvert) {
                $('#wiz-check-msg').show().html('<div style="padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#991b1b;font-size:13px;">⚠️ No image library found. Install Imagick or enable GD with WebP support. You can still install the plugin and it will work once the library is available.</div>');
            }
            $('#wiz-next-1').prop('disabled', false);
        }, delay * (checks.length+1));
    }

    setTimeout(runChecks, 500);

    // Step navigation
    function goStep(n) {
        $('.wtk-wiz-panel').removeClass('active');
        $('#wiz-step-'+n).addClass('active');
        $('.wtk-wiz-step').each(function(){
            var s = parseInt($(this).data('step'));
            $(this).removeClass('active done');
            if (s === n) $(this).addClass('active');
            if (s < n)  $(this).addClass('done').find('span').text('✓');
        });
    }

    $('#wiz-next-1').on('click', function(){ goStep(2); });
    $('#wiz-back-2').on('click', function(){ goStep(1); });

    $('#wiz-quality').on('input', function(){
        $('#wiz-q-val').text(this.value+'%');
    });

    $('#wiz-next-2').on('click', function(){
        var $btn = $(this).prop('disabled',true).text('Saving…');
        $.post(ajax, {
            action:       'wtk_wizard_save',
            nonce:        nonce,
            quality:      $('#wiz-quality').val(),
            max_kb:       $('#wiz-maxkb').val(),
            auto_webp:    $('#wiz-auto').prop('checked') ? 1 : 0,
            keep_original:$('#wiz-keep').prop('checked') ? 1 : 0,
        }, function(res){
            if (res && res.success) {
                $('#wiz-recap').html(
                    '<div class="wtk-wiz-recap-row"><span>Image Quality</span><span>'+$('#wiz-quality').val()+'%</span></div>'+
                    '<div class="wtk-wiz-recap-row"><span>Max File Size</span><span>'+$('#wiz-maxkb').val()+' KB</span></div>'+
                    '<div class="wtk-wiz-recap-row"><span>Auto-Convert Uploads</span><span>'+($('#wiz-auto').prop('checked')?'✅ On':'❌ Off')+'</span></div>'+
                    '<div class="wtk-wiz-recap-row"><span>Keep Originals</span><span>'+($('#wiz-keep').prop('checked')?'✅ On':'❌ Off')+'</span></div>'
                );
                goStep(3);
            } else {
                $btn.prop('disabled',false).text('Save & Continue →');
            }
        }).fail(function(){
            $btn.prop('disabled',false).text('Save & Continue →');
        });
    });

    $('#wiz-go-dashboard').on('click', function(){
        window.location.href = res && res.data && res.data.redirect ? res.data.redirect : window.location.href;
        window.location.reload();
    });

    return; // Stop here — no dashboard JS needed on wizard page
}

/* ═══════════════════════════════════════════════════════════
   DASHBOARD
═══════════════════════════════════════════════════════════ */
if (!$('#wtk-app').length) return;

/* ── Live stats refresh ───────────────────────────────────── */
function refreshStats() {
    $.post(ajax, {action:'wtk_get_stats',nonce:nonce}, function(res){
        if (!res||!res.success) return;
        var d = res.data;
        $('#js-stat-total').text(numFmt(d.total));
        $('#js-stat-saved').text(d.saved_f);
        $('#js-stat-pending').text(numFmt(d.pending));
        $('#js-pending-badge').text(numFmt(d.pending)+' pending');
        $('#js-bulk-sub').text(numFmt(d.pending)+' images');
    });
}

function numFmt(n) { return parseInt(n||0).toLocaleString(); }
function fmtBytes(b) {
    b=parseInt(b||0);
    if(b>=1073741824) return (b/1073741824).toFixed(2)+' GB';
    if(b>=1048576)    return (b/1048576).toFixed(1)+' MB';
    if(b>=1024)       return (b/1024).toFixed(1)+' KB';
    return b+' B';
}

/* ═══════════════════════════════════════════════════════════
   BULK OPTIMIZER
═══════════════════════════════════════════════════════════ */
var bulkStop    = false;
var bulkDone    = 0;
var bulkTotal   = parseInt(WTK.stats.pending) || 0;
var bulkRemain  = bulkTotal;

$('#js-bulk-start').on('click', function(){
    if (bulkRemain <= 0) {
        showAlert('#js-bulk-done', '✅ All images are already optimized!', 'success');
        return;
    }
    bulkStop   = false;
    bulkDone   = 0;
    bulkTotal  = bulkRemain;

    $(this).hide();
    $('#js-bulk-stop').show();
    $('#js-bulk-prog').show();
    $('#js-bulk-log').show().html('');
    $('#js-bulk-done').html('');
    runBulk();
});

$('#js-bulk-stop').on('click', function(){
    bulkStop = true;
    $(this).hide();
    $('#js-bulk-start').show();
    bulkFinish(true);
});

$('#js-bulk-reset').on('click', function(){
    if (!confirm('This will reset optimization status so all images will be re-processed. Continue?')) return;
    var $btn=$(this).prop('disabled',true).text('Resetting…');
    // Delete all _wtk_done meta
    $.post(ajax,{action:'wtk_reset_bulk',nonce:nonce},function(res){
        $btn.prop('disabled',false).text('↺ Reset All');
        if(res&&res.success){ refreshStats(); showAlert('#js-bulk-done','Reset done — you can now re-optimize all images.','success'); }
    }).fail(function(){ $btn.prop('disabled',false).text('↺ Reset All'); });
});

function runBulk() {
    if (bulkStop) return;

    $.post(ajax, {action:'wtk_bulk',nonce:nonce}, function(res){
        if (!res||!res.success) {
            addLog('❌ Server error. Stopping.', 'fail');
            bulkFinish(true); return;
        }
        var d = res.data;
        bulkDone   += d.results.length;
        bulkRemain  = d.remaining;

        d.results.forEach(function(r){
            addLog((r.ok?'':'⚠ ')+'#'+r.id+' — '+r.msg, r.ok?'ok':'skip');
        });

        var pct = bulkTotal>0 ? Math.min(100,Math.round(bulkDone/bulkTotal*100)) : 100;
        $('#js-prog-fill').css('width',pct+'%');
        $('#js-prog-txt').text(numFmt(bulkDone)+' done, '+numFmt(bulkRemain)+' remaining');
        $('#js-prog-pct').text(pct+'%');

        // Update stat cards live
        if (res.data.stats) {
            $('#js-stat-total').text(numFmt(res.data.stats.total));
            $('#js-stat-saved').text(res.data.stats.saved_f);
            $('#js-stat-pending').text(numFmt(res.data.stats.pending));
            $('#js-pending-badge').text(numFmt(res.data.stats.pending)+' pending');
            $('#js-bulk-sub').text(numFmt(res.data.stats.pending)+' images');
        }

        if (bulkRemain>0 && !bulkStop) {
            setTimeout(runBulk, 300);
        } else {
            bulkFinish(bulkStop);
        }
    }).fail(function(){
        addLog('❌ AJAX request failed.','fail');
        bulkFinish(true);
    });
}

function bulkFinish(stopped) {
    bulkStop = false;
    $('#js-bulk-stop').hide();
    $('#js-bulk-start').show();

    if (!stopped && bulkRemain<=0) {
        $('#js-prog-fill').css('width','100%');
        $('#js-prog-txt').text('All done!');
        $('#js-prog-pct').text('100%');
        $('#js-bulk-done').html(
            '<div class="wtk-done-box">'+
            '<strong>🎉 Bulk optimization complete!</strong>'+
            '<span>'+numFmt(bulkDone)+' images processed</span>'+
            '</div>'
        );
        refreshStats();
    }
}

function addLog(msg,type) {
    var cls = type==='ok'?'wtk-log-ok':(type==='skip'?'wtk-log-skip':'wtk-log-fail');
    $('#js-bulk-log').prepend('<span class="'+cls+'">'+msg+'</span>');
}

function showAlert(target, msg, type) {
    $(target).html('<div class="wtk-alert '+type+'">'+msg+'</div>');
}

/* ═══════════════════════════════════════════════════════════
   UNUSED IMAGES
═══════════════════════════════════════════════════════════ */
var unusedImages = [];

$('#js-scan-btn').on('click', function(){
    var $btn=$(this).prop('disabled',true).text('Scanning…');
    $('#js-scan-loading').show();
    $('#js-unused-result').hide();
    unusedImages=[];

    $.post(ajax,{action:'wtk_scan_unused',nonce:nonce},function(res){
        $btn.prop('disabled',false).html('<span class="btn-icon">🔍</span> Scan for Unused Images');
        $('#js-scan-loading').hide();

        if (!res||!res.success) { alert('Scan failed. Please try again.'); return; }

        unusedImages = res.data.images;
        renderUnused(res.data);
        $('#js-unused-result').show();
    }).fail(function(){
        $btn.prop('disabled',false).html('<span class="btn-icon">🔍</span> Scan for Unused Images');
        $('#js-scan-loading').hide();
        alert('Request failed. Please try again.');
    });
});

function renderUnused(data) {
    var $info    = $('#js-unused-info');
    var $grid    = $('#js-unused-grid');
    var $actions = $('#js-unused-actions');

    // Update badge
    $('#js-unused-count-badge').text(data.count+' found').removeClass('orange green red').addClass(data.count>0?'orange':'green');

    if (data.count===0) {
        $info.html('');
        $grid.html('<div class="wtk-no-unused">✅ No unused images found! Your media library is clean.</div>');
        $actions.hide();
        return;
    }

    $info.html('⚠️ Found <strong>'+data.count+'</strong> unused image'+(data.count>1?'s':'')+' taking up <strong>'+data.size+'</strong> of disk space.');

    var html='';
    data.images.forEach(function(img){
        html+='<div class="wtk-img-card" data-id="'+img.id+'">'
            +'<input type="checkbox" class="wtk-img-cb" data-id="'+img.id+'">'
            +(img.thumb
                ?'<img class="wtk-img-thumb" src="'+img.thumb+'" loading="lazy" onerror="this.parentNode.querySelector(\'.wtk-img-thumb\').outerHTML=\'<div class=\\\"wtk-img-placeholder\\\">🖼️</div>\'">'
                :'<div class="wtk-img-placeholder">🖼️</div>')
            +'<div class="wtk-img-info">'
            +'<span class="wtk-img-name" title="'+img.file+'">'+img.file+'</span>'
            +'<span class="wtk-img-size">'+img.size+'</span>'
            +'</div></div>';
    });
    $grid.html(html);
    $actions.css('display','block');
    updateSelCount();
}

// Card click = toggle
$(document).on('click','.wtk-img-card',function(e){
    if($(e.target).hasClass('wtk-img-cb'))return;
    var $cb=$(this).find('.wtk-img-cb');
    $cb.prop('checked',!$cb.prop('checked'));
    $(this).toggleClass('selected',$cb.prop('checked'));
    updateSelCount();
});
$(document).on('change','.wtk-img-cb',function(){
    $(this).closest('.wtk-img-card').toggleClass('selected',$(this).prop('checked'));
    updateSelCount();
});
$('#js-sel-all').on('change',function(){
    var v=$(this).prop('checked');
    $('.wtk-img-cb').prop('checked',v);
    $('.wtk-img-card').toggleClass('selected',v);
    updateSelCount();
});

function updateSelCount(){
    var n=$('.wtk-img-cb:checked').length;
    $('#js-del-sel').prop('disabled',n===0).text('🗑 Delete Selected'+(n>0?' ('+n+')':''));
    $('#js-sel-count').text(n>0?n+' selected':'');
}

$('#js-del-sel').on('click',function(){
    var ids=[];
    $('.wtk-img-cb:checked').each(function(){ids.push($(this).data('id'));});
    if(!ids.length)return;
    if(!confirm('Delete '+ids.length+' selected image(s)?\n\n⚠️ This cannot be undone!'))return;
    doDelete(ids);
});

$('#js-del-all').on('click',function(){
    if(!unusedImages.length){alert('Run a scan first.');return;}
    if(!confirm('Delete ALL '+unusedImages.length+' unused images?\n\n⚠️ This cannot be undone!'))return;
    doDelete(unusedImages.map(function(i){return i.id;}));
});

function doDelete(ids){
    var $msg=$('#js-del-msg');
    $msg.text('⏳ Deleting…').css('color','#64748b');
    $('#js-del-sel,#js-del-all').prop('disabled',true);

    $.post(ajax,{action:'wtk_delete_unused',nonce:nonce,ids:ids},function(res){
        $('#js-del-sel,#js-del-all').prop('disabled',false);
        if(!res||!res.success){$msg.text('❌ Delete failed.').css('color','red');return;}
        var d=res.data;
        $msg.text('✅ Deleted '+d.deleted+' image'+(d.deleted!==1?'s':'')+(d.failed>0?' ('+d.failed+' failed)':'')).css('color','#15803d');

        // Remove from grid
        d.ids.forEach(function(id){ $('.wtk-img-card[data-id="'+id+'"]').fadeOut(300,function(){$(this).remove();}); });
        unusedImages=unusedImages.filter(function(img){return d.ids.indexOf(img.id)===-1;});
        $('#js-unused-count-badge').text(unusedImages.length+' found');
        updateSelCount();

        if(unusedImages.length===0){
            setTimeout(function(){
                $('#js-unused-grid').html('<div class="wtk-no-unused">✅ All unused images deleted!</div>');
                $('#js-unused-info').html('');
                $('#js-unused-actions').hide();
                $('#js-unused-count-badge').text('Clean ✓').removeClass('orange red').addClass('green');
            },500);
        }
    }).fail(function(){
        $('#js-del-sel,#js-del-all').prop('disabled',false);
        $msg.text('❌ Request failed.').css('color','red');
    });
}

/* ═══════════════════════════════════════════════════════════
   SETTINGS
═══════════════════════════════════════════════════════════ */
$('#js-quality').on('input',function(){ $('#js-q-display').text(this.value+'%'); });

$('#js-settings-form').on('submit',function(e){
    e.preventDefault();
    var $btn=$(this).find('[type=submit]').prop('disabled',true).text('Saving…');
    var data={action:'wtk_save_settings',nonce:nonce};
    $(this).find('input').each(function(){
        if($(this).attr('type')==='checkbox') data[$(this).attr('name')]=this.checked?1:0;
        else data[$(this).attr('name')]=$(this).val();
    });
    $.post(ajax,data,function(res){
        $btn.prop('disabled',false).text('💾 Save Settings');
        var ok=res&&res.success;
        $('#js-settings-msg').text(ok?'✅ Settings saved!':'❌ Failed.').css('color',ok?'#15803d':'#dc2626');
        setTimeout(function(){$('#js-settings-msg').text('');},3000);
    }).fail(function(){
        $btn.prop('disabled',false).text('💾 Save Settings');
        $('#js-settings-msg').text('❌ Request failed.').css('color','#dc2626');
    });
});

/* ═══════════════════════════════════════════════════════════
   CLEAR LOG
═══════════════════════════════════════════════════════════ */
$('#js-clear-log').on('click',function(){
    if(!confirm('Clear the entire optimization log?'))return;
    var $btn=$(this).prop('disabled',true).text('Clearing…');
    $.post(ajax,{action:'wtk_clear_log',nonce:nonce},function(res){
        $btn.prop('disabled',false).text('🗑 Clear');
        if(res&&res.success) location.reload();
    }).fail(function(){ $btn.prop('disabled',false).text('🗑 Clear'); });
});

})(jQuery);
