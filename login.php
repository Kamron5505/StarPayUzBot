<?php
/**
 * ╔══════════════════════════════════════════════════════╗
 * ║        ELDER STARS — MadelineProto Login             ║
 * ║  Faqat BIR MARTA ishga tushiring, keyin o'chiring!  ║
 * ╚══════════════════════════════════════════════════════╝
 *
 * Ishga tushirish:
 *   php login.php
 *
 * Terminal so'raydi:
 *   Phone number: +998901234567
 *   Code: 12345
 *   Password: ****  (2FA bo'lsa)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;

echo "╔══════════════════════════════════════╗\n";
echo "║     ELDER STARS — MadelineProto      ║\n";
echo "╚══════════════════════════════════════╝\n\n";

// API ID va HASH tekshirish
if (!defined('TG_API_ID') || TG_API_ID === 0) {
    die("❌ config.php da TG_API_ID va TG_API_HASH ni to'ldiring!\n"
      . "   my.telegram.org dan oling.\n");
}

echo "⏳ MadelineProto ishga tushmoqda...\n";

$settings = new Settings();
$settings->getAppInfo()
    ->setApiId(TG_API_ID)
    ->setApiHash(TG_API_HASH);

$MadelineProto = new API(__DIR__ . '/session.madeline', $settings);
$MadelineProto->start();  // ← Bu yerda telefon raqam va kod so'raladi

$self = $MadelineProto->getSelf();

echo "\n✅ Login muvaffaqiyatli!\n";
echo "👤 Akkaunt: " . ($self['first_name'] ?? '') . ' ' . ($self['last_name'] ?? '') . "\n";
echo "📱 Username: @" . ($self['username'] ?? 'yo\'q') . "\n";
echo "🆔 ID: " . ($self['id'] ?? '') . "\n\n";
echo "🔒 session.madeline fayli saqlandi.\n";
echo "⚠️  Endi bu login.php faylini o'chiring (xavfsizlik uchun)!\n";
echo "   rm login.php\n\n";
