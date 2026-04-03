<?php
session_start();

// Get services for the form
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
  $services = $pdo->query("SELECT id, name FROM services ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $services = [];
}

// Get any session messages
$sessionSuccess = $_SESSION['success'] ?? '';
$sessionError = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);

/* ===== HEADER ===== */
include '../includes/header.php';
?>

<link rel="stylesheet" href="/Smartbookers/public/css/providerlogin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<main>
  <div class="card">
    <h2>Vállalkozói bejelentkezés</h2>

    <div id="msgContainer"></div>

    <div class="tabRow">
      <button type="button" id="tabLogin" class="tabBtn">Belépés</button>
      <button type="button" id="tabRegister" class="tabBtn">Regisztráció</button>
    </div>

    <!-- LOGIN -->
    <form id="loginForm" novalidate>
      <p>Email cím:</p>
      <input class="input" type="email" name="email" placeholder="Email cím" required>
      <p>Jelszó:</p>
      <input class="input" type="password" name="password" placeholder="Jelszó" required>
      <button class="primaryBtn" type="submit">Bejelentkezés</button>
    </form>

    <!-- REGISTER -->
    <form id="registerForm" novalidate style="display:none; margin-top:10px;">
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
      <input class="input" type="password" name="password_confirm" required>

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
  const msgContainer = document.getElementById('msgContainer');

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

  function showMessage(message, isError = false) {
    msgContainer.innerHTML = `<div class="msg ${isError ? 'error' : 'success'}">${message}</div>`;
    msgContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearMessage() {
    msgContainer.innerHTML = '';
  }

  // LOGIN FORM SUBMIT
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearMessage();

    const formData = new FormData(loginForm);
    const data = {
      email: formData.get('email'),
      password: formData.get('password')
    };

    try {
      const response = await fetch('/Smartbookers/api/auth/login.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
      });

      const result = await response.json();

      if (response.ok && result.user.role === 'provider') {
        showMessage(result.message);
        // Redirect after 1 second
        setTimeout(() => {
          window.location.href = '/Smartbookers/business/provider_place.php';
        }, 1000);
      } else if (response.ok) {
        showMessage('Ez a fiók nem vállalkozói belépésre való.', true);
      } else {
        showMessage(result.message, true);
      }
    } catch (error) {
      showMessage('Hálózati hiba történt.', true);
    }
  });

  // REGISTER FORM SUBMIT
  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearMessage();

    const formData = new FormData(registerForm);
    const password = formData.get('password');
    const passwordConfirm = formData.get('password_confirm');

    if (password !== passwordConfirm) {
      showMessage('A jelszó és a megerősítés nem egyezik.', true);
      return;
    }

    if (!formData.get('privacy')) {
      showMessage('Az adatkezelési tájékoztatót el kell fogadnod.', true);
      return;
    }

    const data = {
      name: formData.get('name'),
      business_name: formData.get('business_name'),
      email: formData.get('email'),
      password: password,
      password_confirm: passwordConfirm,
      phone: formData.get('phone'),
      service_id: formData.get('service_id'),
      zip: formData.get('zip'),
      city: formData.get('city'),
      utca: formData.get('utca'),
      hazszam: formData.get('hazszam')
    };

    try {
      const response = await fetch('/Smartbookers/api/auth/register_provider.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
      });

      const result = await response.json();

      if (response.ok) {
        showMessage(result.message);
        registerForm.reset();
        setTimeout(() => {
          setTab('login');
          showMessage('Most már be tudsz jelentkezni!');
        }, 1500);
      } else {
        showMessage(result.message, true);
      }
    } catch (error) {
      showMessage('Hálózati hiba történt.', true);
    }
  });

  tabLogin.addEventListener('click', () => setTab('login'));
  tabRegister.addEventListener('click', () => setTab('register'));

  // Display session messages if any
  const sessionSuccess = <?= json_encode($sessionSuccess) ?>;
  const sessionError = <?= json_encode($sessionError) ?>;

  if (sessionSuccess) {
    showMessage(sessionSuccess);
  } else if (sessionError) {
    showMessage(sessionError, true);
  }

  setTab('login');
})();
</script>

<?php include '../includes/footer.php'; ?>