<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'provider') {
  header("Location: /Smartbookers/business/provider_login.php");
  exit;
}

$mysqli = new mysqli("localhost", "root", "", "idopont_foglalas");
if ($mysqli->connect_error) die("DB hiba: " . $mysqli->connect_error);
$mysqli->set_charset("utf8mb4");

$userId = (int)$_SESSION['user_id'];

$success = "";
$error = "";

/* =========================
   PROVIDER BETÖLTÉS
========================= */
$st = $mysqli->prepare("
  SELECT
    p.id,
    p.service_id,
    p.business_name,
    p.avatar,
    p.bio,
    p.phone,
    p.telepules_id,
    p.utca,
    p.hazszam,
    u.email
  FROM providers p
  JOIN users u ON u.id = p.user_id
  WHERE p.user_id=?
  LIMIT 1
");
$st->bind_param("i", $userId);
$st->execute();
$provider = $st->get_result()->fetch_assoc();
if (!$provider) die("Hiányzik a providers profil.");

$providerId = (int)$provider['id'];

/* =========================
   TELEPÜLÉSEK LISTA (selecthez)
========================= */
$telepulesek = [];
$rsT = $mysqli->query("SELECT id, nev, iranyitoszam FROM telepulesek ORDER BY nev ASC");
if ($rsT) {
  while ($row = $rsT->fetch_assoc()) $telepulesek[] = $row;
}

/* =========================
   SZOLGÁLTATÁS NÉV
========================= */
$serviceName = '';
$st2 = $mysqli->prepare("
  SELECT s.name
  FROM services s
  JOIN providers p ON p.service_id=s.id
  WHERE p.id=?
  LIMIT 1
");
$st2->bind_param("i", $providerId);
$st2->execute();
$serviceName = (string)($st2->get_result()->fetch_assoc()['name'] ?? '');

/* =========================
   AVATAR KEZELÉS
========================= */
$defaultAvatar = "/Smartbookers/public/images/avatars/a1.png";
$currentAvatar = !empty($provider['avatar']) ? (string)$provider['avatar'] : $defaultAvatar;

$uploadDirFs  = __DIR__ . "/../public/img/providers/";   // fájlrendszer út
$uploadDirWeb = "/Smartbookers/public/img/providers/";   // web út
$maxBytes = 2 * 1024 * 1024; // 2MB
$allowedMime = [
  "image/jpeg" => "jpg",
  "image/png"  => "png",
  "image/webp" => "webp"
];

/* =========================
   POST: PROFIL MENTÉS
   (már a címadatokat is)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
  $businessName = trim((string)($_POST['business_name'] ?? ''));
  $bio          = trim((string)($_POST['bio'] ?? ''));

  $phone        = trim((string)($_POST['phone'] ?? ''));
  $telepulesId  = (int)($_POST['telepules_id'] ?? 0);
  $utca         = trim((string)($_POST['utca'] ?? ''));
  $hazszam      = trim((string)($_POST['hazszam'] ?? ''));

  if (mb_strlen($businessName) > 150) {
    $error = "A vállalkozás neve túl hosszú (max 150).";
  } elseif (mb_strlen($bio) > 2000) {
    $error = "A bemutatkozás túl hosszú (max 2000).";
  } elseif (mb_strlen($phone) > 50) {
    $error = "A telefonszám túl hosszú (max 50).";
  } elseif (mb_strlen($utca) > 150) {
    $error = "Az utca túl hosszú (max 150).";
  } elseif (mb_strlen($hazszam) > 50) {
    $error = "A házszám túl hosszú (max 50).";
  } else {
    // telepules_id lehet NULL is
    $telepulesIdDb = ($telepulesId > 0) ? $telepulesId : null;

    $u = $mysqli->prepare("
      UPDATE providers
      SET business_name=?, bio=?, phone=?, telepules_id=?, utca=?, hazszam=?
      WHERE id=?
      LIMIT 1
    ");
    // i paramhoz null eset: bind_param nem szereti -> workaround: ha null, 0 és utána SET telepules_id=NULL
    if ($telepulesIdDb === null) {
      $tmpTelep = 0;
      $u->bind_param("sssissi", $businessName, $bio, $phone, $tmpTelep, $utca, $hazszam, $providerId);
      $ok = $u->execute();
      if ($ok) {
        $u2 = $mysqli->prepare("UPDATE providers SET telepules_id=NULL WHERE id=? LIMIT 1");
        $u2->bind_param("i", $providerId);
        $u2->execute();
      }
    } else {
      $u->bind_param("sssissi", $businessName, $bio, $phone, $telepulesIdDb, $utca, $hazszam, $providerId);
      $ok = $u->execute();
    }

    if (!empty($ok)) {
      $success = "Profil adatok mentve!";
      $provider['business_name'] = $businessName;
      $provider['bio'] = $bio;
      $provider['phone'] = $phone;
      $provider['telepules_id'] = $telepulesIdDb;
      $provider['utca'] = $utca;
      $provider['hazszam'] = $hazszam;
    } else {
      $error = "Hiba mentés közben.";
    }
  }
}

/* ======================
   POST: KÉP FELTÖLTÉS
====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
  if (!isset($_FILES['avatar_file']) || $_FILES['avatar_file']['error'] !== UPLOAD_ERR_OK) {
    $error = "Nem sikerült a feltöltés.";
  } else {
    $tmp  = $_FILES['avatar_file']['tmp_name'];
    $size = (int)$_FILES['avatar_file']['size'];

    if ($size <= 0 || $size > $maxBytes) {
      $error = "A kép mérete túl nagy (max 2MB).";
    } else {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime  = $finfo->file($tmp);

      if (!isset($allowedMime[$mime])) {
        $error = "Csak JPG/PNG/WebP engedélyezett.";
      } else {
        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false) {
          $error = "A feltöltött fájl nem érvényes kép.";
        } else {
          $ext = $allowedMime[$mime];
          $fileName = "p_" . $providerId . "_" . time() . "." . $ext;

          if (!is_dir($uploadDirFs)) {
            @mkdir($uploadDirFs, 0775, true);
          }

          $destFs = $uploadDirFs . $fileName;
          if (!move_uploaded_file($tmp, $destFs)) {
            $error = "Nem sikerült menteni a képet a szerveren.";
          } else {
            $newWebPath = $uploadDirWeb . $fileName;

            $u = $mysqli->prepare("UPDATE providers SET avatar=? WHERE id=? LIMIT 1");
            $u->bind_param("si", $newWebPath, $providerId);

            if ($u->execute()) {
              $success = "Profilkép frissítve!";
              $currentAvatar = $newWebPath;
            
              // SESSION FRISSÍTÉ

              $_SESSION['avatar'] = $newWebPath;
            } else {
              $error = "DB mentés hiba.";
            }
          }
        }
      }
    }
  }
}

/* =========================
   TELEPÜLÉS ADAT KIJELZÉSHEZ
========================= */
$telepNev = '';
$telepIrsz = '';
if (!empty($provider['telepules_id'])) {
  $stC = $mysqli->prepare("SELECT nev, iranyitoszam FROM telepulesek WHERE id=? LIMIT 1");
  $tid = (int)$provider['telepules_id'];
  $stC->bind_param("i", $tid);
  $stC->execute();
  $city = $stC->get_result()->fetch_assoc();
  if ($city) {
    $telepNev = (string)$city['nev'];
    $telepIrsz = (string)$city['iranyitoszam'];
  }
}

include '../includes/header.php';
?>

<style>
.prov-wrap{max-width:880px;margin:26px auto;padding:0 12px;}
.prov-title{color:#0f172a;font-size:34px;margin:0 0 14px;font-weight:900;}
.prov-card{background:rgba(255,255,255,0.92);border:1px solid rgba(15,23,42,0.08);border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,0.10);padding:18px;}
.prov-top{display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:14px;}
.prov-avatar{width:86px;height:86px;border-radius:999px;object-fit:cover;background:#fff;border:4px solid rgba(36,37,110,.18);}
.prov-meta{min-width:240px;}
.prov-meta h2{margin:0;font-size:20px;color:#0f172a;font-weight:900;}
.prov-meta p{margin:4px 0 0;color:#475569;}
.msg{
  text-align: center;
  margin: 10px 0 14px;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid #fecdd3;
  background: #fff1f2;
  color: #b91c1c;
  font-weight: 600;
}

.msg.success{
  border: 1px solid #bbf7d0;
  background: #eafff1;
  color: #166534;
}

.msg.error{
  border: 1px solid #fecdd3;
  background: #fff1f2;
  color: #b91c1c;
}

.stack{display:flex;flex-direction:column;gap:14px;margin-top:14px; }
.box{background:#f5f5ff ;border:1px solid rgba(15,23,42,0.10);border-radius:16px;padding:14px;}
.box h3{margin:0 0 10px;font-size:14px;font-weight:900;color:#0f172a;}

.prov-input,.prov-text,.prov-select{width:100%;border:1px solid rgba(15,23,42,0.16);border-radius:14px;padding:12px;font-family:Inter,sans-serif;font-size:14px;outline:none;background:#fff;}
.prov-text{min-height:140px;resize:vertical;line-height:1.5;}

.prov-btn{border:0;border-radius:14px;padding:12px 14px;font-weight:900;cursor:pointer;color:#fff;background:linear-gradient(135deg,#24256e,#000);width:100%;}

.hint{font-size:12px;color:#64748b;margin-top:8px;line-height:1.4;}
.file{display:block;width:100%;padding:10px;border:1px dashed rgba(15,23,42,0.22);border-radius:14px;background:rgba(15,23,42,0.02);}

.addr{border:1px solid rgba(15,23,42,0.10);border-radius:14px;padding:12px;background:rgba(15,23,42,0.02);margin-bottom:12px;}
.addr strong{display:block;color:#0f172a;font-weight:900;}
.addr span{display:block;color:#475569;margin-top:4px;font-size:13px;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
@media(max-width:700px){.grid2{grid-template-columns:1fr;}}

#msgContainer {
  margin-bottom: 14px;
  min-height: 0;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(15,23,42,0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.modal-box {
  width: 90%;
  max-width: 420px;
  background: #fff;
  border-radius: 18px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 26px 70px rgba(15,23,42,.25);
  padding: 28px 24px;
  text-align: center;
}

.modal-icon {
  width: 56px;
  height: 56px;
  line-height: 56px;
  border-radius: 50%;
  background: #22c55e;
  color: white;
  font-size: 32px;
  font-weight: 900;
  margin: 0 auto 14px;
}

.modal-box h2 {
  margin: 0 0 12px;
  font-size: 28px;
  font-weight: 900;
  color: #0f172a;
}

.modal-box p {
  margin: 0 0 22px;
  color: #334155;
  font-size: 16px;
}

.modal-buttons {
  display: flex;
  justify-content: center;
}

.modal-buttons button {
  min-width: 88px;
  padding: 10px 14px;
  border: 0;
  border-radius: 8px;
  font-weight: 700;
  cursor: pointer;
}

#modalConfirmBtn {
  background: #16a34a;
  color: #fff;
}

.msg {
  text-align: center;
  margin: 10px 0 14px;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid #fecdd3;
  background: #fff1f2;
  color: #b91c1c;
  font-weight: 600;
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.msg.success {
  border: 1px solid #bbf7d0;
  background: #eafff1;
  color: #166534;
}

.msg.error {
  border: 1px solid #fecdd3;
  background: #fff1f2;
  color: #b91c1c;
}
</style>

<div class="prov-wrap">
  <h1 class="prov-title">Vállalkozói profil</h1>

  <div id="msgContainer"></div>

  <div id="modalOverlay" class="modal-overlay" style="display:none">
    <div class="modal-box">
      <div class="modal-icon">!</div>
      <h2 id="modalTitle">Figyelem</h2>
      <p id="modalText">A művelet sikeres volt.</p>
      <div class="modal-buttons">
        <button id="modalConfirmBtn">OK</button>
      </div>
    </div>
  </div>

  <div class="prov-card">
    <div class="prov-top">
      <img class="prov-avatar" src="<?= htmlspecialchars($currentAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="Profilkép">
      <div class="prov-meta">
        <h2><?= htmlspecialchars(($provider['business_name'] ?: 'Vállalkozás'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p>Iparág / szolgáltatás: <strong><?= htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8') ?></strong></p>
      </div>
    </div>

    <!-- EGYMÁS ALATT -->
    <div class="stack">

      <!-- 1) Profilkép feltöltése -->
      <div class="box">
        <h3>Profilkép feltöltése</h3>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="upload_avatar" value="1">
          <input class="file" type="file" name="avatar_file" accept="image/png,image/jpeg,image/webp" required>
          <div class="hint">JPG/PNG/WebP • max 2MB</div>
          <div style="margin-top:10px;">
            <button class="prov-btn" type="submit">Kép feltöltése</button>
          </div>
        </form>
      </div>

      <!-- 2) Profil adatok + cím + bemutatkozás -->
      <div class="box">
        <h2>Profil adatok</h2>

        <div class="addr">

  <div style="margin-bottom:8px;">
    <strong>Email címed:</strong>
    <span><?= htmlspecialchars(trim((string)($provider['email'] ?? '')) ?: "Nincs megadva", ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <div style="margin-bottom:8px;">
    <strong>Telefonszámod:</strong>
    <span><?= htmlspecialchars(trim((string)($provider['phone'] ?? '')) ?: "Nincs megadva", ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <div style="margin-bottom:8px;">
    <strong>Település:</strong>
    <span>
      <?= htmlspecialchars(
        trim(($telepIrsz ? $telepIrsz . " " : "") . ($telepNev ?: "")) ?: "Nincs megadva",
        ENT_QUOTES,
        'UTF-8'
      ) ?>
    </span>
  </div>

  <div style="margin-bottom:8px;">
    <strong>Utca:</strong>
    <span><?= htmlspecialchars(trim((string)($provider['utca'] ?? '')) ?: "Nincs megadva", ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <div>
    <strong>Házszám:</strong>
    <span><?= htmlspecialchars(trim((string)($provider['hazszam'] ?? '')) ?: "Nincs megadva", ENT_QUOTES, 'UTF-8') ?></span>
  </div>

</div>
<h2>Szerkesztés</h2>
        <!-- SZERKESZTÉS + MENTÉS -->
        <form method="post">
          <input type="hidden" name="save_profile" value="1">

          <div class="grid2">
          <p>Vállalkozás nevének szerkesztése:</p>
            <input class="prov-input" type="text" name="business_name"
                   placeholder="Vállalkozás neve"
                   value="<?= htmlspecialchars((string)$provider['business_name'], ENT_QUOTES, 'UTF-8') ?>">
                   <p>Telefonszám szerkesztése:</p>
            <input class="prov-input" type="text" name="phone"
                   placeholder="Telefonszám"
                   value="<?= htmlspecialchars((string)($provider['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div style="height:10px;"></div>

          <div class="grid2">
            <p>Település szerkesztése:</p>
            <select class="prov-select" name="telepules_id">
              <option value="0">Település kiválasztása…</option>
              <?php foreach($telepulesek as $t): ?>
                <?php
                  $tid = (int)$t['id'];
                  $sel = ((int)($provider['telepules_id'] ?? 0) === $tid) ? 'selected' : '';
                  $label = trim((string)$t['iranyitoszam'] . " " . (string)$t['nev']);
                ?>
                <option value="<?= $tid ?>" <?= $sel ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <p>Utca szerkesztése:</p>
            <input class="prov-input" type="text" name="utca"
                   placeholder="Utca"
                   value="<?= htmlspecialchars((string)($provider['utca'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                   
          <p>Házszám szerkesztése:</p>
          <input class="prov-input" type="text" name="hazszam"
                 placeholder="Házszám"
                 value="<?= htmlspecialchars((string)($provider['hazszam'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>

         

          <div style="height:10px;"></div>
          <p>Bemutatkozás:</p>
          <!-- BEMUTATKOZÁS ALUL -->
          <textarea class="prov-text" name="bio" placeholder="Bemutatkozás (max 2000 karakter)"><?= htmlspecialchars((string)($provider['bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

          <div style="margin-top:10px;">
            <button class="prov-btn" type="submit">Profil mentése</button>
          </div>
        </form>

        <div class="hint">Itt tudod javítani, ha regisztrációnál elírtad a címet/telefont.</div>
      </div>

    </div>
  </div>
</div>

<script>
function showModal(title, text) {
  const overlay = document.getElementById('modalOverlay');
  const titleEl = document.getElementById('modalTitle');
  const textEl = document.getElementById('modalText');
  const confirmBtn = document.getElementById('modalConfirmBtn');

  titleEl.textContent = title;
  textEl.textContent = text;
  overlay.style.display = 'flex';

  confirmBtn.onclick = () => {
    overlay.style.display = 'none';
  };
}

function showMessage(message, isError = false) {
  showModal(isError ? 'Hiba' : 'Siker', message);
}

// Ha van PHP success/error, mutasd meg a popup-ot az oldal betöltésekor
document.addEventListener('DOMContentLoaded', () => {
  <?php if ($success): ?>
    showMessage('<?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>', false);
  <?php endif; ?>
  
  <?php if ($error): ?>
    showMessage('<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>', true);
  <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>