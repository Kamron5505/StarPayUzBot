<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/keyboards.php';
require_once __DIR__ . '/stars_api.php';
require_once __DIR__ . '/premium_api.php';
require_once __DIR__ . '/payment_api.php';
require_once __DIR__ . '/gifts_handlers.php';
require_once __DIR__ . '/sendGift.php';
require_once __DIR__ . '/handlers.php';

// ═══════════════════════════════════════════════════════════════
// 1) WEBHOOK IMZO TEKSHIRUVI — faqat Telegram so'rovlarini qabul qil
// ═══════════════════════════════════════════════════════════════
function verifyWebhookSecret(): void {
    $secret = WEBHOOK_SECRET;

    // Agar secret o'rnatilmagan bo'lsa — xavfli, lekin davom etamiz (logda ogohlantirish)
    if (empty($secret)) {
        error_log('[WEBHOOK] XAVF: WEBHOOK_SECRET o\'rnatilmagan! So\'rov tekshirilmadi.');
        return;
    }

    $header = '';

    // PHP-FPM / Apache
    if (isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])) {
        $header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'];
    }
    // Nginx ba'zan getallheaders() orqali keladi
    elseif (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $header  = $headers['x-telegram-bot-api-secret-token'] ?? '';
    }

    if (!hash_equals($secret, $header)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

verifyWebhookSecret();

// ═══════════════════════════════════════════════════════════════
// 2) KIRITMA YUKLASH
// ═══════════════════════════════════════════════════════════════
$rawInput = file_get_contents('php://input');
$update   = json_decode($rawInput, true);

if (!is_array($update) || empty($update)) {
    http_response_code(200);
    exit;
}

// ═══════════════════════════════════════════════════════════════
// 3) Har so'rovda muddati o'tgan topuplarni tekshir
// ═══════════════════════════════════════════════════════════════
checkExpiredTopups();

// ═══════════════════════════════════════════════════════════════
// 4) Telegramga darhol 200 OK qaytaramiz
// ═══════════════════════════════════════════════════════════════
http_response_code(200);
echo 'OK';

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    if (ob_get_level()) ob_end_flush();
    flush();
}

// ═══════════════════════════════════════════════════════════════
// 5) Asosiy logika
// ═══════════════════════════════════════════════════════════════
try {
    handleUpdate($update);
} catch (Throwable $e) {
    error_log('[BOT ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}
