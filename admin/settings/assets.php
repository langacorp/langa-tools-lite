<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_admin_assets($hook){
  if (!is_admin()) return;
  $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
  if (strpos($page,'langa-tools-client') !== 0) return;

  // WP color picker (used by Visual Sitemap settings)
  wp_enqueue_style('wp-color-picker');
  wp_enqueue_script('wp-color-picker');

  $ver = defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '1.0.0';

  // Keep admin UI CSS inline (this is how older working versions behaved)
  // This avoids edge-cases where a theme/security plugin blocks plugin asset URLs.
  wp_register_style('langa-tools-client-admin-ui', false, array(), $ver);
  wp_enqueue_style('langa-tools-client-admin-ui');

  $css = '/* LANGA Tools PRO — admin design system (v1.6.0) */

  /*
   * ═══════════════════════════════════════════════
   *  Design Tokens (Apple-style)
   *
   *  --lt-text:      #1d1d1f    primary text
   *  --lt-text2:     #6e6e73    secondary text
   *  --lt-muted:     #86868b    muted/hint
   *  --lt-border:    #e5e5e7    default border
   *  --lt-border2:   #d2d2d7    stronger border
   *  --lt-bg:        #fafafa    surface bg
   *  --lt-bg2:       #f5f5f7    lighter surface
   *  --lt-card:      #fff       card bg
   *  --lt-accent:    #0071e3    link/focus
   *  --lt-green:     #1b5e20    success text
   *  --lt-green-bg:  #e8f5e9    success bg
   *  --lt-red:       #b71c1c    error text
   *  --lt-red-bg:    #fce4ec    error bg
   *  --lt-amber:     #e65100    warn text
   *  --lt-amber-bg:  #fff3e0    warn bg
   *  --lt-radius:    12px       card/panel radius
   *  --lt-radius-sm: 8px        button/input radius
   *  --lt-shadow:    0 1px 3px rgba(0,0,0,.04)
   * ═══════════════════════════════════════════════
   */

  /* ─── Typography baseline ─── */
  .wrap[class*="langa"],.wrap[class*="langa"] *:not(.dashicons):not([class*="dashicons"]){
    font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
    letter-spacing:-0.01em;
  }
  .wrap[class*="langa"] h1,.wrap[class*="langa"] h2,.wrap[class*="langa"] h3{font-weight:600}
  .wrap[class*="langa"] strong{font-weight:600}
  .wrap[class*="langa"] .description{color:#86868b;font-size:13px}

  /* ─── Layout fundamentals ─── */
  .langa-module-enable{background:#fafafa;border:1px solid #e5e5e7;border-radius:12px;padding:16px 20px;margin:14px 0 14px;max-width:965px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
  .langa-module-enable-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
  .langa-module-enable .title{font-weight:600;margin:0;color:#1d1d1f}
  .langa-module-enable-hint{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:10px;}
  .langa-module-enable .desc{margin:0;color:#6e6e73;}
  .langa-module-enable .button{margin-top:8px;}

  /* ─── Cards ─── */
  .langa-card{background:#fff;border:1px solid #e5e5e7;border-radius:12px;padding:20px 24px;margin-bottom:16px;max-width:965px;box-shadow:0 1px 3px rgba(0,0,0,.04);overflow-x:auto}
  .langa-card h2,.langa-card h3{margin-top:0;color:#1d1d1f}
  .langa-card .form-table{margin-top:0;table-layout:fixed;width:100%}
  .langa-card .form-table th{padding:16px 10px 16px 10px;vertical-align:top;color:#1d1d1f;font-weight:500;font-size:13px;white-space:normal;text-wrap-mode:wrap;width:160px}
  .langa-card .form-table td{padding:14px 10px;overflow:hidden;text-overflow:ellipsis}
  .langa-card .langa-card{border-radius:10px;border-color:#e5e5e7;padding:16px 20px;margin-bottom:12px;max-width:100%;box-shadow:none}
  /* Prevent inner content from pushing card wider */
  .langa-card input[type=text],.langa-card input[type=url],.langa-card input[type=email],.langa-card input[type=number],
  .langa-card textarea,.langa-card select,.langa-card pre,.langa-card code{max-width:100%;box-sizing:border-box}
  .langa-card .regular-text{max-width:100%}
  .langa-card pre{white-space:pre-wrap;word-break:break-all}

  /* ─── Tabs (underline style) ─── */
  .langa-nav-tabs{margin:12px 0 0;max-width:965px;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;scrollbar-width:thin}
  .langa-nav-tabs .nav-tab-wrapper{padding-top:6px;margin:0;border-bottom:1px solid #e5e5e7;display:flex;flex-wrap:nowrap;white-space:nowrap;min-width:max-content}
  .langa-nav-tabs .nav-tab-wrapper .nav-tab{
    border:0 !important;background:transparent !important;color:#6e6e73;font-weight:500;font-size:13px;
    padding:10px 16px;margin-bottom:-1px;border-bottom:2px solid transparent !important;transition:color .15s,border-color .15s;flex-shrink:0;
  }
  .langa-nav-tabs .nav-tab-wrapper .nav-tab:hover{color:#1d1d1f;border-bottom-color:#d2d2d7 !important}
  .langa-nav-tabs .nav-tab-wrapper .nav-tab-active{color:#1d1d1f !important;border-bottom-color:#1d1d1f !important;font-weight:600}
  .langa-tab-panel{border-top-left-radius:0 !important;border-top-right-radius:0 !important;margin-top:0 !important;overflow-x:auto;overflow-y:visible;max-width:100%;box-sizing:border-box}

  /* ─── Tables ─── */
  .form-table{table-layout:fixed;width:100%}
  .form-table th{padding:16px 10px 16px 10px;color:#1d1d1f;font-weight:500;font-size:13px;white-space:normal;text-wrap-mode:wrap;width:160px;vertical-align:top}
  .form-table td{padding:14px 10px;word-wrap:break-word;overflow-wrap:break-word}

  /* Module page outer wrapper: narrow label, wide content */
  .langa-module-wrap{table-layout:auto !important}
  .langa-module-wrap > tbody > tr > th,
  .langa-module-wrap > tr > th{width:250px !important;min-width:0;white-space:nowrap;padding-right:16px}
  .langa-module-wrap > tbody > tr > td,
  .langa-module-wrap > tr > td{width:auto !important}
  /* Inner tab panels: form-tables with fixed layout */
  .langa-module-wrap .langa-tab-panel{width:100%;box-sizing:border-box}
  .langa-module-wrap .langa-tab-panel .form-table{table-layout:fixed;width:100%}
  .langa-module-wrap .langa-tab-panel .form-table th{white-space:normal;text-wrap-mode:wrap;width:160px;min-width:0}
  .langa-module-wrap .langa-tab-panel .form-table td{overflow:hidden;text-overflow:ellipsis}
  /* Prevent overflow from inline max-width values */
  .langa-module-wrap .langa-tab-panel div[style*="max-width"]{max-width:100% !important}
  .langa-module-wrap input[type=text]:not(.langa-color-field):not(.wp-color-picker),.langa-module-wrap input[type=url],
  .langa-module-wrap input[type=email],.langa-module-wrap input[type=number],
  .langa-module-wrap textarea,.langa-module-wrap select,.langa-module-wrap .regular-text:not(.langa-color-field){width:100% !important;max-width:100% !important;box-sizing:border-box !important}
  .langa-module-wrap pre,.langa-module-wrap code{max-width:100%;overflow-x:auto;word-break:break-all}

  /* Global: all plugin inputs stay inside their container */
  .wrap[class*="langa"] input[type=text],.wrap[class*="langa"] input[type=url],
  .wrap[class*="langa"] input[type=email],.wrap[class*="langa"] input[type=number],
  .wrap[class*="langa"] textarea,.wrap[class*="langa"] select{box-sizing:border-box !important;max-width:100% !important}
  .wrap[class*="langa"] .form-table td{overflow:hidden;text-overflow:ellipsis}

  /* ─── Scroll table ─── */
  .langa-scroll-table{max-height:480px;overflow:auto;border:1px solid #e5e5e7;border-radius:12px;background:#fff;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
  .langa-scroll-table table{border:0;margin:0;max-width:none;width:100%;min-width:700px;}
  .langa-scroll-table thead th{position:sticky;top:0;z-index:2;background:#fafafa;box-shadow:0 1px 0 #e5e5e7;font-weight:600;font-size:12px;color:#6e6e73;text-transform:uppercase;letter-spacing:.03em;padding:10px 14px}
  .langa-scroll-table tbody td{padding:10px 14px;font-size:13px;border-bottom:1px solid #f5f5f7}
  .langa-scroll-table tbody tr:last-child td{border-bottom:0;}
  .langa-scroll-table tbody tr:hover{background:#fafafa}
  .langa-scroll-table--short{max-height:260px;}

  /* ─── Buttons ─── */
  .button.button-primary{
    background:#1d1d1f;border-color:#1d1d1f;color:#fff;text-shadow:none;box-shadow:none;border-radius:8px;font-weight:600;
  }
  .button.button-primary:hover,.button.button-primary:focus{
    background:#424245;border-color:#424245;box-shadow:0 1px 4px rgba(0,0,0,.15);
  }
  .submit .button-primary{border-radius:8px;padding:7px 24px;font-size:13px;font-weight:600}
  .button{border-radius:8px;transition:all .15s}

  /* ─── Inputs & Focus ─── */
  input[type=text],input[type=email],input[type=url],input[type=password],input[type=number],textarea,select{
    border-radius:8px;border:1px solid #d2d2d7;transition:border-color .15s,box-shadow .15s;
  }
  input[type=text]:focus,input[type=email]:focus,input[type=url]:focus,input[type=password]:focus,input[type=number]:focus,textarea:focus,select:focus{
    border-color:#0071e3;box-shadow:0 0 0 3px rgba(0,113,227,.12);outline:none;
  }

  /* ─── Checkboxes & Radio ─── */
  .langa-card input[type=checkbox],.langa-card input[type=radio],
  .form-table input[type=checkbox],.form-table input[type=radio]{
    border-radius:4px;border:1px solid #d2d2d7;width:16px;height:16px;margin-right:6px;
  }
  .form-table input[type=radio]{border-radius:50%}
  .langa-card input[type=checkbox]:checked,.form-table input[type=checkbox]:checked{background:#1d1d1f;border-color:#1d1d1f}

  /* ─── Helpers ─── */
  .langa-help{color:#6e6e73;}
  .langa-checkbox{margin:10px 0;}
  .langa-inline-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px;}
  .langa-inline-actions .button{margin:0;}
  .langa-muted{color:#86868b;}
  .langa-subtitle{margin:0 0 8px 0;font-weight:600;color:#1d1d1f}

  /* ─── Grid layout ─── */
  .langa-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;max-width:965px;}
  @media(max-width:900px){.langa-grid-2{grid-template-columns:1fr;}}
  .langa-row{display:grid;gap:16px 24px;max-width:965px;}
  .langa-row-8-4{grid-template-columns:2fr 1fr;}
  .langa-row-4-8{grid-template-columns:1fr 2fr;}
  .langa-row-6-6{grid-template-columns:1fr 1fr;}
  .langa-row-12{grid-template-columns:1fr;}
  @media(max-width:900px){.langa-row-8-4,.langa-row-4-8,.langa-row-6-6{grid-template-columns:1fr;}}
  .langa-field{display:flex;flex-direction:column;gap:6px;}
  .langa-field label{font-weight:600;color:#1d1d1f}
  .langa-field input[type=text],.langa-field input[type=email],.langa-field input[type=url]{width:100%;max-width:100%;}
  .langa-field--full{grid-column:1 / -1;}
  .langa-split-card{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  @media(max-width:900px){.langa-split-card{grid-template-columns:1fr;}}

  /* ─── Copy-to-clipboard ─── */
  .langa-code-wrap{display:inline-flex;align-items:center;gap:6px;}
  .langa-copy-code-btn{border:1px solid #e5e5e7;background:#fafafa;border-radius:8px;padding:0 7px;line-height:20px;height:22px;font-size:12px;cursor:pointer;transition:background .15s}
  .langa-copy-code-btn:hover{background:#fff;}
  #langa-copy-toast{position:fixed;right:18px;bottom:18px;z-index:99999;display:none;padding:10px 14px;border-radius:10px;border:1px solid #e5e5e7;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,0.1);font-weight:600;}
  #langa-copy-toast.is-show{display:block;}

  /* ─── 3rd-party notice displacement ─── */
  .langa-hero .notice,.langa-hero .e-notice,.langa-hero .updated,.langa-hero .error,.langa-hero .update-nag,
  .langa-setup-card .notice,.langa-setup-card .e-notice{display:none !important;}
  .langa-overview-wrap .notice:not(.langa-notice){display:none !important;}

  /* ─── Upload types grid ─── */
  .langa-mime-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:4px 14px;margin:6px 0 0;max-width:800px;}
  .langa-mime-grid label{display:flex;align-items:center;gap:6px;padding:3px 0;font-size:13px;}
  .langa-mime-group-title{font-size:11px;font-weight:600;color:#86868b;text-transform:uppercase;letter-spacing:.05em;margin:0 0 2px;}

  /* ═══ Utility Classes (inline style replacement) ═══ */

  /* Panels */
  .langa-panel{background:#fafafa;border:1px solid #e5e5e7;border-radius:12px;padding:16px 20px;margin:0 0 12px;max-width:720px;font-size:13px;line-height:1.6;color:#1d1d1f}
  .langa-panel--wide{max-width:965px}

  /* Cards inside grid rows must fill their column (not be limited by card max-width) */
  .langa-row .langa-card{max-width:100%}
  .langa-panel--compact{padding:12px 16px}
  .langa-panel--warn{background:#fffbeb;border-color:#fde68a;color:#92400e}
  .langa-panel--info{background:#f0f7ff;border-color:#bfdbfe;color:#1565c0}
  .langa-panel--error{background:#fef2f2;border-color:#fecaca;color:#b71c1c}

  /* Stat boxes */
  .langa-stat{text-align:center;padding:12px 18px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px;min-width:100px}
  .langa-stat strong{font-size:24px;font-weight:600;color:#1d1d1f;letter-spacing:-0.02em;display:block}
  .langa-stat span{font-size:11px;color:#86868b;margin-top:2px;text-transform:uppercase;letter-spacing:.04em}

  /* Inline badges */
  .langa-badge{display:inline-block;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:600;letter-spacing:.02em}
  .langa-badge--ok{background:#e8f5e9;color:#1b5e20}
  .langa-badge--warn{background:#fff3e0;color:#e65100}
  .langa-badge--fail{background:#fce4ec;color:#b71c1c}
  .langa-badge--info{background:#e3f2fd;color:#1565c0}
  .langa-badge--neutral{background:#f5f5f7;color:#6e6e73}

  /* Event type badges */
  .langa-ev-badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap}

  /* Section labels */
  .langa-section-label{font-size:11px;font-weight:600;color:#86868b;text-transform:uppercase;letter-spacing:.05em}

  /* Preview image frame */
  .langa-img-frame{border:1px solid #e5e5e7;border-radius:12px;padding:10px;background:#fff;display:inline-block}
  .langa-img-frame img{display:block;max-width:220px;height:auto;border-radius:8px}

  /* Health status */
  .langa-status-ok{color:#1b5e20;font-weight:600}
  .langa-status-fail{color:#b71c1c;font-weight:600}

  /* Lock/disabled overlay */
  .langa-lock-overlay{position:relative}
  .langa-lock-overlay::after{content:"";position:absolute;inset:0;background:rgba(255,255,255,.6);backdrop-filter:blur(2px);border-radius:12px;z-index:1;pointer-events:all}

  /* WP footer fix */
  #wpfooter{position:relative !important}

  ';

  wp_add_inline_style('langa-tools-client-admin-ui', $css);

  // Media uploader for fields like “Default share image”.
  if (function_exists('wp_enqueue_media')) {
    wp_enqueue_media();
  }

  // IMPORTANT: attach our init to wp-color-picker itself (most reliable)
  // so the pickers always initialize even if our custom handle is filtered.
  $picker_boot = '(function($){
  function initPickers(root){
    root = root || $(document);
    if (!$.fn.wpColorPicker) return;
    root.find(\'.langa-color-field\').each(function(){
      var $f = $(this);
      if ($f.closest(\'.wp-picker-container\').length) return;
      try { $f.wpColorPicker(); } catch(e) {}
    });
  }
  $(function(){ initPickers($(document)); });
  // Re-init after tab switches / dynamic panels
  $(document).on(\'click\', \'.nav-tab, .nav-tab-wrapper a, .langa-nav-tabs a\', function(){
    setTimeout(function(){ initPickers($(document)); }, 60);
  });
  $(document).on(\'langa_tools_client_refresh\', function(){ initPickers($(document)); });
})(jQuery);';
  wp_add_inline_script('wp-color-picker', $picker_boot);

  // Admin helper handle (media uploader, reset colors, etc.)
  wp_register_script('langa-tools-client-admin-ui-js', false, array('jquery'), $ver, true);
  wp_enqueue_script('langa-tools-client-admin-ui-js');

  $js = '(function($){
  var frame, currentTarget;

  // Media uploader (supports multiple targets, robust URL extraction)
  $(document).on(\'click\',\'.langa-media-upload\',function(e){
    e.preventDefault();
    if (typeof wp === \'undefined\' || !wp.media) return;
    var target = $(this).data(\'target\');
    if(!target) return;

    currentTarget = target;

    // Create once, reuse
    if(!frame){
      frame = wp.media({title:\'Select image\',button:{text:\'Use this image\'},multiple:false});
      frame.on(\'select\',function(){
        try{
          var att = frame.state().get(\'selection\').first().toJSON();
          var url = \'\';
          if(att){
            url = att.url || att.source_url || (att.sizes && att.sizes.full && att.sizes.full.url) || (att.guid && att.guid.rendered) || \'\';
          }
          if(url && currentTarget){
            $(currentTarget).val(url).trigger(\'input\').trigger(\'change\');
          }
        }catch(err){
          console.error(\'Media uploader error:\', err);
        }
      });
    }

    frame.open();
  });


  // Reset colors (scoped) — multi-strategy selector
  $(document).on(\'click\', \'.langa-reset-colors\', function(e){
    e.preventDefault();
    var btn = $(this);
    var defsRaw = btn.attr(\'data-defaults\') || \'\';
    if (!defsRaw) return;
    var ok = window.confirm(\'Reset to default colors?\');
    if (!ok) return;
    var defs = null;
    try { defs = JSON.parse(defsRaw); } catch(err){ defs = null; }
    if (!defs || typeof defs !== \'object\') return;

    var scope = btn.data(\'style-scope\') || \'\';
    var root = btn.closest(\'.langa-style-scope\');
    if (!root.length) root = btn.closest(\'.langa-card, .postbox, form\');
    if (!root.length) root = $(document);

    $.each(defs, function(key, val){
      var $field = $();
      // Strategy 1: scoped [name*="[scope][key]"] (works for adminux[maintenance_style][key])
      if (scope) {
        $field = root.find(\'[name*="[\'+scope+\'][\'+key+\']"]\').first();
      }
      // Strategy 2: key-only [name*="[key]"] within root (works for legal[key], bc[main][style][key])
      if (!$field.length) {
        $field = root.find(\'[name*="[\'+key+\']"]\').first();
      }
      // Strategy 3: name ending with [key] or name starts with scope[key]
      if (!$field.length && scope) {
        $field = root.find(\'[name^="\'+scope+\'[\'+key+\']"]\').first();
      }
      if (!$field.length) return;

      if ($field.hasClass(\'langa-color-field\') && $.fn.wpColorPicker) {
        var wrap = $field.closest(\'.wp-picker-container\');
        if (val === \'\' || val === null) {
          // CLEAR: pure DOM manipulation. Do NOT call iris/wpColorPicker
          // because iris(\'color\',\'#ffffff\') sets the value back synchronously.
          $field[0].value = \'\';
          if (wrap.length) {
            wrap.find(\'.wp-color-result\').css(\'background-color\',\'transparent\');
            wrap.find(\'.wp-color-result-text\').text(\'Select Color\');
            // Also clear the iris UI element if visible
            wrap.find(\'.iris-picker\').hide();
          }
          // Re-assert empty after any pending iris events
          setTimeout(function(){ $field[0].value = \'\'; }, 10);
        } else {
          try { $field.wpColorPicker(\'color\', val); } catch(e2){ $field.val(val).trigger(\'change\'); }
        }
      } else {
        $field.val(val === null ? \'\' : val).trigger(\'change\');
      }
    });
    btn.text(\'\\u2713 Reset applicato\');
    setTimeout(function(){ btn.text(\'Reset stile\'); }, 1500);
  });

  // -------------------------
  // UI/UX → Preloader presets (confirm + apply)
  // -------------------------

  // -------------------------
  // UI/UX → Replace
  // - Media Replace picker (wp.media modal)
  // - Text replace: Advanced toggle + confirm visibility
  // -------------------------
  function getExt(name){
    name = (name||\'\').toString().toLowerCase();
    var m = name.match(/\\.([a-z0-9]+)$/);
    return m ? m[1] : \'\';
  }

  function mrSetError(msg){
    var box = $(\'#langa-mr-error\');
    if(!box.length) return;
    if(!msg){ box.hide().find(\'p\').text(\'\'); return; }
    box.show().find(\'p\').text(msg);
  }

  function mrUpdateSelected(att){
    if(!att) return;
    var id = att.id || 0;
    var url = att.url || \'\';
    var filename = att.filename || (url ? url.split(\'/\').pop() : \'\');
    var ext = getExt(filename) || getExt(url);
    $(\'#langa-mr-attachment-id\').val(id);
    $(\'#langa-mr-expected-ext\').val(ext);

    var isImg = (att.type === \'image\') || (att.mime && att.mime.indexOf(\'image/\')===0);
    $(\'#langa-mr-is-image\').val(isImg ? \'1\' : \'0\');
    var html = \'\';
    html += \'<div class="langa-card" id="langa-mr-selected-inner" data-ext="\'+(ext||\'\')+\'" data-id="\'+id+\'" data-url="\'+(url||\'\')+\'" data-isimg="\'+(isImg?1:0)+\'" style="padding:14px;">\';
    html += \'<div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">\';
    if(isImg && url){
      html += \'<div style="border:1px solid #d2d2d7;border-radius:12px;padding:10px;background:#fff;">\';
      html += \'<img src="\'+url+\'" alt="" style="display:block;max-width:220px;height:auto;border-radius:8px;" />\';
      html += \'</div>\';
    }
    html += \'<div style="min-width:320px;flex:1;">\';
    html += \'<div><strong>ID:</strong> \'+id+\'</div>\';
    if(url){ html += \'<div style="margin-top:6px;"><strong>URL:</strong> <code style="word-break:break-all;">\'+url+\'</code></div>\'; }
    if(filename){ html += \'<div style="margin-top:6px;"><strong>File:</strong> <code style="word-break:break-all;">\'+filename+\'</code></div>\'; }
    if(ext){ html += \'<div style="margin-top:6px;"><strong>Estensione:</strong> <code>.\'+ext+\'</code></div>\'; }
    html += \'<div style="margin-top:10px;">\';
    if(att.editLink){ html += \'<a class="button button-secondary" href="\'+att.editLink+\'">Open media</a> \'; }
    html += \'<button type="button" class="button button-link-delete" id="langa-mr-clear">Reset</button>\';
    html += \'</div>\';
    html += \'</div>\';
    html += \'</div>\';
    html += \'</div>\';

    $(\'#langa-mr-selected\').html(html);
    $(\'#langa-mr-upload\').show();
    $(\'#langa-mr-file\').val(\'\');
    $(\'#langa-mr-do\').prop(\'disabled\', false);
    mrSetError(\'\');
  }

  // Open media modal
  $(document).on(\'click\',\'#langa-mr-pick\',function(e){
    e.preventDefault();
    if(typeof wp===\'undefined\' || !wp.media) return;
    var mrFrame = wp.media({ title:\'Select media to replace\', button:{ text:\'Use this media\' }, multiple:false });
    mrFrame.on(\'select\', function(){
      var sel = mrFrame.state().get(\'selection\').first();
      if(!sel) return;
      mrUpdateSelected(sel.toJSON());
    });
    mrFrame.open();
  });

  // Clear selection
  $(document).on(\'click\',\'#langa-mr-clear\',function(e){
    e.preventDefault();
    $(\'#langa-mr-attachment-id\').val(\'0\');
    $(\'#langa-mr-expected-ext\').val(\'\');
    $(\'#langa-mr-is-image\').val(\'0\');
    $(\'#langa-mr-selected\').html(\'<div style="margin:0;max-width:965px;padding:1px 12px;background:#f0f6fc;border:1px solid #c3c4c7;border-left:4px solid #72aee6;border-radius:0"><p style="margin:10px 12px;">Select a media item using the <strong>Select media</strong> button to continue.</p></div>\');
    $(\'#langa-mr-upload\').hide();
    $(\'#langa-mr-file\').val(\'\');
    mrSetError(\'\');
  });

  // Extension validation
  $(document).on(\'change\',\'#langa-mr-file\',function(){
    var expected = ($(\'#langa-mr-expected-ext\').val() || \'\').toLowerCase();
    var isImg = ($(\'#langa-mr-is-image\').val() || \'\') === \'1\';
    var file = this.files && this.files[0] ? this.files[0] : null;
    if(!file){ mrSetError(\'\'); return; }
    var got = getExt(file.name);
    if(expected && got && expected !== got){
      if(isImg){
        mrSetError(\'Note: different extension (. \'+got+\'). For images it will be converted to .\'+expected+\' keeping the same URL.\');
        $(\'#langa-mr-do\').prop(\'disabled\', false);
        return;
      }
      mrSetError(\'Estensione non valida: atteso .\'+expected+\' ma hai scelto .\'+got+\'.\');
      $(\'#langa-mr-do\').prop(\'disabled\', true);
      return;
    }
    mrSetError(\'\');
    $(\'#langa-mr-do\').prop(\'disabled\', false);
  });

  // Text replace: process UX (preview/apply)
  function trSyncConfirm(){
    var dry = $(\'input[name="replace_dry_run"]\').is(\':checked\');
    var $c = $(\'#langa-tr-confirm\');
    var $btn = $(\'#langa-tr-run\');
    var $mode = $(\'#langa-tr-mode\');

    if($mode.length){
      $mode.text(dry ? \'Preview\' : \'Apply\');
    }

    if($btn.length){
      $btn.text(dry ? \'Run preview\' : \'Apply replace\');
    }

    if($c.length){
      if(dry){ $c.hide(); } else { $c.show(); }
    }

    // When applying changes, require explicit ack + token before enabling the action.
    if($btn.length){
      if(dry){
        $btn.prop(\'disabled\', false);
      } else {
        var ack = $(\'input[name="replace_ack"]\').is(\':checked\');
        var token = ($(\'input[name="replace_confirm"]\').val() || \'\').trim();
        $btn.prop(\'disabled\', !(ack && token === \'REPLACE\'));
      }
    }
  }
  $(document).on(\'click\',\'#langa-tr-advanced-toggle\',function(e){
    e.preventDefault();
    $(\'#langa-tr-advanced\').toggle();
  });
  $(document).on(\'change\',\'input[name="replace_dry_run"]\',function(){
    trSyncConfirm();
  });
  $(document).on(\'change keyup\',\'input[name="replace_ack"], input[name="replace_confirm"]\',function(){
    trSyncConfirm();
  });
  $(function(){
    // If initial selection exists (loaded via ?media_id=), set expected ext.
    var inner = $(\'#langa-mr-selected-inner\');
    if(inner.length){
      var ext = inner.attr(\'data-ext\') || \'\';
      $(\'#langa-mr-expected-ext\').val(ext);
      var isImg = (inner.attr(\'data-isimg\') || \'0\') === \'1\';
      $(\'#langa-mr-is-image\').val(isImg ? \'1\' : \'0\');
      $(\'#langa-mr-upload\').show();
    }
    trSyncConfirm();
  });
  // -------------------------
  // Copy-to-clipboard for <code>[shortcode]</code>
  // -------------------------
  var copyToastTimer = null;

  function ensureCopyToast(){
    var $t = $(\'#langa-copy-toast\');
    if ($t.length) return $t;
    $(\'body\').append(\'<div id="langa-copy-toast" role="status" aria-live="polite">Copiato ✓</div>\');
    return $(\'#langa-copy-toast\');
  }

  function showCopyToast(msg){
    var $t = ensureCopyToast();
    $t.text(msg || \'Copiato ✓\').addClass(\'is-show\');
    if (copyToastTimer) window.clearTimeout(copyToastTimer);
    copyToastTimer = window.setTimeout(function(){ $t.removeClass(\'is-show\'); }, 1400);
  }

  function copyTextToClipboard(text){
    text = (text || \'\').toString();
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function(){
        showCopyToast(\'Copiato ✓\');
      }).catch(function(){
        fallbackCopy(text);
      });
    } else {
      fallbackCopy(text);
    }
  }

  function fallbackCopy(text){
    var $ta = $(\'<textarea readonly></textarea>\').val(text).css({position:\'fixed\',left:\'-9999px\',top:\'-9999px\'});
    $(\'body\').append($ta);
    $ta[0].select();
    try {
      document.execCommand(\'copy\');
      showCopyToast(\'Copiato ✓\');
    } catch(e) {
      showCopyToast(\'Copia non riuscita\');
    }
    $ta.remove();
  }

  function enhanceShortcodeCodes(root){
    root = root || document;
    $(root).find(\'code\').each(function(){
      var $c = $(this);
      if ($c.data(\'langaCopyReady\')) return;
      var txt = ($c.text() || \'\').trim();
      if (!txt) return;
      // Only match classic shortcodes: [xxx] or [xxx attr=".."]
      if (!/^\\[[^\\]]+\\]$/.test(txt)) return;
      $c.data(\'langaCopyReady\', 1);

      // Wrap only once
      if (!$c.parent().hasClass(\'langa-code-wrap\')) {
        $c.wrap(\'<span class="langa-code-wrap"></span>\');
      }

      // Add button
      var $btn = $(\'<button type="button" class="langa-copy-code-btn" title="Copia" aria-label="Copia">⧉</button>\');
      $btn.on(\'click\', function(e){
        e.preventDefault();
        e.stopPropagation();
        copyTextToClipboard(txt);
      });
      $c.after($btn);
    });
  }

  $(function(){
    enhanceShortcodeCodes(document);
  });

  // Re-scan after tab switches
  $(document).on(\'click\', \'.nav-tab-wrapper a, .langa-nav-tabs a, .nav-tab\', function(){
    setTimeout(function(){ enhanceShortcodeCodes(document); }, 90);
  });


  // BC UI handled by admin/modules/bc-ui.php (v2)

})(jQuery);';
  wp_add_inline_script('langa-tools-client-admin-ui-js', $js);

  // -------------------------
  // BC: Admin UI assets (tabs + media pickers)
  // Important: admin/modules/bc-ui.php is included AFTER admin_enqueue_scripts,
  // so enqueue here (timing-safe).
  // -------------------------
  if ($page === 'langa-tools-client-bc') {
    wp_register_style('langa-tools-client-bc-admin', LANGA_TOOLS_CLIENT_URL . 'assets/bc-admin.css', array(), $ver);
    wp_enqueue_style('langa-tools-client-bc-admin');

	    $bc_css = '/* BC admin UI (match other modules) */
	      #wpfooter{position:inherit !important;}
	      .lbc-staff-row{border-top:1px dashed #d2d2d7;margin-top:12px;padding-top:12px;}
	      .lbc-media-row{display:flex;gap:10px;align-items:center;}
	      .lbc-media-row input{flex:1;}
	      .langa-card input[type="text"]:not(.langa-color-field):not(.wp-color-picker), .langa-card input[type="url"], .langa-card input[type="email"], .langa-card textarea, .langa-card select, .lbc-staff-extra-link-row{width:100% !important;max-width:100%;}
	      .lbc-langlist{display:flex;gap:10px;flex-wrap:wrap;}
	      .lbc-langlist label{font-weight:600;display:flex;align-items:center;gap:6px;}
	      .lbc-row-actions{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-top:10px;}
	    ';
    wp_add_inline_style('langa-tools-client-bc-admin', $bc_css);

    // Media is needed for logo/badge pickers.
    if (function_exists('wp_enqueue_media')) {
      wp_enqueue_media();
    }

    wp_register_script('langa-tools-client-bc-admin-js', LANGA_TOOLS_CLIENT_URL . 'assets/bc-admin.js', array('jquery','wp-color-picker'), $ver, true);
    wp_enqueue_script('langa-tools-client-bc-admin-js');

	    $bc_js = '(function($){
  // ------- Media helpers -------
  function openMediaSingle(cb){
    if (typeof wp === \'undefined\' || !wp.media) return;
    var frame = wp.media({ title:\'Select image\', button:{ text:\'Use this image\' }, multiple:false });
    frame.on(\'select\', function(){
      var att = frame.state().get(\'selection\').first();
      if (!att) return;
      var a = att.toJSON();
      if (a && a.url) cb(a.url);
    });
    frame.open();
  }

  function openMediaMulti(cb){
    if (typeof wp === \'undefined\' || !wp.media) return;
    var frame = wp.media({ title:\'Select images\', button:{ text:\'Add\' }, multiple:true });
    frame.on(\'select\', function(){
      var urls = [];
      frame.state().get(\'selection\').each(function(att){
        var a = att.toJSON();
        if (a && a.url) urls.push(a.url);
      });
      cb(urls);
    });
    frame.open();
  }

  // Pick media for URL inputs
  $(document).on(\'click\', \'.lbc-pick-media\', function(e){
    e.preventDefault();
    var target = $(this).data(\'target\');
    if (!target) return;
    openMediaSingle(function(url){
      $(\'#\'+target).val(url).trigger(\'change\');
    });
  });

  // ------- Staff add/remove -------
  $(document).on(\'click\', \'#lbc-add-staff\', function(e){
    e.preventDefault();
    var $wrap = $(\'#lbc-staff-wrap\');
    var idx = $wrap.children(\'.lbc-staff-row\').length;
    var tpl = $(\'#lbc-staff-template\').html();
    if (!tpl) return;
    tpl = tpl.replace(/__IDX__/g, idx);
    $wrap.append(tpl);
  });

  $(document).on(\'click\', \'.lbc-remove-staff\', function(e){
    e.preventDefault();
    if (!confirm(\'Sei sicuro?\')) return;
    $(this).closest(\'.lbc-staff-row\').remove();
  });

  // ------- Extra links (per Staff) -------
  function nextExtraIdx($container){
    var max = -1;
    $container.find(\'input[name*="[extra_links]"][name$="[label]"]\').each(function(){
      var name = $(this).attr(\'name\') || \'\';
      var m = name.match(/extra_links\\]\\[(\\d+)\\]/);
      if (m && m[1]) max = Math.max(max, parseInt(m[1], 10));
    });
    return max + 1;
  }

  $(document).on(\'click\', \'.lbc-add-staff-extra-link\', function(e){
    e.preventDefault();
    var $btn = $(this);
    var sidx = $btn.data(\'sidx\');
    var $row = $btn.closest(\'.lbc-staff-row\');
    var $container = $row.find(\'.lbc-staff-extra-links\').first();
    if (!$container.length) return;

    if (typeof sidx === \'undefined\' || sidx === \'\') {
      var n = $row.find(\'input[name^="bc[staff]"]\').first().attr(\'name\') || \'\';
      var mm = n.match(/bc\\[staff\\]\\[(\\d+)\\]/);
      if (mm && mm[1]) sidx = mm[1];
    }
    if (typeof sidx === \'undefined\') return;

    var idx = nextExtraIdx($container);
    var tpl = $(\'#tmpl-lbc-staff-extra-link\').html();
    if (!tpl) return;
    tpl = tpl.replace(/__SIDX__/g, sidx).replace(/__LIDX__/g, idx);
    $container.append(tpl);
  });

  $(document).on(\'click\', \'.lbc-remove-staff-extra-link\', function(e){
    e.preventDefault();
    if (!confirm(\'Sei sicuro?\')) return;
    $(this).closest(\'.lbc-staff-extra-link-row\').remove();
  });

  // ------- Gallery (Main + Staff) -------
  function galleryAdd($list, namePrefix, urls){
    urls.forEach(function(u){
      var safe = String(u).replace(/"/g, \'&quot;\');
      var row = \'\'
        + \'<div class="lbc-gallery-item" style="display:flex;align-items:center;gap:10px;margin:8px 0;">\'
        + \'<input type="hidden" name="\'+namePrefix+\'[]" value="\'+safe+\'">\'
        + \'<img src="\'+u+\'" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #d2d2d7;">\'
        + \'<code style="font-size:11px;opacity:.75;max-width:480px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">\'+u+\'</code>\'
        + \'<button type="button" class="button button-link-delete lbc-remove-gallery">Remove</button>\'
        + \'</div>\';
      $list.append(row);
    });
  }

  $(document).on(\'click\', \'.lbc-add-gallery-main\', function(e){
    e.preventDefault();
    var $list = $(\'#lbc-gallery-main-list\');
    if (!$list.length) return;
    openMediaMulti(function(urls){
      if (!urls || !urls.length) return;
      galleryAdd($list, \'bc[main][gallery_images]\', urls);
    });
  });

  $(document).on(\'click\', \'.lbc-add-gallery-staff\', function(e){
    e.preventDefault();
    var $btn = $(this);
    var sidx = $btn.data(\'sidx\');
    var $row = $btn.closest(\'.lbc-staff-row\');
    var $list = $row.find(\'.lbc-gallery-staff-list\').first();
    if (!$list.length) return;

    if (typeof sidx === \'undefined\' || sidx === \'\') {
      var n = $row.find(\'input[name^="bc[staff]"]\').first().attr(\'name\') || \'\';
      var mm = n.match(/bc\\[staff\\]\\[(\\d+)\\]/);
      if (mm && mm[1]) sidx = mm[1];
    }
    if (typeof sidx === \'undefined\') return;

    openMediaMulti(function(urls){
      if (!urls || !urls.length) return;
      galleryAdd($list, \'bc[staff][\'+sidx+\'][gallery_images]\', urls);
    });
  });

  $(document).on(\'click\', \'.lbc-remove-gallery\', function(e){
    e.preventDefault();
    if (!confirm(\'Sei sicuro?\')) return;
    $(this).closest(\'.lbc-gallery-item\').remove();
  });

})(jQuery);';

    wp_add_inline_script('langa-tools-client-bc-admin-js', $bc_js);
  }
}

/** Minimal CSS for check/toggle clarity (admin only). */
function langa_tools_client_admin_head_css() {
  if (!current_user_can('manage_options')) return;
  $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
  if (strpos($page, 'langa-tools-client') !== 0) return;
  langtoli_inline_style('.form-table .description{display:inline-block;max-width:720px;}.notice{padding:8px 12px;}.langa-status-badge{display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;font-weight:600;font-size:12px;margin:6px 0 8px 0;}.langa-status-badge.langa-on{background:#e8f5e9;color:#1b5e20;}.langa-status-badge.langa-off{background:#fce4ec;color:#b71c1c;}.langa-card{position:relative;}.langa-lock-overlay{position:absolute;inset:0;background:rgba(255,255,255,.6);backdrop-filter:saturate(120%) blur(2px);z-index:5;cursor:not-allowed;border-radius:12px;display:flex;align-items:center;justify-content:center;}.langa-lock-overlay .dashicons{font-size:34px;width:34px;height:34px;}.langa-locked-hint{display:flex;align-items:center;gap:6px;margin:0 0 10px 0;padding:10px 14px;border:1px solid #fde68a;background:#fffbeb;border-radius:12px;max-width:965px;font-size:13px;color:#92400e;}');
}

/**
 * Google Translate widget — renders in admin_footer on LANGA pages.
 * Overview: placed in Quick Links slot. Module pages: fixed bottom-right.
 */
function langa_tools_client_admin_translate_widget() {
  if (!current_user_can('manage_options')) return;
  $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
  if (strpos($page, 'langa-tools-client') !== 0) return;

  // On Overview/Settings: show widget (goes into slot or floats)
  // On module pages: load script (so translation persists) but hide the widget
  $show_pages = array('langa-tools-client', 'langa-tools-client-settings');
  $hide = !in_array($page, $show_pages, true);
  $hide_style = $hide ? 'display:none !important;' : '';
  ?>
  <div id="langa-translate-wrap" style="position:fixed;top:36px;right:16px;z-index:9999;display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #e5e5e7;border-radius:8px;padding:4px 10px;box-shadow:0 2px 8px rgba(0,0,0,.08);font-size:12px;<?php echo esc_attr($hide_style); ?>">
    <span class="dashicons dashicons-translation" style="font-size:16px;width:16px;height:16px;color:#6e6e73;"></span>
    <div id="google_translate_element"></div>
  </div>
<?php langtoli_inline_style('/* Clean up Google Translate widget appearance */
    #langa-translate-wrap .goog-te-gadget{font-size:0 !important;font-family:inherit !important;}
    #langa-translate-wrap .goog-te-gadget .goog-te-combo{
      font-size:12px !important;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,sans-serif !important;
      border:none !important;background:transparent !important;padding:2px 4px !important;
      cursor:pointer !important;color:#1d1d1f !important;outline:none !important;
      -webkit-appearance:none;appearance:none;
    }
    #langa-translate-wrap .goog-te-gadget > span{display:none !important;}
    #langa-translate-wrap .goog-logo-link{display:none !important;}
    /* Hide Google Translate top bar */
    .goog-te-banner-frame{display:none !important;}
    body{top:0 !important;}
    /* When placed in Quick Links slot — inline, no fixed position */
    #langa-translate-slot #langa-translate-wrap{
      position:static !important;box-shadow:none !important;border:0 !important;
      padding:0 !important;background:transparent !important;display:inline-flex !important;
    }
    /* Adjust for WP admin bar on mobile */
    @media screen and (max-width:782px){
      #langa-translate-wrap{top:50px;right:8px;padding:3px 8px;}
    }'); ?>
<?php langtoli_inline_script('function googleTranslateElementInit(){
      new google.translate.TranslateElement({
        pageLanguage:\'en\',
        includedLanguages:\'en,it,de,fr,es,pt,nl,pl,ro,ja,zh-CN,ko,ar,ru,tr\',
        layout:google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay:false
      },\'google_translate_element\');
      // If Quick Links slot exists (Overview page), move widget there
      setTimeout(function(){
        var wrap=document.getElementById(\'langa-translate-wrap\');
        var slot=document.getElementById(\'langa-translate-slot\');
        if(wrap && slot){
          slot.appendChild(wrap);
        } else if(wrap){
          // Module pages: reposition to bottom-right
          wrap.style.top=\'auto\';
          wrap.style.bottom=\'12px\';
          wrap.style.right=\'16px\';
        }
      },300);
      // Mark WP admin chrome as notranslate
      var skip=[\'wpadminbar\',\'adminmenumain\',\'adminmenuback\',\'adminmenuwrap\',\'wpfooter\',\'screen-meta\',\'screen-meta-links\'];
      skip.forEach(function(id){var el=document.getElementById(id);if(el)el.classList.add(\'notranslate\');});
    }'); ?>
  <?php
  // Google Translate — loaded as external service (see readme.txt "External Services")
  wp_enqueue_script(
    'google-translate-element',
    'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit',
    array(),
    null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external service
    true
  );
  ?>
  <?php
}
