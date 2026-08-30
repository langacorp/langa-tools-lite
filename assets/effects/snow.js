(function(){
  if (window.__LANGA_SNOW_BOOT__) return;
  window.__LANGA_SNOW_BOOT__ = true;
  try {
    var MAX_FLAKES = 80;
    var container = document.createElement('div');
    container.className = 'snow-container';
    document.body.appendChild(container);

    for (var i = 0; i < MAX_FLAKES; i++) {
      var flake = document.createElement('span');
      flake.className = 'snowflake';
      flake.textContent = '\u2744';
      flake.style.left = (Math.random() * 100) + 'vw';
      flake.style.animationDelay = (Math.random() * 10).toFixed(2) + 's';
      flake.style.animationDuration = (8 + Math.random() * 12).toFixed(2) + 's';
      flake.style.fontSize = (8 + Math.random() * 18).toFixed(1) + 'px';
      var drift = (Math.random() * 40 - 20).toFixed(1) + 'vw';
      flake.style.setProperty('--x-move', drift);
      var opacity = (0.3 + Math.random() * 0.6).toFixed(2);
      flake.style.setProperty('--opacity', opacity);
      container.appendChild(flake);
    }
  } catch(e) { if(window.console) console.warn('LANGA snow effect:', e); }
})();
