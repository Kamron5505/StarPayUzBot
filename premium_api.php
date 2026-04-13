<?php
/**
 * Fragment-API.uz Premium API — https://fragment-api.uz/api/v1/premium/buy
 *
 * OpenAPI spetsifikatsiyasiga mos (openapi.json dan tekshirilgan):
 *
 * REQUEST:  POST /api/v1/premium/buy
 *           Header: X-API-Key: <key>
 *           Body:   { "username": "durov", "duration": 12 }
 *           NOTE:   duration faqat 3, 6 yoki 12 (integer)
 *
 * RESPONSE SUCCESS (HTTP 200):
 *   { "ok": true, "message": "...", "result": { "username": "durov", "duration": 12 } }
 *
 * RESPONSE ERROR (HTTP 400/401/500):
 *   { "ok": false, "message": "...", "code": "FRAGMENT_ERROR" }
 *   NOTE: xato kalit "code" — "error" yoki "detail" EMAS
 *
 * XATO KODLARI (hujjatda ko'rsatilganlar):
 *   VALIDATION_ERROR, FRAGMENT_ERROR, CRITICAL_SERVER_ERROR
 */

function buyPremium(string $username, int $months, string $orderId): array {
    $apiKey = getFragmentApiKey();
    if (!$apiKey) {
        return [
            'ok'      => false,
            'error'   => 'NO_API_KEY',
            'message' => 'Fragment API kaliti kiritilmagan! Admin sozlamalaridan kiriting.',
            'raw'     => '',
            'http'    => 0,
        ];
    }

    // ✅ API faqat duration: 3, 6, 12 qabul qiladi
    $allowed = [3, 6, 12];
    if (!in_array($months, $allowed, true)) {
        return [
            'ok'      => false,
            'error'   => 'INVALID_DURATION',
            'message' => 'Noto\'g\'ri muddat. Faqat 3, 6 yoki 12 oy bo\'lishi mumkin.',
            'raw'     => '',
            'http'    => 0,
        ];
    }

    $payload = json_encode([
        'username' => ltrim($username, '@'),
        'duration' => (int)$months,   // ✅ "duration" — API kutayotgan maydon nomi
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://fragment-api.uz/api/v1/premium/buy');
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

    if ($errno) {
        error_log('[PREMIUM API CURL ERROR] ' . $errmsg);
        return [
            'ok'      => false,
            'error'   => 'CONNECTION_ERROR',
            'message' => 'API ga ulanib bo\'lmadi: ' . $errmsg,
            'raw'     => '',
            'http'    => 0,
        ];
    }

    error_log('[PREMIUM API] HTTP=' . $httpCode . ' Response=' . $response);

    $data = json_decode($response, true);
    if ($data === null) {
        return [
            'ok'      => false,
            'error'   => 'PARSE_ERROR',
            'message' => 'API noto\'g\'ri javob qaytardi (JSON emas)',
            'raw'     => (string)$response,
            'http'    => $httpCode,
        ];
    }

    // ✅ OpenAPI: xato javobi — { ok: false, message: "...", code: "KOD" }
    $errorMessages = [
        'VALIDATION_ERROR'      => 'Username yoki muddat noto\'g\'ri',
        'FRAGMENT_ERROR'        => 'Fragment saytida muammo yuzaga keldi',
        'CRITICAL_SERVER_ERROR' => 'Fragment-API server ichki xatosi',
        'BLOCKCHAIN_FAIL'       => 'TON blockchain xatosi',
    ];

    // ✅ OpenAPI: muvaffaqiyatli javob — { ok: true, message: "...", result: { username, duration } }
    $isOk = isset($data['ok']) && $data['ok'] === true;

    if ($isOk) {
        $result = $data['result'] ?? [];
        return [
            'ok'       => true,
            'order_id' => $orderId,
            'username' => $result['username'] ?? $username,
            // ✅ API "duration" qaytaradi ("months" emas)
            'months'   => $result['duration'] ?? $months,
            // ✅ API "cost_ton" qaytarmaydi — 0 default
            'cost_ton' => 0,
            'raw'      => $response,
            'http'     => $httpCode,
        ];
    }

    // ✅ Xato kodini "code" kalitidan olish (OpenAPI spetsifikatsiyasiga mos)
    $errorCode = $data['code'] ?? 'UNKNOWN';
    $userMsg   = $errorMessages[$errorCode]
              ?? ('Noma\'lum xato: ' . ($data['message'] ?? $errorCode));

    error_log('[PREMIUM API ERROR] code=' . $errorCode . ' HTTP=' . $httpCode . ' ' . $response);

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
if (!function_exists('getFragmentApiKey')) {
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
}
