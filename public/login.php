<?php
session_start();

// Get any session messages from previous redirects
$sessionSuccess = $_SESSION['success'] ?? '';
$sessionError = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);

/* ===== CSAK MOST JÖHET HTML (header include) ===== */
include '../includes/header.php';
?>

<link rel="stylesheet" href="/Smartbookers/public/css/providerlogin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<main>
  <div class="card">
    <h2>Bejelentkezés</h2>

    <div id="msgContainer"></div>

    <div class="tabRow">
      <button type="button" id="tabLogin" class="tabBtn">Belépés</button>
      <button type="button" id="tabRegister" class="tabBtn">Regisztráció</button>
    </div>

    <!-- LOGIN -->
    <form id="loginForm" novalidate>
      <p>Email cím: </p>
      <input class="input" type="email" name="email" placeholder="Email cím" required>
      <p>Jelszó: </p>
      <input class="input" type="password" name="password" placeholder="Jelszó" required>
      <button class="primaryBtn" type="submit">Belépés</button>
    </form>

    <!-- REGISTER -->
    <form id="registerForm" novalidate style="display:none; margin-top:10px;">
      <p>Név: 🞴</p>
      <input class="input" type="text" name="name" placeholder="Név" required>
      <p>Email cím: 🞴</p>
      <input class="input" type="email" name="email" placeholder="Email cím" required>
      <p>Jelszó: 🞴</p>
      <input class="input" type="password" name="password" placeholder="Jelszó (min. 6 karakter)" required>
      <p style="margin:6px 0 0; font-size:13px; opacity:.85;">
        Kötelező: legalább 6 karakter, 1 nagybetű, 1 szám, 1 speciális karakter.
      </p>
      <p>Jelszó megerősítése: 🞴</p>
      <input class="input" type="password" name="password_confirm" placeholder="Jelszó megerősítése" required>
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

      if (response.ok) {
        showMessage(result.message);

        // Redirect after 1 second based on role
        const role = result.user?.role || 'user';
        const target = role === 'provider' ? '/Smartbookers/business/provider_place.php' : '/Smartbookers/user/profile.php';

        setTimeout(() => {
          window.location.href = target;
        }, 1000);
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

    const data = {
      name: formData.get('name'),
      email: formData.get('email'),
      password: password
    };

    try {
      const response = await fetch('/Smartbookers/api/auth/register.php', {
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
