(function () {
  if (window.__LANGA_EASTER_BOOT__) return;
  window.__LANGA_EASTER_BOOT__ = true;

  const STATE = {
    enabled: false,
    rafGrass: null,
    rafRabbits: null,
    units: [],
    blades: [],
    can: null,
    ctx: null,
    wrapper: null,
    back: null,
    front: null,
    clicked: false,
    m: { x: 0, y: 0 },
    STRIP_H: 150,
    NUM_BLADES: 320,
    substeps: 1,
    RABBIT_BOTTOM: -2,
    dragging: null,
    lastPointer: { x: 0, y: 0, t: 0 }
  };

  const vw = () => Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
  const vh = () => Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
  const dis = (x, y, x2, y2) => Math.hypot(x2 - x, y2 - y);
  const randFrom = (min, max) => Math.random() * (max - min) + min;

  function ensureRoot() {
    let root = document.getElementById("langa-easter");
    if (!root) {
      root = document.createElement("div");
      root.id = "langa-easter";
      root.setAttribute("aria-hidden", "true");
      document.body.appendChild(root);
    } else if (root.parentElement !== document.body) {
      document.body.appendChild(root);
    }
    STATE.wrapper = root;
  }

  function ensureEl(id, tag = "div") {
    let el = document.getElementById(id);
    if (!el) {
      el = document.createElement(tag);
      el.id = id;
      el.setAttribute("aria-hidden", "true");
      STATE.wrapper.appendChild(el);
    } else if (el.parentElement !== STATE.wrapper) {
      STATE.wrapper.appendChild(el);
    }
    return el;
  }

  let curveArray = [];
  function curveVertex(px, py, context, active) {
    curveArray.push({ x: px, y: py });
    const length = curveArray.length;
    if (length === 4) {
      const tan1 = { x: (curveArray[2].x - curveArray[0].x) / 4, y: (curveArray[2].y - curveArray[0].y) / 4 };
      const tan2 = { x: (curveArray[1].x - curveArray[3].x) / 4, y: (curveArray[1].y - curveArray[3].y) / 4 };
      context.bezierCurveTo(
        curveArray[1].x + tan1.x, curveArray[1].y + tan1.y,
        curveArray[2].x + tan2.x, curveArray[2].y + tan2.y,
        curveArray[2].x, curveArray[2].y
      );
      curveArray.shift();
    } else if (length === 2) {
      context.moveTo(curveArray[1].x, curveArray[1].y);
    }
    if (active === false) curveArray = [];
  }

  function Vector(x = 0, y = 0) {
    this.x = x; this.y = y;
    this.mag = () => dis(0, 0, this.x, this.y);
    this.add = (v) => { this.x += v.x; this.y += v.y; };
    this.mult = (n) => { this.x *= n; this.y *= n; };
    this.div = (n) => { this.x /= n; this.y /= n; };
    this.makeMag = (m) => { const mg = this.mag(); if (mg) { this.div(mg); this.mult(m); } };
    this.get = () => new Vector(this.x, this.y);
  }

  const PVector = {
    add: (v1, v2) => new Vector(v1.x + v2.x, v1.y + v2.y),
    sub: (v1, v2) => new Vector(v1.x - v2.x, v1.y - v2.y),
    dot: (v1, v2) => v1.x * v2.x + v1.y * v2.y,
    clamp: (v, mag) => { const V = v.get(); V.makeMag(mag); return V; }
  };

  function applySpringForce(p1, p2, l, k, d) {
    const disp = dis(p1.pos.x, p1.pos.y, p2.pos.x, p2.pos.y);
    const e = disp - l;
    const elF = k * e;
    const norm = PVector.sub(p2.pos, p1.pos); norm.makeMag(1);
    const diff = PVector.sub(p2.vel, p1.vel);
    const dF = PVector.dot(norm, diff) * d;
    const sF = PVector.sub(p2.pos, p1.pos);
    sF.makeMag(elF + dF);
    if (!p1.locked) p1.applyForce(sF);
    sF.mult(-1);
    if (!p2.locked) p2.applyForce(sF);
  }

  function Particle(x, y) {
    this.pos = new Vector(x, y);
    this.vel = new Vector();
    this.acc = new Vector();
    this.locked = false;
    this.invMass = 1;
    this.mField = { rad: 100, mag: 12 };
    const wave = { angVel: 0.05, amplitude: 0.5, phase: 0 };

    this.applyForce = (f) => {
      const F = f.get();
      F.mult(this.invMass);
      this.acc.add(F);
    };

    this.upd = () => {
      if (!this.locked) {
        const mForce = PVector.sub(this.pos, STATE.m);
        if (STATE.clicked && mForce.mag() < this.mField.rad) {
          mForce.makeMag((this.mField.rad - mForce.mag()) / this.mField.rad * this.mField.mag);
          this.applyForce(mForce);
        }

        const initPhase = 2 * Math.PI * this.pos.x / (vw() || 1);
        if (Math.sin(wave.phase - initPhase) > 0.97) {
          this.acc.x += wave.amplitude * (Math.sin(wave.phase - initPhase) + 1);
        }
        wave.phase += wave.angVel;

        this.vel.add({ x: this.acc.x / STATE.substeps, y: this.acc.y / STATE.substeps });
        this.pos.add({ x: this.vel.x / STATE.substeps, y: this.vel.y / STATE.substeps });
      }
      this.acc.mult(0);
    };
  }

  function Blade(x, y, dir, baseLength) {
    const base = new Particle(x, y);
    const col = `hsl(${randFrom(85, 120)},100%,${randFrom(20, 45)}%)`;
    const shrink = 0.8;

    const l1 = baseLength;
    const l2 = baseLength * shrink;
    const l3 = baseLength * shrink * shrink;

    const n1 = new Particle(x + dir.x * l1, y + dir.y * l1);
    const n2 = new Particle(x + dir.x * (l1 + l2), y + dir.y * (l1 + l2));
    const n3 = new Particle(x + dir.x * (l1 + l2 + l3), y + dir.y * (l1 + l2 + l3));

    this.show = () => {
      STATE.ctx.strokeStyle = col;
      STATE.ctx.lineWidth = 4;
      STATE.ctx.beginPath();
      curveVertex(x, y, STATE.ctx);
      curveVertex(x, y, STATE.ctx);
      curveVertex(n1.pos.x, n1.pos.y, STATE.ctx);
      curveVertex(n2.pos.x, n2.pos.y, STATE.ctx);
      curveVertex(n3.pos.x, n3.pos.y, STATE.ctx);
      curveVertex(n3.pos.x, n3.pos.y, STATE.ctx, false);
      STATE.ctx.stroke();
    };

    this.upd = () => {
      const anc1 = { pos: { x: x + dir.x * l1, y: y + dir.y * l1 }, vel: { x: 0, y: 0 }, locked: true };
      const anc2 = { pos: PVector.add(PVector.clamp(PVector.sub(n1.pos, base.pos), l2), n1.pos), vel: { x: 0, y: 0 }, locked: true };
      const anc3 = { pos: PVector.add(PVector.clamp(PVector.sub(n2.pos, n1.pos), l3), n2.pos), vel: { x: 0, y: 0 }, locked: true };

      applySpringForce(n1, anc1, 0, 0.3, 0.3);
      applySpringForce(n2, anc2, 0, 0.3, 0.3);
      applySpringForce(n3, anc3, 0, 0.3, 0.3);

      applySpringForce(base, n1, l1, 0.3, 0.3);
      applySpringForce(n1, n2, l2, 0.3, 0.3);
      applySpringForce(n2, n3, l3, 0.3, 0.3);

      n1.upd(); n2.upd(); n3.upd();
    };
  }

  function resizeGrass() {
    const dpr = Math.max(1, window.devicePixelRatio || 1);
    const W = vw();

    STATE.can.style.width = "100vw";
    STATE.can.style.height = STATE.STRIP_H + "px";

    STATE.can.width = Math.floor(W * dpr);
    STATE.can.height = Math.floor(STATE.STRIP_H * dpr);

    STATE.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function makeGrass() {
    STATE.blades.length = 0;
    const W = vw();
    for (let i = 0; i < STATE.NUM_BLADES; i++) {
      const variety = 0.06;
      const ang = -Math.PI / 2 + randFrom(-variety, variety);
      STATE.blades.push(new Blade(
        (i + 1) / (STATE.NUM_BLADES + 1) * W,
        STATE.STRIP_H,
        { x: Math.cos(ang), y: Math.sin(ang) },
        randFrom(22, 40)
      ));
    }
  }

  function grassLoop() {
    if (!STATE.enabled) return;
    if (document.hidden) { STATE.rafGrass = requestAnimationFrame(grassLoop); return; }
    STATE.ctx.clearRect(0, 0, vw(), STATE.STRIP_H);
    for (let i = 0; i < STATE.blades.length; i++) {
      STATE.blades[i].show();
      for (let j = 0; j < STATE.substeps; j++) STATE.blades[i].upd();
    }
    STATE.rafGrass = requestAnimationFrame(grassLoop);
  }

  function setPointer(clientX, clientY) {
    const r = STATE.can.getBoundingClientRect();
    STATE.m.x = clientX - r.left;
    STATE.m.y = clientY - r.top;
  }

  function spawnRabbit(layer, isFront) {
    const unit = document.createElement("div");
    unit.className = "rabbit-unit";
    unit.style.left = "0px";
    unit.style.bottom = STATE.RABBIT_BOTTOM + "px";

    const rabbit = document.createElement("div");
    rabbit.className = "rabbit";

    const eyeX = document.createElement("span");
    eyeX.className = "rabbit-eye-x";
    rabbit.appendChild(eyeX);

    unit.appendChild(rabbit);
    layer.appendChild(unit);

    const scale = randFrom(isFront ? 0.85 : 0.65, isFront ? 1.15 : 0.95);
    const speedMag = randFrom(45, 110);
    const vx = speedMag * (Math.random() < 0.5 ? -1 : 1);

    rabbit.style.animationDelay = `${randFrom(0, 0.9)}s`;
    rabbit.style.animationDuration = `${randFrom(0.9, 1.2)}s`;

    const u = { el: unit, x: randFrom(0, vw()), vx, scale, wApprox: 180 * scale };

    unit.addEventListener("pointerdown", (e) => startDrag(e, u), { passive: false });

    return u;
  }

  function initRabbits() {
    STATE.back.innerHTML = "";
    STATE.front.innerHTML = "";
    STATE.units.length = 0;
    for (let i = 0; i < 3; i++) STATE.units.push(spawnRabbit(STATE.back, false));
    for (let i = 0; i < 2; i++) STATE.units.push(spawnRabbit(STATE.front, true));
  }

  function scare(clientX, clientY) {
    const stripTop = vh() - STATE.STRIP_H;
    if (clientY < stripTop) return;

    for (const u of STATE.units) {
      const w = u.wApprox;
      const center = u.x + w * 0.35;
      const near = (Math.abs(clientX - center) < 120);

      if (near) {
        const goRight = clientX < center;
        const base = Math.max(60, Math.min(160, Math.abs(u.vx)));
        u.vx = (goRight ? 1 : -1) * (base + 70);
        u.vx = Math.max(-220, Math.min(220, u.vx));
        break;
      }
    }
  }

  function startDrag(e, u) {
    if (!STATE.enabled) return;
    e.preventDefault();
    e.stopPropagation();
    try { e.currentTarget.setPointerCapture(e.pointerId); } catch (_) {}

    STATE.dragging = { u, pointerId: e.pointerId, offsetX: e.clientX - u.x };
    STATE.lastPointer = { x: e.clientX, y: e.clientY, t: performance.now() };
    u.vx = 0;
    u.el.classList.add("is-dragging");
  }

  function onPointerMove(e) {
    if (!STATE.dragging) return;
    const { u, offsetX } = STATE.dragging;

    STATE.lastPointer = { x: e.clientX, y: e.clientY, t: performance.now() };

    const W = vw();
    u.x = Math.max(-u.wApprox, Math.min(e.clientX - offsetX, W + u.wApprox));

    u.el.style.left = `${u.x}px`;
    u.el.style.bottom = `${STATE.RABBIT_BOTTOM}px`;
    u.el.style.transform = `scale(${u.scale}) scaleX(1)`;
  }

  function endDrag(e) {
    if (!STATE.dragging) return;

    const { u } = STATE.dragging;
    u.el.classList.remove("is-dragging");

    const dt = Math.max(1, performance.now() - STATE.lastPointer.t);
    const dx = (e.clientX ?? STATE.lastPointer.x) - STATE.lastPointer.x;
    let vx = (dx / dt) * 1000;

    if (Math.abs(vx) < 40) vx = (Math.random() < 0.5 ? -1 : 1) * randFrom(80, 160);
    u.vx = Math.max(-220, Math.min(220, vx));

    STATE.dragging = null;
  }

  function onStripPointerDown(e) {
    if (STATE.dragging) return;
    if (e.target === STATE.can) return;
    scare(e.clientX, e.clientY);
  }

  function rabbitsLoop() {
    if (!STATE.enabled) return;

    const W = vw();
    const dt = 0.016;

    for (const u of STATE.units) {
      if (STATE.dragging && STATE.dragging.u === u) continue;

      u.x += u.vx * dt;

      if (u.x < -u.wApprox) u.x = W + u.wApprox;
      if (u.x > W + u.wApprox) u.x = -u.wApprox;

      const flip = u.vx < 0 ? -1 : 1;
      u.el.style.left = `${u.x}px`;
      u.el.style.bottom = `${STATE.RABBIT_BOTTOM}px`;
      u.el.style.transform = `scale(${u.scale}) scaleX(${flip})`;
    }

    STATE.rafRabbits = requestAnimationFrame(rabbitsLoop);
  }

  function enable() {
    if (STATE.enabled) return;
    STATE.enabled = true;

    ensureRoot();

    STATE.can = ensureEl("grass-canvas", "canvas");
    STATE.ctx = STATE.can.getContext("2d");

    STATE.back = ensureEl("easter-eggs-back", "div");
    STATE.front = ensureEl("easter-eggs-front", "div");

    const onGrassDown = (e) => { STATE.clicked = true; setPointer(e.clientX, e.clientY); };
    const onGrassMove = (e) => { if (!STATE.clicked) return; setPointer(e.clientX, e.clientY); };
    const onGrassUp = () => { STATE.clicked = false; };

    STATE.can.addEventListener("pointerdown", onGrassDown, { passive: true });
    window.addEventListener("pointermove", onGrassMove, { passive: true });
    window.addEventListener("pointerup", onGrassUp, { passive: true });

    STATE.wrapper.addEventListener("pointerdown", onStripPointerDown, { passive: true });

    window.addEventListener("pointermove", onPointerMove, { passive: true });
    window.addEventListener("pointerup", endDrag, { passive: true });
    window.addEventListener("pointercancel", endDrag, { passive: true });

    window.addEventListener("resize", () => {
      if (!STATE.enabled) return;
      resizeGrass();
      makeGrass();
    });

    resizeGrass();
    makeGrass();
    initRabbits();

    grassLoop();
    rabbitsLoop();
  }

  function disable() {
    STATE.enabled = false;
    if (STATE.rafGrass) cancelAnimationFrame(STATE.rafGrass);
    if (STATE.rafRabbits) cancelAnimationFrame(STATE.rafRabbits);

    const root = document.getElementById("langa-easter");
    if (root) root.remove();
  }

  window.LANGA_EASTER_ENABLE = enable;
  window.LANGA_EASTER_DISABLE = disable;
  window.LANGA_EASTER_TOGGLE = (on) => (on ? enable() : disable());

  document.addEventListener("DOMContentLoaded", function() { try { enable(); } catch(e) { if(window.console) console.warn('LANGA easter effect:', e); } });
})();