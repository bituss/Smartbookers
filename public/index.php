<?php include '../includes/header.php'; ?>

<section class="hero">
  <div class="hero-text">

    <div class="hero-badges">
      <span class="badge">⚡ 1 perc alatt foglalás</span>
      <span class="badge">🔔 Automata emlékeztetők</span>
      <span class="badge">🗓️ Naptár szinkron</span>
    </div>

    <h1>Időpontfoglalás.<br><span>Okosan.</span></h1>

    <p>
      Modern online időpontfoglaló rendszer vállalkozásoknak és ügyfeleknek.
      Kevesebb telefon, több bevétel, elégedettebb vendégek.
    </p>

   

    <div class="hero-proof">
      <div class="proof-item"><strong>4.9/5</strong><span>értékelés</span></div>
      <div class="proof-item"><strong>1200+</strong><span>foglalás / hó</span></div>
      <div class="proof-item"><strong>GDPR</strong><span>kompatibilis</span></div>
    </div>

    <!-- Quick search -->
    <form id="quickSearch"
      class="quick-search"
      method="get"
      action="/Smartbookers/public/industry.php">
      resourcebundle_get_error_message

  <div>
    <label for="industry">Iparág választás:</label>

    <select id="industry" name="slug">
      <option value="kozmetika">Kozmetika</option>
      <option value="fodraszat">Fodrászat</option>
      <option value="mukorom">Műköröm</option>
      <option value="masszazs">Masszázs</option>
      <option value="egeszseg">Egészség</option>
    </select>
  </div>

  <button type="submit" class="btn primary">Keresés</button>

</form>


    <p class="quick-search-hint">
      Tipp: válassz iparágat, és máris látod a szabad időpontokat.
    </p>

  </div>

  <div class="hero-mockup">
    <div class="mock-card">
      <div class="mock-top">
        <span class="date">Feb 12 • 14:00</span>
        <span>Fodrászat</span>
      </div>

      <p class="mock-text">
       <strong> Következő időpontod: érkezz pontosan, 10 perc ráhagyással.</strong>
      </p>

      <div class="mock-row">
        <span>Szolgáltatás:</span>
        <strong>Női hajvágás</strong>
      </div>

      <div class="mock-row">
        <span>Helyszín:</span>
        <strong>Budapest</strong>
      </div>

      <div class="mock-actions">
        <a href="#features" class="btn-primary">Megtekintés</a>
      </div>
    </div>
  </div>
</section>

<section id="features" class="features section">
  <div class="container">
    <div class="features-head">
      <div>
        <h2 class="section-title">Miért SmartBookers?</h2>
        <p class="section-subtitle">
          Prémium foglalási élmény ügyfeleknek és vállalkozásoknak – kevesebb admin, több bevétel.
        </p>
      </div>

      <div class="features-toggle" role="tablist" aria-label="Célcsoport választó">
        <button class="ft-btn is-active" type="button" data-target="clients">👤 Felhasználóknak</button>
        <button class="ft-btn" type="button" data-target="biz">🏢 Vállalkozásoknak</button>
      </div>
    </div>

    <!-- Felhasználóknak -->
    <div class="features-grid is-active" data-panel="clients">
      <article class="feature-card">
        <div class="feature-ico">⚡</div>
        <h3>1 perc alatt foglalás</h3>
        <p>Gyors időpontválasztás, azonnali visszaigazolás, minden mobilbarát.</p>
      </article>

      <article class="feature-card">
        <div class="feature-ico">🔔</div>
        <h3>Automata emlékeztetők</h3>
        <p>Kevesebb elfelejtett időpont – email/SMS értesítések (igény szerint).</p>
      </article>

      <article class="feature-card">
        <div class="feature-ico">🗓️</div>
        <h3>Naptár szinkron</h3>
        <p>Google/Apple naptárba mentés, hogy mindig kéznél legyen.</p>
      </article>

      <article class="feature-card">
        <div class="feature-ico">✉️</div>
        <h3>Közvetlen üzenet</h3>
        <p>Kérdezz rá szolgáltatásra, parkolásra, címre — minden egy helyen.</p>
      </article>
    </div>

    <!-- Vállalkozásoknak -->
    <div class="features-grid" data-panel="biz">
      <article class="feature-card">
        <div class="feature-ico">📆</div>
        <h3>Átlátható naptár</h3>
        <p>Valós idejű foglalások, szünetek, munkaidő, több szolgáltató kezelése.</p>
      </article>

      <article class="feature-card">
        <div class="feature-ico">💸</div>
        <h3>Kevesebb kiesés</h3>
        <p>Emlékeztetők + egyszerű lemondás/módosítás = kevesebb no-show.</p>
      </article>

      <article class="feature-card">
        <div class="feature-ico">👥</div>
        <h3>Ügyfél-nyilvántartás</h3>
        <p>Vendégadatok, előzmények, megjegyzések — profin rendszerezve.</p>
      </article>

      <article class="feature-card">
        <div class="feature-ico">📈</div>
        <h3>Statisztikák</h3>
        <p>Foglalások, kihasználtság, csúcsidők – döntések adatok alapján.</p>
      </article>
    </div>


  </div>
</section>

<section id="reviews" class="reviews section">
  <div class="container">
    <div class="reviews-head">
      <h2 class="section-title">Vélemények</h2>
      <p class="section-subtitle">Valódi felhasználói visszajelzések a gyorsabb foglalásról és a kevesebb kiesésről.</p>
    </div>

    <div class="reviews-grid">
      <article class="review-card">
        <div class="review-top">
          <div class="stars" aria-label="5 csillag">★★★★★</div>
          <span class="review-tag">Fodrászat</span>
        </div>
        <p class="review-text">
          “Végre nem telefonálgatunk egész nap. A vendégek maguk foglalnak, mi meg látjuk egyben a naptárt.”
        </p>
        <div class="review-author">
          <div class="avatar">D</div>
          <div>
            <strong>Dóra</strong>
            <span>Budapest</span>
          </div>
        </div>
      </article>

      <article class="review-card">
        <div class="review-top">
          <div class="stars" aria-label="5 csillag">★★★★★</div>
          <span class="review-tag">Kozmetika</span>
        </div>
        <p class="review-text">
          “Az emlékeztetők óta látványosan csökkent a lemondás. Profi, letisztult felület.”
        </p>
        <div class="review-author">
          <div class="avatar">K</div>
          <div>
            <strong>Kata</strong>
            <span>Szeged</span>
          </div>
        </div>
      </article>

      <article class="review-card">
        <div class="review-top">
          <div class="stars" aria-label="4 csillag">★★★★☆</div>
          <span class="review-tag">Masszázs</span>
        </div>
        <p class="review-text">
          “Nagyon jó, hogy a vendégek látják a szabad időpontokat. Nekem rengeteg időt spórol.”
        </p>
        <div class="review-author">
          <div class="avatar">B</div>
          <div>
            <strong>Bence</strong>
            <span>Győr</span>
          </div>
        </div>
      </article>
    </div>
<!-- Image slider (Features alatt) -->
<div class="container">
  <section class="img-slider" aria-label="SmartBookers felhasználói képek">
    <div class="img-slider__viewport">
      <div class="img-slider__track" id="sbSliderTrack">
        <div class="img-slider__slide">
          <img src="/Smartbookers/public/images/kozmetikus.png" alt="Kozmetikus – SmartBookers a laptopon" loading="lazy">
        </div>
        <div class="img-slider__slide">
          <img src="/Smartbookers/public/images/fodrasz.png" alt="Fodrász – SmartBookers a laptopon" loading="lazy">
        </div>
        <div class="img-slider__slide">
          <img src="/Smartbookers/public/images/masszazs.png" alt="Masszőr – SmartBookers a laptopon" loading="lazy">
        </div>
        <div class="img-slider__slide">
          <img src="/Smartbookers/public/images/mentalhigenikus.png" alt="Mentálhigiénés szakember – SmartBookers a laptopon" loading="lazy">
        </div>
        <div class="img-slider__slide">
          <img src="/Smartbookers/public/images/mukormos.png" alt="Műkörmös – SmartBookers a laptopon" loading="lazy">
        </div>
      </div>

      <button class="img-slider__nav img-slider__nav--prev" type="button" aria-label="Előző kép" id="sbPrev">‹</button>
      <button class="img-slider__nav img-slider__nav--next" type="button" aria-label="Következő kép" id="sbNext">›</button>
    </div>

    <div class="img-slider__dots" id="sbDots" aria-label="Lapozó pöttyök"></div>
  </section>
</div>
  </div>
</section>


<section id="how" class="how section">
  <div class="container">
    <div class="how-head">
      <div>
        <h2 class="section-title">Hogyan működik?</h2>
        <p class="section-subtitle">
          Néhány perc alatt elindulhatsz. Válaszd ki, hogy felhasználóként vagy vállalkozóként nézed.
        </p>
      </div>

      <div class="how-toggle" role="tablist" aria-label="Hogyan működik – nézet választó">
        <button class="how-btn is-active" type="button" data-how="client">👤 Felhasználóknak</button>
        <button class="how-btn" type="button" data-how="biz">🏢 Vállalkozásoknak</button>
      </div>
    </div>

    <!-- Felhasználóknak -->
    <div class="how-grid is-active" data-how-panel="client">
      <article class="how-card">
        <div class="how-step">1</div>
        <h3>Keress szolgáltatót</h3>
        <p>Válassz iparágat (pl. kozmetika), add meg a várost/kerületet, és listázzuk a legjobb találatokat.</p>
        <ul class="how-bullets">
          <li>Szűrés iparág szerint</li>
          <li>Gyors találatok városra</li>
          <li>Átlátható profilok</li>
        </ul>
      </article>

      <article class="how-card">
        <div class="how-step">2</div>
        <h3>Válassz időpontot</h3>
        <p>Valós idejű naptárban látod a szabad helyeket. Pár kattintás és kész a foglalás.</p>
        <ul class="how-bullets">
          <li>Szabad idősávok azonnal</li>
          <li>Mobilon is gyors</li>
          <li>Azonnali visszaigazolás</li>
        </ul>
      </article>

      <article class="how-card">
        <div class="how-step">3</div>
        <h3>Emlékeztetők & naptár</h3>
        <p>Automatikus email/SMS emlékeztetők (beállítástól függően), plusz naptárszinkron.</p>
        <ul class="how-bullets">
          <li>Kevesebb elfelejtett időpont</li>
          <li>Google/Apple naptár</li>
          <li>Lemondás/módosítás egyszerűen</li>
        </ul>
      </article>
    </div>

    <!-- Vállalkozásoknak -->
    <div class="how-grid" data-how-panel="biz">
      <article class="how-card">
        <div class="how-step">1</div>
        <h3>Regisztráció & profil</h3>
        <p>Hozd létre vállalkozásod adatlapját: szolgáltatások, árak, nyitvatartás, cím, képek.</p>
        <ul class="how-bullets">
          <li>Szolgáltatáslista és árak</li>
          <li>Nyitvatartás, szünetek</li>
          <li>Megjelenés a keresőben</li>
        </ul>
      </article>

      <article class="how-card">
        <div class="how-step">2</div>
        <h3>Naptár beállítás</h3>
        <p>Állítsd be a munkaidőt, időtartamokat, több szolgáltatót — a rendszer automatikusan számol.</p>
        <ul class="how-bullets">
          <li>Valós idejű foglalások</li>
          <li>No-show csökkentés</li>
          <li>Szinkron külső naptárral</li>
        </ul>
      </article>

      <article class="how-card">
        <div class="how-step">3</div>
        <h3>Foglalások kezelése</h3>
        <p>Minden foglalás egy helyen: vendégadatok, előzmények, megjegyzések, gyors módosítás.</p>
        <ul class="how-bullets">
          <li>Ügyfél-nyilvántartás</li>
          <li>Automata értesítések</li>
          <li>Statisztikák, kihasználtság</li>
        </ul>
      </article>
    </div>


  </div>
</section>


<?php include '../includes/footer.php'; ?>

<script src="/Smartbookers/js/index.js"></script>
