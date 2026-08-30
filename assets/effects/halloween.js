(function () {
  if (window.__LANGA_HALLOWEEN_BOOT__) return;
  window.__LANGA_HALLOWEEN_BOOT__ = true;

  function enable() {
    if (document.getElementById('ltc-hallow-wrap')) return;

    var wrap = document.createElement('div');
    wrap.id = 'ltc-hallow-wrap';
    wrap.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:999999;overflow:hidden;';
    document.body.appendChild(wrap);

    var emojis = ['👻','🦇','🎃'];

    function spawn() {
      if (document.hidden) return;
      if (wrap.childElementCount >= 20) return;
      var el = document.createElement('div');
      el.className = 'ltc-hallow-float';
      el.textContent = emojis[Math.floor(Math.random()*emojis.length)];

      el.style.left = (Math.random() * 100) + 'vw';
      el.style.animationDuration = (4 + Math.random() * 5) + 's';
      el.style.fontSize = (18 + Math.random() * 34) + 'px';
      wrap.appendChild(el);

      setTimeout(function () { try { el.remove(); } catch (e) {} }, 11000);
    }

    spawn();
    setInterval(spawn, 1800);
  }

  window.LANGA_HALLOWEEN_ENABLE = enable;

  document.addEventListener('DOMContentLoaded', function () {
    try { enable(); } catch (e) {}
  });
})();
