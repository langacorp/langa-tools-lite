/*
 * LANGA Credits Bar [v2.4.3]
 *
 * iframe is EMPTY at page load (no src) — Blob URL stored in window._lcBlobUrl
 * open() sets iframe.src on first click. Subsequent opens reuse same URL.
 * close() blurs + dismissKeyboard. visualViewport keeps form above keyboard.
 */
(function () {
  'use strict';

  var langaButton, langaIframe, langaBottomBorder;
  var isOpen = false;
  var scrollY = 0;
  var vpHandler = null;
  var iframeLoaded = false;

  function lockScroll() {
    scrollY = window.pageYOffset || 0;
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.top = -scrollY + 'px';
    document.body.style.width = '100%';
  }

  function unlockScroll() {
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('position');
    document.body.style.removeProperty('top');
    document.body.style.removeProperty('width');
    window.scrollTo(0, scrollY);
  }

  function dismissKeyboard() {
    var tmp = document.createElement('input');
    tmp.setAttribute('readonly', 'readonly');
    tmp.style.cssText = 'position:fixed;top:-9999px;opacity:0;height:0;width:0;font-size:16px;';
    document.body.appendChild(tmp);
    tmp.focus();
    setTimeout(function() {
      tmp.blur();
      if (tmp.parentNode) tmp.parentNode.removeChild(tmp);
    }, 10);
  }

  function syncViewport() {
    if (!isOpen || !langaIframe) return;
    if (window.visualViewport) {
      var vpH = window.visualViewport.height;
      var vpOff = window.visualViewport.offsetTop;
      langaIframe.style.setProperty('height', vpH + 'px', 'important');
      langaIframe.style.setProperty('top', vpOff + 'px', 'important');
    }
  }

  function startViewportSync() {
    if (window.visualViewport && !vpHandler) {
      vpHandler = syncViewport;
      window.visualViewport.addEventListener('resize', vpHandler);
      window.visualViewport.addEventListener('scroll', vpHandler);
      syncViewport();
    }
  }

  function stopViewportSync() {
    if (window.visualViewport && vpHandler) {
      window.visualViewport.removeEventListener('resize', vpHandler);
      window.visualViewport.removeEventListener('scroll', vpHandler);
      vpHandler = null;
    }
    if (langaIframe) {
      langaIframe.style.setProperty('height', '100%', 'important');
      langaIframe.style.setProperty('top', '0', 'important');
    }
  }

  function showPanel() {
    langaIframe.classList.add('is-open');
    langaIframe.removeAttribute('tabindex');
    var off = (window.innerWidth <= 680) ? '120px' : '80px';
    langaButton.style.setProperty('bottom', off, 'important');
    langaBottomBorder.style.display = 'none';
    lockScroll();
    isOpen = true;
    startViewportSync();
  }

  function open() {
    if (!window._lcBlobUrl) return;
    if (!iframeLoaded) {
      // First open: load iframe content
      langaIframe.src = window._lcBlobUrl;
      langaIframe.onload = function() {
        langaIframe.onload = null;
        iframeLoaded = true;
        showPanel();
      };
      // Fallback if onload doesn't fire (some iOS versions)
      setTimeout(function() { if (!isOpen) { iframeLoaded = true; showPanel(); } }, 400);
    } else {
      showPanel();
    }
  }

  function close() {
    stopViewportSync();
    langaIframe.classList.remove('is-open');
    langaIframe.setAttribute('tabindex', '-1');
    try { langaIframe.contentWindow.document.activeElement.blur(); } catch(e){}
    langaIframe.blur();
    langaButton.style.setProperty('bottom', '0px', 'important');
    langaBottomBorder.style.display = '';
    unlockScroll();
    isOpen = false;
    dismissKeyboard();
  }

  function toggle() {
    if (isOpen) close(); else open();
  }

  function init() {
    if (document.getElementById('langa-button')) return; // already initialized
    langaIframe = document.getElementById('langa-credits-iframe');
    if (!langaIframe) return;

    langaButton = document.createElement('button');
    langaButton.textContent = 'Credits';
    langaButton.id = 'langa-button';
    langaButton.addEventListener('click', toggle);

    langaBottomBorder = document.createElement('div');
    langaBottomBorder.id = 'langa-bottom-border';

    var maxZ = 2147483000;
    document.querySelectorAll('body *').forEach(function(el) {
      var z = parseInt(window.getComputedStyle(el).zIndex, 10);
      if (z > maxZ) maxZ = z;
    });

    document.body.appendChild(langaButton);
    document.body.appendChild(langaBottomBorder);
    langaBottomBorder.style.zIndex = String(maxZ - 1);
    langaButton.style.zIndex = String(maxZ);

    window.addEventListener('message', function(e) {
      if (!e.data || typeof e.data.type !== 'string') return;
      if (e.data.type === 'langa-credits-done') {
        if (isOpen) close();
      }
    });
  }

  function waitForIframe() {
    if (document.getElementById('langa-credits-iframe')) {
      init();
    } else {
      var n = 0;
      var t = setInterval(function() {
        if (document.getElementById('langa-credits-iframe') || ++n > 100) {
          clearInterval(t);
          init();
        }
      }, 50);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', waitForIframe);
  } else {
    waitForIframe();
  }
})();
