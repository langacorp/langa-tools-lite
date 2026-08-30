(function(){
  if (window.__LANGA_VALENTINE_BOOT__) return;
  window.__LANGA_VALENTINE_BOOT__ = true;
  try {
    function rand(min,max){return Math.random()*(max-min)+min;}

    var MAX_HEARTS = 28;
    function heartCount(){
      return document.querySelectorAll('.langa-heart').length;
    }

    function createHeart(x,y){
      if (heartCount() >= MAX_HEARTS) return;
      var el=document.createElement('div');
      el.className='langa-heart';
      el.style.left=x+'px';
      el.style.top=y+'px';

      el.style.setProperty('--dx', Math.round(rand(-30,30))+'px');
      el.style.setProperty('--dy', Math.round(rand(-90,-50))+'px');
      el.style.setProperty('--rot', Math.round(rand(-20,20))+'deg');
      el.style.setProperty('--scale', (1 + rand(0,0.35)).toFixed(2));
      el.style.setProperty('--dur', Math.round(rand(900,1400))+'ms');

      var b1=document.createElement('div');
      b1.className='langa-heart-bubble langa-heart-b1';
      var b2=document.createElement('div');
      b2.className='langa-heart-bubble langa-heart-b2';
      el.appendChild(b1); el.appendChild(b2);

      document.body.appendChild(el);

      el.addEventListener('animationend', function(){
        if (el && el.parentNode) el.parentNode.removeChild(el);
      }, {once:true});
    }

    var last=0;
    function throttledMove(e){
      var now=Date.now();
      if(now-last<120) return;
      last=now;
      createHeart(e.clientX, e.clientY);
    }

    window.addEventListener('click', function(e){
      createHeart(e.clientX, e.clientY);
    }, {passive:true});

    if (window.matchMedia && window.matchMedia('(pointer:fine)').matches) {
      window.addEventListener('mousemove', throttledMove, {passive:true});
    }
  } catch(e) { if(window.console) console.warn('LANGA valentine effect:', e); }
})();
