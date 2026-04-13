<?php
/**
 * ╔══════════════════════════════════════════════════════╗
 * ║        ELDER STARS — Gift Sender (MadelineProto)     ║
 * ║  MadelineProto 8.6 | hide_name = true (Hidden User) ║
 * ╚══════════════════════════════════════════════════════╝
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;

$GLOBALS['_gifts'] = require_once __DIR__ . '/gifts.php';

// ─── Logger ───────────────────────────────────────────────────────────────────
function giftLog(string $level, string $msg, array $ctx = []): void
{
    $logFile = __DIR__ . '/data/gift_log.txt';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    $line = sprintf(
        "[%s] [%s] %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $msg,
        $ctx ? ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) : ''
    );
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ─── Gift ID olish ────────────────────────────────────────────────────────────
function getGiftId(string $giftName): ?int
{
    $gifts = $GLOBALS['_gifts'];
    $key   = strtolower(trim($giftName));
    return isset($gifts[$key]) ? (int)$gifts[$key] : null;
}

// ─── Asosiy gift yuborish funksiyasi ─────────────────────────────────────────
/**
 * @param string|int $user      Username (@bilan/siz) yoki user_id
 * @param string     $giftName  gifts.php dagi kalit ('bear', 'heart', ...)
 * @param bool       $hideMe    TRUE  → "Hidden User" (anonim) ← DEFAULT
 *                              false → sizning ismingiz ko'rinadi
 * @param string     $message   Ixtiyoriy xabar matni
 */
function sendGift(
    string|int $user,
    string     $giftName,
    bool       $hideMe  = true,   // ← DEFAULT true — doim anonim
    string     $message = ''
): array {
    // 1. Gift ID tekshirish
    $giftId = getGiftId($giftName);
    if ($giftId === null) {
        giftLog('error', "Noma'lum gift nomi", ['gift' => $giftName]);
        return [
            'ok'      => false,
            'error'   => 'UNKNOWN_GIFT',
            'message' => "❌ «{$giftName}» nomi bilan gift topilmadi.",
        ];
    }

    // 2. Peer normalize
    $peer = is_string($user)
        ? '@' . ltrim(trim($user), '@')
        : (int)$user;

    giftLog('info', 'Gift yuborilmoqda', [
        'peer'     => $peer,
        'gift'     => $giftName,
        'gift_id'  => $giftId,
        'hide_me'  => $hideMe,
    ]);

    try {
        $mp = getMadeline();

        // 3. Invoice — MadelineProto 8.6 sintaksisi
        //    hide_name = true  →  Telegram "Hidden User" ko'rsatadi
        $invoice = [
            '_'         => 'inputInvoiceStarGift',
            'hide_name' => $hideMe,   // bool: true = anonim, false = ochiq
            'peer'      => $peer,
            'gift_id'   => $giftId,
        ];

        // Xabar bo'lsa qo'shamiz
        if ($message !== '') {
            $invoice['message'] = [
                '_'        => 'textWithEntities',
                'text'     => $message,
                'entities' => [],
            ];
        }

        giftLog('debug', 'Invoice', $invoice);

        // 4. Payment form olamiz
        $form   = $mp->payments->getPaymentForm(invoice: $invoice);
        $formId = $form['form_id'];

        giftLog('info', 'Payment form olindi', [
            'form_id' => $formId,
            'peer'    => $peer,
        ]);

        // 5. Stars bilan to'laymiz
        $result = $mp->payments->sendStarsForm(
            form_id: $formId,
            invoice: $invoice
        );

        $status = is_array($result) ? ($result['_'] ?? 'ok') : (string)$result;

        giftLog('info', 'Gift yuborildi ✅', [
            'peer'    => $peer,
            'gift'    => $giftName,
            'hidden'  => $hideMe,
            'result'  => $status,
        ]);

        return [
            'ok'      => true,
            'message' => "✅ {$giftName} yuborildi!" . ($hideMe ? ' (Hidden User)' : ''),
            'peer'    => $peer,
            'gift_id' => $giftId,
            'hidden'  => $hideMe,
        ];

    } catch (\Throwable $e) {
        $err = $e->getMessage();

        $known = [
            'FORM_EXPIRED'    => '⚠️ Payment form muddati tugadi, qayta chaqiring.',
            'BALANCE_TOO_LOW' => '💸 Yetarli Telegram Stars yo\'q.',
            'USER_NOT_FOUND'  => '🔍 Foydalanuvchi topilmadi.',
            'GIFT_SOLD_OUT'   => '🎁 Bu gift tugagan.',
        ];

        foreach ($known as $code => $hint) {
            if (str_contains($err, $code)) {
                giftLog('warn', $code, ['peer' => $peer, 'gift' => $giftName]);
                return ['ok' => false, 'error' => $code, 'message' => $hint];
            }
        }

        giftLog('error', 'Kutilmagan xato', [
            'peer'  => $peer,
            'gift'  => $giftName,
            'error' => $err,
        ]);

        return [
            'ok'      => false,
            'error'   => 'MADELINE_ERROR',
            'message' => '❌ Xato: ' . $err,
        ];
    }
}

// ─── MadelineProto singleton ─────────────────────────────────────────────────
function getMadeline(): API
{
    static $mp = null;

    if ($mp === null) {
        $settings = new Settings();
        $settings->getAppInfo()
            ->setApiId((int)(defined('TG_API_ID')   ? TG_API_ID   : getenv('TG_API_ID')))
            ->setApiHash(defined('TG_API_HASH') ? TG_API_HASH : getenv('TG_API_HASH'));

        $mp = new API(__DIR__ . '/session.madeline', $settings);
        $mp->start();
    }

    return $mp;
}