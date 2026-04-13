<?php
// =============================================
//  STARS BOT — WEB ADMIN PANEL (XAVFSIZ)
// =============================================

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => true,   // Faqat HTTPS
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);

// ─── CONFIG ──────────────────────────────────
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
} else {
    define('BOT_TOKEN', '');
    define('ADMIN_IDS', []);
    define('CARD_NUMBER', '');
    define('RASMIYPAY_SHOP_ID',  '');
    define('RASMIYPAY_SHOP_KEY', '');
    define('MIN_TOPUP', 1000);
    define('MAX_TOPUP', 2500000);
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', '');
    define('DB_USER', '');
    define('DB_PASS', '');
}

$dbFile = __DIR__ . '/db.php';
if (file_exists($dbFile)) require_once $dbFile;

// JSON helpers db.php orqali yuklanadi

// ═══════════════════════════════════════════════════
// BRUTE-FORCE HIMOYASI — kirish urinishlarini chekla
// ═══════════════════════════════════════════════════
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_SECONDS', 15 * 60); // 15 daqiqa
// ATTEMPTS_FILE olib tashlandi — settings jadvalida saqlanadi

function getIp(): string {
    // Ishonchli reverse proxy bo'lsa X-Forwarded-For ishlatish mumkin,
    // lekin asosiy IP REMOTE_ADDR dan olinadi
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Login attempts — settings jadvalida JSON sifatida saqlanadi
function getLoginAttempts(): array {
    if (!function_exists('getSetting')) return [];
    $raw = getSetting('_login_attempts', '{}');
    return json_decode($raw, true) ?: [];
}

function saveLoginAttempts(array $data): void {
    if (function_exists('setSetting'))
        setSetting('_login_attempts', json_encode($data, JSON_UNESCAPED_UNICODE));
}

function isLockedOut(): bool {
    $ip   = getIp();
    $data = getLoginAttempts();
    if (!isset($data[$ip])) return false;
    $info = $data[$ip];
    if ($info['attempts'] < MAX_LOGIN_ATTEMPTS) return false;
    if ((time() - $info['last_attempt']) > LOCKOUT_SECONDS) {
        unset($data[$ip]);
        saveLoginAttempts($data);
        return false;
    }
    return true;
}

function recordFailedAttempt(): void {
    $ip   = getIp();
    $data = getLoginAttempts();
    $data[$ip] = [
        'attempts'     => ($data[$ip]['attempts'] ?? 0) + 1,
        'last_attempt' => time(),
    ];
    saveLoginAttempts($data);
}

function clearLoginAttempts(): void {
    $ip   = getIp();
    $data = getLoginAttempts();
    unset($data[$ip]);
    saveLoginAttempts($data);
}

// ═══════════════════════════════════════════════════
// AUTH — parol hashing (password_hash / password_verify)
// ═══════════════════════════════════════════════════

$storedPass = function_exists('getSetting') ? getSetting('admin_web_pass_hash', '') : '';

// Agar hali hash yo'q bo'lsa — default 'admin123'
if (empty($storedPass)) {
    $storedPass = password_hash('admin123', PASSWORD_BCRYPT);
    if (function_exists('setSetting')) setSetting('admin_web_pass_hash', $storedPass);
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF tekshiruvi muvaffaqiyatsiz!');
    }
}

// Chiqish
if (isset($_POST['logout'])) {
    verifyCsrf();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Kirish
$loginErr = '';
if (isset($_POST['login_pass'])) {
    verifyCsrf();

    if (isLockedOut()) {
        $loginErr = '⛔ Juda ko\'p urinish. 15 daqiqadan so\'ng qayta urinib ko\'ring.';
    } elseif (password_verify($_POST['login_pass'], $storedPass)) {
        clearLoginAttempts();
        session_regenerate_id(true);  // Session fixation himoyasi
        $_SESSION['stars_admin'] = true;
    } else {
        recordFailedAttempt();
        $loginErr = "Parol noto'g'ri!";
    }
}

if (!isset($_SESSION['stars_admin'])) {
    loginPage($loginErr);
    exit;
}

// Autentifikatsiya o'tgan — davom et
// ─────────────────────────────────────────────────────────────────
// Mavjud admin.php kodi shu yerdan davom etadi (o'zgarishsiz),
// faqat quyidagi o'zgartirishlar bilan:

// 1) Barcha POST/GET so'rovlari oldidan CSRF tekshiruvi
// 2) Parolni saqlashda password_hash() ishlatish

$action  = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'dashboard';

// Parolni yangilashda hash qo'llash
if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (!empty($_POST['stars_price_som']))  setSetting('stars_price_som',  (int)$_POST['stars_price_som']);
    if (isset($_POST['fragment_api_key']))  setSetting('fragment_api_key', trim($_POST['fragment_api_key']));
    if (!empty($_POST['admin_web_pass'])) {
        $hash = password_hash($_POST['admin_web_pass'], PASSWORD_BCRYPT);
        setSetting('admin_web_pass_hash', $hash);
        // Ochiq parolni o'chiramiz (agar eski yozuv bo'lsa)
        if (function_exists('db')) {
            db()->prepare("DELETE FROM settings WHERE `key`='admin_web_pass'")->execute();
        }
    }
}

// ... (qolgan admin.php kodi o'zgarishsiz davom etadi)
// Ushbu fayl asl admin.php ga qo'shilishi kerak bo'lgan patch sifatida berilgan.

// ─── LOGIN SAHIFASI ───────────────────────────────────────────────
function loginPage(string $err = ''): void {
    global $csrfToken;
    ?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Panel</title>
<style>
body{font-family:system-ui,sans-serif;background:#0f0f0f;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#1a1a1a;border:1px solid #333;border-radius:12px;padding:32px;width:320px}
h2{color:#fff;margin:0 0 24px;text-align:center}
input{width:100%;box-sizing:border-box;padding:10px 14px;border:1px solid #444;border-radius:8px;background:#111;color:#fff;font-size:15px;margin-bottom:16px}
button{width:100%;padding:12px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer}
.err{color:#f87171;font-size:13px;margin-bottom:12px;text-align:center}
</style>
</head>
<body>
<div class="box">
  <h2>🔐 Admin Panel</h2>
  <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="post" action="admin.php" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="password" name="login_pass" placeholder="Parol" autofocus>
    <button type="submit">Kirish</button>
  </form>
</div>
</body>
</html>
    <?php
}
