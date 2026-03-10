<?php include '../includes/header.php'; ?>
<?php


// MySQLi kapcsolat
$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) {
    die("Kapcsolódási hiba: " . $mysqli->connect_error);
}

$subscription_started = false;

// Ha be van jelentkezve és a gombot nyomják
if (isset($_SESSION['user_id']) && isset($_POST['start_pro'])) {
    $user_id = $_SESSION['user_id'];

    // Ellenőrizzük, hogy a felhasználó vállalkozó-e
    $result = $mysqli->query("SELECT role, pro_start FROM users WHERE id = $user_id");
    $row = $result->fetch_assoc();

    if ($row['role'] === 'provider') {
        if (empty($row['pro_start'])) {
            $now = date('Y-m-d H:i:s');
            $mysqli->query("UPDATE users SET pro_start = '$now' WHERE id = $user_id");
        }
        $subscription_started = true;
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pro csomag</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Inter', sans-serif;
    margin: 0;
    padding: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #24256e, #ffffff);
    color: #fff;
}

header {
    text-align: center;
    
}

header h1 {
    font-size: 2.8rem;
    margin-bottom: 10px;
}

header p {
    font-size: 1.2rem;
    color: #d1d5db;
}

.package-card {
    background: rgba(255,255,255,0.95);
    color: #111827;
    border-radius: 16px;
    padding: 40px 30px;
    max-width: 450px;
    margin: 0 auto 60px auto;
    text-align: center;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s;
}

.package-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 32px rgba(0,0,0,0.25);
}

.package-card h2 {
    margin-bottom: 15px;
    font-size: 2rem;
}

.package-card p {
    font-size: 1.1rem;
    margin-bottom: 30px;
    color: #4b5563;
}

.price {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 20px;
}

.btn {
    padding: 16px 32px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #24256e, #000000);
    color: white;
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    font-size: 1.1rem;
    text-align: center;
}

.btn-primary:hover {
    transform: translateY(-4px) scale(1.05);
    box-shadow: 0 12px 24px rgba(0,0,0,0.3);
}

.btn-primary:active {
    transform: translateY(-2px) scale(1);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

.btn-block {
    display: block;
    width: 100%;
    margin: 10px 0;
}

.info-section {
    text-align: center;
    max-width: 800px;
    margin: 0 auto 60px auto;
    padding: 0 20px;
}

.info-section h3 {
    font-size: 1.8rem;
    margin-bottom: 15px;
}

.info-section p {
    font-size: 1.1rem;
    color: #d1d5db;
}

@media (max-width: 768px) {
    header h1 { font-size: 2rem; }
    .package-card { padding: 30px 20px; }
}
</style>
</head>
<body>

<header>
    <h1>Pro csomag</h1>
    <p>Csatlakozz vállalkozóként, és kezeld több szolgáltatásodat egy helyen, éves 30 000 Ft-ért!</p>
</header>

<div class="package-card">
    <h2>Pro csomag</h2>
    <p class="price">30 000 Ft / év</p>
    <p>Vállalkozóként korlátlan számú szolgáltatást tölthetsz fel, és teljes hozzáférést kapsz minden funkcióhoz.</p>

    <?php if (isset($_SESSION['user_id'])): ?>
        <?php if ($_SESSION['role'] !== 'provider'): ?>
            <p style="color:#ef4444; font-weight:600;">Csak vállalkozói fiókkal érhető el. Kérlek jelentkezz be megfelelő fiókkal.</p>
            <a href="login.php" class="btn btn-primary btn-block">Bejelentkezés</a>
        <?php else: ?>
            <?php if ($subscription_started): ?>
                <p style="color:#10b981; font-weight:600;">A Pro csomag előfizetésed aktív!</p>
            <?php else: ?>
                <form method="POST">
                    <button type="submit" name="start_pro" class="btn btn-primary btn-block">Előfizetés indítása</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <a href="admin\adminlogin.php" class="btn btn-primary btn-block">Előfizetés indítása</a>
    <?php endif; ?>
</div>

<div class="info-section">
    <h3>Mi történik az előfizetés után?</h3>
    <p>Éves 30 000 Ft-ért vállalkozóként korlátlan számú szolgáltatást tölthetsz fel és teljes hozzáférést kapsz minden funkcióhoz. Ha még nincs vállalkozói fiókod, regisztrálj most!</p>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
