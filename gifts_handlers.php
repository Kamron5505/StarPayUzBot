<?php
/**
 * ╔══════════════════════════════════════════════════════╗
 * ║      ELDER STARS — Gift qo'shimcha handlerlar        ║
 * ║  Faqat handlers.php da yo'q funksiyalar shu yerda    ║
 * ╚══════════════════════════════════════════════════════╝
 */

declare(strict_types=1);

// ─── Unikal Giftlar menyusi ───────────────────────────────────────────────────
/**
 * Callback data format: gift_unikal_{username}
 * "😎 Unikal Giftlar" tugmasi bosilganda chaqiriladi.
 */
function cbGiftUnikalMenu(int $chatId, int $msgId, string $cbId,
                          int $userId, array $user, string $data): void
{
    $username = substr($data, strlen('gift_unikal_'));

    if (empty($username)) {
        answerCallback($cbId, '❌ Xato ma\'lumot', true);
        return;
    }

    editMessage($chatId, $msgId,
        "😎 <b>Unikal Giftlar</b>\n\n"
        . "👤 Qabul qiluvchi: @{$username}\n\n"
        . "Quyidagi unikal giftlardan birini tanlang:",
        giftUnikalListKb($username)
    );

    answerCallback($cbId);
}
