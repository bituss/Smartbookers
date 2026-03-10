// Accordion
document.querySelectorAll('.accordion-header').forEach((header) => {
  header.addEventListener('click', () => {
    const body = header.nextElementSibling;
    const isOpen = body && body.style.maxHeight && body.style.maxHeight !== "0px";

    document.querySelectorAll('.accordion-body').forEach((b) => {
      b.style.maxHeight = null;
    });

    if (!isOpen && body) {
      body.style.maxHeight = body.scrollHeight + "px";
    }
  });
});

/// Quick search routing (industry alapján) -> industry.php?slug=...
(function(){
  const form = document.getElementById('quickSearch');
  if (!form) return;

  const industry = document.getElementById('industry');

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const val  = industry ? industry.value : 'kozmetika';
    const city = document.getElementById('city')?.value || '';

    const url = new URL('/Smartbookers/public/industry.php', window.location.origin);
    url.searchParams.set('slug', val);

    if (city.trim()) url.searchParams.set('city', city.trim()); // opcionális

    window.location.href = url.toString();
  });
})();

(function(){
  const buttons = document.querySelectorAll('.ft-btn');
  const panels  = document.querySelectorAll('.features-grid');

  if(!buttons.length || !panels.length) return;

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.target;

      buttons.forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');

      panels.forEach(p => {
        p.classList.toggle('is-active', p.dataset.panel === target);
      });
    });
  });
})();

(function(){
  const track = document.getElementById('sbSliderTrack');
  const prev  = document.getElementById('sbPrev');
  const next  = document.getElementById('sbNext');
  const dotsWrap = document.getElementById('sbDots');

  if(!track) return;

  const slides = Array.from(track.children);
  const total  = slides.length;
  let index = 0;
  let timer = null;
  const INTERVAL = 5000;

  // Dots
  const dots = slides.map((_, i) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'img-slider__dot' + (i === 0 ? ' is-active' : '');
    b.setAttribute('aria-label', `Kép ${i+1}/${total}`);
    b.addEventListener('click', () => goTo(i, true));
    dotsWrap.appendChild(b);
    return b;
  });

  function update(){
    track.style.transform = `translateX(-${index * 100}%)`;
    dots.forEach((d, i) => d.classList.toggle('is-active', i === index));
  }

  function goTo(i, resetTimer=false){
    index = (i + total) % total;
    update();
    if(resetTimer) restart();
  }

  function start(){
    stop();
    timer = setInterval(() => goTo(index + 1), INTERVAL);
  }

  function stop(){
    if(timer) clearInterval(timer);
    timer = null;
  }

  function restart(){
    start();
  }

  prev?.addEventListener('click', () => goTo(index - 1, true));
  next?.addEventListener('click', () => goTo(index + 1, true));

  // hover/focus pause
  const viewport = track.closest('.img-slider__viewport');
  viewport?.addEventListener('mouseenter', stop);
  viewport?.addEventListener('mouseleave', start);
  viewport?.addEventListener('focusin', stop);
  viewport?.addEventListener('focusout', start);

  // swipe (mobil)
  let x0 = null;
  viewport?.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, {passive:true});
  viewport?.addEventListener('touchend', (e) => {
    if(x0 === null) return;
    const x1 = e.changedTouches[0].clientX;
    const dx = x1 - x0;
    x0 = null;
    if(Math.abs(dx) > 40){
      goTo(dx > 0 ? index - 1 : index + 1, true);
    }
  }, {passive:true});

  update();
  start();
})();
(function(){
  const buttons = document.querySelectorAll('.how-btn');
  const panels  = document.querySelectorAll('.how-grid');

  if(!buttons.length || !panels.length) return;

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.how;

      buttons.forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');

      panels.forEach(p => {
        p.classList.toggle('is-active', p.dataset.howPanel === target);
      });
    });
  });
})();




