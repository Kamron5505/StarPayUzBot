<?php
/**
 * Fragment-API.uz Stars API — https://fragment-api.uz/api/v1/stars/buy
 *
 * OpenAPI spetsifikatsiyasiga mos (openapi.json dan tekshirilgan):
 *
 * REQUEST:  POST /api/v1/stars/buy
 *           Header: X-API-Key: <key>
 *           Body:   { "username": "durov", "amount": 60 }
 *           NOTE:   username @ belgisiz yoki @ bilan ham qabul qiladi
 *
 * RESPONSE SUCCESS (HTTP 200):
 *   { "ok": true, "message": "...", "result": { "username": "durov", "amount": 60 } }
 *
 * RESPONSE ERROR (HTTP 400/401/500):
 *   { "ok": false, "message": "...", "code": "FRAGMENT_ERROR" }
 *   NOTE: xato kalit "code" — "error" yoki "detail" EMAS
 *
 * XATO KODLARI (hujjatda ko'rsatilganlar):
 *   VALIDATION_ERROR, FRAGMENT_ERROR, CRITICAL_SERVER_ERROR
 */

function buyStars(string $username, int $amount, string $orderId): array {
    $apiKey = getFragmentApiKey();
    if (!$apiKey) {
        return [
            'ok'      => false,
            'error'   => 'NO_API_KEY',
            'message' => '❌ Fragment API kaliti kiritilmagan! Admin /admin → ⚙️ Sozlamalar orqali kiriting.',
            'raw'     => '',
            'http'    => 0,
        ];
    }

    // API @ belgisiz ham, bilan ham qabul qiladi — lekin aniq yuboramiz
    $payload = json_encode([
        'username' => ltrim($username, '@'),
        'amount'   => (int)$amount,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://fragment-api.uz/api/v1/stars/buy');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno    = curl_errno($ch);
    $errmsg   = curl_error($ch);
    curl_close($ch);

    // CURL ulanish xatosi
    if ($errno) {
        error_log('[STARS API CURL ERROR] ' . $errmsg);
        return [
            'ok'      => false,
            'error'   => 'CONNECTION_ERROR',
            'message' => '❌ API ga ulanib bo\'lmadi: ' . $errmsg,
            'raw'     => '',
            'http'    => 0,
        ];
    }

    error_log('[STARS API] HTTP=' . $httpCode . ' Response=' . $response);

    $data = json_decode($response, true);
    if ($data === null) {
        return [
            'ok'      => false,
            'error'   => 'PARSE_ERROR',
            'message' => '❌ API noto\'g\'ri javob qaytardi (JSON emas)',
            'raw'     => (string)$response,
            'http'    => $httpCode,
        ];
    }

    // ✅ OpenAPI: xato javobi strukturasi — { ok: false, message: "...", code: "KOD" }
    // Xato kodi "code" kalitida keladi (EMAS "error" yoki "detail")
    $errorMessages = [
        'VALIDATION_ERROR'      => '❌ Username yoki miqdor noto\'g\'ri (min: 50 stars)',
        'FRAGMENT_ERROR'        => '❌ Fragment saytida muammo yuzaga keldi',
        'CRITICAL_SERVER_ERROR' => '❌ Fragment-API server ichki xatosi',
        'BLOCKCHAIN_FAIL'       => '❌ TON blockchain xatosi',
    ];

    // ✅ OpenAPI: muvaffaqiyatli javob — { ok: true, message: "...", result: { username, amount } }
    $isOk = isset($data['ok']) && $data['ok'] === true;

    if ($isOk) {
        $result = $data['result'] ?? [];
        return [
            'ok'           => true,
            'order_id'     => $orderId,
            'username'     => $result['username'] ?? $username,
            // ✅ API "amount" qaytaradi ("stars_amount" emas)
            'stars_amount' => $result['amount'] ?? $amount,
            // ✅ API "cost_ton" qaytarmaydi — 0 default
            'cost_ton'     => 0,
            'raw'          => $response,
            'http'         => $httpCode,
        ];
    }

    // ✅ Xato kodini "code" kalitidan olish (OpenAPI spetsifikatsiyasiga mos)
    $errorCode = $data['code'] ?? 'UNKNOWN';
    $userMsg   = $errorMessages[$errorCode]
              ?? ('❌ Xato: ' . ($data['message'] ?? $errorCode));

    error_log('[STARS API ERROR] code=' . $errorCode . ' HTTP=' . $httpCode . ' ' . $response);

    return [
        'ok'      => false,
        'error'   => $errorCode,
        'message' => $userMsg,
        'raw'     => $response,
        'http'    => $httpCode,
    ];
}

/**
 * Fragment API kalitini DB, .env yoki config.php dan olish
 */
function getFragmentApiKey(): string {
    if (function_exists('getSetting')) {
        $key = getSetting('fragment_api_key');
        if ($key) return (string)$key;
    }
    if (!empty($_ENV['FRAGMENT_API_KEY'])) {
        return $_ENV['FRAGMENT_API_KEY'];
    }
    if (defined('FRAGMENT_API_KEY') && FRAGMENT_API_KEY) {
        return FRAGMENT_API_KEY;
    }
    return '';
}
