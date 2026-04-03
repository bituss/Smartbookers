<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$logged_in = isset($_SESSION['role'], $_SESSION['user_id']);
$role = $logged_in ? (string)$_SESSION['role'] : '';

// ===== INAKTÍV FELHASZNÁLÓK KIZÁRÁSA =====
if ($logged_in) {
  try {
    $pdo_check = new PDO('mysql:host=localhost;dbname=idopont_foglalas;charset=utf8mb4', 'root', '');
    
    // Ellenőrizzük, hogy létezik-e a deactivated_at oszlop
    $checkColumn = $pdo_check->query("SHOW COLUMNS FROM users LIKE 'deactivated_at'")->fetch();
    $hasDeactivated = !empty($checkColumn);
    
    if ($hasDeactivated) {
      $stmt_check = $pdo_check->prepare("SELECT deactivated_at FROM users WHERE id = ? LIMIT 1");
      $stmt_check->execute([(int)$_SESSION['user_id']]);
      $user_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
      
      if ($user_check && $user_check['deactivated_at'] !== null) {
        // Felhasználó inaktív - kijelentkeztetés
        session_destroy();
        $_SESSION = [];
        $logged_in = false;
        $role = '';
        header("Location: /Smartbookers/public/logout.php?reason=inactive");
        exit;
      }
    }
  } catch (Exception $e) {
    // Hiba a DB kapcsolódásnál - ne szakítsa meg az oldalt
  }
}

// ===== PROFILKÉP (user/provider) =====
$defaultAvatar = "/Smartbookers/public/images/avatars/a1.png";
$avatarUrl = $defaultAvatar;

if ($logged_in) {
  // session cache
  if (!empty($_SESSION['avatar'])) {
    $avatarUrl = (string)$_SESSION['avatar'];
  } else {
    $mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
    if (!$mysqli->connect_error) {
      $uid = (int)$_SESSION['user_id'];

      if ($role === 'user') {
        $st = $mysqli->prepare("SELECT avatar FROM users WHERE id=? LIMIT 1");
        $st->bind_param("i", $uid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if (!empty($row['avatar'])) $avatarUrl = (string)$row['avatar'];
      } elseif ($role === 'provider') {
        $st = $mysqli->prepare("SELECT avatar FROM providers WHERE user_id=? LIMIT 1");
        $st->bind_param("i", $uid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if (!empty($row['avatar'])) $avatarUrl = (string)$row['avatar'];
      }

      $_SESSION['avatar'] = $avatarUrl; // cache
    }
  }
}


// ✅ Iparágak menü csak akkor, ha NEM provider
$showIndustries = (!$logged_in) || ($role !== 'provider');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<title>SmartBookers</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/Smartbookers/public/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<header class="app-header">

  <div class="header-inner">

    <!-- LOGO -->
    <div class="logo">
      <a href="/Smartbookers/public/index.php">
        <img src="/Smartbookers/public/images/smartbookers-logo.png" alt="SmartBookers logó" class="logo-img">
      </a>
    </div>

    <!-- NAV DESKTOP -->
    <nav class="nav-desktop">

      <!-- ✅ IPARÁGAK: vendég + user + admin (provider nem) -->
      <?php if($showIndustries): ?>
       <div class="dropdown industries-dd"  >
  <button class="dropdown-toggle btn small"   type="button">Iparágak ▾</button>

  <div class="dropdown-menu" style="margin-top: -7px;">

    <!-- Kozmetika -->
    <div class="dd-item has-sub" >
      <a class="dd-link" href="/Smartbookers/public/industry.php?slug=kozmetika">
        Kozmetika <span class="arrow">▸</span>
      </a>
      <div class="dropdown-submenu" style="margin-left: -1px;">
        <a href="/Smartbookers/public/industry.php?slug=kozmetika&sub=arckezeles">Arckezelések</a>
        <a href="/Smartbookers/public/industry.php?slug=kozmetika&sub=gyantazas">Gyantázás</a>
        <a href="/Smartbookers/public/industry.php?slug=kozmetika&sub=muszempilla">Műszempilla</a>
        <a href="/Smartbookers/public/industry.php?slug=kozmetika&sub=smink">Smink</a>
        <a href="/Smartbookers/public/industry.php?slug=kozmetika&sub=szemoldok-formazas">Szemöldök formázás</a>
        
      </div>
    </div>

    <!-- Fodrászat -->
    <div class="dd-item has-sub">
  <a class="dd-link" href="/Smartbookers/public/industry.php?slug=fodraszat">
    Fodrászat <span class="arrow">▸</span>
  </a>
  <div class="dropdown-submenu" style="margin-left: -1px;">

    <a href="/Smartbookers/public/industry.php?slug=fodraszat&sub=ferfi-hajvagas">
      Férfi hajvágás
    </a>

    <a href="/Smartbookers/public/industry.php?slug=fodraszat&sub=festes">
      Festés
    </a>

    <a href="/Smartbookers/public/industry.php?slug=fodraszat&sub=hajmosas-szaritas">
      Hajmosás + szárítás
    </a>

    <a href="/Smartbookers/public/industry.php?slug=fodraszat&sub=melir">
      Melír
    </a>

    <a href="/Smartbookers/public/industry.php?slug=fodraszat&sub=noi-hajvagas">
      Női hajvágás
    </a>

  </div>
</div>

    <!-- Műköröm -->
<div class="dd-item has-sub">
  <a class="dd-link" href="/Smartbookers/public/industry.php?slug=mukorom">
    Műköröm <span class="arrow">▸</span>
  </a>

  <div class="dropdown-submenu" style="margin-left: -1px;">

    <a href="/Smartbookers/public/industry.php?slug=mukorom&sub=gellakk">
      Géllakk
    </a>

    <a href="/Smartbookers/public/industry.php?slug=mukorom&sub=porcelan">
      Porcelán
    </a>

    <a href="/Smartbookers/public/industry.php?slug=mukorom&sub=manikur">
      Manikűr
    </a>

  </div>
</div>

    <!-- Masszázs -->
<div class="dd-item has-sub">
  <a class="dd-link" href="/Smartbookers/public/industry.php?slug=masszazs">
    Masszázs <span class="arrow">▸</span>
  </a>

  <div class="dropdown-submenu" style="margin-left: -1px;">

    <a href="/Smartbookers/public/industry.php?slug=masszazs&sub=relax-masszazs">
      Relax masszázs
    </a>

    <a href="/Smartbookers/public/industry.php?slug=masszazs&sub=sport-masszazs">
      Sport masszázs
    </a>

    <a href="/Smartbookers/public/industry.php?slug=masszazs&sub=sved-masszazs">
      Svéd masszázs
    </a>

  </div>
</div>

   <!-- Egészség -->
<div class="dd-item has-sub">
  <a class="dd-link" href="/Smartbookers/public/industry.php?slug=egeszseg">
    Egészség <span class="arrow">▸</span>
  </a>

  <div class="dropdown-submenu" style="margin-left: -1px;">

    <a href="/Smartbookers/public/industry.php?slug=egeszseg&sub=dietetika">
      Dietetika
    </a>

    <a href="/Smartbookers/public/industry.php?slug=egeszseg&sub=gyogytorna">
      Gyógytorna
    </a>

    <a href="/Smartbookers/public/industry.php?slug=egeszseg&sub=kontroll">
      Kontroll
    </a>

    <a href="/Smartbookers/public/industry.php?slug=egeszseg&sub=tanacsadas">
      Tanácsadás
    </a>

  </div>
</div>

  </div>
</div>

      <?php endif; ?>

      <?php if($logged_in): ?>

        <?php if($role === 'user'): ?>
          <a href="/Smartbookers/user/profile.php" class="btn small" style="gap:10px;">
            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                 alt="Profilkép"
                 style="width:22px;height:22px;border-radius:999px;object-fit:cover;background:#fff;">
            Profilom
          </a>

        <?php elseif($role === 'provider'): ?>
          <a href="/Smartbookers/business/provider_profile.php" class="btn small" style="gap:10px;">
            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                 alt="Profilkép"
                 style="width: 22px;px;height:22px;border-radius:999px;object-fit:cover;background:#fff;">
            Profilom
          </a>
          <a href="/Smartbookers/business/provider_place.php" class="btn small">Vállalkozás</a>

        <?php elseif($role === 'admin'): ?>
          <a href="/Smartbookers/admin/dashboard.php" class="btn small">Admin</a>
        <?php endif; ?>

        <div class="chat-dd" id="chatDD">
          <button type="button" class="btn small chat-btn" id="chatBtn">
            Chat
            <span class="chat-badge" id="chatBadge" style="display:none;">0</span>
          </button>
        </div>

        <a href="#" onclick="openLogoutConfirm(event)" class="btn small danger">Kilépés</a>

      <?php else: ?>


        <a href="/Smartbookers/public/login.php" class="btn small">Felhasználói belépés</a>
        <a href="/Smartbookers/business/provider_login.php" class="btn small">Üzleti belépés</a>
        <a href="/Smartbookers/public/contact.php" class="btn small">Kapcsolat</a>

      <?php endif; ?>

    </nav>

    <!-- MOBIL MENÜ TOGGLE -->
    <button type="button" class="menu-toggle" id="menuToggle">☰</button>

  </div>

  <!-- NAV MOBILE -->
  <nav class="nav-mobile">

    <!-- ✅ IPARÁGAK: vendég + user + admin (provider nem) -->
    <?php if($showIndustries): ?>
     <details class="mobile-dd">
  <summary class="btn small">Iparágak</summary>

  <details class="mobile-sub">
  <summary class="btn small">Kozmetika</summary>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=kozmetika&sub=muszempilla">
    Műszempilla
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=kozmetika&sub=arckezeles">
    Arckezelés
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=kozmetika&sub=smink">
    Smink
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=kozmetika&sub=szemoldok-formazas">
    Szemöldök formázás
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=kozmetika&sub=gyantazas">
    Gyantázás
  </a>
</details>

  <details class="mobile-sub">
  <summary class="btn small">Fodrászat</summary>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=fodraszat&sub=ferfi-hajvagas">
     Férfi hajvágás
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=fodraszat&sub=festes">
     Festés
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=fodraszat&sub=hajmosas-szaritas">
     Hajmosás + szárítás
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=fodraszat&sub=melir">
     Melír
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=fodraszat&sub=noi-hajvagas">
     Női hajvágás
  </a>

</details>

  <details class="mobile-sub">
  <summary class="btn small">Műköröm</summary>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=mukorom&sub=gellakk">
     Géllakk
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=mukorom&sub=porcelan">
     Porcelán
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=mukorom&sub=manikur">
     Manikűr
  </a>
</details>

  <details class="mobile-sub">
  <summary class="btn small">Masszázs</summary>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=masszazs&sub=relax-masszazs">
     Relax masszázs
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=masszazs&sub=sport-masszazs">
     Sport masszázs
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=masszazs&sub=sved-masszazs">
     Svéd masszázs
  </a>
</details>

  <details class="mobile-sub">
  <summary class="btn small">Egészség</summary>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=egeszseg&sub=dietetika">
     Dietetika
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=egeszseg&sub=gyogytorna">
     Gyógytorna
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=egeszseg&sub=kontroll">
     Kontroll
  </a>

  <a class="btn small"
     href="/Smartbookers/public/industry.php?slug=egeszseg&sub=tanacsadas">
     Tanácsadás
  </a>
</details>

</details>

    <?php endif; ?>

    <?php if($logged_in): ?>

      <?php if($role === 'user'): ?>
        <a href="/Smartbookers/user/profile.php" class="btn small" style="gap:10px;">
          <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
               alt="Profilkép"
               style="width:22px;height:22px;border-radius:999px;object-fit:cover;background:#fff;">
          Profilom
        </a>

      <?php elseif($role === 'provider'): ?>
        <a href="/Smartbookers/business/provider_profile.php" class="btn small" style="gap:10px;">
          <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
               alt="Profilkép"
               style="width:22px;height:22px;border-radius:999px;object-fit:cover;background:#fff;">
          Profilom
        </a>
        <a href="/Smartbookers/business/provider_place.php" class="btn small">Vállalkozás</a>

      <?php elseif($role === 'admin'): ?>
        <a href="/Smartbookers/admin/dashboard.php" class="btn small">Admin</a>
      <?php endif; ?>

      <button type="button" class="btn small" id="chatBtnMobile">
        Chat <span class="chat-badge" id="chatBadgeMobile" style="display:none;">0</span>
      </button>

      <a href="#" onclick="openLogoutConfirm(event)" class="btn small danger">Kilépés</a>

    <?php else: ?>

      <a href="/Smartbookers/public/login.php" class="btn small">Felhasználói belépés</a>
      <a href="/Smartbookers/business/provider_login.php" class="btn small">Üzleti belépés</a>
      <a href="/Smartbookers/public/contact.php" class="btn small">Kapcsolat</a>

    <?php endif; ?>

  </nav>

  <?php if($logged_in): ?>
  <div class="chat-menu" id="chatMenu" aria-hidden="true">
    <div class="chat-head">
      <strong>Üzenetek</strong>
      <button type="button" class="chat-close" id="chatClose">✕</button>
    </div>

    <div class="chat-body">
      <div class="chat-threads" id="chatThreads">
        <div class="chat-empty">Betöltés...</div>
      </div>

      <div class="chat-panel">
        <div class="chat-panel-head" id="chatPanelHead">Válassz egy beszélgetést</div>

        <div class="chat-messages" id="chatMessages">
          <div class="chat-empty">Nincs kiválasztott beszélgetés.</div>
        </div>

        <form class="chat-form" id="chatForm" autocomplete="off">
          <input type="hidden" id="chatConversationId" value="">
          <input class="chat-input" id="chatInput" type="text" placeholder="Írj üzenetet..." disabled>
          <button class="chat-send" id="chatSend" type="submit" disabled>Küldés</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

</header>
<div id="logoutModal" class="modalOverlay" style="display:none;">
  <div class="modalBox">

    <div class="icon" style="background:#ef4444;">!</div>

    <h2>Kijelentkezés</h2>
    <p>Biztosan ki szeretnél jelentkezni?</p>

    <div style="display:flex; gap:10px; justify-content:center;">
      <button onclick="confirmLogout()" style="background:#ef4444;">Igen</button>
      <button onclick="closeLogout()">Mégse</button>
    </div>

  </div>
</div>

<script src="/Smartbookers/js/header.js"></script>
