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

/* ===== szolgáltatások (főkategória) ===== */
$services = [];
try {
  $s = $pdo->query("SELECT id, name FROM services ORDER BY name ASC");
  $services = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $services = [];
  $error = "Hiba a szolgáltatások betöltésekor: " . $e->getMessage();
}

/* ===== provider role id ===== */
$roleProviderId = 0;
try {
  $r = $pdo->prepare("SELECT id FROM roles WHERE name='provider' LIMIT 1");
  $r->execute();
  $roleProviderId = (int)($r->fetchColumn() ?: 0);
} catch (Throwable $e) {
  $roleProviderId = 0;
  $error = "Hiba: nem található 'provider' szerepkör a roles táblában.";
}

/* ===== action ===== */
$action = (string)($_POST['action'] ?? 'login');

/* ===== JELSZÓ SZABÁLY: min. 6 + nagybetű + szám + speciális ===== */
function isStrongPassword(string $pw): bool {
  if (mb_strlen($pw) < 6) return false;
  if (!preg_match('/[A-Z]/', $pw)) return false;
  if (!preg_match('/\d/', $pw)) return false;
  if (!preg_match('/[^A-Za-z0-9]/', $pw)) return false;
  return true;
}

/* =========================================================
   REGISZTRÁCIÓ (Vállalkozó) + TELEPÜLÉS + UTCA/HÁZSZÁM
   + CSAK service_id mentés providers-be (sub_service majd időpontnál)
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'register') {

  $name        = trim((string)($_POST["name"] ?? ''));
  $business    = trim((string)($_POST["business_name"] ?? ''));
  $email       = trim((string)($_POST["email"] ?? ''));
  $password    = (string)($_POST["password"] ?? '');
  $password2   = (string)($_POST["password2"] ?? '');

  $serviceId   = (int)($_POST["service_id"] ?? 0);
  $phone = trim((string)($_POST["phone"] ?? '')); // CSAK EZ KELL

  // cím adatok
  $zip     = trim((string)($_POST["zip"] ?? ''));
  $city    = trim((string)($_POST["city"] ?? ''));
  $utca    = trim((string)($_POST["utca"] ?? ''));
  $hazszam = trim((string)($_POST["hazszam"] ?? ''));

  if (
    $name === '' || $business === '' || $email === '' ||
    $password === '' || $password2 === '' ||
    $serviceId <= 0 ||
    $zip === '' || $city === '' || $utca === '' || $hazszam === ''
  ) {
    $error = "Minden mező kötelező (szolgáltatás + teljes cím).";

  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Hibás email formátum.";

  } elseif (!preg_match('/^[0-9]{4}$/', $zip)) {
    $error = "Az irányítószám 4 számjegy legyen.";

  } elseif ($password !== $password2) {
    $error = "A jelszó és a megerősítés nem egyezik.";

  } elseif (!isStrongPassword($password)) {
    $error = "A jelszónak legalább 6 karakteresnek kell lennie, és tartalmaznia kell: 1 nagybetűt, 1 számot és 1 speciális karaktert.";

  } elseif ($roleProviderId <= 0) {
    $error = "Nincs 'provider' szerepkör a roles táblában.";

  } else {

    // email foglalt?
    $chk = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $chk->execute([$email]);
    if ($chk->fetchColumn()) {
      $error = "Ez az email már létezik.";
    } else {

      // ellenőrzés: service létezik-e
      $checkService = $pdo->prepare("SELECT id FROM services WHERE id=? LIMIT 1");
      $checkService->execute([$serviceId]);
      if (!$checkService->fetchColumn()) {
        $error = "Érvénytelen szolgáltatás választás.";
      } else {

        try {
          $pdo->beginTransaction();

          // 1) település: keres / beszúr
          $selTown = $pdo->prepare("SELECT id FROM telepulesek WHERE iranyitoszam=? AND nev=? LIMIT 1");
          $selTown->execute([$zip, $city]);
          $telepulesId = (int)($selTown->fetchColumn() ?: 0);

          if ($telepulesId <= 0) {
            $insTown = $pdo->prepare("INSERT INTO telepulesek (iranyitoszam, nev) VALUES (?, ?)");
            $insTown->execute([$zip, $city]);
            $telepulesId = (int)$pdo->lastInsertId();
          }

          // 2) user
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $insU = $pdo->prepare("INSERT INTO users (name,email,password,role_id) VALUES (?,?,?,?)");
          $insU->execute([$name, $email, $hash, $roleProviderId]);
          $newUserId = (int)$pdo->lastInsertId();

          // 3) provider (user_id + service_id + cím)
          $industryId = null;
         

          $insP = $pdo->prepare("
            INSERT INTO providers (user_id, business_name, phone, telepules_id, industry_id, service_id, utca, hazszam)
            VALUES (?,?,?,?,?,?,?,?)
          ");
          $insP->execute([
            $newUserId,
            $business,
            $phone,
            $telepulesId,
            $industryId,
            $serviceId,
            $utca,
            $hazszam
          ]);

          $pdo->commit();
          $success = "Sikeres regisztráció! Most már be tudsz jelentkezni.";
          $action = 'login';

        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $error = "Hiba: " . $e->getMessage();
        }
      }
    }
  }
}

/* =========================================================
   BELÉPÉS (Csak PROVIDER)
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === 'login') {

  $email    = trim((string)($_POST["email"] ?? ''));
  $password = (string)($_POST["password"] ?? '');

  // 1) user + role
  $stmt = $pdo->prepare("
    SELECT u.*, r.name AS role_name
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.email = ?
    LIMIT 1
  ");
  $stmt->execute([$email]);
  $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($userRow && password_verify($password, (string)$userRow["password"])) {

    if (($userRow['role_name'] ?? '') !== 'provider') {
      $error = "Hibás adatok vagy nem vállalkozói fiók.";
    } else {

      // 2) provider betöltés user_id alapján (service_id join)
      $p = $pdo->prepare("
        SELECT
          p.*,
          s.name AS service_name,
          t.nev AS telepules_nev,
          t.iranyitoszam
        FROM providers p
        JOIN services s ON s.id = p.service_id
        LEFT JOIN telepulesek t ON t.id = p.telepules_id
        WHERE p.user_id = ?
        LIMIT 1
      ");
      $p->execute([(int)$userRow['id']]);
      $provider = $p->fetch(PDO::FETCH_ASSOC);

      if (!$provider) {
        $error = "Hiányzik a szolgáltatói profil (providers tábla).";
      } else {
        $_SESSION["user_id"] = (int)$userRow["id"];
        $_SESSION["name"]    = (string)$userRow["name"];
        $_SESSION["role"]    = "provider";

        $_SESSION["provider_id"] = (int)$provider["id"];
        $_SESSION["service_id"]  = (int)$provider["service_id"];
        $_SESSION["service"]     = (string)($provider["service_name"] ?? '');

        $_SESSION["telepules"] = trim((string)($provider["iranyitoszam"] ?? '') . " " . (string)($provider["telepules_nev"] ?? ''));
        $_SESSION["utca"]      = (string)($provider["utca"] ?? '');
        $_SESSION["hazszam"]   = (string)($provider["hazszam"] ?? '');

        header("Location: /Smartbookers/business/provider_place.php");
        exit;
      }
    }

  } else {
    $error = "Hibás adatok vagy nem vállalkozói fiók.";
  }
}

/* ===== HEADER ===== */
include '../includes/header.php';
?>

<link rel="stylesheet" href="/Smartbookers/public/css/providerlogin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<main>
  <div class="card">
    <h2>Vállalkozói bejelentkezés</h2>

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
      <p>Email cím:</p>
      <input class="input" type="email" name="email" placeholder="Email cím" required>
      <p>Jelszó:</p>
      <input class="input" type="password" name="password" placeholder="Jelszó" required>
      <button class="primaryBtn" type="submit">Bejelentkezés</button>
    </form>

    <!-- REGISTER -->
    <form method="POST" id="registerForm" novalidate style="display:none; margin-top:10px;">
      <input type="hidden" name="action" value="register">

      <p>Tulajdonos neve: 🞴</p>
      <input class="input" type="text" name="name" required>

      <p>Vállalkozás neve: 🞴</p>
      <input class="input" type="text" name="business_name" required>

      <p>Irányítószám: 🞴</p>
      <input class="input" type="text" name="zip" placeholder="Pl. 1051" required>

      <p>Település: 🞴</p>
      <input class="input" type="text" name="city" placeholder="Pl. Budapest" required>

      <p>Utca: 🞴</p>
      <input class="input" type="text" name="utca" required>

      <p>Házszám: 🞴</p>
      <input class="input" type="text" name="hazszam" required>

      <p>Telefonszám: 🞴</p>
      <input  class="input" type="tel" name="phone" placeholder="Telefonszám" pattern="[0-9]{9,15}" required>


      <p>Email cím: 🞴</p>
      <input class="input" type="email" name="email" required>

      
      <p>Jelszó: 🞴</p>
      <input class="input" type="password" name="password" placeholder="Min. 6, nagybetű + szám + speciális" required>

      <p>Jelszó megerősítése: 🞴</p>
      <input class="input" type="password" name="password2" required>

      <p style="margin:6px 0 0; font-size:13px; opacity:.85;">
        Kötelező: legalább 6 karakter, 1 nagybetű, 1 szám, 1 speciális karakter.
      </p>

      <p>Szolgáltatás: 🞴</p>
      <select class="select" name="service_id" required>
        <option value="">Válassz szolgáltatást</option>
        <?php foreach($services as $sv): ?>
          <option value="<?= (int)$sv['id'] ?>">
            <?= htmlspecialchars($sv['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div class="form-field form-field--full form-check" style="margin-bottom:10px;">
        <input id="privacy" name="privacy" type="checkbox" required>
        <label for="privacy">
          Elfogadom az <a href="#" onclick="return false;" style="text-decoration: underline;">adatkezelési tájékoztatót</a>.
        </label>
      </div>

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