<?php
session_start();
include '../includes/header.php';
$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) {
    die("Kapcsolódási hiba: " . $mysqli->connect_error);
}
$error = "";
$success = "";
$allowed_services = [
    'Kozmetika',
    'Masszőr',
    'Műköröm építő',
    'Fodrász',
    'Mentálhigiénia'
];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $service_name = $_POST["service_type"] ?? "";
    if (!$name || !$email || !$password || !$service_name) {
        $error = "Minden mező kitöltése kötelező!";
    } elseif (!in_array($service_name, $allowed_services, true)) {
        $error = "Érvénytelen tevékenység!";
    } else {
        $svcStmt = $mysqli->prepare("SELECT id FROM services WHERE name = ? LIMIT 1");
        $svcStmt->bind_param("s", $service_name);
        $svcStmt->execute();
        $svcRes = $svcStmt->get_result();
        $svcRow = $svcRes ? $svcRes->fetch_assoc() : null;
        if (!$svcRow) {
            $error = "Hiba: a választott szolgáltatás nincs a services táblában: " . htmlspecialchars($service_name, ENT_QUOTES, 'UTF-8');
        } else {
            $serviceId = (int)$svcRow['id'];
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $mysqli->begin_transaction();
            try {
                $uStmt = $mysqli->prepare("
                    INSERT INTO users (name, email, password, role)
                    VALUES (?, ?, ?, 'provider')
                ");
                $uStmt->bind_param("sss", $name, $email, $hashed);
                $uStmt->execute();
                    $newUserId = $mysqli->insert_id;
                $pStmt = $mysqli->prepare("
                    INSERT INTO providers (user_id, business_name, service_id, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $pStmt->bind_param("isi", $newUserId, $name, $serviceId);
                $pStmt->execute();
                $mysqli->commit();
                $success = "Sikeres regisztráció! Most már bejelentkezhetsz.";
            } catch (mysqli_sql_exception $e) {
                $mysqli->rollback();
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    $error = "Ez az email már létezik.";
                } else {
                    $error = "Hiba történt a regisztráció során: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<title>Vállalkozói regisztráció</title>
<link href="https:
<link rel="stylesheet" href="/Smartbookers/public/css/businessdashboard.css">
</head>
<body>
<main>
  <div class="card">
    <h2>Vállalkozói regisztráció</h2>
    <?php if ($error): ?>
      <div class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="msg success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="text" name="name" placeholder="Vállalkozás neve" required>
      <select name="service_type" required>
        <option value="">Válassz tevékenységet</option>
        <?php foreach ($allowed_services as $service): ?>
          <option value="<?= htmlspecialchars($service, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($service, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input type="email" name="email" placeholder="Email cím" required>
      <input type="password" name="password" placeholder="Jelszó" required>
      <button type="submit">Regisztráció</button>
    </form>
    <p style="text-align:center;margin-top:15px;">
      Már van fiókod? <a href="/Smartbookers/business/provider_login.php">Bejelentkezés</a>
    </p>
  </div>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
