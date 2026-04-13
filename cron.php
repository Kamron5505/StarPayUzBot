<?php
/**
 * cron.php — Har daqiqada bir marta cron orqali ishga tushiriladi.
 * Ichida 6 soniya oraliqda 10 marta tekshiradi → amalda har 6 sekundda ishlaydi.
 *
 * cPanel Cron Job:
 *   * * * * * php /var/www/.../cron.php >> /dev/null 2>&1
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/keyboards.php';
require_once __DIR__ . '/payment_api.php';
require_once __DIR__ . '/handlers.php';

// Bir vaqtda faqat bitta process ishlashi uchun lock
$lockFile = sys_get_temp_dir() . '/starsbot_cron.lock';
$lock = fopen($lockFile, 'w');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    // Avvalgi process hali tugamagan — chiqib ketamiz
    exit;
}

$interval  = 6;   // har necha sekundda tekshirish
$duration  = 58;  // necha sekund ishlash (58 sek — keyingi cron kelishidan oldin to'xtaymiz)
$startTime = time();

while ((time() - $startTime) < $duration) {
    $iterStart = microtime(true);

    try {
        checkExpiredTopups();
    } catch (Throwable $e) {
        error_log('[CRON ERROR] ' . $e->getMessage());
    }

    // Keyingi iteratsiyagacha qolgan vaqtni hisoblab uxlaymiz
    $elapsed = microtime(true) - $iterStart;
    $sleep   = max(0, $interval - $elapsed);
    if ($sleep > 0) {
        usleep((int)($sleep * 1_000_000));
    }
}

flock($lock, LOCK_UN);
fclose($lock);