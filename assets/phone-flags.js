(function(w){
  'use strict';

  // Shared phone flags (inline SVG data URIs)
  // Used by: Forms, Maintenance, BC.
  // Extend this list if you add more countries to the selectors.
  var FLAGS = {
    IT: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2"><rect width="1" height="2" fill="%23009246"/><rect x="1" width="1" height="2" fill="%23fff"/><rect x="2" width="1" height="2" fill="%23ce2b37"/></svg>',
    CH: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" fill="%23d52b1e"/><rect x="13" y="6" width="6" height="20" fill="%23fff"/><rect x="6" y="13" width="20" height="6" fill="%23fff"/></svg>',
    FR: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2"><rect width="1" height="2" fill="%230052a5"/><rect x="1" width="1" height="2" fill="%23fff"/><rect x="2" width="1" height="2" fill="%23ef4135"/></svg>',
    DE: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 5 3"><rect width="5" height="1" y="0" fill="%23000"/><rect width="5" height="1" y="1" fill="%23dd0000"/><rect width="5" height="1" y="2" fill="%23ffce00"/></svg>',
    GB: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 30"><clipPath id="s"><path d="M0,0 v30 h60 v-30 z"/></clipPath><g clip-path="url(%23s)"><path d="M0,0 v30 h60 v-30 z" fill="%23012169"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="%23fff" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="%23C8102E" stroke-width="4"/><path d="M30,0 v30 M0,15 h60" stroke="%23fff" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="%23C8102E" stroke-width="6"/></g></svg>',
    US: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 19 10"><rect width="19" height="10" fill="%23b22234"/><g fill="%23fff"><rect y="1" width="19" height="1"/><rect y="3" width="19" height="1"/><rect y="5" width="19" height="1"/><rect y="7" width="19" height="1"/><rect y="9" width="19" height="1"/></g><rect width="8" height="7" fill="%233c3b6e"/></svg>',
    ES: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2"><rect width="3" height="2" fill="%23aa151b"/><rect y="0.5" width="3" height="1" fill="%23f1bf00"/></svg>',
    AE: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 6"><rect x="3" width="9" height="2" fill="%23009639"/><rect x="3" y="2" width="9" height="2" fill="%23fff"/><rect x="3" y="4" width="9" height="2" fill="%23000"/><rect width="3" height="6" fill="%23ce1126"/></svg>'
  };

  // Optional: expose map for other scripts
  w.LANGA_PHONE_FLAGS = FLAGS;

  function pickCountry(sel){
    try{
      var opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;
      var cc = opt ? (opt.getAttribute('data-country') || '') : '';
      if(!cc){ cc = (sel && sel.value) ? (sel.value + '') : ''; }
      return (cc || '').toUpperCase();
    }catch(e){
      return '';
    }
  }

  function applyFlag(sel){
    if(!sel) return;
    var cc = pickCountry(sel);
    var url = (cc && FLAGS[cc]) ? FLAGS[cc] : '';

    // 1) Robust overlay IMG (works even when browser/theme ignores select background)
    var wrap = sel.parentElement;
    if (wrap && !wrap.classList.contains('langa-phone-cc-wrap')) {
      // Wrap select into a container to place the image above it
      var wdiv = document.createElement('span');
      wdiv.className = 'langa-phone-cc-wrap';
      sel.parentElement.insertBefore(wdiv, sel);
      wdiv.appendChild(sel);
      wrap = wdiv;
    }

    var img = wrap ? wrap.querySelector('img.langa-phone-flag') : null;
    if (!img && wrap) {
      img = document.createElement('img');
      img.className = 'langa-phone-flag';
      img.setAttribute('alt', cc ? ('Bandiera ' + cc) : 'Bandiera');
      img.setAttribute('draggable', 'false');
      img.setAttribute('aria-hidden', 'true');
      wrap.insertBefore(img, sel);
    }

    if (img) {
      if (url) {
        img.src = url;
        img.style.display = 'block';
        img.alt = cc ? ('Bandiera ' + cc) : 'Bandiera';
      } else {
        img.removeAttribute('src');
        img.style.display = 'none';
      }
    }

    // 2) Keep legacy background-image as a best-effort fallback
    if (sel.style) {
      sel.style.backgroundImage = url ? ('url("' + url + '")') : '';
    }
  }

  function init(root){
    root = root || document;
    if(!root || !root.querySelectorAll) return;
    var list = root.querySelectorAll('select[data-phone-cc]');
    if(!list || !list.length) return;

    for(var i=0;i<list.length;i++){
      (function(sel){
        applyFlag(sel);
        sel.addEventListener('change', function(){ applyFlag(sel); });
      })(list[i]);
    }
  }

  w.langaPhoneFlagsInit = init;

  if(typeof document !== 'undefined' && document.addEventListener){
    document.addEventListener('DOMContentLoaded', function(){ init(document); });
  }
})(window);
