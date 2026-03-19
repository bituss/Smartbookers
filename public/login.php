<?php
session_start();

/* ===== ADATBÁZIS KAPCSOLAT (PDO) ===== */
$host = "localhost";
$db   = "idopont_foglalas";
$user = "root";
$pass = "";

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (PDOException $e) {
  die("Hiba az adatbázishoz való kapcsolódáskor: " . $e->getMessage());
}

$error = "";
$success = "";

/* ===== user role id (roles táblából) ===== */
$roleUserId = 0;
try {
  $r = $pdo->prepare("SELECT id FROM roles WHERE name='user' LIMIT 1");
  $r->execute();
  $roleUserId = (int)($r->fetchColumn() ?: 0);
} catch (Throwable $e) {
  $roleUserId = 0;
}

/* ===== MELYIK MŰVELET? login / register ===== */
$action = (string)($_POST['action'] ?? 'login');

/* =========================================================
   REGISZTRÁCIÓ (Felhasználó)
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'register') {
  $name     = trim((string)($_POST["name"] ?? ''));
  $email    = trim((string)($_POST["email"] ?? ''));
  $password = (string)($_POST["password"] ?? '');

  if ($name === '' || $email === '' || $password === '') {
    $error = "Minden mező kitöltése kötelező!";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Hibás email formátum.";
  } elseif (mb_strlen($password) < 6) {
    $error = "A jelszó legyen legalább 6 karakter.";
  } elseif ($roleUserId <= 0) {
    $error = "Hiba: nincs 'user' szerepkör a roles táblában.";
  } else {
    $chk = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $chk->execute([$email]);

    if ($chk->fetchColumn()) {
      $error = "Ez az email már létezik.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      try {
        $ins = $pdo->prepare("INSERT INTO users (name,email,password,role_id) VALUES (?,?,?,?)");
        $ins->execute([$name, $email, $hash, $roleUserId]);

        $success = "Sikeres regisztráció! Most már be tudsz jelentkezni.";
        $action = 'login';
      } catch (Throwable $e) {
        $error = "Hiba regisztráció közben: " . $e->getMessage();
      }
    }
  }
}

/* =========================================================
   BELÉPÉS (Csak USER) + REDIRECT (MÉG NINCS HTML!)
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'login') {
  $email    = trim((string)($_POST["email"] ?? ''));
  $password = (string)($_POST["password"] ?? '');

  $stmt = $pdo->prepare("
      SELECT u.*, r.name AS role_name
      FROM users u
      JOIN roles r ON r.id = u.role_id
      WHERE u.email = ?
      LIMIT 1
  ");
  $stmt->execute([$email]);
  $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($userRow && password_verify($password, $userRow["password"])) {

    if (($userRow['role_name'] ?? '') !== 'user') {
      $error = "Ez a fiók nem felhasználói belépésre való.";
    } else {
      $_SESSION["user_id"] = (int)$userRow["id"];
      $_SESSION["name"]    = (string)$userRow["name"];
      $_SESSION["role"]    = "user";

      if($_SESSION["book_provider"] && $_SESSION["book_provider"] != null) {
        header("Location: /Smartbookers/user/book_provider.php?provider_id={$_SESSION["book_provider"]}");
      } else {
        header("Location: /Smartbookers/user/profile.php");
      }
      exit;
    }

  } else {
    $error = "Hibás email vagy jelszó.";
  }
}

/* ===== CSAK MOST JÖHET HTML (header include) ===== */
include '../includes/header.php';
?>

<link rel="stylesheet" href="/Smartbookers/public/css/providerlogin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<main>
  <div class="card">
    <h2>Bejelentkezés</h2>

    <?php if($error): ?>
      <div class="msg"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if($success): ?>
      <div class="msg success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="tabRow">
      <button type="button" id="tabLogin" class="tabBtn">Belépés</button>
      <button type="button" id="tabRegister" class="tabBtn">Regisztráció</button>
    </div>

    <!-- LOGIN -->
     
    <form method="POST" id="loginForm" novalidate>
      <input type="hidden" name="action" value="login">
      <p>Email cím: </p>
      <input class="input" type="email" name="email" placeholder="Email cím" required>
      <p>Jelszó: </p>
      <input class="input" type="password" name="password" placeholder="Jelszó" required>
      <button class="primaryBtn" type="submit">Belépés</button>
    </form>

    <!-- REGISTER -->
    <form method="POST" id="registerForm" novalidate style="display:none; margin-top:10px;">
      <input type="hidden" name="action" value="register">
      <p>Név: 🞴</p>
      <input class="input" type="text" name="name" placeholder="Név" required>
      <p>Email cím: 🞴</p>
      <input class="input" type="email" name="email" placeholder="Email cím" required>
      <p>Telefonszám: 🞴</p>
      <input class="input" type="tel" name="phone" placeholder="Telefonszám" pattern="[0-9]{9,15}" required>
      <p>Jelszó: 🞴</p>
      <input class="input" type="password" name="password" placeholder="Jelszó (min. 6 karakter)" required>
      <p>Jelszó megerősítése: 🞴</p>
      <input class="input" type="password" name="password" placeholder="Jelszó megerősítése" required>
      <button class="primaryBtn" type="submit">Regisztráció</button>
    </form>
  </div>
</main>

<script>
(function(){
  const tabLogin = document.getElementById('tabLogin');
  const tabRegister = document.getElementById('tabRegister');
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');

  function setTab(which){
    if(which === 'register'){
      loginForm.style.display = 'none';
      registerForm.style.display = 'block';
      tabRegister.classList.add('active');
      tabLogin.classList.remove('active');
    } else {
      registerForm.style.display = 'none';
      loginForm.style.display = 'block';
      tabLogin.classList.add('active');
      tabRegister.classList.remove('active');
    }
  }

  tabLogin.addEventListener('click', ()=>setTab('login'));
  tabRegister.addEventListener('click', ()=>setTab('register'));

  const hadRegisterError = <?= json_encode($error !== '' && (($_POST['action'] ?? '') === 'register')) ?>;
  setTab(hadRegisterError ? 'register' : 'login');
})();
</script>

<?php include '../includes/footer.php'; ?>
