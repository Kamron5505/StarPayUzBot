<?php
// ═══════════════════════════════════════════════════════════════════
// BOT SOZLAMALARI — .env faylidan o'qiladi
// ═══════════════════════════════════════════════════════════════════

// .env faylini yuklash
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key); $val = trim($val, " \t\n\r\0\x0B\"'");
        if (!empty($key) && !array_key_exists($key, $_ENV)) {
            putenv("$key=$val");
        }
    }
}

function env(string $key, string $default = ''): string {
    $v = getenv($key);
    return ($v !== false && $v !== '') ? $v : $default;
}

// ─── Bot token ────────────────────────────────────────────────────
define('BOT_TOKEN',      env('BOT_TOKEN'));
define('WEBHOOK_SECRET', env('WEBHOOK_SECRET'));

// ─── Admin ────────────────────────────────────────────────────────
$_adminIds = array_filter(array_map('intval', explode(',', env('ADMIN_IDS', ''))));
define('ADMIN_IDS',      $_adminIds ?: []);
define('ADMIN_USERNAME', env('ADMIN_USERNAME', ''));

// ─── RasmiyPay ────────────────────────────────────────────────────
define('RASMIYPAY_SHOP_ID',  env('RASMIYPAY_SHOP_ID'));
define('RASMIYPAY_SHOP_KEY', env('RASMIYPAY_SHOP_KEY'));

// ─── To'lov karta raqami ──────────────────────────────────────────
define('CARD_NUMBER', env('CARD_NUMBER'));

// ─── To'lov limitleri (so'm) ──────────────────────────────────────
define('MIN_TOPUP', 1000);
define('MAX_TOPUP', 2500000);

// ─── Stars paketlari ──────────────────────────────────────────────
define('STARS_PACKAGES', [50, 100, 150, 200]);

// ─── MySQL ulanish (YANGI) ────────────────────────────────────────
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'c1720_lastof'));
define('DB_USER', env('DB_USER', 'c1720_lastof'));
define('DB_PASS', env('DB_PASS', 'IAN0360708'));

// ─── MadelineProto ────────────────────────────────────────────────
define('TG_API_ID',   (int)env('TG_API_ID', '0'));
define('TG_API_HASH', env('TG_API_HASH'));

// ─── Asosiy qiymatlarni tekshirish ────────────────────────────────
if (empty(BOT_TOKEN)) {
    error_log('[CONFIG] BOT_TOKEN o\'rnatilmagan!');
}
if (empty(WEBHOOK_SECRET)) {
    error_log('[CONFIG] WEBHOOK_SECRET o\'rnatilmagan! Webhook himoyalanmagan.');
}
if (empty(DB_NAME)) {
    error_log('[CONFIG] DB_NAME o\'rnatilmagan! .env faylini tekshiring.');
}
