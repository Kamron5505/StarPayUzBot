<?php
/**
 * ElderPay API — https://elderpay.bigsaver.ru/api
 */

define('ELDERPAY_BASE', 'https://elder.uz/api');

function elderPayRequest(string $method, array $params): array {
    $url = ELDERPAY_BASE . '?method=' . $method;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(array_merge([
            'shop_id'  => RASMIYPAY_SHOP_ID,
            'shop_key' => RASMIYPAY_SHOP_KEY,
        ], $params)),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errmsg   = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        error_log('[ELDERPAY CURL] ' . $errmsg);
        return ['ok' => false, 'message' => 'API ga ulanib bo\'lmadi'];
    }
    if ($httpCode !== 200) {
        error_log('[ELDERPAY HTTP] method=' . $method . ' http=' . $httpCode . ' response=' . $response);
        return ['ok' => false, 'message' => 'API xato qaytardi: HTTP ' . $httpCode];
    }
    $data = json_decode($response, true);
    if (!$data) {
        return ['ok' => false, 'message' => 'API javob noto\'g\'ri'];
    }
    return $data;
}

// ── To'lov yaratish ──────────────────────────────────────────────────────────
function createPayment(int $amount): array {
    $data = elderPayRequest('create', ['amount' => $amount]);

    if (($data['status'] ?? '') === 'error') {
        return ['ok' => false, 'message' => $data['message'] ?? 'Noma\'lum xato'];
    }

    if (empty($data['order'])) {
        error_log('[ELDERPAY CREATE] order kodi kelmadi. Raw: ' . json_encode($data));
        return ['ok' => false, 'message' => 'To\'lov yaratilmadi: order kodi kelmadi'];
    }

    return [
        'ok'        => true,
        'order'     => $data['order'],
        'insert_id' => $data['insert_id'] ?? null,
        'amount'    => $amount,
    ];
}

// ── To'lovni tekshirish ──────────────────────────────────────────────────────
// Namunaviy docs'ga ko'ra: shop_id + shop_key + order → POST orqali yuboriladi
function checkPayment(string $orderCode): array {
    $data = elderPayRequest('check', ['order' => $orderCode]);

    error_log('[ELDERPAY CHECK] order=' . $orderCode . ' raw=' . json_encode($data));

    if (!$data || ($data['status'] ?? '') !== 'success') {
        return ['ok' => false, 'message' => $data['message'] ?? 'Buyurtma topilmadi'];
    }

    $orderData = $data['data'] ?? [];
    $rawStatus = strtolower(trim($orderData['status'] ?? ''));

    // Namunaviy koddagi holatlarga mos normalizatsiya
    if (in_array($rawStatus, ['paid', 'success', 'completed', 'approved'])) {
        $normalStatus = 'completed';
    } elseif (in_array($rawStatus, ['cancel', 'cancelled', 'canceled', 'rejected', 'failed'])) {
        $normalStatus = 'cancel';
    } else {
        $normalStatus = 'pending';
    }

    return [
        'ok'         => true,
        'status'     => $normalStatus,
        'raw_status' => $rawStatus,
        'amount'     => (int)($orderData['amount'] ?? 0),
        'user_id'    => (int)($orderData['user_id'] ?? 0),
        'order'      => $orderCode,
    ];
}

// ── To'lovni bekor qilish ────────────────────────────────────────────────────
function cancelPayment(string $orderCode): array {
    $data = elderPayRequest('cancel', ['order' => $orderCode]);
    return [
        'ok'      => ($data['status'] ?? '') === 'success',
        'message' => $data['message'] ?? '',
    ];
}

// ── Do'kon ma'lumotlari ──────────────────────────────────────────────────────
function getShopInfo(): array {
    $data = elderPayRequest('shop', []);
    if (($data['status'] ?? '') === 'success') {
        return array_merge(['ok' => true], $data['data'] ?? []);
    }
    return ['ok' => false, 'message' => $data['message'] ?? ''];
}