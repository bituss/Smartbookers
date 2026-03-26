<?php
declare(strict_types=1);

session_start();
include '../includes/header.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
  header("Location: /Smartbookers/public/login.php");
  exit;
}

$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) {
  die("Kapcsolódási hiba: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

$userId = (int)$_SESSION['user_id'];
$role = (string)$_SESSION['role']; // 'user' vagy 'provider'

if ($role !== 'user' && $role !== 'provider') {
  echo "<div style='padding:20px;'>Hibás szerepkör.</div>";
  include '../includes/footer.php';
  exit;
}

$conversationId = (int)($_GET['conversation_id'] ?? 0);
if ($conversationId <= 0) {
  echo "<div style='padding:20px;'>Nincs kiválasztott beszélgetés.</div>";
  include '../includes/footer.php';
  exit;
}

/**
 * ProviderId (ha provider)
 */
$providerId = 0;
if ($role === 'provider') {
  $providerId = (int)($_SESSION['provider_id'] ?? 0);
  if ($providerId <= 0) {
    $p = $mysqli->prepare("SELECT id FROM providers WHERE user_id=? LIMIT 1");
    $p->bind_param("i", $userId);
    $p->execute();
    $row = $p->get_result()->fetch_assoc();
    $providerId = (int)($row['id'] ?? 0);
    $_SESSION['provider_id'] = $providerId;
  }
  if ($providerId <= 0) {
    echo "<div style='padding:20px;'>Nincs szolgáltató profil.</div>";
    include '../includes/footer.php';
    exit;
  }
}

/**
 * Jogosultság ellenőrzés + cím (kivel beszélek)
 */
$title = "Chat";

if ($role === 'user') {
  $stmt = $mysqli->prepare("
    SELECT c.id, p.business_name AS title
    FROM conversations c
    JOIN providers p ON p.id = c.provider_id
    WHERE c.id=? AND c.user_id=?
    LIMIT 1
  ");
  $stmt->bind_param("ii", $conversationId, $userId);
} else {
  $stmt = $mysqli->prepare("
    SELECT c.id, u.name AS title
    FROM conversations c
    JOIN users u ON u.id = c.user_id
    WHERE c.id=? AND c.provider_id=?
    LIMIT 1
  ");
  $stmt->bind_param("ii", $conversationId, $providerId);
}

$stmt->execute();
$conv = $stmt->get_result()->fetch_assoc();

if (!$conv) {
  echo "<div style='padding:20px;'>Nincs jogosultság ehhez a beszélgetéshez.</div>";
  include '../includes/footer.php';
  exit;
}

$title = (string)$conv['title'];

/**
 * Olvasottra jelölés (csak a másik fél üzeneteit)
 */
if ($role === 'user') {
  $stmt = $mysqli->prepare("
    UPDATE messages
    SET seen_by_user=1
    WHERE conversation_id=? AND by_provider=1
  ");
} else {
  $stmt = $mysqli->prepare("
    UPDATE messages
    SET seen_by_provider=1
    WHERE conversation_id=? AND by_provider=0
  ");
}
$stmt->bind_param("i", $conversationId);
$stmt->execute();

/**
 * Üzenetek (kezdeti betöltés)
 */
$stmt = $mysqli->prepare("
  SELECT id, by_provider, body, created_at
  FROM messages
  WHERE conversation_id=?
  ORDER BY id ASC
  LIMIT 300
");
$stmt->bind_param("i", $conversationId);
$stmt->execute();
$msgs = $stmt->get_result();

$lastId = 0;
?>
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Chat</title>
<style>
  body{font-family:Inter, sans-serif; margin:0; background:#f6f7fb;}
  .wrap{max-width:900px; margin:24px auto; padding:0 14px;}
  .card{background:#fff; border-radius:14px; box-shadow:0 10px 25px rgba(0,0,0,.08); overflow:hidden;}
  .head{padding:14px 16px; border-bottom:1px solid #e5e7eb; font-weight:800;}
  .msgs{padding:14px 16px; height:60vh; overflow:auto; background:#fafafa;}
  .m{max-width:75%; padding:10px 12px; border-radius:14px; margin:8px 0; line-height:1.35;}
  .me{margin-left:auto; background:#24256e; color:#fff;}
  .other{margin-right:auto; background:#e5e7eb; color:#0f172a;}
  .time{font-size:.75rem; opacity:.75; margin-top:4px;}
  .form{display:flex; gap:10px; padding:12px 16px; border-top:1px solid #e5e7eb;}
  textarea{flex:1; resize:none; border-radius:12px; border:1px solid #cbd5e1; padding:10px;}
  button{border:0; border-radius:12px; padding:10px 14px; font-weight:800; color:#fff; background:linear-gradient(135deg,#24256e,#000); cursor:pointer;}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">💬 <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>

    <div class="msgs" id="msgs">
      <?php while($m = $msgs->fetch_assoc()):
        $lastId = max($lastId, (int)$m['id']);
        $isMe = ($role === 'provider') ? (bool)$m['by_provider'] : !(bool)$m['by_provider'];
      ?>
        <div class="m <?= $isMe ? 'me' : 'other' ?>" data-id="<?= (int)$m['id'] ?>">
          <?= nl2br(htmlspecialchars((string)$m['body'], ENT_QUOTES, 'UTF-8')) ?>
          <div class="time"><?= htmlspecialchars((string)$m['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      <?php endwhile; ?>
    </div>

    <!-- NINCS már szerver oldali POST küldés, csak JS -->
    <form class="form" id="chatForm" autocomplete="off">
      <textarea id="body" rows="2" placeholder="Írj üzenetet..."></textarea>
      <button type="submit">Küldés</button>
    </form>
  </div>
</div>

<script>
  window.CHAT = {
    conversationId: <?= (int)$conversationId ?>,
    lastId: <?= (int)$lastId ?>,
    role: "<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"
  };
</script>
<script src="/Smartbookers/js/chat.js"></script>

<?php include '../includes/footer.php'; ?>
</body>
</html>