<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="/Smartbookers/public/css/contact.css">
<section class="contact-hero">
  <div class="container contact-hero__inner">
    <div class="contact-hero__text">
      <div class="contact-badges">
        <span class="contact-badge">⚡ Gyors válasz</span>
        <span class="contact-badge">🔒 GDPR-kompatibilis</span>
        <span class="contact-badge">🤝 Ügyfélközpontú</span>
      </div>
      <h1>Kapcsolat</h1>
      <p>
        Írj nekünk, és segítünk beállításban, csomagválasztásban vagy bármilyen kérdésben.
        Általában <strong>24 órán belül</strong> válaszolunk.
      </p>
      <div class="contact-cards">
        <div class="contact-card">
          <div class="contact-card__icon">✉️</div>
          <div>
            <div class="contact-card__title">Email</div>
            <div class="contact-card__value">
              <a href="mailto:hello@smartbookers.hu">smartbookerhelp@gmail.com</a>
            </div>
            <div class="contact-card__hint">Támogatás, kérdések, együttműködés</div>
          </div>
        </div>
        <div class="contact-card">
          <div class="contact-card__icon">📞</div>
          <div>
            <div class="contact-card__title">Telefon</div>
            <div class="contact-card__value">
              <a href="tel:+3610000000">+36 1 000 0000</a>
            </div>
            <div class="contact-card__hint">H–P 9:00–17:00</div>
          </div>
        </div>
        <div class="contact-card">
          <div class="contact-card__icon">📍</div>
          <div>
            <div class="contact-card__title">Iroda</div>
            <div class="contact-card__value">Balassagyarmat</div>
            <div class="contact-card__hint">Előzetes egyeztetéssel</div>
          </div>
        </div>
      </div>
    </div>
    <div class="contact-hero__form">
      <div class="form-shell">
        <div class="form-head">
          <h2>Írj üzenetet</h2>
          <p>Kérlek töltsd ki az adatokat, és küldjük a választ.</p>
        </div>
        <?php if(isset($_GET['sent'])): ?>
  <div class="form-alert form-alert--ok">✅ Köszi! Az üzeneted megérkezett, hamarosan válaszolunk.</div>
<?php endif; ?>
<?php if(isset($_GET['error'])): ?>
  <div class="form-alert form-alert--err">⚠️ <?php echo htmlspecialchars($_GET['msg'] ?? 'Hiba történt.', ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form class="contact-form" method="post" action="/Smartbookers/public/send_contact.php">
  <!-- Honeypot spam ellen -->
  <input type="text" name="website" autocomplete="off" tabindex="-1" style="position:absolute; left:-9999px; opacity:0;">
  <div class="form-grid">
    <div class="form-field">
      <label for="name">Név</label>
      <input id="name" name="name" type="text" placeholder="Teljes név" required>
    </div>
    <div class="form-field">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" placeholder="pelda@email.hu" required>
    </div>
    <div class="form-field">
      <label for="topic">Téma</label>
      <select id="topic" name="topic" required>
        <option value="" selected disabled>Válassz témát</option>
        <option value="support">Technikai segítség</option>
        <option value="business">Vállalkozói csomag</option>
        <option value="partnership">Együttműködés</option>
        <option value="other">Egyéb</option>
      </select>
    </div>
    <div class="form-field">
      <label for="phone">Telefonszám (opcionális)</label>
      <input id="phone" name="phone" type="tel" placeholder="+36 ...">
    </div>
    <div class="form-field form-field--full">
      <label for="message">Üzenet</label>
      <textarea id="message" name="message" rows="6" placeholder="Írd le röviden, miben segíthetünk…" required></textarea>
    </div>
    <div class="form-field form-field--full form-check">
      <input id="privacy" name="privacy" type="checkbox" required>
      <label for="privacy">
        Elfogadom az <a href="#" onclick="return false;">adatkezelési tájékoztatót</a>.
      </label>
    </div>
  </div>
  <button type="submit" class="btn primary form-submit">Üzenet küldése</button>
  <p class="form-note">
    Küldés után visszairányítunk a Kapcsolat oldalra. (Siker/hiba üzenet felül.)
  </p>
</form>
      </div>
    </div>
  </div>
</section>
<section class="contact-extra">
  <div class="container">
    <div class="contact-extra__grid">
      <div class="extra-card">
        <h3>Gyakori kérdések</h3>
        <p>Ha gyorsan szeretnél választ, nézd meg a leggyakoribb témákat.</p>
        <ul>
          <li>Hogyan állítsam be a szolgáltatásokat?</li>
          <li>Hogyan csökkenthető a no-show?</li>
          <li>Van naptár szinkron?</li>
        </ul>
        <a class="btn primary" href="/Smartbookers/public/index.php#features">Funkciók</a>
      </div>
      <div class="extra-card extra-card--map">
        <h3>Hol érhetsz el minket?</h3>
        <p>Online támogatás elsődlegesen, személyes egyeztetéssel.</p>
        <!-- térkép beágyazás-->
        <div class="map-shell" aria-label="Térkép: Balassagyarmati Szent-Györgyi Albert Technikum">
  <iframe
    class="gmap"
    loading="lazy"
    allowfullscreen
    referrerpolicy="no-referrer-when-downgrade"
    src="https:
  </iframe>
</div>
      </div>
    </div>
  </div>
</section>
<?php include '../includes/footer.php'; ?>
