#!/usr/bin/env php
<?php
/**
 * MIGRATION.PHP — JSON data/ -> MySQL
 * Jadvallarni yaratadi + barcha ma'lumotlarni import qiladi
 *
 * Ishlatish:
 *   php migration.php             <- haqiqiy import
 *   php migration.php --dry-run   <- faqat hisoblaydi
 *
 * .env faylida bo'lishi kerak:
 *   DB_HOST=localhost
 *   DB_NAME=c1720_elderstars
 *   DB_USER=c1720_elder
 *   DB_PASS=parol
 */

// === .ENV YUKLASH ===
$_envFile = __DIR__ . '/.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_ln) {
        if (str_starts_with(trim($_ln), '#') || !str_contains($_ln, '=')) continue;
        [$_k, $_v] = explode('=', $_ln, 2);
        $_k = trim($_k); $_v = trim($_v, " \t\n\r\0\x0B\"'");
        if (!empty($_k) && getenv($_k) === false) putenv("$_k=$_v");
    }
}
function _env(string $k, string $d = ''): string {
    $v = getenv($k); return ($v !== false && $v !== '') ? $v : $d;
}

define('DATA_DIR', __DIR__ . '/data');
define('DB_HOST',  _env('DB_HOST', '127.0.0.1'));
define('DB_PORT',  _env('DB_PORT', '3306'));
define('DB_NAME',  _env('DB_NAME', ''));
define('DB_USER',  _env('DB_USER', ''));
define('DB_PASS',  _env('DB_PASS', ''));

// === RANGLAR ===
define('G', "\033[32m");
define('R', "\033[31m");
define('Y', "\033[33m");
define('C', "\033[36m");
define('B', "\033[1m");
define('X', "\033[0m");

$isDryRun = in_array('--dry-run', $argv ?? []);

function log_ok(string $m):   void { echo G."  [OK] $m".X."\n"; }
function log_err(string $m):  void { echo R."  [ERR] $m".X."\n"; }
function log_warn(string $m): void { echo Y."  [WARN] $m".X."\n"; }
function log_info(string $m): void { echo C."  [INFO] $m".X."\n"; }
function log_head(string $m): void { echo "\n".B."=== $m ===".X."\n"; }

function jsonRead(string $f, $def = null) {
    if (!file_exists($f)) return $def;
    $d = json_decode(file_get_contents($f), true);
    return $d !== null ? $d : $def;
}

function progress(int $cur, int $tot, string $lbl): void {
    $p = $tot > 0 ? (int)($cur / $tot * 40) : 0;
    echo "\r  ".C.str_repeat('#', $p).str_repeat('.', 40-$p).X." $cur/$tot $lbl  ";
    if ($cur === $tot) echo "\n";
}

$S = [];
foreach (['settings','users','states','orders','topups','gifts','premium','channels','giftstats'] as $t) {
    $S[$t] = ['ok'=>0,'skip'=>0,'err'=>0];
}

// === HEADER ===
echo "\n".B."=======================================================".X."\n";
echo B."  JSON -> MySQL MIGRATION".X."\n";
echo B."  ".date('Y-m-d H:i:s').X."\n";
if ($isDryRun) echo Y."  DRY-RUN rejimi — hech narsa yozilmaydi".X."\n";
echo B."=======================================================".X."\n";

if (!is_dir(DATA_DIR)) { log_err("data/ papkasi topilmadi: ".DATA_DIR); exit(1); }
log_ok("data/ papkasi: ".DATA_DIR);

if (!DB_NAME || !DB_USER) {
    log_err(".env faylida DB_NAME va DB_USER yo'q!");
    log_info(".env fayliga qo'shing: DB_NAME=..., DB_USER=..., DB_PASS=...");
    exit(1);
}

// === DB ULANISH ===
log_head("MySQL ulanish");
$pdo = null;
if (!$isDryRun) {
    try {
        $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        log_ok("Ulandi: ".DB_NAME."@".DB_HOST);
    } catch (PDOException $e) {
        log_err("DB xatosi: ".$e->getMessage());
        exit(1);
    }
} else {
    log_info("[dry-run] DB ulanish o'tkazildi");
}

// === JADVALLAR YARATISH ===
log_head("JADVALLAR YARATISH (CREATE TABLE IF NOT EXISTS)");

$tables = [];

$tables['settings'] =
"CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(64) NOT NULL,
  `value`      TEXT        NULL,
  `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['users'] =
"CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `telegram_id`  BIGINT       NOT NULL,
  `username`     VARCHAR(64)  NOT NULL DEFAULT '',
  `full_name`    VARCHAR(255) NOT NULL DEFAULT '',
  `balance`      INT          NOT NULL DEFAULT 0,
  `total_spent`  INT          NOT NULL DEFAULT 0,
  `orders_count` INT          NOT NULL DEFAULT 0,
  `is_banned`    TINYINT(1)   NOT NULL DEFAULT 0,
  `referred_by`  VARCHAR(64)  NULL DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_telegram_id` (`telegram_id`),
  INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['states'] =
"CREATE TABLE IF NOT EXISTS `states` (
  `telegram_id` BIGINT      NOT NULL,
  `state`       VARCHAR(64) NOT NULL DEFAULT '',
  `data`        JSON        NULL,
  `updated_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['orders'] =
"CREATE TABLE IF NOT EXISTS `orders` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_bot_id`      INT          NOT NULL DEFAULT 0,
  `buyer_telegram_id` BIGINT       NOT NULL,
  `target_username`   VARCHAR(64)  NOT NULL DEFAULT '',
  `stars_amount`      INT          NOT NULL DEFAULT 0,
  `price`             INT          NOT NULL DEFAULT 0,
  `status`            ENUM('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `api_order_id`      VARCHAR(128) NULL,
  `note`              TEXT         NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_buyer`  (`buyer_telegram_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['topup_orders'] =
"CREATE TABLE IF NOT EXISTS `topup_orders` (
  `order_code`  VARCHAR(64)  NOT NULL,
  `telegram_id` BIGINT       NOT NULL,
  `bot_id`      INT          NOT NULL DEFAULT 0,
  `amount`      INT          NOT NULL DEFAULT 0,
  `status`      ENUM('pending','completed','expired','cancelled') NOT NULL DEFAULT 'pending',
  `expire_at`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_code`),
  INDEX `idx_telegram` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['gift_orders'] =
"CREATE TABLE IF NOT EXISTS `gift_orders` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_telegram_id` BIGINT       NOT NULL,
  `buyer_bot_id`      INT          NOT NULL DEFAULT 0,
  `target_username`   VARCHAR(64)  NOT NULL DEFAULT '',
  `gift_name`         VARCHAR(32)  NOT NULL DEFAULT '',
  `stars_amount`      INT          NOT NULL DEFAULT 0,
  `som_price`         INT          NOT NULL DEFAULT 0,
  `status`            ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `error_code`        VARCHAR(64)  NOT NULL DEFAULT '',
  `error_message`     TEXT         NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_buyer` (`buyer_telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['premium_orders'] =
"CREATE TABLE IF NOT EXISTS `premium_orders` (
  `id`                VARCHAR(32)      NOT NULL,
  `buyer_telegram_id` BIGINT           NOT NULL,
  `buyer_bot_id`      INT              NOT NULL DEFAULT 0,
  `target_username`   VARCHAR(64)      NOT NULL DEFAULT '',
  `months`            TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `price`             INT              NOT NULL DEFAULT 0,
  `status`            ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `api_response`      JSON             NULL,
  `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at`      DATETIME         NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_buyer` (`buyer_telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['channels'] =
"CREATE TABLE IF NOT EXISTS `channels` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` VARCHAR(64)  NOT NULL,
  `title`      VARCHAR(255) NOT NULL DEFAULT '',
  `link`       VARCHAR(255) NOT NULL DEFAULT '',
  `added_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['gift_stats'] =
"CREATE TABLE IF NOT EXISTS `gift_stats` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stat_key`  VARCHAR(64)  NOT NULL,
  `stat_date` DATE         NULL,
  `value`     BIGINT       NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key_date` (`stat_key`, `stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($pdo) {
    foreach ($tables as $name => $sql) {
        try {
            $pdo->exec($sql);
            log_ok("`$name` jadvali tayyor");
        } catch (PDOException $e) {
            log_err("`$name` yaratishda xato: ".$e->getMessage());
            exit(1);
        }
    }
    // Agar users jadvali oldin yaratilgan bo'lsa va referred_by yo'q bo'lsa
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'referred_by'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `referred_by` VARCHAR(64) NULL DEFAULT NULL");
            log_ok("users.referred_by ustuni qo'shildi");
        }
    } catch (PDOException $e) { /* ignore */ }
} else {
    log_info("[dry-run] Jadvallar yaratilmadi");
}

// === SETTINGS ===
log_head("SETTINGS");
$settingsData = jsonRead(DATA_DIR.'/settings.json', []);
if (!$settingsData) {
    log_warn("settings.json topilmadi");
} else {
    $st = $pdo ? $pdo->prepare(
        "INSERT INTO `settings` (`key`, `value`) VALUES (?,?)
         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
    ) : null;
    foreach ($settingsData as $k => $v) {
        $val = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v;
        if ($isDryRun) { $S['settings']['ok']++; continue; }
        try { $st->execute([$k, $val]); $S['settings']['ok']++; }
        catch (PDOException $e) { $S['settings']['err']++; log_err("settings[$k]: ".$e->getMessage()); }
    }
    log_ok("Settings: {$S['settings']['ok']} ta kalit");
}

// === USERS ===
log_head("USERS");
$userFiles = glob(DATA_DIR.'/users/*.json') ?: [];
$total = count($userFiles);
echo "  Topildi: $total ta fayl\n";

$st = $pdo ? $pdo->prepare("
    INSERT INTO `users`
        (id,telegram_id,username,full_name,balance,total_spent,orders_count,is_banned,referred_by,created_at,last_seen)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        username=VALUES(username),full_name=VALUES(full_name),
        balance=VALUES(balance),total_spent=VALUES(total_spent),
        orders_count=VALUES(orders_count),is_banned=VALUES(is_banned),
        referred_by=VALUES(referred_by),last_seen=VALUES(last_seen)
") : null;

foreach ($userFiles as $i => $f) {
    progress($i+1, $total, "users");
    $u = jsonRead($f);
    if (!$u) { $S['users']['skip']++; continue; }
    if ($isDryRun) { $S['users']['ok']++; continue; }
    try {
        $st->execute([
            (int)($u['id']           ?? 0),
            (int)($u['telegram_id']  ?? 0),
            (string)($u['username']  ?? ''),
            (string)($u['full_name'] ?? ''),
            (int)($u['balance']      ?? 0),
            (int)($u['total_spent']  ?? 0),
            (int)($u['orders_count'] ?? 0),
            (int)($u['is_banned']    ?? 0),
            $u['referred_by'] ?? null,
            $u['created_at']  ?? null,
            $u['last_seen']   ?? null,
        ]);
        $S['users']['ok']++;
    } catch (PDOException $e) {
        $S['users']['err']++;
        log_err("user {$u['telegram_id']}: ".$e->getMessage());
    }
}
log_ok("Users: ok={$S['users']['ok']}  skip={$S['users']['skip']}  err={$S['users']['err']}");

// === STATES ===
log_head("STATES");
$statesData = jsonRead(DATA_DIR.'/states.json', []);
$total = count($statesData);
echo "  Topildi: $total ta holat\n";

$st = $pdo ? $pdo->prepare("
    INSERT INTO `states` (telegram_id,state,data)
    VALUES (?,?,?)
    ON DUPLICATE KEY UPDATE state=VALUES(state),data=VALUES(data)
") : null;

$i = 0;
foreach ($statesData as $tid => $stObj) {
    progress(++$i, $total, "states");
    if ($isDryRun) { $S['states']['ok']++; continue; }
    try {
        $st->execute([
            (int)$tid,
            (string)($stObj['state'] ?? ''),
            json_encode($stObj['data'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        $S['states']['ok']++;
    } catch (PDOException $e) {
        $S['states']['err']++;
        log_err("state $tid: ".$e->getMessage());
    }
}
log_ok("States: ok={$S['states']['ok']}  err={$S['states']['err']}");

// === STARS ORDERS ===
log_head("STARS ORDERS");
$orderFiles = glob(DATA_DIR.'/orders/*.json') ?: [];
usort($orderFiles, fn($a,$b) => (int)basename($a,'.json') <=> (int)basename($b,'.json'));
$total = count($orderFiles);
echo "  Topildi: $total ta fayl\n";

$allowedStatus = ['pending','processing','completed','failed','cancelled'];
$st = $pdo ? $pdo->prepare("
    INSERT INTO `orders`
        (id,buyer_bot_id,buyer_telegram_id,target_username,stars_amount,price,status,api_order_id,note,created_at,updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        status=VALUES(status),api_order_id=VALUES(api_order_id),
        note=VALUES(note),updated_at=VALUES(updated_at)
") : null;

foreach ($orderFiles as $i => $f) {
    progress($i+1, $total, "orders");
    $o = jsonRead($f);
    if (!$o) { $S['orders']['skip']++; continue; }
    $status = in_array($o['status']??'', $allowedStatus) ? $o['status'] : 'pending';
    if ($isDryRun) { $S['orders']['ok']++; continue; }
    try {
        $st->execute([
            (int)($o['id']                ?? 0),
            (int)($o['buyer_bot_id']      ?? 0),
            (int)($o['buyer_telegram_id'] ?? 0),
            (string)($o['target_username'] ?? ''),
            (int)($o['stars_amount'] ?? 0),
            (int)($o['price']        ?? 0),
            $status,
            $o['api_order_id'] ?? null,
            (string)($o['note'] ?? ''),
            $o['created_at'] ?? null,
            $o['updated_at'] ?? null,
        ]);
        $S['orders']['ok']++;
    } catch (PDOException $e) {
        $S['orders']['err']++;
        log_err("order {$o['id']}: ".$e->getMessage());
    }
}
log_ok("Orders: ok={$S['orders']['ok']}  skip={$S['orders']['skip']}  err={$S['orders']['err']}");

// === TOPUP ORDERS ===
log_head("TOPUP ORDERS");
$topupFiles = glob(DATA_DIR.'/topups/*.json') ?: [];
$total = count($topupFiles);
echo "  Topildi: $total ta fayl\n";

$topupMap = ['pending'=>'pending','paid'=>'completed','completed'=>'completed','expired'=>'expired','cancelled'=>'cancelled'];
$st = $pdo ? $pdo->prepare("
    INSERT INTO `topup_orders`
        (order_code,telegram_id,bot_id,amount,status,expire_at,created_at,updated_at)
    VALUES (?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE status=VALUES(status),updated_at=VALUES(updated_at)
") : null;

foreach ($topupFiles as $i => $f) {
    progress($i+1, $total, "topups");
    $t = jsonRead($f);
    if (!$t) { $S['topups']['skip']++; continue; }
    $status = $topupMap[$t['status']??''] ?? 'pending';
    if ($isDryRun) { $S['topups']['ok']++; continue; }
    try {
        $st->execute([
            (string)($t['order_code'] ?? ''),
            (int)($t['telegram_id']   ?? 0),
            (int)($t['bot_id']        ?? 0),
            (int)($t['amount']        ?? 0),
            $status,
            (int)($t['expire_at']     ?? 0),
            $t['created_at'] ?? null,
            $t['updated_at'] ?? null,
        ]);
        $S['topups']['ok']++;
    } catch (PDOException $e) {
        $S['topups']['err']++;
        log_err("topup {$t['order_code']}: ".$e->getMessage());
    }
}
log_ok("Topups: ok={$S['topups']['ok']}  skip={$S['topups']['skip']}  err={$S['topups']['err']}");

// === GIFT ORDERS ===
log_head("GIFT ORDERS");
$giftFiles = glob(DATA_DIR.'/gifts/order_*.json') ?: [];
usort($giftFiles, function($a,$b) {
    preg_match('/order_(\d+)/', $a, $ma); preg_match('/order_(\d+)/', $b, $mb);
    return (int)($ma[1]??0) <=> (int)($mb[1]??0);
});
$total = count($giftFiles);
echo "  Topildi: $total ta fayl\n";

$st = $pdo ? $pdo->prepare("
    INSERT INTO `gift_orders`
        (id,buyer_telegram_id,buyer_bot_id,target_username,gift_name,stars_amount,som_price,status,error_code,error_message,created_at,updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        status=VALUES(status),error_code=VALUES(error_code),
        error_message=VALUES(error_message),updated_at=VALUES(updated_at)
") : null;

foreach ($giftFiles as $i => $f) {
    progress($i+1, $total, "gifts");
    $g = jsonRead($f);
    if (!$g) { $S['gifts']['skip']++; continue; }
    if ($isDryRun) { $S['gifts']['ok']++; continue; }
    try {
        $st->execute([
            (int)($g['id']                ?? 0),
            (int)($g['buyer_telegram_id'] ?? 0),
            (int)($g['buyer_bot_id']      ?? 0),
            (string)($g['target_username'] ?? ''),
            (string)($g['gift_name']       ?? ''),
            (int)($g['stars_amount']    ?? 0),
            (int)($g['som_price']       ?? 0),
            (string)($g['status']       ?? 'pending'),
            (string)($g['error_code']   ?? ''),
            (string)($g['error_message']?? ''),
            $g['created_at'] ?? null,
            $g['updated_at'] ?? null,
        ]);
        $S['gifts']['ok']++;
    } catch (PDOException $e) {
        $S['gifts']['err']++;
        log_err("gift {$g['id']}: ".$e->getMessage());
    }
}
log_ok("Gifts: ok={$S['gifts']['ok']}  skip={$S['gifts']['skip']}  err={$S['gifts']['err']}");

// === PREMIUM ORDERS ===
log_head("PREMIUM ORDERS");
$premFiles = glob(DATA_DIR.'/premium_orders/*.json') ?: [];
$total = count($premFiles);
echo "  Topildi: $total ta fayl\n";

$st = $pdo ? $pdo->prepare("
    INSERT INTO `premium_orders`
        (id,buyer_telegram_id,buyer_bot_id,target_username,months,price,status,api_response,created_at,completed_at)
    VALUES (?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        status=VALUES(status),api_response=VALUES(api_response),completed_at=VALUES(completed_at)
") : null;

foreach ($premFiles as $i => $f) {
    progress($i+1, $total, "premium");
    $p = jsonRead($f);
    if (!$p) { $S['premium']['skip']++; continue; }
    $apiResp = isset($p['api_response']) ? json_encode($p['api_response'], JSON_UNESCAPED_UNICODE) : null;
    if ($isDryRun) { $S['premium']['ok']++; continue; }
    try {
        $st->execute([
            (string)($p['id']                ?? ''),
            (int)($p['buyer_telegram_id']    ?? 0),
            (int)($p['buyer_bot_id']         ?? 0),
            (string)($p['target_username']   ?? ''),
            (int)($p['months']  ?? 1),
            (int)($p['price']   ?? 0),
            (string)($p['status'] ?? 'pending'),
            $apiResp,
            $p['created_at']  ?? null,
            $p['completed_at']?? null,
        ]);
        $S['premium']['ok']++;
    } catch (PDOException $e) {
        $S['premium']['err']++;
        log_err("premium {$p['id']}: ".$e->getMessage());
    }
}
log_ok("Premium: ok={$S['premium']['ok']}  skip={$S['premium']['skip']}  err={$S['premium']['err']}");

// === CHANNELS ===
log_head("CHANNELS");
$channelsData = jsonRead(DATA_DIR.'/channels.json', []);
if (is_array($channelsData) && count($channelsData) > 0) {
    $st = $pdo ? $pdo->prepare(
        "INSERT IGNORE INTO `channels` (channel_id,title,link) VALUES (?,?,?)"
    ) : null;
    foreach ($channelsData as $ch) {
        if ($isDryRun) { $S['channels']['ok']++; continue; }
        try { $st->execute([$ch['id']??'',$ch['title']??'',$ch['link']??'']); $S['channels']['ok']++; }
        catch (PDOException $e) { $S['channels']['err']++; log_err("channel: ".$e->getMessage()); }
    }
    log_ok("Channels: {$S['channels']['ok']} ta");
} else {
    log_info("Kanallar bo'sh");
}

// === GIFT STATS ===
log_head("GIFT STATS");
$gs = jsonRead(DATA_DIR.'/gift_stats.json', []);
if ($gs) {
    $st = $pdo ? $pdo->prepare("
        INSERT INTO `gift_stats` (stat_key,stat_date,value) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE value=VALUES(value)
    ") : null;
    $rows = [];
    foreach (['total_sent','total_completed','total_failed'] as $k) $rows[] = [$k, null, (int)($gs[$k]??0)];
    foreach ($gs['by_gift']??[] as $n => $c) $rows[] = ["gift:$n", null, (int)$c];
    foreach ($gs['daily'] ??[] as $d => $c) $rows[] = ['daily',  $d, (int)$c];
    foreach ($gs['weekly']??[] as $w => $c) $rows[] = ["weekly:$w", null, (int)$c]; // 2026-W13 formatini stat_key ga joylashtiramiz
    foreach ($rows as $row) {
        if ($isDryRun) { $S['giftstats']['ok']++; continue; }
        try { $st->execute($row); $S['giftstats']['ok']++; }
        catch (PDOException $e) { $S['giftstats']['err']++; log_err("gift_stat: ".$e->getMessage()); }
    }
    log_ok("Gift stats: {$S['giftstats']['ok']} ta yozuv");
} else {
    log_warn("gift_stats.json topilmadi");
}

// === AUTO_INCREMENT ===
log_head("AUTO_INCREMENT");
$counter = jsonRead(DATA_DIR.'/counter.json', []);
if ($counter) {
    $uid  = (int)($counter['user_id']       ?? 0) + 1;
    $oid  = (int)($counter['order_id']      ?? 0) + 1;
    $goid = (int)($counter['gift_order_id'] ?? 0) + 1;
    if ($pdo && !$isDryRun) {
        $pdo->exec("ALTER TABLE `users`       AUTO_INCREMENT = $uid");
        $pdo->exec("ALTER TABLE `orders`      AUTO_INCREMENT = $oid");
        $pdo->exec("ALTER TABLE `gift_orders` AUTO_INCREMENT = $goid");
    }
    log_ok("users AUTO_INCREMENT = $uid");
    log_ok("orders AUTO_INCREMENT = $oid");
    log_ok("gift_orders AUTO_INCREMENT = $goid");
}

// === NATIJA ===
$totalErr = array_sum(array_column($S, 'err'));

echo "\n".B."=======================================================".X."\n";
echo B."  NATIJA".X."\n";
echo B."=======================================================".X."\n\n";
printf("  %-26s %8s %8s %8s\n", 'Jadval', 'OK', 'Skip', 'Xato');
echo "  ".str_repeat('-', 53)."\n";
foreach ([
    ['Sozlamalar',          $S['settings']],
    ['Foydalanuvchilar',    $S['users']],
    ['Holatlar (states)',   $S['states']],
    ['Stars buyurtmalar',   $S['orders']],
    ['Topup buyurtmalar',   $S['topups']],
    ['Gift buyurtmalar',    $S['gifts']],
    ['Premium buyurtmalar', $S['premium']],
    ['Kanallar',            $S['channels']],
    ['Gift statistika',     $S['giftstats']],
] as [$lbl, $s]) {
    printf("  %-26s %s%8d%s %8d %s%8d%s\n",
        $lbl,
        G, $s['ok'], X,
        $s['skip'],
        ($s['err']>0?R:X), $s['err'], X
    );
}
echo "  ".str_repeat('-', 53)."\n\n";

if ($totalErr === 0) echo G.B."  MIGRATION MUVAFFAQIYATLI TUGADI!".X."\n";
else echo R."  $totalErr ta xato. Yuqoridagi [ERR] loglarni tekshiring.".X."\n";
if ($isDryRun) echo Y."  Dry-run edi. Haqiqiy import uchun --dry-run ni olib tashlang.".X."\n";
echo "\n".B."=======================================================".X."\n\n";

exit($totalErr > 0 ? 1 : 0);
