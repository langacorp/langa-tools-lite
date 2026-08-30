(function () {
  if (window.__LANGA_SPRING_BOOT__) return;
  window.__LANGA_SPRING_BOOT__ = true;
  try {

  var wrapId = 'ltc-spring-wrap';
  if (document.getElementById(wrapId)) return;

  var wrap = document.createElement('div');
  wrap.id = wrapId;
  wrap.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:999999;overflow:hidden;';
  document.body.appendChild(wrap);

  var flowers = ['🌸'];

  function spawn() {
    if (document.hidden) return;
    if (wrap.childElementCount >= 20) return;
    var el = document.createElement('div');
    el.className = 'ltc-spring-float';
    el.textContent = flowers[Math.floor(Math.random()*flowers.length)];

    el.style.left = (Math.random()*100) + 'vw';
    el.style.animationDuration = (6 + Math.random()*6) + 's';
    el.style.fontSize = (18 + Math.random()*34) + 'px';
    wrap.appendChild(el);

    setTimeout(function(){ try{ el.remove(); }catch(e){} }, 13000);
  }

  spawn();
  setInterval(spawn, 1200);
  } catch(e) { if(window.console) console.warn('LANGA spring effect:', e); }
})();
