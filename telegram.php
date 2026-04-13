<?php
/**
 * Telegram Bot API — HTTP so'rovlar
 * Optimizatsiya: timeout kamaytirilib, parallel kanal tekshiruv qo'shildi
 */

function apiRequest(string $method, array $params = []): ?array {
    $url  = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $json = json_encode($params, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TCP_NODELAY    => true,
    ]);
    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errmsg   = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        error_log('[TG CURL ERROR] ' . $errmsg);
        return null;
    }
    if (!$response) return null;

    $data = json_decode($response, true);
    if (!$data || !$data['ok']) {
        error_log('[TG API ERROR] ' . $method . ' => ' . $response);
        return null;
    }

    $result = $data['result'] ?? null;
    if ($result === true)      return ['ok' => true];
    if ($result === false)     return null;
    if (!is_array($result))    return null;
    return $result;
}

function sendMessage(int $chatId, string $text,
                     array $replyMarkup = [], string $parseMode = 'HTML'): ?array {
    $params = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => $parseMode,
    ];
    if ($replyMarkup) $params['reply_markup'] = $replyMarkup;
    return apiRequest('sendMessage', $params);
}

function editMessage(int $chatId, int $messageId, string $text,
                     array $replyMarkup = [], string $parseMode = 'HTML'): ?array {
    $params = [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'text'       => $text,
        'parse_mode' => $parseMode,
    ];
    if ($replyMarkup) $params['reply_markup'] = $replyMarkup;
    return apiRequest('editMessageText', $params);
}

function answerCallback(string $callbackId, string $text = '',
                        bool $showAlert = false): ?array {
    return apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text'              => $text,
        'show_alert'        => $showAlert,
    ]);
}

function deleteMessage(int $chatId, int $messageId): ?array {
    return apiRequest('deleteMessage', [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
    ]);
}

function fmtMoney(int $amount): string {
    return number_format($amount, 0, '.', ' ');
}

function isAdmin(int $userId): bool {
    return in_array($userId, ADMIN_IDS, true);
}

function copyMessage(int $toChatId, int $fromChatId, int $messageId,
                     array $replyMarkup = []): ?array {
    $params = [
        'chat_id'      => $toChatId,
        'from_chat_id' => $fromChatId,
        'message_id'   => $messageId,
    ];
    if ($replyMarkup) $params['reply_markup'] = $replyMarkup;
    return apiRequest('copyMessage', $params);
}

// ─── MAJBURIY OBUNA — PARALLEL TEKSHIRISH ────────────────────────────────────

function checkAllChannels(int $userId): array {
    $channels = getChannels();
    if (empty($channels)) return ['ok' => true];

    $baseUrl = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getChatMember';
    $mh      = curl_multi_init();
    $handles = [];

    foreach ($channels as $i => $ch) {
        $json = json_encode(['chat_id' => $ch['id'], 'user_id' => $userId], JSON_UNESCAPED_UNICODE);
        $c = curl_init($baseUrl);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TCP_NODELAY    => true,
        ]);
        curl_multi_add_handle($mh, $c);
        $handles[$i] = $c;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.5);
    } while ($running > 0);

    $notJoined = [];
    foreach ($handles as $i => $c) {
        $response = curl_multi_getcontent($c);
        $data     = $response ? json_decode($response, true) : null;
        $status   = $data['result']['status'] ?? 'left';
        $isMember = in_array($status, ['member', 'administrator', 'creator'], true);
        if (!$isMember) {
            $notJoined[] = $channels[$i];
        }
        curl_multi_remove_handle($mh, $c);
        curl_close($c);
    }

    curl_multi_close($mh);

    if (!$notJoined) return ['ok' => true];
    return ['ok' => false, 'channels' => $notJoined];
}

function sendSubscribeMessage(int $chatId, array $notJoined): void {
    $text = "<tg-emoji emoji-id=\"5334768291267226515\">📢</tg-emoji> <b>Botdan foydalanish uchun quyidagi kanallarga a'zo bo'ling:</b>\n\n";
    $rows = [];
    foreach ($notJoined as $ch) {
        $rows[] = [btn("{$ch['title']} ⚠️", '', $ch['link'])];
    }
    $rows[] = [btn("✅ A'zo bo'ldim, tekshirish", 'check_subscribe', '', '', 'success')];
    $text  .= "A'zo bo'lgandan so'ng <b>«A'zo bo'ldim»</b> tugmasini bosing.";
    sendMessage($chatId, $text, inlineKb($rows));
}