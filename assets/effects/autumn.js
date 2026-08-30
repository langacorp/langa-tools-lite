// LANGA Tools Effect: autumn
// Lightweight falling leaves (canvas).
(function(){
  if (window.__langaEffectAutumn) return;
  window.__langaEffectAutumn = true;
  var d=document;
  var canvas=d.createElement('canvas');
  canvas.className='langa-effect-canvas langa-effect-autumn';
  var ctx=canvas.getContext('2d');
  if(!ctx) return;
  var w=0,h=0,raf=0;
  var leaves=[];
  var maxLeaves=34;
  function resize(){w=canvas.width=window.innerWidth;h=canvas.height=window.innerHeight;}
  function rand(a,b){return a+Math.random()*(b-a)}
  function spawn(){
    var size=rand(10,22);
    leaves.push({
      x:rand(0,w),
      y:rand(-h*0.3, -20),
      vx:rand(-0.25,0.25),
      vy:rand(0.6,1.4),
      rot:rand(0,Math.PI*2),
      spin:rand(-0.015,0.015),
      sway:rand(0.5,1.6),
      phase:rand(0,Math.PI*2),
      r:size,
      hue:Math.floor(rand(18,45)),
      lum:Math.floor(rand(40,60))
    });
  }
  function drawLeaf(l){
    ctx.save();
    ctx.translate(l.x, l.y);
    ctx.rotate(l.rot);
    ctx.fillStyle='hsla('+l.hue+',70%,'+l.lum+'%,0.85)';
    ctx.beginPath();
    // simple leaf shape
    ctx.moveTo(0, -l.r);
    ctx.quadraticCurveTo(l.r, -l.r*0.3, 0, l.r);
    ctx.quadraticCurveTo(-l.r, -l.r*0.3, 0, -l.r);
    ctx.fill();
    // vein
    ctx.strokeStyle='rgba(80,50,20,0.35)';
    ctx.lineWidth=1;
    ctx.beginPath();
    ctx.moveTo(0, -l.r*0.8);
    ctx.lineTo(0, l.r*0.8);
    ctx.stroke();
    ctx.restore();
  }
  function tick(t){
    if(document.hidden){raf=requestAnimationFrame(tick);return;}
    ctx.clearRect(0,0,w,h);
    while(leaves.length<maxLeaves) spawn();
    for(var i=leaves.length-1;i>=0;i--){
      var l=leaves[i];
      l.phase+=0.01;
      l.x += l.vx + Math.sin(l.phase)*l.sway*0.5;
      l.y += l.vy;
      l.rot += l.spin;
      drawLeaf(l);
      if(l.y>h+40){leaves.splice(i,1);}
    }
    raf=requestAnimationFrame(tick);
  }
  function init(){
    d.body.appendChild(canvas);
    resize();
    window.addEventListener('resize', resize);
    raf=requestAnimationFrame(tick);
  }
  if(d.readyState==='loading') d.addEventListener('DOMContentLoaded',function(){try{init();}catch(e){if(window.console)console.warn('LANGA autumn effect:',e);}}); else try{init();}catch(e){if(window.console)console.warn('LANGA autumn effect:',e);}
})();
