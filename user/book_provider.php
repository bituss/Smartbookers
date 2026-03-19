<?php
session_start();
/*
1.: Meg kell vizsgálni, hogy van-e prodiver_id. Ha nincs, akkor írja ki, hogy kérjük scenneljen be egy cég qr kódot
2: Vizsgáljuk meg, hogy bevan-e jelentkezve a felhasználó
Ha nincs, akkor állítsa be a $_SESSION["book_provider"]-t - ide a provider_id fog értékként bekerülni - majd dobjon át a bejelentkezés oldalra
3.: Kilistázzuk a provider-hez tartozó összes időpontot a iparágas foglalás alapján
Foglaláskor pedig a többi foglalásnak mebfelelően hasztódik végre a foglalás(book.php-n keresztül)

+.: Ha marad idő: Belehetne tenni egy iparágas szűrőt. Alapból a cég összes foglalható időpontját mutatná egyébként pedig csak  a leszűrt paraméterek alapján
+.: Továbbfejlesztési funkció: Necsak egy időpontot lehessen így foglalni egyszerre, hanem többet is.
*/
$error = "";
$providerId = $_GET["provider_id"] ?? null;
$_SESSION["book_provider"] = null;

if(!$providerId) { 
    $error = "Kérjük scanneljen be egy céges qr-kódot";
}  else {
    if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
        $_SESSION["book_provider"] = $providerId;
        header("Location: /Smartbookers/public/login.php");
        exit;
    }
}

if($error != "") {?>
    <div class="msg"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php } else {
    $mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
    if ($mysqli->connect_error) die("Kapcsolódási hiba: " . $mysqli->connect_error);
    $mysqli->set_charset("utf8mb4");

    $st = $mysqli->prepare("
    SELECT
        a.id,
        a.provider_id,
        a.sub_service_id,
        a.slot_date,
        a.start_time,
        a.is_active,
        p.business_name,
        ss.name AS sub_service_name
    FROM provider_availability a
    JOIN providers p ON p.id = a.provider_id
    LEFT JOIN sub_services ss ON ss.id = a.sub_service_id
    WHERE a.id = ?
    ");
    $st->bind_param("i", $providerId);
    $st->execute();
    $slot = $st->get_result()->fetch_assoc();

    echo "<pre>";
    print_r($slot);
    echo "</pre>";
}