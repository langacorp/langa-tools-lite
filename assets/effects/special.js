(function () {
  if (window.__LANGA_SPECIAL_BOOT__) return;
  window.__LANGA_SPECIAL_BOOT__ = true;

  var MAX_PIECES = 60;

  function ensureWrap() {
    var wrap = document.getElementById('ltc-confetti-wrap');
    if (wrap) return wrap;
    wrap = document.createElement('div');
    wrap.id = 'ltc-confetti-wrap';
    wrap.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:999999;overflow:hidden;';
    document.body.appendChild(wrap);
    return wrap;
  }

  function startConfetti() {
    var wrap = ensureWrap();
    if (wrap.__langa_confetti_started) return;
    wrap.__langa_confetti_started = true;

    var colors = ['#ff0', '#f0f', '#0ff', '#f00', '#ffd700', '#fff', '#00ff7f'];

    function createPiece() {
      if (document.hidden) return;
      if (wrap.childElementCount >= MAX_PIECES) return;
      var c = document.createElement('div');
      c.style.position = 'absolute';
      var size = (Math.random() * 10 + 5);
      c.style.width = c.style.height = size + 'px';
      c.style.background = colors[Math.floor(Math.random() * colors.length)];
      c.style.top = '-20px';
      c.style.left = (Math.random() * 100) + 'vw';
      c.style.opacity = (0.3 + Math.random() * 0.7);
      c.style.borderRadius = (Math.random() < 0.5) ? '2px' : '50%';
      var duration = (Math.random() * 3 + 2);
      c.style.transition = 'transform ' + duration + 's linear, opacity ' + duration + 's';
      wrap.appendChild(c);
      setTimeout(function () {
        c.style.transform = 'translate(' + (Math.random() * 100 - 50) + 'px, 110vh) rotate(' + (Math.random() * 360) + 'deg)';
        c.style.opacity = '0';
      }, 10);
      setTimeout(function () { try { c.remove(); } catch (e) {} }, (duration + 0.5) * 1000);
    }

    setInterval(createPiece, 160);
  }

  // Particle trail following the mouse (lightweight)
  function startTrail() {
    if (window.__LANGA_SPECIAL_TRAIL__) return;
    window.__LANGA_SPECIAL_TRAIL__ = true;

    var canvas = document.createElement('canvas');
    canvas.id = 'langa-special-trail';
    canvas.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:999998;';
    document.body.appendChild(canvas);
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    var w = 0, h = 0;
    function resize() {
      w = canvas.width = window.innerWidth;
      h = canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    var particles = [];
    var mouse = { x: w / 2, y: h / 2 };
    window.addEventListener('mousemove', function (e) {
      mouse.x = e.clientX;
      mouse.y = e.clientY;
      for (var i = 0; i < 2; i++) {
        particles.push({
          x: mouse.x,
          y: mouse.y,
          vx: (Math.random() - 0.5) * 1.5,
          vy: (Math.random() - 0.5) * 1.5,
          life: 40 + Math.random() * 20,
          r: 1 + Math.random() * 2.5,
        });
      }
      if (particles.length > 600) particles.splice(0, particles.length - 600);
    }, { passive: true });

    function tick() {
      if (document.hidden) { requestAnimationFrame(tick); return; }
      ctx.clearRect(0, 0, w, h);
      ctx.globalCompositeOperation = 'lighter';
      for (var i = particles.length - 1; i >= 0; i--) {
        var p = particles[i];
        p.x += p.vx;
        p.y += p.vy;
        p.life -= 1;
        if (p.life <= 0) { particles.splice(i, 1); continue; }
        var a = Math.min(1, p.life / 50);
        ctx.fillStyle = 'rgba(255, 215, 0, ' + (a * 0.6) + ')';
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
      }
      ctx.globalCompositeOperation = 'source-over';
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  window.LANGA_SPECIAL_ENABLE = function () {
    startConfetti();
    startTrail();
  };

  document.addEventListener('DOMContentLoaded', function () {
    try { window.LANGA_SPECIAL_ENABLE(); } catch (e) { if (window.console) console.warn('LANGA special effect:', e); }
  });
})();