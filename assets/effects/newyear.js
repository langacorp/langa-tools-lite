// LANGA Tools Effect: newyear
// Canvas confetti + subtle particle trail on mouse move.
(function () {
  if (window.__langaEffectNewYear) return;
  window.__langaEffectNewYear = true;

  var d = document;
  var canvas = d.createElement('canvas');
  canvas.className = 'langa-effect-canvas langa-effect-newyear';
  var ctx = canvas.getContext('2d');
  if (!ctx) return;

  var w = 0, h = 0, raf = 0;
  var confetti = [];
  var trail = [];
  var maxTrail = 60;
  var lastX = null, lastY = null;

  function resize() {
    w = canvas.width = window.innerWidth;
    h = canvas.height = window.innerHeight;
  }

  function rand(min, max) {
    return min + Math.random() * (max - min);
  }

  function spawnConfettiBurst(x, y, count) {
    count = count || 140;
    for (var i = 0; i < count; i++) {
      confetti.push({
        x: x,
        y: y,
        vx: rand(-4.5, 4.5),
        vy: rand(-8, -2),
        g: rand(0.08, 0.18),
        r: rand(2, 5),
        a: 1,
        spin: rand(-0.25, 0.25),
        rot: rand(0, Math.PI * 2),
        hue: Math.floor(rand(0, 360))
      });
    }
  }

  function tick() {
    if (document.hidden) { raf = window.requestAnimationFrame(tick); return; }
    ctx.clearRect(0, 0, w, h);

    // Trail
    for (var i = trail.length - 1; i >= 0; i--) {
      var t = trail[i];
      t.a *= 0.93;
      t.r *= 0.98;
      if (t.a < 0.03) {
        trail.splice(i, 1);
        continue;
      }
      ctx.beginPath();
      ctx.fillStyle = 'rgba(255,255,255,' + t.a.toFixed(3) + ')';
      ctx.arc(t.x, t.y, t.r, 0, Math.PI * 2);
      ctx.fill();
    }

    // Confetti
    for (var j = confetti.length - 1; j >= 0; j--) {
      var c = confetti[j];
      c.vy += c.g;
      c.x += c.vx;
      c.y += c.vy;
      c.rot += c.spin;
      // fade after leaving viewport
      if (c.y > h + 60 || c.x < -60 || c.x > w + 60) c.a *= 0.96;
      if (c.a < 0.05) {
        confetti.splice(j, 1);
        continue;
      }
      ctx.save();
      ctx.translate(c.x, c.y);
      ctx.rotate(c.rot);
      ctx.fillStyle = 'hsla(' + c.hue + ', 90%, 60%, ' + c.a.toFixed(3) + ')';
      ctx.fillRect(-c.r, -c.r / 2, c.r * 2, c.r);
      ctx.restore();
    }

    raf = window.requestAnimationFrame(tick);
  }

  function onMove(e) {
    var x = e.clientX;
    var y = e.clientY;
    if (lastX === null) { lastX = x; lastY = y; }
    var dx = x - lastX;
    var dy = y - lastY;
    var dist = Math.sqrt(dx * dx + dy * dy);
    if (dist > 0.5) {
      trail.push({ x: x, y: y, r: Math.min(10, 2 + dist * 0.08), a: 0.22 });
      if (trail.length > maxTrail) trail.shift();
    }
    lastX = x; lastY = y;
  }

  function onClick(e) {
    spawnConfettiBurst(e.clientX, e.clientY, 180);
  }

  function init() {
    d.body.appendChild(canvas);
    resize();

    // initial subtle burst top center
    spawnConfettiBurst(w / 2, h * 0.2, 160);

    window.addEventListener('resize', resize);
    window.addEventListener('mousemove', onMove, { passive: true });
    window.addEventListener('click', onClick, { passive: true });
    raf = window.requestAnimationFrame(tick);
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', function(){try{init();}catch(e){if(window.console)console.warn('LANGA newyear effect:',e);}});
  } else {
    try{init();}catch(e){if(window.console)console.warn('LANGA newyear effect:',e);}
  }
})();
