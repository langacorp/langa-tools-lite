<?php
if(!defined('ABSPATH')) exit;
class LANGTOLI_WP_Improvements {
    const META_MENU_ORDER = '_langtoli_menu_order';
    const NONCE_ACTION    = 'langtoli_menu_order_save';
    const NONCE_NAME      = 'langtoli_nonce';

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_langtoli_save_menu_order', array($this, 'ajax_save_menu_order'));
        add_filter('custom_menu_order', '__return_true');
        add_filter('menu_order', array($this, 'apply_saved_menu_order'));

        // Frontend admin-bar toggle: CSS enqueued in <head>, HTML+JS in footer.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_toggle_css'));
        add_action('wp_footer', array($this, 'frontend_adminbar_toggle'));
        add_action('wp_ajax_langtoli_toggle_toolbar_front', array($this, 'ajax_toggle_toolbar_front'));
    }

    public function enqueue_admin_assets($hook) {
        $accent = '#f37f0d';
        if (function_exists('langa_credits_primary_color')) {
            $accent = langa_credits_primary_color();
        } else {
            $s = get_option('langa_tools_adminux_settings', array());
            if (is_array($s) && !empty($s['custom_login_color'])) $accent = (string)$s['custom_login_color'];
        }
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) $accent = '#f37f0d';
        $css = '/* Accent */
:root{ --langtoli-accent:' . esc_attr($accent) . '; }
#langtoli-menu-search-wrap{ padding:10px 10px 0; }
#langtoli-menu-search{ width:100%; box-sizing:border-box; padding:0px 5px; border:1px solid var(--langtoli-accent); border-radius:6px; background-color:#ffffff20; color:var(--langtoli-accent); }
#adminmenu .wp-menu-image { display: none; }
button#collapse-button { display: none!important; }
#adminmenu div.wp-menu-name{ padding:8px!important; display:flex; align-items:center; gap:8px; }
#adminmenu > li.menu-top .langtoli-number{white-space:nowrap; display:inline-flex; align-items:center; justify-content:center; font-weight:700; border:1.5px solid var(--langtoli-accent); color:var(--langtoli-accent); border-radius:50% !important; line-height:1; font-size:11px; padding:0; width:22px; height:22px; min-width:22px; min-height:22px; box-sizing:border-box; }
#adminmenu > li.menu-top.langtoli-draggable{ cursor:grab; }
#adminmenu > li.menu-top.langtoli-drag-over{ outline:2px dashed var(--langtoli-accent); }
#adminmenu{ user-select:none; -webkit-user-select:none; }';

        wp_register_style('langtoli-admin-style', false, array(), '1.2.7');
        wp_enqueue_style('langtoli-admin-style');
        wp_add_inline_style('langtoli-admin-style', $css);

        $nonce      = wp_create_nonce(self::NONCE_ACTION);
        $nonce_name = self::NONCE_NAME;
        $ajaxurl    = admin_url('admin-ajax.php');

        $script = '(function(){
  function el(tag, attrs, html){
    var e=document.createElement(tag);
    if(attrs){ Object.keys(attrs).forEach(function(k){ e.setAttribute(k, attrs[k]); }); }
    if(html!==undefined) e.innerHTML=html;
    return e;
  }

  function sanitize(str){
    return (str||\'\').toLowerCase().normalize(\'NFD\').replace(/\\p{Diacritic}/gu,\'\');
  }

  function getMenuSlugFromLi(li){
    var a = li && li.querySelector ? li.querySelector(\'a.menu-top\') : null;
    if(!a) return \'\';

    var raw = (a.getAttribute(\'href\') || \'\').trim();
    if(!raw || raw === \'#\') return \'\';

    raw = raw.replace(/&amp;/g,\'&\');

    var path = \'\';
    var search = \'\';
    try{
      var u = new URL(raw, window.location.origin);
      path = u.pathname || \'\';
      search = u.search || \'\';
    }catch(e){
      var qIdx = raw.indexOf(\'?\');
      path = qIdx > -1 ? raw.slice(0, qIdx) : raw;
      search = qIdx > -1 ? raw.slice(qIdx) : \'\';
    }

    var idx = path.indexOf(\'/wp-admin/\');
    if(idx > -1) path = path.slice(idx + 10);
    path = path.replace(/^\\//,\'\');

    if(path === \'admin.php\' && search){
      try{
        var params = new URLSearchParams(search);
        var page = params.get(\'page\');
        if(page) return page;
      }catch(e){}
    }

    return (path + search).replace(/^\\//,\'\');
  }

  function addMenuSearch(){
    var wrap=document.getElementById(\'adminmenuwrap\');
    if(!wrap || document.getElementById(\'langtoli-menu-search-wrap\')) return;

    var div=el(\'div\',{id:\'langtoli-menu-search-wrap\'});
    var input=el(\'input\',{id:\'langtoli-menu-search\',type:\'search\',placeholder:\'Search...\'});
    div.appendChild(input);
    wrap.insertBefore(div,wrap.firstChild);

    input.addEventListener(\'input\',function(){
      var q=sanitize(this.value);
      document.querySelectorAll(\'#adminmenu > li.menu-top\').forEach(function(li){
        var text=sanitize(li.innerText);
        li.style.display = (!q || text.indexOf(q)>-1) ? \'\' : \'none\';
      });
      renumberMenu();
    });
  }

  function renumberMenu(){
    var items=Array.from(document.querySelectorAll(\'#adminmenu > li.menu-top\'))
      .filter(function(li){ return li.style.display !== \'none\'; });

    var n=1;
    items.forEach(function(li){
      var a=li.querySelector(\'a.menu-top\'); if(!a) return;
      var name=a.querySelector(\'.wp-menu-name\'); if(!name) return;

      var badge=name.querySelector(\'.langtoli-number\');
      if(!badge){
        badge=el(\'span\',{class:\'langtoli-number\'});
        name.insertBefore(badge,name.firstChild);
      }
      badge.textContent = n++;
    });
  }

  function collectOrder(){
    var list=document.getElementById(\'adminmenu\');
    if(!list) return [];

    var order=[];
    Array.from(list.querySelectorAll(\':scope > li.menu-top\')).forEach(function(li){
      if(li.id === \'collapse-menu\') return;
      if(li.style.display === \'none\') return;
      var slug=getMenuSlugFromLi(li);
      if(slug) order.push(slug);
    });
    return order;
  }

  var saveTimer=null;
  function saveOrder(){
    var body=new FormData();
    body.append(\'action\',\'langtoli_save_menu_order\');
    body.append(\'' . esc_js($nonce_name) . '\',\'' . esc_js($nonce) . '\');
    body.append(\'order\', JSON.stringify(collectOrder()));

    fetch(\'' . esc_js($ajaxurl) . '\', {
      method:\'POST\',
      credentials:\'same-origin\',
      body:body
    }).catch(function(err){
      console.error(\'WPUI save failed\', err);
    });
  }
  function saveOrderDebounced(){
    if(saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(saveOrder, 200);
  }

  var langtoliDragging = false;
  document.addEventListener(\'click\', function(e){
    if(!langtoliDragging) return;
    e.preventDefault();
    e.stopPropagation();
  }, true);

  function enableDrag(){
    var list=document.getElementById(\'adminmenu\');
    if(!list) return;

    function getClosestMenuItem(y){
      var items = Array.from(list.querySelectorAll(\':scope > li.menu-top\'))
        .filter(function(li){ return li.id !== \'collapse-menu\' && li.style.display !== \'none\'; });

      var closest=null, closestDist=Infinity;
      items.forEach(function(li){
        var r=li.getBoundingClientRect();
        var mid=r.top + r.height/2;
        var dist=Math.abs(y - mid);
        if(dist < closestDist){ closestDist=dist; closest=li; }
      });
      return closest;
    }

    list.addEventListener(\'dragover\', function(ev){ ev.preventDefault(); });

    list.addEventListener(\'drop\', function(ev){
      ev.preventDefault();

      var draggingId = ev.dataTransfer.getData(\'text/plain\');
      var dragging = draggingId ? document.getElementById(draggingId) : null;
      if(!dragging) return;

      var target = getClosestMenuItem(ev.clientY);
      if(!target || target === dragging) return;

      var r = target.getBoundingClientRect();
      var before = (ev.clientY - r.top) < (r.height / 2);

      if(before) list.insertBefore(dragging, target);
      else list.insertBefore(dragging, target.nextSibling);

      renumberMenu();
      saveOrderDebounced();
    });

    Array.from(list.querySelectorAll(\':scope > li.menu-top\')).forEach(function(li){
      if(li.id === \'collapse-menu\') return;

      li.classList.add(\'langtoli-draggable\');
      li.setAttribute(\'draggable\',\'true\');

      li.addEventListener(\'dragstart\',function(ev){
        langtoliDragging = true;
        try{ ev.dataTransfer.effectAllowed = \'move\'; }catch(e){}
        ev.dataTransfer.setData(\'text/plain\', li.id);
      });

      li.addEventListener(\'dragend\',function(){
        setTimeout(function(){ langtoliDragging = false; }, 0);
        renumberMenu();
        saveOrderDebounced();
      });

      li.addEventListener(\'dragover\',function(ev){
        ev.preventDefault();
        li.classList.add(\'langtoli-drag-over\');
      });

      li.addEventListener(\'dragleave\',function(){
        li.classList.remove(\'langtoli-drag-over\');
      });

      li.addEventListener(\'drop\',function(ev){
        ev.preventDefault();
        li.classList.remove(\'langtoli-drag-over\');

        var draggingId = ev.dataTransfer.getData(\'text/plain\');
        var dragging = draggingId ? document.getElementById(draggingId) : null;
        if(!dragging || dragging===li) return;

        var r = li.getBoundingClientRect();
        var before = (ev.clientY - r.top) < (r.height / 2);

        if(before) list.insertBefore(dragging, li);
        else list.insertBefore(dragging, li.nextSibling);

        renumberMenu();
        saveOrderDebounced();
      });
    });
  }

  document.addEventListener(\'DOMContentLoaded\', function(){
    addMenuSearch();
    renumberMenu();
    enableDrag();
  });
})();
';

        wp_register_script('langtoli-admin', '', array(), '1.2.7', true);
        wp_enqueue_script('langtoli-admin');
        wp_add_inline_script('langtoli-admin', $script);
    }

    public function ajax_save_menu_order() {
        check_ajax_referer(self::NONCE_ACTION, self::NONCE_NAME);
        if (!current_user_can('manage_options')) {
            wp_send_json_error('perm');
        }

        $order = isset($_POST['order']) ? json_decode(sanitize_text_field(wp_unslash($_POST['order'])), true) : array();
        if (!is_array($order)) $order = array();

        update_user_meta(get_current_user_id(), self::META_MENU_ORDER, $order);
        wp_send_json_success(array('saved' => count($order)));
    }

    public function apply_saved_menu_order($order) {
        $saved = get_user_meta(get_current_user_id(), self::META_MENU_ORDER, true);
        if (!is_array($saved) || empty($saved)) return $order;

        global $menu;
        if (!is_array($menu)) return $order;

        $current_slugs = array();
        foreach ($menu as $pos => $item) {
            $slug = isset($item[2]) ? (string)$item[2] : '';
            if ($slug) $current_slugs[$slug] = $pos;
        }

        $final = array();
        foreach ($saved as $slug) {
            if (isset($current_slugs[$slug])) $final[] = $slug;
        }
        foreach (array_keys($current_slugs) as $slug) {
            if (!in_array($slug, $final, true)) $final[] = $slug;
        }

        return $final;
    }

    /**
     * Enqueue toggle-adminbar CSS in <head> (wp_enqueue_scripts).
     * Doing it here ensures the stylesheet is printed by wp_head().
     */
    public function enqueue_frontend_toggle_css() {
        if (!is_user_logged_in()) return;

        $ft_accent = '#f37f0d';
        if (function_exists('langa_credits_primary_color')) {
            $ft_accent = langa_credits_primary_color();
        } else {
            $ft_s = get_option('langa_tools_adminux_settings', array());
            if (is_array($ft_s) && !empty($ft_s['custom_login_color'])) {
                $ft_accent = (string) $ft_s['custom_login_color'];
            }
        }
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $ft_accent)) {
            $ft_accent = '#f37f0d';
        }

        $toggle_css = ':root{ --langtoli-accent:' . esc_attr($ft_accent) . '; }
            #langtoli-toggle-adminbar { position: fixed; left: 6px; z-index: 99999; bottom: 6px !important; }
            #langtoli-toggle-adminbar button { width:24px; height:24px; border-radius:50%; border:1px solid var(--langtoli-accent); background:transparent; color:var(--langtoli-accent); cursor:pointer; opacity:.65; padding:0; display:flex; align-items:center; justify-content:center; }
            #langtoli-toggle-adminbar .langtoli-icon{display:flex;align-items:center;justify-content:center;width:10px;height:10px;font-size:12px;line-height:1;}
            #langtoli-toggle-adminbar button:hover{ opacity:.9; }';

        wp_register_style('langtoli-toggle-adminbar', false, array(), '1.0'); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_enqueue_style('langtoli-toggle-adminbar');
        wp_add_inline_style('langtoli-toggle-adminbar', $toggle_css);
    }

    /**
     * Output toggle-adminbar HTML + JS in wp_footer.
     */
    public function frontend_adminbar_toggle() {
        if (!is_user_logged_in()) return;

        $nonce = wp_create_nonce('langtoli_toggle_toolbar_front');

        $showing = function_exists('is_admin_bar_showing') && is_admin_bar_showing();
        $arrow = $showing ? "\xE2\x86\x91" : "\xE2\x86\x93"; // ↑ or ↓
        ?>
        <div id="langtoli-toggle-adminbar" aria-live="polite">
            <button type="button" aria-label="Toggle admin bar"><span class="langtoli-icon"><?php echo esc_html($arrow); ?></span></button>
        </div>
        <?php
        wp_register_script('langtoli-toggle-adminbar', false, array(), '1.0', true); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
        wp_enqueue_script('langtoli-toggle-adminbar');
        wp_add_inline_script('langtoli-toggle-adminbar', '(function(){
            var btn=document.querySelector("#langtoli-toggle-adminbar button");
            if(!btn) return;
            btn.addEventListener("click", function(){
                var body=new FormData();
                body.append("action","langtoli_toggle_toolbar_front");
                body.append("_wpnonce","' . esc_js($nonce) . '");
                fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
                    method:"POST", credentials:"same-origin", body:body
                }).then(function(r){ return r.json(); })
                  .then(function(){ location.reload(); });
            });
        })();');
    }

    public function ajax_toggle_toolbar_front() {
        // Security: user must be logged in AND nonce must be valid. Both checks are required.
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'not_logged_in', 403 );
        }
        if ( ! check_ajax_referer( 'langtoli_toggle_toolbar_front', '_wpnonce', false ) ) {
            wp_send_json_error( 'forbidden', 403 );
        }

        $user_id = get_current_user_id();
        $current = get_user_option('show_admin_bar_front', $user_id);

        // Some environments may return boolean false or "0" instead of string "false".
        $is_hidden = ($current === 'false' || $current === false || $current === '0' || $current === 0);
        $new       = $is_hidden ? 'true' : 'false';

        // Persist using both APIs for maximum compatibility.
        update_user_meta($user_id, 'show_admin_bar_front', $new);
        update_user_option($user_id, 'show_admin_bar_front', $new, true);
        wp_cache_delete($user_id, 'user_meta');

        wp_send_json_success(array('show_admin_bar_front' => $new));
    }
}
