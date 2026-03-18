<?php
session_start();

$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) die("Kapcsolódási hiba: " . $mysqli->connect_error);
$mysqli->set_charset("utf8mb4");

// provider ne lássa
if (isset($_SESSION['role']) && $_SESSION['role'] === 'provider') {
  header("Location: /Smartbookers/business/provider_place.php");
  exit;
}

function slugify_hu(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = [
    'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u',
    'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ö'=>'o','Ő'=>'o','Ú'=>'u','Ü'=>'u','Ű'=>'u',
  ];
  $s = strtr($s, $map);
  $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
  $s = trim($s, '-');
  return $s;
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
  header("Location: /Smartbookers/public/index.php");
  exit;
}

$subFilterSlug = trim((string)($_GET['sub'] ?? ''));
$subFilterSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower($subFilterSlug));

$stI = $mysqli->prepare("SELECT id, name, description, slug FROM industries WHERE slug=? AND is_active=1 LIMIT 1");
$stI->bind_param("s", $slug);
$stI->execute();
$industry = $stI->get_result()->fetch_assoc();

include '../includes/header.php';

if (!$industry) {
  http_response_code(404);
  echo "<div style='padding:40px; text-align:center;'>Karbantartás alatt...</div>";
  include '../includes/footer.php';
  exit;
}

$defaultAvatar = "/Smartbookers/public/images/avatars/a1.png";
$logged_in = isset($_SESSION['role'], $_SESSION['user_id']);
$role = $logged_in ? (string)$_SESSION['role'] : '';
$isUser = $logged_in && $role === 'user';

$slugToServiceName = [
  'fodraszat' => 'Fodrászat',
  'mukorom'   => 'Műköröm',
  'egeszseg'  => 'Egészség',
  'masszazs'  => 'Masszázs',
  'kozmetika' => 'Kozmetikus',
];

$serviceName = $slugToServiceName[$industry['slug']] ?? $industry['name'];

$stS = $mysqli->prepare("SELECT id FROM services WHERE name=? LIMIT 1");
$stS->bind_param("s", $serviceName);
$stS->execute();
$serviceRow = $stS->get_result()->fetch_assoc();
$serviceId = (int)($serviceRow['id'] ?? 0);

if ($serviceId <= 0) {
  echo "<div style='padding:40px; text-align:center;'>Ehhez az iparághoz nincs beállítva szolgáltatás (service).</div>";
  include '../includes/footer.php';
  exit;
}

/* 1) MINDEN alszolgáltatás mindig jön a DB-ből */
$subServices = [];
$stSub = $mysqli->prepare("SELECT id, name FROM sub_services WHERE service_id=? ORDER BY name ASC");
$stSub->bind_param("i", $serviceId);
$stSub->execute();
$rsSub = $stSub->get_result();
while ($r = $rsSub->fetch_assoc()) {
  $r['slug'] = slugify_hu((string)$r['name']);
  $subServices[] = $r;
}

// sub filter id
$subFilterId = 0;
if ($subFilterSlug !== '') {
  foreach ($subServices as $ss) {
    if ($ss['slug'] === $subFilterSlug) {
      $subFilterId = (int)$ss['id'];
      break;
    }
  }
}

/* 2) Szabad idők lekérése (csak időpontok), és hozzárendelés sub_service_id szerint */
$sql = "
  SELECT
    a.sub_service_id,
    p.id AS provider_id,
    p.business_name,
    COALESCE(NULLIF(p.avatar,''), ?) AS provider_avatar,
    COALESCE(NULLIF(p.bio,''), '') AS bio,
    CONCAT(
        COALESCE(p.utca,''), ' ',
        COALESCE(p.hazszam,''), ', ',
        COALESCE(t.nev,'')
    ) AS location,  -- <-- ide jön a helyszín

    a.id AS availability_id,
    a.slot_date,
    a.start_time,
    a.end_time,
    a.slot_minutes

  FROM provider_availability a
  JOIN providers p
    ON p.id = a.provider_id
  LEFT JOIN telepulesek t
    ON t.id = p.telepules_id
  LEFT JOIN bookings b
    ON b.provider_id = p.id
   AND b.booking_time = CONCAT(a.slot_date, ' ', a.start_time)
   AND b.cancelled_at IS NULL

  WHERE a.is_active = 1
    AND p.service_id = ?
    AND a.sub_service_id IS NOT NULL
    AND CONCAT(a.slot_date, ' ', a.start_time) >= NOW()
    AND b.id IS NULL
    " . ($subFilterId > 0 ? "AND a.sub_service_id = ?" : "") . "

  ORDER BY a.sub_service_id ASC, p.business_name ASC, a.slot_date ASC, a.start_time ASC
";
if ($subFilterId > 0) {
  $st = $mysqli->prepare($sql);
  $st->bind_param("sii", $defaultAvatar, $serviceId, $subFilterId);
} else {
  $st = $mysqli->prepare($sql);
  $st->bind_param("si", $defaultAvatar, $serviceId);
}
$st->execute();
$res = $st->get_result();

$bySub = []; // sub_service_id -> providers list
while ($row = $res->fetch_assoc()) {
  $ssid = (int)$row['sub_service_id'];
  $pid  = (int)$row['provider_id'];

  if (!isset($bySub[$ssid])) $bySub[$ssid] = [];

  if (!isset($bySub[$ssid][$pid])) {
    $bySub[$ssid][$pid] = [
      'provider_id' => $pid,
      'business_name' => $row['business_name'],
      'avatar' => $row['provider_avatar'] ?: $defaultAvatar,
      'bio' => $row['bio'],
      'location' => $row['location'], // <-- ide jött
      'slots' => []
    ];
  }

  $bySub[$ssid][$pid]['slots'][] = [
    'availability_id' => (int)$row['availability_id'],
    'slot_date' => $row['slot_date'],
    'start_time' => $row['start_time'],
    'end_time' => $row['end_time'],
    'slot_minutes' => (int)$row['slot_minutes'],
  ];
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($industry['name'], ENT_QUOTES, 'UTF-8') ?> – SmartBookers</title>
  <style>
    body{background:linear-gradient(135deg,#24256e,#ffffff); min-height:100vh;}
    .wrap{max-width:1200px; margin:24px auto; padding:0 12px;}
    .heros{
  position: relative;
  background: linear-gradient(135deg, #24256e 0%, #1e293b 100%);
  border-radius: 22px;
  padding: 60px 60px;
  color: white;
  box-shadow: 
    0 25px 60px rgba(0,0,0,0.35),
    inset 0 1px 0 rgba(255,255,255,0.08);
  overflow: hidden;
}

/* finom fény effekt */
.heros::before{
  content:"";
  position:absolute;
  top:-120px;
  right:-120px;
  width:400px;
  height:400px;
  background: radial-gradient(circle, rgba(255,255,255,0.12), transparent 70%);
  transform: rotate(20deg);
}

.heros h1{
  font-size: 38px;
  font-weight: 900;
  margin: 0 0 16px;
  letter-spacing: -1px;
}

.heros p{
  font-size: 17px;
  opacity: 0.92;
  max-width: 720px;
  line-height: 1.6;
  margin: 0;
}
@media (max-width: 900px){
  .heros{
    padding: 45px 30px;
  }
  .heros h1{
    font-size: 34px;
  }
  .heros p{
    font-size: 15px;
  }
}

    .section{margin-top:18px;background:rgba(255,255,255,0.92);border-radius:18px;padding:18px;box-shadow:0 10px 25px rgba(0,0,0,.10);}
    .sectionTitle{margin:0 0 10px;font-weight:900;color:#0f172a;font-size:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;}
    .sectionHint{font-size:12px;color:#64748b;font-weight:800;margin:0 0 14px;}

    .grid{display:grid;grid-template-columns:repeat(3, minmax(0, 1fr));gap:14px;}
    .card{background:rgba(255,255,255,0.92);border-radius:18px;padding:16px;box-shadow:0 10px 25px rgba(0,0,0,.08);display:flex;flex-direction:column;gap:12px;border:1px solid rgba(15,23,42,0.08);}

    .top{display:flex;gap:12px;align-items:center;}
    .av{width:56px;height:56px;border-radius:999px;border:3px solid rgba(36,37,110,.18);object-fit:cover;background:#fff;flex:0 0 auto;}
    .meta{min-width:0; flex:1;}
    .name{font-weight:900;color:#0f172a;margin:0;font-size:15px;}
    .bio{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.35;}

    .slotsTitle{font-weight:900;color:#0f172a;font-size:13px;margin:4px 0 0;}
    .slots{display:flex;flex-direction:column;gap:10px;}
    .slot{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 12px;border-radius:14px;border:1px solid rgba(15,23,42,0.10);background:rgba(15,23,42,0.02);}
    .dt{font-weight:900;color:#0f172a;font-size:13px;}
    .btn{display:inline-flex;padding:10px 12px;border-radius:12px;font-weight:900;color:#fff;background:linear-gradient(135deg,#24256e,#000);white-space:nowrap;text-decoration:none;}
    .btn.light{background:#fff;color:#24256e;border:2px solid #24256e;}
    .empty{color:#64748b;font-weight:900;padding:10px 0;}

    @media (max-width: 900px){.grid{grid-template-columns:repeat(2, minmax(0, 1fr));}}
    @media (max-width: 560px){.grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="heros" >
    <span style="display:inline-block;
background:rgba(255,255,255,0.15);
padding:6px 14px;
border-radius:999px;
font-size:27px;
font-weight:800;
margin-bottom:14px;">
IPARÁG
</span>
      <h1><?= htmlspecialchars($industry['name'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p><?= htmlspecialchars($industry['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <?php foreach ($subServices as $ss): ?>
      <?php
        $ssid = (int)$ss['id'];
        $secName = (string)$ss['name'];
        $secSlug = (string)$ss['slug'];

        if ($subFilterId > 0 && $ssid !== $subFilterId) continue;

        $providers = $bySub[$ssid] ?? [];
        $sectionId = "sub-" . htmlspecialchars($secSlug, ENT_QUOTES, 'UTF-8');
      ?>

      <div class="section" id="<?= $sectionId ?>">
        <div class="sectionTitle">
          <span><?= htmlspecialchars($secName, ENT_QUOTES, 'UTF-8') ?></span>
          <span style="font-size:12px;color:#64748b;font-weight:900;">
            <?= count($providers) ?> Szolgáltató
          </span>
        </div>

       
        <?php if (count($providers) === 0): ?>
          <div class="empty">Jelenleg nincs szabad időpont ennél az alszolgáltatásnál.</div>
        <?php else: ?>
          <div class="grid">
            <?php foreach($providers as $p): ?>
              <div class="card">
                <div class="top">
                  <img class="av" src="<?= htmlspecialchars($p['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="avatar">
                  <div class="meta">
                    <p class="name"><?= htmlspecialchars($p['business_name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="bio"><?= htmlspecialchars($p['bio'] ?: 'Bemutatkozás hamarosan.', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="location" style="font-size:13px;color:#64748b;font-weight:700;">
  <?= htmlspecialchars($p['location'], ENT_QUOTES, 'UTF-8') ?>
</p>
                  </div>
                </div>

                <div class="slotsTitle">Szabad időpontok</div>

                <div class="slots">
                  <?php
                    $shown = 0;
                    foreach($p['slots'] as $s):
                      $shown++;
                      if ($shown > 6) break;
                      $dt = date("Y-m-d H:i", strtotime($s['slot_date'] . ' ' . $s['start_time']));
                  ?>
                    <div class="slot">
                      <div class="dt"><?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?></div>

                      <?php if ($isUser): ?>
                        <a class="btn" href="/Smartbookers/user/book.php?availability_id=<?= (int)$s['availability_id'] ?>">
                          Foglalás
                        </a>
                      <?php else: ?>
                        <a class="btn light" href="/Smartbookers/public/login.php">
                          Jelentkezz be
                        </a>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>

                  <?php if (count($p['slots']) > 6): ?>
                    <div style="color:#64748b;font-size:12px;font-weight:800;">
                      + <?= (int)(count($p['slots']) - 6) ?> további szabad időpont
                    </div>
                  <?php endif; ?>
                </div>

              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

  </div>

  <?php include '../includes/footer.php'; ?>

  <?php if($subFilterId > 0 && $subFilterSlug !== ''): ?>
  <script>
    const el = document.getElementById("sub-<?= htmlspecialchars($subFilterSlug, ENT_QUOTES, 'UTF-8') ?>");
    if (el) el.scrollIntoView({behavior:"smooth", block:"start"});
  </script>
  <?php endif; ?>
</body>
</html>