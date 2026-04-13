<?php
// =============================================
//  STARS BOT — WEB ADMIN PANEL
// =============================================

session_start();

// ─── CONFIG YUKLASH ──────────────────────────
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
} else {
    // Agar config yo'q bo'lsa default qiymatlar
    define('BOT_TOKEN', '');
    define('ADMIN_IDS', []);
    define('CARD_NUMBER', '');
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

// ─── AUTH ─────────────────────────────────────
$ADMIN_PASS = function_exists('getSetting') ? getSetting('admin_web_pass', 'admin123') : 'admin123';

if (isset($_POST['logout'])) { session_destroy(); header('Location: admin.php'); exit; }
if (isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === $ADMIN_PASS) {
        $_SESSION['stars_admin'] = true;
    } else {
        $loginErr = "Parol noto'g'ri!";
    }
}
if (!isset($_SESSION['stars_admin'])) { loginPage($loginErr ?? ''); exit; }

// ─── TELEGRAM API ─────────────────────────────
function tgSend(string $method, array $params): array {
    if (!defined('BOT_TOKEN') || !BOT_TOKEN) return ['ok' => false];
    $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/{$method}");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    $r = curl_exec($ch); curl_close($ch);
    return json_decode($r, true) ?? [];
}

// ─── ACTIONS ──────────────────────────────────
$action  = $_GET['action'] ?? 'dashboard';
$message = '';
$msgType = 'success';

// Foydalanuvchini ban/unban
if ($action === 'toggle_ban' && isset($_GET['tid'])) {
    $tid = (int)$_GET['tid'];
    if (function_exists('getUser') && function_exists('saveUser')) {
        $u = getUser($tid);
        if ($u) {
            $u['is_banned'] = !$u['is_banned'];
            saveUser($u);
            $message = $u['is_banned'] ? "⛔ Foydalanuvchi bloklandi." : "✅ Blok ochildi.";
        }
    }
    $action = 'users';
}

// Balans qo'shish/ayirish
if ($action === 'adj_balance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid = (int)$_POST['tid'];
    $amt = (int)$_POST['amount'];
    if (function_exists('addBalance') && function_exists('getUser')) {
        try {
            if ($amt > 0) addBalance($tid, $amt);
            elseif ($amt < 0) {
                $u = getUser($tid);
                if ($u) {
                    $u['balance'] += $amt;
                    if ($u['balance'] < 0) $u['balance'] = 0;
                    saveUser($u);
                }
            }
            $message = "✅ Balans yangilandi.";
            tgSend('sendMessage', ['chat_id' => $tid, 'text' => $amt > 0
                ? "💰 Admin balansingizga " . number_format($amt, 0, '.', ' ') . " so'm qo'shdi."
                : "💸 Admin balansingizdan " . number_format(abs($amt), 0, '.', ' ') . " so'm ayirdi."]);
        } catch (Exception $e) {
            $message = "❌ " . $e->getMessage(); $msgType = 'error';
        }
    }
    $action = 'users';
}

// Buyurtma statusini yangilash
if ($action === 'update_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $oid = (int)$_POST['order_id'];
    $st  = $_POST['status'];
    if (function_exists('updateOrderStatus')) {
        try {
            updateOrderStatus($oid, $st);
            $message = "✅ Buyurtma #$oid statusi yangilandi: $st";
        } catch (Exception $e) {
            $message = "❌ " . $e->getMessage(); $msgType = 'error';
        }
    }
    $action = 'orders';
}

// Stars narxini yangilash
if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['stars_price_som']))  setSetting('stars_price_som',  (int)$_POST['stars_price_som']);
    if (isset($_POST['fragment_api_key'])) setSetting('fragment_api_key', trim($_POST['fragment_api_key']));
    if (isset($_POST['admin_web_pass']) && $_POST['admin_web_pass'])
                                          setSetting('admin_web_pass', $_POST['admin_web_pass']);
    $message = "✅ Sozlamalar saqlandi.";
    $action  = 'settings';
}

// Kanal qo'shish
if ($action === 'add_channel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('addChannel')) {
        $cid   = trim($_POST['channel_id']);
        $title = trim($_POST['title']);
        $link  = trim($_POST['link']);
        if ($cid && $title && $link) {
            $ok = addChannel($cid, $title, $link);
            $message = $ok ? "✅ Kanal qo'shildi." : "⚠️ Bu kanal allaqachon mavjud.";
        } else {
            $message = "❌ Barcha maydonlarni to'ldiring."; $msgType = 'error';
        }
    }
    $action = 'channels';
}

// Kanal o'chirish
if ($action === 'del_channel' && isset($_GET['cid'])) {
    if (function_exists('removeChannel')) {
        removeChannel(urldecode($_GET['cid']));
        $message = "✅ Kanal o'chirildi.";
    }
    $action = 'channels';
}

// Broadcast
if ($action === 'broadcast_send' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['btext'])) {
    $text = $_POST['btext'];
    $users = function_exists('getAllUsers') ? getAllUsers() : [];
    $sent = $failed = 0;
    foreach ($users as $u) {
        $r = tgSend('sendMessage', ['chat_id' => $u['telegram_id'], 'text' => $text, 'parse_mode' => 'HTML']);
        $r['ok'] ? $sent++ : $failed++;
        usleep(35000);
    }
    $message = "✅ $sent ta yuborildi, $failed ta xato.";
    $action  = 'broadcast';
}

// ─── HELPERS ──────────────────────────────────
function sBadge(string $s): string {
    $m = [
        'pending'   => ['#f59e0b', '#fffbeb', '⏳'],
        'completed' => ['#22c55e', '#f0fdf4', '✅'],
        'failed'    => ['#ef4444', '#fef2f2', '❌'],
        'cancelled' => ['#6b7280', '#f9fafb', '🚫'],
        'paid'      => ['#22c55e', '#f0fdf4', '✅'],
        'expired'   => ['#6b7280', '#f9fafb', '⌛'],
    ];
    [$clr, $bg, $ico] = $m[$s] ?? ['#a855f7', '#faf5ff', '❓'];
    return "<span style='display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:{$bg};color:{$clr};border:1px solid {$clr}33'>{$ico} {$s}</span>";
}

function safeGetStats(): array {
    if (function_exists('getStats')) return getStats();
    $users  = function_exists('getAllUsers')  ? getAllUsers()  : [];
    $orders = function_exists('getAllOrders') ? getAllOrders() : [];
    return [
        'total_users'      => count($users),
        'active_users'     => count(array_filter($users, fn($u) => !($u['is_banned'] ?? false))),
        'banned_users'     => count(array_filter($users, fn($u) => $u['is_banned'] ?? false)),
        'total_orders'     => count($orders),
        'completed_orders' => count(array_filter($orders, fn($o) => $o['status'] === 'completed')),
        'pending_orders'   => count(array_filter($orders, fn($o) => $o['status'] === 'pending')),
        'total_revenue'    => array_sum(array_map(fn($o) => $o['status'] === 'completed' ? (int)$o['price'] : 0, $orders)),
        'total_stars_sold' => array_sum(array_map(fn($o) => $o['status'] === 'completed' ? (int)$o['stars_amount'] : 0, $orders)),
    ];
}

$pageTitle = match($action) {
    'dashboard' => 'Dashboard',
    'users'     => 'Foydalanuvchilar',
    'orders'    => 'Buyurtmalar',
    'topups'    => "To'ldirish tarixi",
    'channels'  => 'Kanallar',
    'settings'  => 'Sozlamalar',
    'broadcast' => 'Xabar yuborish',
    default     => 'Admin'
};
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $pageTitle ?> — Stars Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --bg:       #07090f;
  --surface:  #0d1117;
  --card:     #111827;
  --border:   #1f2937;
  --border2:  #374151;
  --text:     #f0f4ff;
  --muted:    #6b7280;
  --muted2:   #9ca3af;
  --accent:   #fbbf24;
  --accent2:  #f59e0b;
  --star:     #fde68a;
  --blue:     #60a5fa;
  --green:    #34d399;
  --red:      #f87171;
  --purple:   #c084fc;
  --cyan:     #22d3ee;
  --font-mono: 'Space Mono', monospace;
  --font-body: 'DM Sans', sans-serif;
}
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%}
body{
  font-family:var(--font-body);
  background:var(--bg);
  color:var(--text);
  display:flex;
  min-height:100vh;
  background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(251,191,36,.07), transparent);
}

/* ── SIDEBAR ── */
.sidebar {
  width:230px;
  background:var(--surface);
  border-right:1px solid var(--border);
  position:fixed;
  top:0;bottom:0;left:0;
  display:flex;flex-direction:column;
  z-index:50;
  overflow:hidden;
}
.logo {
  padding:24px 20px 20px;
  border-bottom:1px solid var(--border);
}
.logo-star {
  font-size:28px;
  line-height:1;
  margin-bottom:6px;
  filter: drop-shadow(0 0 12px #fbbf24aa);
}
.logo h2 {
  font-family:var(--font-mono);
  font-size:14px;
  font-weight:700;
  letter-spacing:2px;
  text-transform:uppercase;
  color:var(--accent);
}
.logo p { font-size:11px; color:var(--muted); margin-top:2px; }

.nav { flex:1; padding:12px 0; overflow-y:auto; }
.nav-label {
  font-size:9px;
  text-transform:uppercase;
  letter-spacing:1.5px;
  color:var(--muted);
  padding:10px 20px 4px;
  font-family:var(--font-mono);
}
.nav a {
  display:flex;align-items:center;gap:10px;
  padding:9px 20px;
  color:var(--muted2);
  text-decoration:none;
  font-size:13px;
  font-weight:500;
  transition:all .15s;
  border-left:2px solid transparent;
  position:relative;
}
.nav a:hover { color:var(--text); background:rgba(255,255,255,.03); }
.nav a.on {
  color:var(--accent);
  background:rgba(251,191,36,.06);
  border-left-color:var(--accent);
}
.nav a i { width:16px; text-align:center; font-size:12px; flex-shrink:0; }
.nav a .badge-dot {
  margin-left:auto;
  width:7px;height:7px;
  border-radius:50%;
  background:var(--red);
  box-shadow:0 0 6px var(--red);
}

.sidebar-foot {
  padding:14px 16px;
  border-top:1px solid var(--border);
}
.logout-btn {
  display:flex;align-items:center;gap:8px;
  width:100%;padding:8px 12px;
  background:rgba(248,113,113,.08);
  border:1px solid rgba(248,113,113,.15);
  border-radius:8px;
  color:var(--red);
  font-size:12px;font-weight:600;
  cursor:pointer;
  transition:.15s;
  font-family:var(--font-body);
}
.logout-btn:hover { background:rgba(248,113,113,.14); }

/* ── MAIN ── */
.main { margin-left:230px; flex:1; padding:24px 28px; min-height:100vh; }
.topbar {
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:24px;
}
.topbar h1 { font-size:22px; font-weight:700; letter-spacing:-.3px; }
.topbar-right { display:flex;align-items:center;gap:10px; }
.time-badge {
  font-family:var(--font-mono);
  font-size:11px;
  color:var(--muted);
  background:var(--card);
  border:1px solid var(--border);
  padding:5px 10px;
  border-radius:20px;
}

/* ── ALERT ── */
.alert {
  padding:11px 16px;
  border-radius:10px;
  font-size:13px;
  margin-bottom:18px;
  display:flex;align-items:center;gap:8px;
}
.alert-s { background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.2);color:var(--green); }
.alert-e { background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);color:var(--red); }

/* ── STATS GRID ── */
.stats-grid {
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
  gap:14px;
  margin-bottom:22px;
}
.stat-card {
  background:var(--card);
  border:1px solid var(--border);
  border-radius:12px;
  padding:18px;
  position:relative;
  overflow:hidden;
  transition:.2s;
}
.stat-card:hover { border-color:var(--border2); transform:translateY(-2px); }
.stat-card::before {
  content:'';
  position:absolute;
  top:0;right:0;
  width:60px;height:60px;
  border-radius:50%;
  opacity:.06;
  transform:translate(20px,-20px);
}
.stat-card.gold::before { background:var(--accent); }
.stat-card.blue::before { background:var(--blue); }
.stat-card.green::before { background:var(--green); }
.stat-card.red::before { background:var(--red); }
.stat-card.purple::before { background:var(--purple); }
.stat-card.cyan::before { background:var(--cyan); }

.stat-icon {
  width:34px;height:34px;
  border-radius:9px;
  display:flex;align-items:center;justify-content:center;
  font-size:14px;
  margin-bottom:12px;
}
.si-gold { background:rgba(251,191,36,.12); color:var(--accent); }
.si-blue { background:rgba(96,165,250,.12); color:var(--blue); }
.si-green { background:rgba(52,211,153,.12); color:var(--green); }
.si-red { background:rgba(248,113,113,.12); color:var(--red); }
.si-purple { background:rgba(192,132,252,.12); color:var(--purple); }
.si-cyan { background:rgba(34,211,238,.12); color:var(--cyan); }

.stat-label { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.6px; font-family:var(--font-mono); margin-bottom:4px; }
.stat-val { font-size:28px; font-weight:700; line-height:1; font-family:var(--font-mono); }
.stat-val.gold { color:var(--accent); }
.stat-val.star { color:var(--star); }
.stat-sub { font-size:11px; color:var(--muted); margin-top:4px; }

/* ── BOX ── */
.box {
  background:var(--card);
  border:1px solid var(--border);
  border-radius:12px;
  overflow:hidden;
  margin-bottom:20px;
}
.box-head {
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 18px;
  border-bottom:1px solid var(--border);
  background:rgba(255,255,255,.01);
}
.box-title { font-size:14px; font-weight:600; display:flex;align-items:center;gap:7px; }
.box-actions { display:flex;gap:6px; }

/* ── TABLE ── */
table { width:100%; border-collapse:collapse; }
th {
  padding:9px 16px;
  text-align:left;
  font-size:9px;
  text-transform:uppercase;
  letter-spacing:.8px;
  color:var(--muted);
  font-family:var(--font-mono);
  background:rgba(255,255,255,.02);
  border-bottom:1px solid var(--border);
  white-space:nowrap;
}
td {
  padding:11px 16px;
  font-size:12px;
  border-bottom:1px solid rgba(31,41,55,.5);
  vertical-align:middle;
}
tr:last-child td { border-bottom:none; }
tr:hover td { background:rgba(255,255,255,.018); }
.td-mono { font-family:var(--font-mono); font-size:11px; }
.td-muted { color:var(--muted); }

/* ── BUTTONS ── */
.btn {
  display:inline-flex;align-items:center;gap:5px;
  padding:7px 13px;
  border:none;border-radius:8px;
  cursor:pointer;
  font-size:11px;font-weight:600;
  text-decoration:none;
  transition:all .15s;
  font-family:var(--font-body);
  white-space:nowrap;
}
.btn:hover { opacity:.85; transform:translateY(-1px); }
.btn-gold { background:var(--accent); color:#000; }
.btn-blue { background:rgba(96,165,250,.15); color:var(--blue); border:1px solid rgba(96,165,250,.2); }
.btn-green { background:rgba(52,211,153,.15); color:var(--green); border:1px solid rgba(52,211,153,.2); }
.btn-red { background:rgba(248,113,113,.12); color:var(--red); border:1px solid rgba(248,113,113,.2); }
.btn-ghost { background:rgba(255,255,255,.05); color:var(--muted2); border:1px solid var(--border); }
.btn-sm { padding:4px 9px; font-size:10px; border-radius:6px; }
.btn-xs { padding:2px 6px; font-size:10px; border-radius:5px; }

/* ── FORM ── */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; padding:18px; }
.form-group { margin-bottom:14px; }
.form-group:last-child { margin-bottom:0; }
.form-label { display:block; font-size:11px; color:var(--muted); margin-bottom:5px; font-family:var(--font-mono); text-transform:uppercase; letter-spacing:.4px; }
.inp {
  width:100%;
  padding:9px 12px;
  background:rgba(255,255,255,.04);
  border:1px solid var(--border);
  border-radius:8px;
  color:var(--text);
  font-size:13px;
  outline:none;
  transition:.15s;
  font-family:var(--font-body);
}
.inp:focus { border-color:var(--accent); background:rgba(251,191,36,.04); }
select.inp { cursor:pointer; }
textarea.inp { resize:vertical; }
.inp-mono { font-family:var(--font-mono); font-size:12px; }
.form-pad { padding:18px; }

/* ── MODAL ── */
.modal-wrap {
  display:none;
  position:fixed;inset:0;
  background:rgba(0,0,0,.75);
  z-index:200;
  align-items:center;justify-content:center;
  backdrop-filter:blur(3px);
}
.modal-wrap.open { display:flex; }
.modal {
  background:var(--card);
  border:1px solid var(--border2);
  border-radius:14px;
  padding:26px;
  width:360px;
  box-shadow:0 25px 60px rgba(0,0,0,.5);
}
.modal h3 { font-size:16px; font-weight:700; margin-bottom:16px; }
.modal-close {
  display:flex;gap:8px;margin-top:14px;
}
.modal-close button { flex:1; }

/* ── AVATAR ── */
.avatar {
  width:30px;height:30px;
  border-radius:50%;
  background:linear-gradient(135deg,#fbbf24,#f59e0b);
  display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:700;color:#000;
  flex-shrink:0;
}

/* ── STARS DISPLAY ── */
.stars-val {
  font-family:var(--font-mono);
  font-weight:700;
  color:var(--star);
}
.stars-val::before { content:'⭐ '; font-size:.85em; }

/* ── MISC ── */
.chip {
  display:inline-flex;align-items:center;gap:4px;
  padding:2px 8px;
  border-radius:20px;
  font-size:10px;font-weight:600;
  background:rgba(255,255,255,.06);
  color:var(--muted2);
  border:1px solid var(--border);
}
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.empty-state {
  text-align:center;
  padding:50px 20px;
  color:var(--muted);
}
.empty-state i { font-size:38px; margin-bottom:12px; opacity:.3; display:block; }
.empty-state p { font-size:13px; }

code {
  font-family:var(--font-mono);
  background:rgba(255,255,255,.07);
  padding:1px 6px;
  border-radius:4px;
  font-size:11px;
}

/* ── SEARCH ── */
.search-row {
  display:flex;gap:7px;align-items:center;
}
.search-inp {
  width:200px;
  padding:6px 10px;
  background:rgba(255,255,255,.04);
  border:1px solid var(--border);
  border-radius:8px;
  color:var(--text);
  font-size:12px;
  outline:none;
}
.search-inp:focus { border-color:var(--accent); }

/* ── REVENUE BANNER ── */
.rev-banner {
  background:linear-gradient(135deg, rgba(251,191,36,.1), rgba(245,158,11,.05));
  border:1px solid rgba(251,191,36,.2);
  border-radius:12px;
  padding:20px 24px;
  margin-bottom:20px;
  display:flex;align-items:center;justify-content:space-between;
}
.rev-left h3 { font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:1px; font-family:var(--font-mono); margin-bottom:4px; }
.rev-left p { font-size:32px; font-weight:700; color:var(--accent); font-family:var(--font-mono); }
.rev-left span { font-size:12px; color:var(--muted2); }
.rev-right { font-size:48px; opacity:.15; }

@media(max-width:900px){
  .sidebar{display:none}
  .main{margin-left:0}
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  .two-col,.form-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="logo">
    <div class="logo-star">⭐</div>
    <h2>Stars Admin</h2>
    <p>Boshqaruv paneli</p>
  </div>
  <nav class="nav">
    <div class="nav-label">Asosiy</div>
    <?php
    $navItems = [
      ['dashboard','fa-chart-line','Dashboard'],
      ['users','fa-users','Foydalanuvchilar'],
      ['orders','fa-star','Buyurtmalar'],
    ];
    foreach($navItems as [$a,$i,$l]):
    ?>
    <a href="?action=<?=$a?>" class="<?=$action===$a?'on':''?>">
      <i class="fas <?=$i?>"></i> <?=$l?>
    </a>
    <?php endforeach; ?>

    <div class="nav-label">Moliya</div>
    <a href="?action=topups" class="<?=$action==='topups'?'on':''?>">
      <i class="fas fa-wallet"></i> To'ldirish tarixi
    </a>

    <div class="nav-label">Tizim</div>
    <?php
    $navSys = [
      ['channels','fa-at','Kanallar'],
      ['broadcast','fa-paper-plane','Xabar yuborish'],
      ['settings','fa-sliders','Sozlamalar'],
    ];
    foreach($navSys as [$a,$i,$l]):
    ?>
    <a href="?action=<?=$a?>" class="<?=$action===$a?'on':''?>">
      <i class="fas <?=$i?>"></i> <?=$l?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <form method="post">
      <button class="logout-btn" name="logout">
        <i class="fas fa-arrow-right-from-bracket"></i> Chiqish
      </button>
    </form>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <h1><?= $pageTitle ?></h1>
    <div class="topbar-right">
      <span class="time-badge"><i class="fas fa-clock" style="margin-right:5px;color:var(--muted)"></i><?=date('d.m.Y H:i')?></span>
      <?php if($action==='dashboard'): ?>
      <a href="?action=dashboard" class="btn btn-ghost btn-sm"><i class="fas fa-rotate"></i> Yangilash</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if($message): ?>
  <div class="alert <?=$msgType==='error'?'alert-e':'alert-s'?>">
    <i class="fas <?=$msgType==='error'?'fa-circle-exclamation':'fa-circle-check'?>"></i>
    <?=$message?>
  </div>
  <?php endif; ?>

  <?php
  match($action) {
    'dashboard' => pageDashboard(),
    'users'     => pageUsers(),
    'orders'    => pageOrders(),
    'topups'    => pageTopups(),
    'channels'  => pageChannels(),
    'settings'  => pageSettings(),
    'broadcast' => pageBroadcast(),
    default     => pageDashboard(),
  };
  ?>
</div>

<?php
// ══════════════════════════════════════════════
// SAHIFALAR
// ══════════════════════════════════════════════

function pageDashboard(): void {
    $s    = safeGetStats();
    $price = function_exists('getSetting') ? (int)getSetting('stars_price_som', '195') : 195;
    ?>
    <!-- REVENUE BANNER -->
    <div class="rev-banner">
      <div class="rev-left">
        <h3>Jami daromad</h3>
        <p><?= number_format($s['total_revenue'], 0, '.', ' ') ?> <span style="font-size:16px;color:var(--muted2)">so'm</span></p>
        <span>⭐ <?= number_format($s['total_stars_sold']) ?> ta stars sotilgan</span>
      </div>
      <div class="rev-right">⭐</div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
      <?php
      $cards = [
        ['Foydalanuvchilar', $s['total_users'], "{$s['active_users']} faol, {$s['banned_users']} ban", 'fa-users', 'si-blue', 'blue'],
        ['Jami buyurtmalar', $s['total_orders'], "{$s['completed_orders']} bajarildi", 'fa-star', 'si-gold', 'gold'],
        ['Bajarilgan', $s['completed_orders'], "Jami: {$s['total_orders']}", 'fa-circle-check', 'si-green', 'green'],
        ['Kutayotgan', $s['pending_orders'], 'Yangilash kerak', 'fa-clock', 'si-red', 'red'],
        ['Stars narxi', number_format($price, 0, '.', ' ') . ' so\'m', '1 ta Telegram Stars', 'fa-coins', 'si-gold', 'gold'],
        ['Stars sotildi', number_format($s['total_stars_sold']), 'Bajarilgan buyurtmalar', 'fa-star', 'si-cyan', 'cyan'],
      ];
      foreach($cards as [$label, $val, $sub, $icon, $iconClass, $cardClass]):
      ?>
      <div class="stat-card <?=$cardClass?>">
        <div class="stat-icon <?=$iconClass?>"><i class="fas <?=$icon?>"></i></div>
        <div class="stat-label"><?=$label?></div>
        <div class="stat-val <?php echo ($cardClass==='gold')?'gold':''; ?>"><?=$val?></div>
        <div class="stat-sub"><?=$sub?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- OXIRGI BUYURTMALAR -->
    <?php
    $orders = function_exists('getAllOrders') ? array_slice(getAllOrders(), 0, 10) : [];
    ?>
    <div class="box">
      <div class="box-head">
        <span class="box-title"><i class="fas fa-star" style="color:var(--accent)"></i> Oxirgi buyurtmalar</span>
        <a href="?action=orders" class="btn btn-gold btn-sm">Barchasi →</a>
      </div>
      <?php if(empty($orders)): ?>
      <div class="empty-state"><i class="fas fa-star"></i><p>Hali buyurtmalar yo'q</p></div>
      <?php else: ?>
      <table>
        <tr><th>#</th><th>Kimdan</th><th>Kimga</th><th>Stars</th><th>Narx</th><th>Status</th><th>Vaqt</th></tr>
        <?php foreach($orders as $o):
          $u = function_exists('getUser') ? getUser((int)$o['buyer_telegram_id']) : null;
          $un = $u ? ($u['username'] ? "@{$u['username']}" : htmlspecialchars($u['full_name'])) : $o['buyer_telegram_id'];
        ?>
        <tr>
          <td class="td-mono" style="color:var(--muted)">#<?=$o['id']?></td>
          <td>
            <div style="display:flex;align-items:center;gap:7px">
              <div class="avatar"><?=mb_substr($u['full_name']??'?',0,1)?></div>
              <span style="font-size:12px"><?=$un?></span>
            </div>
          </td>
          <td class="td-mono" style="color:var(--blue)">@<?=htmlspecialchars($o['target_username']??'?')?></td>
          <td><span class="stars-val"><?=number_format($o['stars_amount'])?></span></td>
          <td class="td-mono" style="color:var(--green)"><?=number_format($o['price'],0,'.',' ')?></td>
          <td><?=sBadge($o['status'])?></td>
          <td class="td-muted td-mono"><?=date('d.m H:i',strtotime($o['created_at']))?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>

    <!-- OXIRGI TOPUP -->
    <?php
    $topups = function_exists('getAllTopupOrders') ? array_slice(getAllTopupOrders(), 0, 5) : [];
    if(!empty($topups)): ?>
    <div class="box">
      <div class="box-head">
        <span class="box-title"><i class="fas fa-wallet" style="color:var(--green)"></i> Oxirgi to'ldirish</span>
        <a href="?action=topups" class="btn btn-ghost btn-sm">Ko'proq →</a>
      </div>
      <table>
        <tr><th>Kod</th><th>Foydalanuvchi</th><th>Miqdor</th><th>Status</th><th>Vaqt</th></tr>
        <?php foreach($topups as $t):
          $u = function_exists('getUser') ? getUser((int)$t['telegram_id']) : null;
          $un = $u ? ($u['username']?"@{$u['username']}":htmlspecialchars($u['full_name']??'')) : $t['telegram_id'];
        ?>
        <tr>
          <td><code><?=htmlspecialchars($t['order_code'])?></code></td>
          <td><?=$un?></td>
          <td class="td-mono" style="color:var(--green)"><?=number_format($t['amount'],0,'.',' ')?> so'm</td>
          <td><?=sBadge($t['status'])?></td>
          <td class="td-muted td-mono"><?=date('d.m H:i',strtotime($t['created_at']))?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <!-- ENG FAOL FOYDALANUVCHILAR -->
    <?php
    $allUsers  = function_exists('getAllUsers') ? getAllUsers() : [];
    $allOrders = function_exists('getAllOrders') ? getAllOrders() : [];

    // Top nechtalikni ko'rsatish — GET parametrdan yoki default 5
    $topN = max(1, min(50, (int)($_GET['top_n'] ?? 5)));

    // Har bir foydalanuvchi uchun to'liq statistika hisoblash
    $userStats = [];
    foreach ($allUsers as $u) {
        $tid = (int)$u['telegram_id'];
        $userOrders     = array_filter($allOrders, fn($o) => (int)$o['buyer_telegram_id'] === $tid);
        $completedOrds  = array_filter($userOrders, fn($o) => $o['status'] === 'completed');
        $totalStars     = array_sum(array_map(fn($o) => (int)$o['stars_amount'], $completedOrds));
        $totalSpent     = array_sum(array_map(fn($o) => (int)$o['price'], $completedOrds));
        $userStats[] = [
            'user'           => $u,
            'orders_count'   => count($completedOrds),
            'total_stars'    => $totalStars,
            'total_spent'    => $totalSpent,
        ];
    }

    // Stars miqdori bo'yicha saralash (eng ko'p stars xarid qilgan)
    usort($userStats, fn($a, $b) => $b['total_stars'] <=> $a['total_stars']);
    $topUsers = array_slice($userStats, 0, $topN);

    $medals = ['🥇','🥈','🥉'];
    ?>
    <div class="box" style="margin-top:20px">
      <div class="box-head" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <span class="box-title">
          <i class="fas fa-fire" style="color:var(--accent)"></i>
          Eng faol foydalanuvchilar
          <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:6px">— Stars xarid bo'yicha TOP</span>
        </span>
        <form method="get" style="display:flex;align-items:center;gap:8px">
          <input type="hidden" name="action" value="dashboard">
          <label style="font-size:12px;color:var(--muted2)">Top</label>
          <input type="number" name="top_n" value="<?= $topN ?>" min="1" max="50"
                 style="width:60px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;
                        color:var(--fg);padding:5px 8px;font-size:13px;text-align:center"
                 placeholder="5">
          <button type="submit"
                  style="background:var(--accent);color:#000;border:none;border-radius:8px;
                         padding:5px 14px;font-size:12px;font-weight:600;cursor:pointer">
            Ko'rish
          </button>
        </form>
      </div>

      <?php if(empty($topUsers)): ?>
      <div class="empty-state"><i class="fas fa-fire"></i><p>Hali buyurtmalar yo'q</p></div>
      <?php else: ?>
      <table>
        <tr>
          <th style="width:40px">#</th>
          <th>Foydalanuvchi</th>
          <th>Telegram ID</th>
          <th style="color:var(--accent)">⭐ Stars</th>
          <th style="color:var(--green)">Sarflagan</th>
          <th>Buyurtmalar</th>
          <th>Holat</th>
        </tr>
        <?php foreach($topUsers as $i => $stat):
          $u    = $stat['user'];
          $rank = $i + 1;
          $medal = $medals[$i] ?? "<span style='color:var(--muted);font-size:11px'>#{$rank}</span>";
        ?>
        <tr style="<?= $rank <= 3 ? 'background:rgba(255,212,0,0.04)' : '' ?>">
          <td style="text-align:center;font-size:16px"><?= $medal ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="avatar" style="<?= $rank===1?'background:linear-gradient(135deg,#ffd700,#ff8c00);color:#000':'' ?>">
                <?= mb_substr($u['full_name'] ?? '?', 0, 1) ?>
              </div>
              <div>
                <div style="font-size:12px;font-weight:600"><?= htmlspecialchars($u['full_name'] ?? '—') ?></div>
                <?php if($u['username']): ?>
                <div style="font-size:10px;color:var(--muted)">@<?= htmlspecialchars($u['username']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td><code><?= $u['telegram_id'] ?></code></td>
          <td>
            <span style="font-weight:700;font-size:14px;color:var(--accent)">
              <?= number_format($stat['total_stars']) ?>
            </span>
            <span style="font-size:10px;color:var(--muted);margin-left:3px">ta</span>
          </td>
          <td class="td-mono" style="color:var(--green)">
            <?= number_format($stat['total_spent'], 0, '.', ' ') ?>
            <span style="font-size:10px;color:var(--muted)">so'm</span>
          </td>
          <td style="text-align:center">
            <span class="chip">
              <i class="fas fa-star" style="color:var(--accent);font-size:9px"></i>
              <?= $stat['orders_count'] ?>
            </span>
          </td>
          <td>
            <?php if($u['is_banned'] ?? false): ?>
            <span style="color:var(--red);font-size:11px;font-weight:600">⛔ Ban</span>
            <?php else: ?>
            <span style="color:var(--green);font-size:11px;font-weight:600">✓ Faol</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <div style="padding:10px 16px;font-size:11px;color:var(--muted);border-top:1px solid var(--border)">
        Jami <?= count($allUsers) ?> foydalanuvchidan eng faol <?= count($topUsers) ?> tasi ko'rsatilmoqda
        · Stars xaridi bo'yicha saralangan
      </div>
      <?php endif; ?>
    </div>
    <?php
}

function pageUsers(): void {
    $search = strtolower(trim($_GET['q'] ?? ''));
    $users  = function_exists('getAllUsers') ? getAllUsers() : [];
    if ($search) {
        $users = array_filter($users, function($u) use($search) {
            return str_contains(strtolower($u['full_name']), $search)
                || str_contains(strtolower($u['username'] ?? ''), $search)
                || (string)$u['telegram_id'] === $search;
        });
    }
    usort($users, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
    ?>
    <div class="box">
      <div class="box-head">
        <span class="box-title"><i class="fas fa-users" style="color:var(--blue)"></i> Foydalanuvchilar (<?=count($users)?>)</span>
        <form method="get" class="search-row">
          <input type="hidden" name="action" value="users">
          <input class="search-inp" name="q" placeholder="Ism, username yoki ID..." value="<?=htmlspecialchars($search)?>">
          <button class="btn btn-gold btn-sm"><i class="fas fa-search"></i></button>
          <?php if($search): ?><a href="?action=users" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
        </form>
      </div>
      <?php if(empty($users)): ?>
      <div class="empty-state"><i class="fas fa-users"></i><p>Foydalanuvchilar topilmadi</p></div>
      <?php else: ?>
      <table>
        <tr><th>ID</th><th>Foydalanuvchi</th><th>Telegram ID</th><th>Balans</th><th>Sarflangan</th><th>Buyurtmalar</th><th>Holat</th><th>Amallar</th></tr>
        <?php foreach(array_slice($users, 0, 60) as $u): ?>
        <tr>
          <td class="td-mono td-muted"><?=$u['id']?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="avatar"><?=mb_substr($u['full_name'],0,1)?></div>
              <div>
                <div style="font-size:12px;font-weight:500"><?=htmlspecialchars($u['full_name'])?></div>
                <?php if($u['username']): ?><div style="font-size:10px;color:var(--muted)">@<?=htmlspecialchars($u['username'])?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td><code><?=$u['telegram_id']?></code></td>
          <td class="td-mono" style="color:var(--accent)"><?=number_format($u['balance'],0,'.',' ')?> <span style="font-size:10px;color:var(--muted)">so'm</span></td>
          <td class="td-mono td-muted"><?=number_format($u['total_spent']??0,0,'.',' ')?></td>
          <td style="text-align:center"><span class="chip"><i class="fas fa-star" style="color:var(--accent);font-size:9px"></i> <?=$u['orders_count']??0?></span></td>
          <td>
            <?php if($u['is_banned']): ?>
            <span style="color:var(--red);font-size:11px;font-weight:600">⛔ Ban</span>
            <?php else: ?>
            <span style="color:var(--green);font-size:11px;font-weight:600">✓ Faol</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="?action=toggle_ban&tid=<?=$u['telegram_id']?>" class="btn <?=$u['is_banned']?'btn-green':'btn-red'?> btn-sm">
                <?=$u['is_banned']?'🔓':'🔒'?>
              </a>
              <button onclick="openBalModal(<?=$u['telegram_id']?>, '<?=addslashes(htmlspecialchars($u['full_name']))?>')" class="btn btn-blue btn-sm">
                <i class="fas fa-coins"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>

    <!-- BALANCE MODAL -->
    <div class="modal-wrap" id="balModal">
      <div class="modal">
        <h3>💰 Balans o'zgartirish</h3>
        <p id="balName" style="font-size:12px;color:var(--muted);margin-bottom:14px"></p>
        <form method="post" action="?action=adj_balance">
          <input type="hidden" id="balTid" name="tid">
          <div class="form-group">
            <label class="form-label">Miqdor (manfiy = ayirish)</label>
            <input class="inp inp-mono" name="amount" type="number" placeholder="Masalan: 50000 yoki -10000" required>
          </div>
          <div class="modal-close">
            <button class="btn btn-green" style="flex:1"><i class="fas fa-check"></i> Saqlash</button>
            <button type="button" onclick="document.getElementById('balModal').classList.remove('open')" class="btn btn-ghost" style="flex:1">Yopish</button>
          </div>
        </form>
      </div>
    </div>
    <script>
    function openBalModal(tid, name) {
      document.getElementById('balTid').value = tid;
      document.getElementById('balName').textContent = name + ' • ID: ' + tid;
      document.getElementById('balModal').classList.add('open');
    }
    </script>
    <?php
}

function pageOrders(): void {
    $filter = $_GET['s'] ?? '';
    $orders = function_exists('getAllOrders') ? getAllOrders() : [];
    if ($filter) $orders = array_filter($orders, fn($o) => $o['status'] === $filter);
    $statuses = ['pending','completed','failed','cancelled'];
    ?>
    <div class="box">
      <div class="box-head">
        <span class="box-title"><i class="fas fa-star" style="color:var(--accent)"></i> Stars buyurtmalari</span>
        <div style="display:flex;gap:5px;flex-wrap:wrap">
          <a href="?action=orders" class="btn <?=!$filter?'btn-gold':'btn-ghost'?> btn-sm">Barchasi</a>
          <?php foreach($statuses as $st): ?>
          <a href="?action=orders&s=<?=$st?>" class="btn <?=$filter===$st?'btn-gold':'btn-ghost'?> btn-sm"><?=$st?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if(empty($orders)): ?>
      <div class="empty-state"><i class="fas fa-star"></i><p>Buyurtmalar topilmadi</p></div>
      <?php else: ?>
      <table>
        <tr><th>#</th><th>Xaridor</th><th>Manzil</th><th>Stars</th><th>Narx</th><th>API ID</th><th>Status</th><th>Vaqt</th><th>O'zgartirish</th></tr>
        <?php foreach(array_slice($orders, 0, 80) as $o):
          $u  = function_exists('getUser') ? getUser((int)$o['buyer_telegram_id']) : null;
          $un = $u ? ($u['username']?"@{$u['username']}":htmlspecialchars($u['full_name'])) : $o['buyer_telegram_id'];
        ?>
        <tr>
          <td class="td-mono" style="color:var(--muted)">#<?=$o['id']?></td>
          <td style="font-size:12px"><?=$un?></td>
          <td class="td-mono" style="color:var(--blue)">@<?=htmlspecialchars($o['target_username']??'?')?></td>
          <td><span class="stars-val"><?=number_format($o['stars_amount'])?></span></td>
          <td class="td-mono" style="color:var(--green)"><?=number_format($o['price'],0,'.',' ')?></td>
          <td><code style="font-size:10px"><?=$o['api_order_id']??'—'?></code></td>
          <td><?=sBadge($o['status'])?></td>
          <td class="td-muted td-mono"><?=date('d.m H:i',strtotime($o['created_at']))?></td>
          <td>
            <form method="post" action="?action=update_order" style="display:flex;gap:4px">
              <input type="hidden" name="order_id" value="<?=$o['id']?>">
              <select name="status" class="inp" style="padding:3px 6px;font-size:10px;width:100px;font-family:var(--font-mono)">
                <?php foreach($statuses as $st): ?><option value="<?=$st?>" <?=$o['status']===$st?'selected':''?>><?=$st?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-gold btn-sm">✓</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php
}

function pageTopups(): void {
    $topups = function_exists('getAllTopupOrders') ? getAllTopupOrders() : [];
    $total   = array_sum(array_map(fn($t) => in_array($t['status'],['paid','completed']) ? (int)$t['amount'] : 0, $topups));
    $pending = count(array_filter($topups, fn($t) => in_array($t['status'],['pending','expired'])));
    $paid    = count(array_filter($topups, fn($t) => in_array($t['status'],['paid','completed'])));
    ?>
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
      <?php foreach([
        ["Jami to'ldirildi", number_format($total,0,'.',' ')." so'm", 'fa-money-bill-wave','si-green','green'],
        ['Muvaffaqiyatli', $paid, 'fa-circle-check','si-blue','blue'],
        ['Kutayotgan/bekor', $pending, 'fa-clock','si-red','red'],
      ] as [$l,$v,$i,$ic,$c]): ?>
      <div class="stat-card <?=$c?>">
        <div class="stat-icon <?=$ic?>"><i class="fas <?=$i?>"></i></div>
        <div class="stat-label"><?=$l?></div>
        <div class="stat-val"><?=$v?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="box">
      <div class="box-head">
        <span class="box-title"><i class="fas fa-wallet" style="color:var(--green)"></i> Balans to'ldirish tarixi</span>
        <span class="chip"><?=count($topups)?> ta jami</span>
      </div>
      <?php if(empty($topups)): ?>
      <div class="empty-state"><i class="fas fa-wallet"></i><p>To'ldirish tarixi yo'q</p></div>
      <?php else: ?>
      <table>
        <tr><th>Buyurtma kodi</th><th>Foydalanuvchi</th><th>Miqdor</th><th>Status</th><th>Muddati</th><th>Yaratildi</th></tr>
        <?php foreach($topups as $t):
          $u = function_exists('getUser') ? getUser((int)$t['telegram_id']) : null;
          $un = $u ? ($u['username']?"@{$u['username']}":htmlspecialchars($u['full_name']??'')) : $t['telegram_id'];
          $exp = (int)$t['expire_at'];
          $expStr = $exp ? date('d.m H:i', $exp) : '—';
          $expired = $exp && time() > $exp && in_array($t['status'],['pending','expired']);
        ?>
        <tr>
          <td><code><?=htmlspecialchars($t['order_code'])?></code></td>
          <td style="font-size:12px"><?=$un?> <span class="td-muted" style="font-size:10px">(<?=$t['telegram_id']?>)</span></td>
          <td class="td-mono" style="color:var(--green);font-weight:700"><?=number_format($t['amount'],0,'.',' ')?> so'm</td>
          <td><?=sBadge($t['status'])?> <?=$expired?"<span style='font-size:10px;color:var(--red)'>muddati o'tgan</span>":''?></td>
          <td class="td-muted td-mono" style="font-size:10px"><?=$expStr?></td>
          <td class="td-muted td-mono"><?=date('d.m H:i',strtotime($t['created_at']))?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php
}

function pageChannels(): void {
    $channels = function_exists('getChannels') ? getChannels() : [];
    ?>
    <div class="two-col">
      <!-- KANAL QO'SHISH -->
      <div class="box">
        <div class="box-head"><span class="box-title"><i class="fas fa-plus" style="color:var(--green)"></i> Kanal qo'shish</span></div>
        <form method="post" action="?action=add_channel" class="form-pad">
          <div class="form-group">
            <label class="form-label">Kanal ID (@username yoki -100xxx)</label>
            <input class="inp inp-mono" name="channel_id" placeholder="@mychannel" required>
          </div>
          <div class="form-group">
            <label class="form-label">Kanal nomi</label>
            <input class="inp" name="title" placeholder="Mening kanalim" required>
          </div>
          <div class="form-group">
            <label class="form-label">Havola (t.me/...)</label>
            <input class="inp" name="link" placeholder="https://t.me/mychannel" required>
          </div>
          <button class="btn btn-gold"><i class="fas fa-plus"></i> Qo'shish</button>
        </form>
      </div>

      <!-- MAVJUD KANALLAR -->
      <div class="box">
        <div class="box-head">
          <span class="box-title"><i class="fas fa-at" style="color:var(--blue)"></i> Majburiy obuna kanallar</span>
          <span class="chip"><?=count($channels)?> ta</span>
        </div>
        <?php if(empty($channels)): ?>
        <div class="empty-state"><i class="fas fa-at"></i><p>Kanallar qo'shilmagan</p></div>
        <?php else: ?>
        <table>
          <tr><th>ID</th><th>Nomi</th><th>Havola</th><th>Amal</th></tr>
          <?php foreach($channels as $ch): ?>
          <tr>
            <td><code><?=htmlspecialchars($ch['id'])?></code></td>
            <td style="font-size:12px;font-weight:500"><?=htmlspecialchars($ch['title'])?></td>
            <td><a href="<?=htmlspecialchars($ch['link'])?>" target="_blank" class="btn btn-blue btn-xs"><i class="fas fa-arrow-up-right-from-square"></i></a></td>
            <td><a href="?action=del_channel&cid=<?=urlencode($ch['id'])?>" class="btn btn-red btn-sm" onclick="return confirm('O\'chirishni tasdiqlaysizmi?')"><i class="fas fa-trash"></i></a></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

function pageSettings(): void {
    $s = function_exists('getSettings') ? getSettings() : [];
    $starsPackages = defined('STARS_PACKAGES') ? STARS_PACKAGES : [50,100,250,500,1000,2500];
    ?>
    <div class="box" style="margin-bottom:16px">
      <div class="box-head"><span class="box-title"><i class="fas fa-star" style="color:var(--accent)"></i> Stars va narx sozlamalari</span></div>
      <form method="post" action="?action=save_settings">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">⭐ 1 Stars narxi (so'm)</label>
            <input class="inp inp-mono" name="stars_price_som" type="number" value="<?=(int)($s['stars_price_som']??195)?>" required>
            <p style="font-size:10px;color:var(--muted);margin-top:4px">Hozirda: <?=number_format((int)($s['stars_price_som']??195),0,'.',' ')?> so'm/stars</p>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">🔑 Fragment-API.uz API kaliti</label>
            <input class="inp inp-mono" name="fragment_api_key" type="text" placeholder="fragment-api.uz dan olingan API kalitingiz..." value="<?=htmlspecialchars($s['fragment_api_key']??'')?>">
            <p style="font-size:10px;color:var(--muted);margin-top:4px">⚠️ <a href="https://fragment-api.uz" target="_blank">fragment-api.uz</a> saytidan ro'yxatdan o'tib oling. Bu kalitni hech kim bilan ulashmaslik kerak!</p>
          </div>
          <div class="form-group">
            <label class="form-label">🔐 Admin panel paroli</label>
            <input class="inp" name="admin_web_pass" type="password" placeholder="Yangi parol (bo'sh qoldirsangiz o'zgarmaydi)">
          </div>
          <div></div>
        </div>
        <div style="padding:0 18px 18px">
          <button class="btn btn-gold"><i class="fas fa-save"></i> Saqlash</button>
        </div>
      </form>
    </div>

    <!-- CONFIG INFO -->
    <div class="box">
      <div class="box-head"><span class="box-title"><i class="fas fa-circle-info" style="color:var(--blue)"></i> Tizim ma'lumotlari</span></div>
      <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <?php
        $infos = [
          ['BOT TOKEN', defined('BOT_TOKEN') && BOT_TOKEN ? substr(BOT_TOKEN,0,15).'***' : '❌ Kiritilmagan'],
          ['KARTA RAQAMI', defined('CARD_NUMBER') ? htmlspecialchars(CARD_NUMBER) : '—'],
          ['RASMIYPAY SHOP ID', defined('RASMIYPAY_SHOP_ID') ? RASMIYPAY_SHOP_ID : '—'],
          ['MIN TOPUP', defined('MIN_TOPUP') ? number_format(MIN_TOPUP,0,'.',' ').' so\'m' : '—'],
          ['MAX TOPUP', defined('MAX_TOPUP') ? number_format(MAX_TOPUP,0,'.',' ').' so\'m' : '—'],
          ['STARS PAKETLARI', implode(', ', $starsPackages)],
        ];
        foreach($infos as [$k,$v]):
        ?>
        <div style="background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;padding:12px">
          <div style="font-size:9px;font-family:var(--font-mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px"><?=$k?></div>
          <div style="font-size:12px;font-family:var(--font-mono);color:var(--text)"><?=$v?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}

function pageBroadcast(): void {
    $users = function_exists('getAllUsers') ? getAllUsers() : [];
    $total = count($users);
    ?>
    <div class="two-col">
      <div class="box">
        <div class="box-head"><span class="box-title"><i class="fas fa-paper-plane" style="color:var(--purple)"></i> Xabar yuborish</span></div>
        <form method="post" action="?action=broadcast_send" class="form-pad">
          <div class="form-group">
            <label class="form-label">Xabar matni (HTML qo'llab-quvvatlanadi)</label>
            <textarea class="inp" name="btext" rows="10" placeholder="Xabaringizni yozing...&#10;&#10;<b>Qalin</b>, <i>kursiv</i>, <code>kod</code>, <a href='...'>havola</a>"></textarea>
          </div>
          <div style="background:rgba(168,85,247,.07);border:1px solid rgba(168,85,247,.2);border-radius:8px;padding:10px;margin-bottom:12px">
            <p style="font-size:12px;color:var(--purple)">⚠️ Barcha <b><?=$total?></b> ta foydalanuvchiga yuboriladi!</p>
          </div>
          <button class="btn btn-gold"><i class="fas fa-paper-plane"></i> Yuborish</button>
        </form>
      </div>

      <div class="box">
        <div class="box-head"><span class="box-title"><i class="fas fa-circle-info" style="color:var(--blue)"></i> HTML formatlar</span></div>
        <div style="padding:16px">
          <?php foreach([
            ['<b>matn</b>', 'Qalin matn'],
            ['<i>matn</i>', 'Kursiv matn'],
            ['<code>kod</code>', 'Kod (bir qator)'],
            ['<pre>kod blok</pre>', 'Kod bloki'],
            ['<a href="URL">matn</a>', 'Havola'],
            ['<u>matn</u>', 'Tagiga chizilgan'],
            ['<s>matn</s>', 'O\'chirilgan'],
            ['<tg-spoiler>matn</tg-spoiler>', 'Spoiler'],
          ] as [$html,$desc]): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:12px">
            <code><?=htmlspecialchars($html)?></code>
            <span style="color:var(--muted)"><?=$desc?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php
}

// ══════════════════════════════════════════════
// LOGIN SAHIFASI
// ══════════════════════════════════════════════
function loginPage(string $err = ''): void { ?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stars Admin — Kirish</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
  background:#07090f;
  color:#f0f4ff;
  font-family:'DM Sans',sans-serif;
  display:flex;align-items:center;justify-content:center;min-height:100vh;
  background-image:radial-gradient(ellipse 60% 50% at 50% 0%,rgba(251,191,36,.08),transparent);
}
.login-box {
  background:#111827;
  border:1px solid #1f2937;
  border-radius:16px;
  padding:40px;
  width:370px;
  text-align:center;
  box-shadow:0 30px 80px rgba(0,0,0,.4);
}
.star-glow {
  font-size:52px;
  display:block;
  margin-bottom:12px;
  filter:drop-shadow(0 0 20px #fbbf24aa);
}
h2 {
  font-family:'Space Mono',monospace;
  font-size:15px;
  letter-spacing:3px;
  text-transform:uppercase;
  color:#fbbf24;
  margin-bottom:4px;
}
p { font-size:12px; color:#6b7280; margin-bottom:28px; }
input {
  width:100%;padding:11px 14px;
  background:rgba(255,255,255,.04);
  border:1px solid #1f2937;
  border-radius:9px;
  color:#f0f4ff;
  font-size:14px;
  outline:none;
  margin-bottom:12px;
  font-family:'Space Mono',monospace;
  transition:.15s;
}
input:focus { border-color:#fbbf24; background:rgba(251,191,36,.04); }
button {
  width:100%;padding:12px;
  background:#fbbf24;
  border:none;border-radius:9px;
  color:#000;
  font-size:14px;font-weight:700;
  cursor:pointer;
  font-family:'DM Sans',sans-serif;
  letter-spacing:.3px;
  transition:.15s;
}
button:hover { background:#f59e0b; transform:translateY(-1px); }
.err {
  background:rgba(248,113,113,.08);
  border:1px solid rgba(248,113,113,.2);
  color:#f87171;
  padding:10px;
  border-radius:8px;
  font-size:12px;
  margin-bottom:14px;
}
</style>
</head>
<body>
<div class="login-box">
  <span class="star-glow">⭐</span>
  <h2>Stars Admin</h2>
  <p>Telegram Stars Bot boshqaruvi</p>
  <?php if($err): ?><div class="err">❌ <?=htmlspecialchars($err)?></div><?php endif; ?>
  <form method="post">
    <input type="password" name="login_pass" placeholder="••••••••" autofocus>
    <button>Kirish →</button>
  </form>
</div>
</body>
</html>
<?php }
?>
