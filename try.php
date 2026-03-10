<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<title>SmartBookers</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
/* =====================
   GLOBAL
===================== */
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: 'Inter', sans-serif;
  background: #f9fafb;
  color: #111827;
  line-height: 1.6;
}

/* =====================
   HEADER / NAVBAR
===================== */
.app-header {
  position: sticky;
  top: 0;
  z-index: 200;
  background: rgba(255,255,255,0.85);
  backdrop-filter: blur(14px);
  border-bottom: 1px solid rgba(0,0,0,0.05);
}
.header-inner {
  max-width: 1700px;
  margin: auto;
  padding: 16px 10%;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo-img { height: 50px; width: auto; display: block; }
.logo { font-size: 30px; font-weight: 700; display: flex; align-items: center; gap: 3px; }

/* =====================
   BUTTONS
===================== */
.btn {
  display: inline-block;
  padding: 10px 18px;
  border-radius: 8px;
  font-weight: 600;
  text-align: center;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.3s ease;
  color: white;
  background: #24256e;
  border: none;
}
.btn:hover { background: #1f215c; }
.btn.danger { background: #ef4444; }
.btn.danger:hover { background: #dc2626; }

/* =====================
   DESKTOP NAV
===================== */
.nav-desktop a { 
  margin-left: 24px; 
  text-decoration: none; 
  font-weight: 500; 
  color: #1a1e24; 
  position: relative; 
}
.nav-desktop a::after {
  content:"";
  position: absolute;
  left:0; bottom:-6px;
  width:0; height:2px;
  background:#000; transition: width 0.4s;
}
.nav-desktop a:hover::after { width:100%; }

/* =====================
   DROPDOWN
===================== */
.dropdown { position: relative; display: inline-block; margin-left: 16px; }
.dropdown-toggle { cursor:pointer; background:#24256e; color:white; border:none; border-radius:8px; padding:10px 18px; font-weight:600; transition: background 0.3s; }
.dropdown-toggle:hover { background:#1f215c; }

.dropdown-menu {
  display:none;
  position:absolute;
  top:calc(100% + 4px);
  left:0;
  background:#fff;
  border-radius:8px;
  min-width:180px;
  box-shadow:0 8px 16px rgba(0,0,0,0.15);
  z-index:1000;
  padding:0;
}
.dropdown-menu a {
  display:block;
  padding:10px 16px;
  color:#111827;
  text-decoration:none;
}
.dropdown-menu a:hover { background:#f3f4f6; }

/* Hover desktop */
.dropdown:hover .dropdown-menu { display:block; }

/* =====================
   MOBIL NAV
===================== */
.menu-toggle { display:none; font-size:26px; cursor:pointer; }
.nav-mobile { display:none; flex-direction:column; padding:20px; background:#fff; }
.nav-mobile a { padding:12px 0; margin-bottom:8px; font-weight:600; color:#111827; text-decoration:none; }

/* MOBILE ACCORDION */
.mobile-accordion {
  width:100%; padding:10px 18px; font-weight:600; border:none; outline:none; border-radius:8px;
  background:#24256e; color:white; cursor:pointer; margin-bottom:6px; transition: background 0.3s;
}
.mobile-accordion:hover { background:#1f215c; }
.mobile-panel {
  max-height:0; overflow:hidden; transition:max-height 0.3s ease;
  background:#fff; border-radius:0 0 8px 8px; box-shadow:0 4px 8px rgba(0,0,0,0.05); margin-bottom:12px;
}

/* =====================
   RESZPONZÍV
===================== */
@media (max-width:900px){
  .nav-desktop{ display:none; }
  .menu-toggle{ display:block; }
  body.menu-open .nav-mobile{ display:flex; }
}
@media (max-width:500px){
  .btn{ width:100%; text-align:center; }
}
</style>
</head>
<body>

<header class="app-header">
  <div class="header-inner">

    <!-- LOGO -->
    <div class="logo">
      <a href="#"><img src="images/smartbookers-logo.png" alt="SmartBookers logó" class="logo-img"></a>
    </div>

    <!-- DESKTOP NAV -->
    <nav class="nav-desktop">
      <div class="dropdown">
        <button class="dropdown-toggle btn small">Iparágak ▾</button>
        <div class="dropdown-menu">
          <a href="#">Fodrászat</a>
          <a href="#">Masszázs</a>
          <a href="#">Kozmetika</a>
          <a href="#">Egészség</a>
        </div>
      </div>

      <div class="dropdown">
        <button class="dropdown-toggle btn small">Árak ▾</button>
        <div class="dropdown-menu">
          <a href="#">Ingyenes próbaidőszak</a>
          <a href="#">Basic csomag</a>
          <a href="#">Pro csomag</a>
        </div>
      </div>

      <a href="#" class="btn small">Belépés</a>
      <a href="#" class="btn small">Üzleti Regisztráció</a>
    </nav>

    <!-- MOBIL GOMB -->
    <div class="menu-toggle" onclick="document.body.classList.toggle('menu-open')">☰</div>

  </div>

  <!-- MOBIL NAV -->
  <nav class="nav-mobile">
    <button class="mobile-accordion">Iparágak ▾</button>
    <div class="mobile-panel">
      <a href="#">Fodrászat</a>
      <a href="#">Masszázs</a>
      <a href="#">Kozmetika</a>
      <a href="#">Egészség</a>
    </div>

    <button class="mobile-accordion">Árak ▾</button>
    <div class="mobile-panel">
      <a href="#">Ingyenes próbaidőszak</a>
      <a href="#">Basic csomag</a>
      <a href="#">Pro csomag</a>
    </div>

    <a href="#">Belépés</a>
    <a href="#">Üzleti Regisztráció</a>
  </nav>
</header>

<!-- =====================
   MOBIL JS
===================== -->
<script>
document.querySelectorAll('.mobile-accordion').forEach(btn => {
  btn.addEventListener('click', () => {
    const panel = btn.nextElementSibling;
    panel.style.maxHeight = panel.style.maxHeight ? null : panel.scrollHeight + "px";
  });
});
</script>

</body>
</html>
