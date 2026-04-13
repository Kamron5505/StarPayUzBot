<?php
/**
 * MySQL (PDO) baza — JSON fayl bazasi o'rniga
 * ─────────────────────────────────────────────
 * .env ga qo'shing:
 *   DB_HOST=localhost
 *   DB_PORT=3306
 *   DB_NAME=tgbot
 *   DB_USER=root
 *   DB_PASS=secret
 */

// ═══════════════════════════════════════════════════════════════════
// PDO ULANISH
// ═══════════════════════════════════════════════════════════════════

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'c1720_lastof');
    $user = env('DB_USER', 'c1720_lastof');
    $pass = env('DB_PASS', 'IAN0360708');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/** Qisqa SELECT yordamchi funksiya */
function dbRow(string $sql, array $params = []): ?array {
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: null;
}

function dbRows(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function dbRun(string $sql, array $params = []): int {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

// ═══════════════════════════════════════════════════════════════════
// SOZLAMALAR (settings jadvali)
// ═══════════════════════════════════════════════════════════════════

function getSettings(): array {
    $rows = dbRows("SELECT `key`, `value` FROM settings");
    $out  = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    return $out;
}

function getSetting(string $key, $default = ''): string {
    $row = dbRow("SELECT `value` FROM settings WHERE `key` = ?", [$key]);
    return $row ? (string)$row['value'] : (string)$default;
}

function setSetting(string $key, $value): void {
    $v = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
    db()->prepare(
        "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    )->execute([$key, $v]);
}

function saveSettings(array $s): void {
    foreach ($s as $k => $v) setSetting($k, $v);
}

function getStarsPrice(): int         { return (int)getSetting('stars_price_som', 280); }
function setStarsPrice(int $p): void  { setSetting('stars_price_som', $p); }
function calculatePrice(int $stars): int { return $stars * getStarsPrice(); }

// ═══════════════════════════════════════════════════════════════════
// FOYDALANUVCHI HOLATLARI (states jadvali)
// ═══════════════════════════════════════════════════════════════════

function getState(int $telegramId): array {
    $row = dbRow("SELECT state, data FROM states WHERE telegram_id = ?", [$telegramId]);
    if (!$row) return ['state' => '', 'data' => []];
    return [
        'state' => $row['state'],
        'data'  => $row['data'] ? (json_decode($row['data'], true) ?? []) : [],
    ];
}

function setState(int $telegramId, string $state, array $data = []): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    db()->prepare(
        "INSERT INTO states (telegram_id, state, data) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data)"
    )->execute([$telegramId, $state, $json]);
}

function clearState(int $telegramId): void { setState($telegramId, '', []); }

// ═══════════════════════════════════════════════════════════════════
// FOYDALANUVCHILAR (users jadvali)
// ═══════════════════════════════════════════════════════════════════

function getUser(int $telegramId): ?array {
    return dbRow("SELECT * FROM users WHERE telegram_id = ?", [$telegramId]);
}

function getUserByBotId(int $botId): ?array {
    return dbRow("SELECT * FROM users WHERE id = ?", [$botId]);
}

function getUserByUsername(string $username): ?array {
    $uname = strtolower(ltrim($username, '@'));
    return dbRow("SELECT * FROM users WHERE LOWER(username) = ?", [$uname]);
}

function getAllUsers(): array {
    return dbRows("SELECT * FROM users ORDER BY id ASC");
}

function saveUser(array $user): void {
    db()->prepare("
        UPDATE users SET
            username     = ?,
            full_name    = ?,
            balance      = ?,
            total_spent  = ?,
            orders_count = ?,
            is_banned    = ?,
            last_seen    = ?
        WHERE telegram_id = ?
    ")->execute([
        $user['username'],
        $user['full_name'],
        (int)$user['balance'],
        (int)$user['total_spent'],
        (int)$user['orders_count'],
        (int)$user['is_banned'],
        $user['last_seen'] ?? date('Y-m-d H:i:s'),
        (int)$user['telegram_id'],
    ]);
}

function createUser(int $telegramId, string $username, string $fullName): array {
    $now = date('Y-m-d H:i:s');
    db()->prepare("
        INSERT INTO users (telegram_id, username, full_name, balance, total_spent, orders_count, is_banned, created_at, last_seen)
        VALUES (?, ?, ?, 0, 0, 0, 0, ?, ?)
    ")->execute([$telegramId, $username, $fullName, $now, $now]);

    return getUser($telegramId);
}

function getOrCreateUser(int $telegramId, string $username, string $fullName): array {
    $user = getUser($telegramId);
    if ($user) {
        db()->prepare("
            UPDATE users SET
                username  = COALESCE(NULLIF(?, ''), username),
                full_name = COALESCE(NULLIF(?, ''), full_name),
                last_seen = NOW()
            WHERE telegram_id = ?
        ")->execute([$username, $fullName, $telegramId]);
        return getUser($telegramId);
    }
    return createUser($telegramId, $username, $fullName);
}

function addBalance(int $telegramId, int $amount): array {
    $n = dbRun(
        "UPDATE users SET balance = balance + ? WHERE telegram_id = ?",
        [$amount, $telegramId]
    );
    if (!$n) throw new RuntimeException('Foydalanuvchi topilmadi');
    return getUser($telegramId);
}

function deductBalance(int $telegramId, int $amount): array {
    $user = getUser($telegramId);
    if (!$user) throw new RuntimeException('Foydalanuvchi topilmadi');
    if ((int)$user['balance'] < $amount) throw new RuntimeException('Balans yetarli emas');

    dbRun(
        "UPDATE users SET balance = balance - ?, total_spent = total_spent + ? WHERE telegram_id = ?",
        [$amount, $amount, $telegramId]
    );
    return getUser($telegramId);
}

// ═══════════════════════════════════════════════════════════════════
// STARS BUYURTMALARI (orders jadvali)
// ═══════════════════════════════════════════════════════════════════

function getOrder(int $orderId): ?array {
    return dbRow("SELECT * FROM orders WHERE id = ?", [$orderId]);
}

function createOrder(int $buyerTelegramId, int $buyerBotId,
                     string $targetUsername, int $starsAmount, int $price): array {
    $st = db()->prepare("
        INSERT INTO orders (buyer_bot_id, buyer_telegram_id, target_username, stars_amount, price, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $st->execute([$buyerBotId, $buyerTelegramId, $targetUsername, $starsAmount, $price]);
    $id = (int)db()->lastInsertId();

    dbRun(
        "UPDATE users SET orders_count = orders_count + 1 WHERE telegram_id = ?",
        [$buyerTelegramId]
    );
    return getOrder($id);
}

function updateOrderStatus(int $orderId, string $status,
                           ?string $apiOrderId = null, string $note = ''): array {
    $sets   = ["status = ?", "updated_at = NOW()"];
    $params = [$status];
    if ($apiOrderId !== null) { $sets[] = "api_order_id = ?"; $params[] = $apiOrderId; }
    if ($note !== '')         { $sets[] = "note = ?";         $params[] = $note; }
    $params[] = $orderId;

    dbRun("UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    $o = getOrder($orderId);
    if (!$o) throw new RuntimeException('Buyurtma topilmadi');
    return $o;
}

function getAllOrders(): array {
    return dbRows("SELECT * FROM orders ORDER BY id DESC");
}

function getUserOrders(int $telegramId): array {
    return dbRows("SELECT * FROM orders WHERE buyer_telegram_id = ? ORDER BY id DESC", [$telegramId]);
}

// ═══════════════════════════════════════════════════════════════════
// STATISTIKA
// ═══════════════════════════════════════════════════════════════════

function getStats(): array {
    $u = dbRow("
        SELECT
            COUNT(*) AS total_users,
            SUM(is_banned = 0) AS active_users,
            SUM(is_banned = 1) AS banned_users
        FROM users
    ");
    $o = dbRow("
        SELECT
            COUNT(*) AS total_orders,
            SUM(status = 'completed') AS completed_orders,
            SUM(status = 'pending')   AS pending_orders,
            SUM(CASE WHEN status = 'completed' THEN price ELSE 0 END) AS total_revenue,
            SUM(CASE WHEN status = 'completed' THEN stars_amount ELSE 0 END) AS total_stars_sold
        FROM orders
    ");
    return array_merge($u ?? [], $o ?? []);
}

// ═══════════════════════════════════════════════════════════════════
// BALANS TO'LDIRISH (topup_orders jadvali)
// ═══════════════════════════════════════════════════════════════════

function getTopupOrder(string $orderCode): ?array {
    return dbRow("SELECT * FROM topup_orders WHERE order_code = ?", [$orderCode]);
}

function createTopupOrder(int $telegramId, int $botId,
                          string $orderCode, int $amount,
                          int $expireAt = 0): array {
    if (!$expireAt) $expireAt = time() + 300;
    $now = date('Y-m-d H:i:s');
    db()->prepare("
        INSERT INTO topup_orders (order_code, telegram_id, bot_id, amount, status, expire_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)
    ")->execute([$orderCode, $telegramId, $botId, $amount, $expireAt, $now, $now]);

    return getTopupOrder($orderCode);
}

function updateTopupOrder(string $orderCode, string $status): ?array {
    // 'paid' -> 'completed' ga o'tkazamiz (eski kod va API mosligini ta'minlash uchun)
    $statusMap = ['paid' => 'completed', 'cancel' => 'cancelled', 'success' => 'completed'];
    $status = $statusMap[$status] ?? $status;
    // Noto'g'ri status kelsa 'completed' emas, warning log qilamiz
    $allowed = ['pending', 'completed', 'expired', 'cancelled'];
    if (!in_array($status, $allowed)) {
        error_log("[updateTopupOrder] Noto'g'ri status: '$status' — 'cancelled' ga o'tkazildi");
        $status = 'cancelled';
    }
    dbRun(
        "UPDATE topup_orders SET status = ?, updated_at = NOW() WHERE order_code = ?",
        [$status, $orderCode]
    );
    return getTopupOrder($orderCode);
}

function getAllTopupOrders(): array {
    return dbRows("SELECT * FROM topup_orders ORDER BY created_at DESC");
}

// ═══════════════════════════════════════════════════════════════════
// MAJBURIY OBUNA KANALLAR (channels jadvali)
// ═══════════════════════════════════════════════════════════════════

function getChannels(): array {
    return dbRows("SELECT * FROM channels ORDER BY id ASC");
}

function saveChannels(array $channels): void {
    // To'liq almashtirish kerak bo'lganda — ishlatmang, quyidagi addChannel/removeChannel yetarli
    db()->exec("DELETE FROM channels");
    $st = db()->prepare("INSERT INTO channels (channel_id, title, link, added_at) VALUES (?, ?, ?, ?)");
    foreach ($channels as $ch) {
        $st->execute([$ch['id'], $ch['title'], $ch['link'], $ch['added_at'] ?? date('Y-m-d H:i:s')]);
    }
}

function addChannel(string $channelId, string $title, string $link): bool {
    try {
        db()->prepare(
            "INSERT INTO channels (channel_id, title, link) VALUES (?, ?, ?)"
        )->execute([$channelId, $title, $link]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) return false; // Duplicate
        throw $e;
    }
}

function removeChannel(string $channelId): bool {
    return (bool)dbRun("DELETE FROM channels WHERE channel_id = ?", [$channelId]);
}

function getOrdersChannelLink(): string { return getSetting('orders_channel_link', ''); }
function setOrdersChannelLink(string $link): void { setSetting('orders_channel_link', $link); }
function getOrdersChannelId(): string  { return getSetting('orders_channel_id', ''); }
function setOrdersChannelId(string $id): void  { setSetting('orders_channel_id', $id); }

// ═══════════════════════════════════════════════════════════════════
// GIFT MA'LUMOTLARI (o'zgarmaslar — DB kerak emas)
// ═══════════════════════════════════════════════════════════════════

function getGiftMeta(): array {
    return [
        'bear'    => ['emoji' => '🧸', 'name' => 'Ayiqcha',  'default_som' => 5000,  'emoji_id' => '5348275319168080470'],
        'heart'   => ['emoji' => '💝', 'name' => 'Yurak',    'default_som' => 5000,  'emoji_id' => '5348565014712195320'],
        'gift'    => ['emoji' => '🎁', 'name' => "Sovg'a",   'default_som' => 5000,  'emoji_id' => '5348068314629315530'],
        'rose'    => ['emoji' => '🌹', 'name' => 'Atirgul',  'default_som' => 5000,  'emoji_id' => '5348268919666809267'],
        'cake'    => ['emoji' => '🎂', 'name' => 'Tort',     'default_som' => 7500,  'emoji_id' => '5346220392065309034'],
        'flower'  => ['emoji' => '💐', 'name' => 'Gul',      'default_som' => 5000,  'emoji_id' => '5346209104891255920'],
        'rocket'  => ['emoji' => '🚀', 'name' => 'Raketa',   'default_som' => 10000, 'emoji_id' => '5345913151579787927'],
        'final'   => ['emoji' => '🏆', 'name' => 'Final',    'default_som' => 15000, 'emoji_id' => '5346192101115729092'],
        'ring'    => ['emoji' => '💍', 'name' => 'Uzuk',     'default_som' => 20000, 'emoji_id' => '5346085770610380022'],
        'diamond' => ['emoji' => '💎', 'name' => 'Olmos',    'default_som' => 30000, 'emoji_id' => '5346085770610380022'],
        'cola'    => ['emoji' => '🍾', 'name' => 'Shampan',  'default_som' => 5000,  'emoji_id' => '5348237669484761439'],
        // ─── Unikal Giftlar ───────────────────────────────────────────────────────
        'unikal1' => ['emoji' => '', 'name' => 'Unikal 1', 'emoji_id' => '5379850840691476775', 'default_som' => 100000, 'unikal' => true],
        'unikal2' => ['emoji' => '', 'name' => 'Unikal 2', 'emoji_id' => '5226661632259691727', 'default_som' => 100000, 'unikal' => true],
        'unikal3' => ['emoji' => '', 'name' => 'Unikal 3', 'emoji_id' => '5289761157173775507', 'default_som' => 100000, 'unikal' => true],
        'unikal4' => ['emoji' => '', 'name' => 'Unikal 4', 'emoji_id' => '5317000922096769303', 'default_som' => 100000, 'unikal' => true],
        'unikal5' => ['emoji' => '', 'name' => 'Unikal 5', 'emoji_id' => '5359736160224586485', 'default_som' => 100000, 'unikal' => true],
        'unikal6' => ['emoji' => '', 'name' => 'Unikal 6', 'emoji_id' => '5393309541620291208', 'default_som' => 100000, 'unikal' => true],
    ];
}

function getGiftIds(): array {
    return [
        'bear'    => 5170233102089322756,
        'heart'   => 5170145012310081615,
        'gift'    => 5170250947678437525,
        'rose'    => 5168103777563050263,
        'cake'    => 5170144170496491616,
        'flower'  => 5170314324215857265,
        'rocket'  => 5170564780938756245,
        'final'   => 5168043875654172773,
        'ring'    => 5170690322832818290,
        'diamond' => 5170521118301225164,
        'cola'    => 6028601630662853006,
        // ─── Unikal Giftlar ───────────────────────────────────────────────
        'unikal1' => 5956217000635139069,
        'unikal2' => 5800655655995968830,
        'unikal3' => 5866352046986232958,
        'unikal4' => 5893356958802511476,
        'unikal5' => 5935895822435615975,
        'unikal6' => 5969796561943660080,
    ];
}

function getGiftTgId(string $giftName): ?int {
    $ids = getGiftIds();
    $key = strtolower(trim($giftName));
    return isset($ids[$key]) ? (int)$ids[$key] : null;
}

// ─── Gift narxlari ────────────────────────────────────────────────

function getGiftPrices(): array {
    $saved = getSetting('gift_prices_som', '');
    $saved = $saved ? (json_decode($saved, true) ?? []) : [];
    $meta  = getGiftMeta();
    $prices = [];
    foreach ($meta as $key => $info) {
        $prices[$key] = isset($saved[$key]) ? (int)$saved[$key] : $info['default_som'];
    }
    return $prices;
}

function getGiftPrice(string $giftName): int {
    $prices = getGiftPrices();
    return $prices[$giftName] ?? 5000;
}

function setGiftPrice(string $giftName, int $som): void {
    $prices = getGiftPrices();
    $prices[$giftName] = $som;
    setSetting('gift_prices_som', $prices);
}

function giftPriceInSom(string $giftName): int { return getGiftPrice($giftName); }

// ═══════════════════════════════════════════════════════════════════
// GIFT BUYURTMALARI (gift_orders jadvali)
// ═══════════════════════════════════════════════════════════════════

function getGiftOrder(int $orderId): ?array {
    return dbRow("SELECT * FROM gift_orders WHERE id = ?", [$orderId]);
}

function createGiftOrder(int $buyerTelegramId, int $buyerBotId, string $targetUsername,
                          string $giftName, int $starsAmount, int $somPrice): array {
    $st = db()->prepare("
        INSERT INTO gift_orders
            (buyer_telegram_id, buyer_bot_id, target_username, gift_name, stars_amount, som_price, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $st->execute([$buyerTelegramId, $buyerBotId, $targetUsername, $giftName, $starsAmount, $somPrice]);
    $id = (int)db()->lastInsertId();

    _giftStatIncrement('total_sent');
    return getGiftOrder($id);
}

function updateGiftOrder(int $orderId, string $status,
                          string $errorCode = '', string $errorMsg = ''): array {
    $sets   = ["status = ?", "updated_at = NOW()"];
    $params = [$status];
    if ($errorCode) { $sets[] = "error_code = ?";    $params[] = $errorCode; }
    if ($errorMsg)  { $sets[] = "error_message = ?"; $params[] = $errorMsg;  }
    $params[] = $orderId;

    dbRun("UPDATE gift_orders SET " . implode(', ', $sets) . " WHERE id = ?", $params);

    $order = getGiftOrder($orderId);
    if (!$order) throw new RuntimeException("Gift buyurtma topilmadi: #{$orderId}");

    if ($status === 'completed') {
        _giftStatIncrement('total_completed');
        _giftStatIncrementGift($order['gift_name']);
    } elseif ($status === 'failed') {
        _giftStatIncrement('total_failed');
    }
    return $order;
}

function getAllGiftOrders(): array {
    return dbRows("SELECT * FROM gift_orders ORDER BY id DESC");
}

function getUserGiftHistory(int $telegramId, int $limit = 10): array {
    return dbRows(
        "SELECT * FROM gift_orders WHERE buyer_telegram_id = ? ORDER BY id DESC LIMIT ?",
        [$telegramId, $limit]
    );
}

// ═══════════════════════════════════════════════════════════════════
// GIFT STATISTIKA (gift_stats jadvali)
// ═══════════════════════════════════════════════════════════════════

function getGiftStats(): array {
    $rows = dbRows("SELECT stat_key, stat_date, value FROM gift_stats");

    $stats = [
        'total_sent'      => 0,
        'total_completed' => 0,
        'total_failed'    => 0,
        'by_gift'         => [],
        'daily'           => [],
        'weekly'          => [],
    ];

    foreach ($rows as $r) {
        $key = $r['stat_key'];
        $val = (int)$r['value'];
        $date = $r['stat_date'];

        if (in_array($key, ['total_sent', 'total_completed', 'total_failed'])) {
            $stats[$key] = $val;
        } elseif (str_starts_with($key, 'gift:')) {
            $giftName = substr($key, 5);
            $stats['by_gift'][$giftName] = $val;
        } elseif ($key === 'daily' && $date) {
            $stats['daily'][$date] = $val;
        } elseif (str_starts_with($key, 'weekly:')) {
            $week = substr($key, 7); // '2026-W13'
            $stats['weekly'][$week] = $val;
        }
    }
    return $stats;
}

function _giftStatIncrement(string $key): void {
    $today = date('Y-m-d');
    $week  = date('Y-\WW');

    db()->prepare("
        INSERT INTO gift_stats (stat_key, stat_date, value) VALUES (?, NULL, 1)
        ON DUPLICATE KEY UPDATE value = value + 1
    ")->execute([$key]);

    db()->prepare("
        INSERT INTO gift_stats (stat_key, stat_date, value) VALUES ('daily', ?, 1)
        ON DUPLICATE KEY UPDATE value = value + 1
    ")->execute([$today]);

    db()->prepare("
        INSERT INTO gift_stats (stat_key, stat_date, value) VALUES (?, NULL, 1)
        ON DUPLICATE KEY UPDATE value = value + 1
    ")->execute(["weekly:$week"]);
}

function _giftStatIncrementGift(string $giftName): void {
    db()->prepare("
        INSERT INTO gift_stats (stat_key, stat_date, value) VALUES (?, NULL, 1)
        ON DUPLICATE KEY UPDATE value = value + 1
    ")->execute(["gift:{$giftName}"]);
}

function getTopGifts(int $n = 5): array {
    $rows = dbRows(
        "SELECT stat_key, value FROM gift_stats
         WHERE stat_key LIKE 'gift:%' AND stat_date IS NULL
         ORDER BY value DESC LIMIT ?",
        [$n]
    );
    $meta   = getGiftMeta();
    $result = [];
    foreach ($rows as $r) {
        $name = substr($r['stat_key'], 5);
        $result[] = [
            'name'  => $name,
            'emoji' => $meta[$name]['emoji'] ?? '🎁',
            'label' => $meta[$name]['name']  ?? $name,
            'count' => (int)$r['value'],
        ];
    }
    return $result;
}

function giftDailyReport(): string {
    $stats    = getGiftStats();
    $today    = date('Y-m-d');
    $yest     = date('Y-m-d', strtotime('-1 day'));
    $todayCnt = $stats['daily'][$today] ?? 0;
    $yesterdayCnt = $stats['daily'][$yest] ?? 0;
    $top      = getTopGifts(3);
    $topStr   = '';
    foreach ($top as $i => $t) $topStr .= ($i + 1) . ". {$t['emoji']} {$t['label']} — {$t['count']} ta\n";
    return "📊 <b>Gift hisobot</b>\n\n"
         . "📅 Bugun: <b>{$todayCnt} ta</b>\n"
         . "📅 Kecha: <b>{$yesterdayCnt} ta</b>\n"
         . "✅ Jami bajarilgan: <b>{$stats['total_completed']}</b>\n"
         . "❌ Muvaffaqiyatsiz: <b>{$stats['total_failed']}</b>\n\n"
         . "🏆 <b>Top giftlar:</b>\n"
         . ($topStr ?: "Hali gift yuborilmagan\n");
}

function giftWeeklyReport(): string {
    $stats = getGiftStats();
    $lines = '';
    $total = 0;
    for ($i = 6; $i >= 0; $i--) {
        $d     = date('Y-m-d', strtotime("-{$i} day"));
        $label = date('d-M', strtotime($d));
        $cnt   = $stats['daily'][$d] ?? 0;
        $total += $cnt;
        $bar   = str_repeat('▓', min($cnt, 20)) ?: '—';
        $lines .= "{$label}: {$bar} ({$cnt})\n";
    }
    $top    = getTopGifts(5);
    $topStr = '';
    foreach ($top as $i => $t) $topStr .= ($i + 1) . ". {$t['emoji']} {$t['label']} — {$t['count']} ta\n";
    return "📈 <b>Haftalik Gift Hisobot</b>\n\n"
         . "<code>{$lines}</code>\n"
         . "📦 Jami 7 kunda: <b>{$total} ta</b>\n\n"
         . "🏆 <b>Eng ko'p yuborilgan:</b>\n"
         . ($topStr ?: "Hali gift yuborilmagan\n");
}

// ═══════════════════════════════════════════════════════════════════
// PREMIUM TIZIMI (premium_orders jadvali)
// ═══════════════════════════════════════════════════════════════════

function getPremiumPackages(): array {
    return [
        3  => (int)getSetting('premium_price_3',  179000),
        6  => (int)getSetting('premium_price_6',  329000),
        12 => (int)getSetting('premium_price_12', 599000),
    ];
}

function getPremiumPrice(int $months): int {
    return getPremiumPackages()[$months] ?? 69000;
}

function setPremiumPrice(int $months, int $price): void {
    setSetting("premium_price_{$months}", $price);
}

function premiumMonthsLabel(int $months): string {
    $labels = [1 => '1 oy', 3 => '3 oy', 6 => '6 oy', 12 => '1 yil'];
    return $labels[$months] ?? "{$months} oy";
}

function createPremiumOrder(int $buyerTelegramId, int $buyerBotId,
                             string $targetUsername, int $months, int $price): array {
    $orderId = 'PRE' . time() . rand(100, 999);
    $now     = date('Y-m-d H:i:s');

    db()->prepare("
        INSERT INTO premium_orders (id, buyer_telegram_id, buyer_bot_id, target_username, months, price, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
    ")->execute([$orderId, $buyerTelegramId, $buyerBotId, $targetUsername, $months, $price, $now]);

    // Balansdan ayirish
    dbRun(
        "UPDATE users SET balance = balance - ?, total_spent = total_spent + ? WHERE telegram_id = ?",
        [$price, $price, $buyerTelegramId]
    );

    return getPremiumOrder($orderId);
}

function getPremiumOrder(string $orderId): ?array {
    return dbRow("SELECT * FROM premium_orders WHERE id = ?", [$orderId]);
}

function updatePremiumOrderStatus(string $orderId, string $status, ?array $apiResponse = null): void {
    $sets   = ["status = ?"];
    $params = [$status];
    if ($status === 'completed') { $sets[] = "completed_at = NOW()"; }
    if ($apiResponse !== null)  {
        $sets[]   = "api_response = ?";
        $params[] = json_encode($apiResponse, JSON_UNESCAPED_UNICODE);
    }
    $params[] = $orderId;
    dbRun("UPDATE premium_orders SET " . implode(', ', $sets) . " WHERE id = ?", $params);
}

function getUserPremiumOrders(int $telegramId, int $limit = 10): array {
    return dbRows(
        "SELECT * FROM premium_orders WHERE buyer_telegram_id = ? ORDER BY created_at DESC LIMIT ?",
        [$telegramId, $limit]
    );
}

function getAllPremiumOrders(): array {
    return dbRows("SELECT * FROM premium_orders ORDER BY created_at DESC");
}