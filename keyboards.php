<?php
/**
 * Inline klaviaturalar
 * Bot API 9.4 — style (rangli tugmalar) + icon_custom_emoji_id (premium emoji)
 *
 * style:  "primary" (ko'k) | "success" (yashil) | "danger" (qizil)
 * emoji:  icon_custom_emoji_id — premium custom emoji ID
 */

// ─── Premium Emoji ID lar ─────────────────────────────────────────────────────
define('E_WAVE',    '5472427507842032538');  // 👋
define('E_STAR',    '5807791714093502248');  // ⭐️
define('E_MONEY',   '5258204546391351475');  // 💰
define('E_USER',    '6035084557378654059');  // 👤
define('E_INFO',    '5974193375799152241');  // ℹ️
define('E_ID',      '5811989245761426317');  // 🆔
define('E_WALLET',  '5472363448404809929');  // 👛
define('E_OK',      '5774022692642492953');  // ✅
define('E_CARD',    '5927169041595634481');  // 💳
define('E_CLOCK',   '5778420863707649338');  // ⏰
define('E_WARN',    '5316554554735607106');  // ⚠️
define('E_EDIT',    '5766915217552315762');  // ✏️
define('E_ADMIN',   '5258011929993026890');  // 👤 (Admin)
define('E_BACK',    '6039539366177541657');  // ⬅️
define('E_CHAT',    '6030776052345737530');  // 💬 Buyurtmalar kanali
define('E_PREMIUM', '5951802070607597636');  // 💎 Premium

// ─── Gift Emoji ID lari (premium) — Bot API 9.4 ────────────────────────────────
define('E_RING',       '5348178819842872295');  // 💍  10
define('E_DIAMOND',    '5346085770610380022');  // 💎  11
define('E_CHAMPAGNE',  '5348237669484761439');  // 🍾  12
define('E_BOUQUET',    '5346209104891255920');  // 💐  13
define('E_ROSE',       '5348268919666809267');  // 🌹  14
define('E_TROPHY',     '5346192101115729092');  // 🏆  15
define('E_ROCKET',     '5345913151579787927');  // 🚀  16
define('E_BEAR',       '5348275319168080470');  // 🧸  17
define('E_HEART_GIFT', '5348565014712195320');  // 💝  18
define('E_GIFT',       '5348068314629315530');  // 🎁  19
define('E_CAKE',       '5346220392065309034');  // 🎂  20

// ─── Xabarlarda ishlatish uchun premium emoji ─────────────────────────────────
function e(string $emojiId, string $fallback): string {
    return '<tg-emoji emoji-id="' . $emojiId . '">' . $fallback . '</tg-emoji>';
}

// Tayyor emoji o'zgaruvchilar (xabarlarda)
function eW(): string { return e(E_WAVE,   '👋'); }
function eST(): string { return e(E_STAR,   '⭐️'); }
function eMN(): string { return e(E_MONEY,  '💰'); }
function eUS(): string { return e(E_USER,   '👤'); }
function eIN(): string { return e(E_INFO,   'ℹ️'); }
function eID(): string { return e(E_ID,     '🆔'); }
function eWL(): string { return e(E_WALLET, '👛'); }
function eOK(): string { return e(E_OK,     '✅'); }
function eCD(): string { return e(E_CARD,   '💳'); }
function eCL(): string { return e(E_CLOCK,  '⏰'); }
function eWN(): string { return e(E_WARN,   '⚠️'); }

// ─── Tugma yaratuvchi ─────────────────────────────────────────────────────────
function btn(string $text, string $cb = '', string $url = '',
             string $emoji = '', string $style = ''): array {
    $b = ['text' => $text];
    if ($cb    !== '') $b['callback_data']       = $cb;
    if ($url   !== '') $b['url']                 = $url;
    if ($emoji !== '') $b['icon_custom_emoji_id'] = $emoji;  // Bot API 9.4
    if ($style !== '') $b['style']               = $style;  // Bot API 9.4
    return $b;
}

function inlineKb(array $rows): array {
    return ['inline_keyboard' => $rows];
}

// ═══════════════════════════════════════════════════════════════════════════════
// USER KLAVIATURALAR
// ═══════════════════════════════════════════════════════════════════════════════

function mainMenuKb(): array {
    return inlineKb([
        // 1-qator: Stars olish (keng, yashil)
        [btn('Stars olish', 'buy_stars', '', E_STAR, 'success')],
        // 2-qator: Premium olish + Gift olish (rangsiz)
        [btn('Premium olish', 'buy_premium', '', E_PREMIUM),
         btn('Gift olish',    'send_gift',   '', E_GIFT)],
        // 3-qator: Balans to'ldirish + Profil (rangsiz)
        [btn("Balans to'ldirish", 'topup',   '', E_MONEY),
         btn('Profil',            'cabinet', '', E_USER)],
        // 4-qator: Yordam (rangsiz)
        [btn('Yordam', 'help', '', E_INFO)],
    ]);
}

function starsTargetKb(): array {
    return inlineKb([
        [btn("O'zim uchun", 'stars_for_self', '', E_USER, 'primary')],
        [btn('Orqaga',      'back_main',      '', E_BACK)],
    ]);
}

function starsPackagesKb(string $targetUsername, int $pricePerStar): array {
    $rows = [];
    foreach (STARS_PACKAGES as $amount) {
        $total = $amount * $pricePerStar;
        $rows[] = [btn(
            $amount . ' Stars — ' . fmtMoney($total) . " so'm",
            'pkg_' . $amount . '_' . $targetUsername,
            '', E_STAR
        )];
    }
    $rows[] = [btn('Boshqa qiymat kiritish', 'pkg_custom_' . $targetUsername, '', E_EDIT)];
    $rows[] = [btn('Orqaga', 'buy_stars', '', E_BACK)];
    return inlineKb($rows);
}

function confirmOrderKb(int $stars, string $targetUsername): array {
    return inlineKb([[
        btn('Tasdiqlash',   'confirm_' . $stars . '_' . $targetUsername, '', E_OK,  'success'),
        btn('Bekor qilish', 'buy_stars',                                  '', '',    'danger'),
    ]]);
}

function cabinetKb(): array {
    $link = getOrdersChannelLink();
    $rows = [
        [btn('Buyurtmalarim', 'my_orders', '', E_STAR)],
    ];
    if ($link) {
        $rows[] = [btn('Buyurtmalar kanali', '', $link, E_CHAT)];
    }
    $rows[] = [btn('Orqaga', 'back_main', '', E_BACK)];
    return inlineKb($rows);
}

function ordersBackKb(): array {
    return inlineKb([[btn('Orqaga', 'cabinet', '', E_BACK)]]);
}

function successOrderKb(): array {
    $link = getOrdersChannelLink();
    $rows = [];
    if ($link) {
        $rows[] = [btn('Buyurtmalar kanali', '', $link, E_CHAT)];
    }
    $rows[] = [btn('Bosh menyu', 'back_main', '', '', 'primary')];
    return inlineKb($rows);
}

function helpKb(): array {
    $adminUsername = defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin';
    return inlineKb([
        [btn('Admin bilan bog\'lanish', '', 'https://t.me/' . $adminUsername, E_ADMIN)],
        [btn('Orqaga', 'back_main', '', E_BACK)],
    ]);
}

function topupAmountKb(): array {
    return inlineKb([
        [btn('Orqaga', 'back_main', '', E_BACK)],
    ]);
}

function backMainKb(): array {
    return inlineKb([[btn('Bosh menyu', 'back_main', '', '', 'primary')]]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN KLAVIATURALAR
// ═══════════════════════════════════════════════════════════════════════════════

function adminMainKb(): array {
    return inlineKb([
        [btn('Foydalanuvchilar',   'adm_users',        '', E_USER,    'primary'),
         btn('Statistika',         'adm_stats',        '', E_ID)],
        [btn('Stars buyurtmalar',  'adm_orders',       '', E_STAR),
         btn('Balans boshqaruv',   'adm_balance',      '', E_MONEY,   'success')],
        [btn('Gift boshqaruv',     'adm_gifts',        '', E_GIFT,    'primary'),
         btn('Premium boshqaruv',  'adm_premium',      '', E_PREMIUM, 'primary')],
        [btn("To'lov buyurtmalar", 'adm_topup_orders', '', E_CARD),
         btn("Do'kon ma'lumoti",   'adm_shop_info',    '', E_WALLET)],
        [btn('Sozlamalar',         'adm_settings'),
         btn('Xabar yuborish',     'adm_broadcast')],
    ]);
}

function adminBackKb(): array {
    return inlineKb([[btn('Orqaga', 'adm_main', '', E_BACK)]]);
}

function adminSettingsKb(int $price, string $apiKey): array {
    $keyStatus  = $apiKey ? 'Kiritilgan ✅' : 'Kiritilmagan ❌';
    $chLink     = getOrdersChannelLink();
    $chStatus   = $chLink ? 'Kiritilgan' : 'Kiritilmagan';
    return inlineKb([
        [btn("1 Stars narxi: {$price} so'm — O'zgartirish",
             'adm_set_price', '', E_MONEY, 'primary')],
        [btn("Fragment API kalit: {$keyStatus} — O'zgartirish", 'adm_set_seed')],
        [btn("API kalitni ko'rish", 'adm_view_seed')],
        [btn('Majburiy kanallar', 'adm_channels', '', '', 'primary')],
        [btn("Buyurtmalar kanali: {$chStatus} — O'zgartirish", 'adm_set_orders_channel', '', E_CHAT)],
        [btn('Orqaga', 'adm_main', '', E_BACK)],
    ]);
}

function adminSettingsBackKb(): array {
    return inlineKb([[btn('Orqaga', 'adm_settings', '', E_BACK)]]);
}

function adminUsersKb(): array {
    return inlineKb([
        [btn("Bot ID bo'yicha qidirish", 'adm_find_user', '', E_ID),
         btn('Barchasi',                 'adm_all_users', '', E_USER)],
        [btn('Orqaga', 'adm_main', '', E_BACK)],
    ]);
}

function adminUserActionsKb(int $telegramId, bool $isBanned): array {
    $banText  = $isBanned ? 'Banldan chiqarish' : 'Banlaish';
    $banCb    = $isBanned ? 'adm_unban_' . $telegramId : 'adm_ban_' . $telegramId;
    $banStyle = $isBanned ? 'success' : 'danger';
    return inlineKb([
        [btn("Balans qo'shish", 'adm_add_bal_' . $telegramId, '', E_MONEY, 'success'),
         btn('Balans ayirish',   'adm_sub_bal_' . $telegramId, '', E_MONEY, 'danger')],
        [btn($banText, $banCb, '', '', $banStyle)],
        [btn('Orqaga', 'adm_users', '', E_BACK)],
    ]);
}

function adminOrdersKb(): array {
    return inlineKb([
        [btn('Kutayotganlar', 'adm_orders_pending', '', '', 'primary'),
         btn('Bajarilganlar', 'adm_orders_done',    '', '', 'success')],
        [btn('Barchasi',  'adm_orders_all'),
         btn('Orqaga', 'adm_main', '', E_BACK)],
    ]);
}

// ─── MAJBURIY OBUNA ADMIN KLAVIATURASI ────────────────────────────────────────

function adminChannelsKb(array $channels): array {
    $rows = [];
    foreach ($channels as $ch) {
        $rows[] = [
            btn($ch['title'], '', $ch['link']),
            btn("O'chirish", 'adm_del_channel_' . base64_encode($ch['id']), '', E_WARN, 'danger'),
        ];
    }
    $rows[] = [btn("Kanal qo'shish", 'adm_add_channel', '', '', 'success')];
    $rows[] = [btn('Orqaga', 'adm_settings', '', E_BACK)];
    return inlineKb($rows);
}

// ═══════════════════════════════════════════════════════════════════════════════
// PREMIUM KLAVIATURALAR
// ═══════════════════════════════════════════════════════════════════════════════

// Premium paketlar ro'yxati
function premiumPackagesKb(string $targetUsername): array {
    $packages = getPremiumPackages();
    $rows = [];
    foreach ($packages as $months => $price) {
        $label = premiumMonthsLabel($months);
        $rows[] = [btn(
            "{$label} — " . fmtMoney($price) . " so'm",
            "premium_pkg_{$months}_{$targetUsername}",
            '', E_PREMIUM
        )];
    }
    $rows[] = [btn('Orqaga', 'back_main', '', E_BACK)];
    return inlineKb($rows);
}

// Premium tasdiqlash
function premiumConfirmKb(int $months, string $targetUsername): array {
    return inlineKb([
        [btn('Tasdiqlash', "premium_confirm_{$months}_{$targetUsername}", '', E_OK, 'success'),
         btn('Bekor',      'buy_premium',                                 '', '',   'danger')],
    ]);
}

// Premium muvaffaqiyat
function premiumSuccessKb(): array {
    return inlineKb([
        [btn('Yana Premium olish', 'buy_premium', '', E_PREMIUM, 'primary')],
        [btn('Profil',             'cabinet',     '', E_USER)],
        [btn('Bosh menyu',         'back_main',   '', '', 'primary')],
    ]);
}

// Premium xato
function premiumErrorKb(): array {
    return inlineKb([
        [btn('Qayta urinish', 'buy_premium', '', E_WARN, 'danger')],
        [btn('Bosh menyu',    'back_main',   '', E_BACK)],
    ]);
}

// Premium username tanlash
function premiumTargetKb(): array {
    return inlineKb([
        [btn("👤 O'zim uchun", 'premium_self', '', E_USER, 'primary')],
        [btn('Orqaga',          'back_main',   '', E_BACK)],
    ]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// GIFT KLAVIATURALAR
// ═══════════════════════════════════════════════════════════════════════════════

// Gift ro'yxati
function giftListKb(string $targetUsername): array {
    $meta   = getGiftMeta();
    $prices = getGiftPrices();
    $rows   = [];
    $row    = [];
    foreach ($meta as $key => $info) {
        // Unikal giftlarni o'tkazib yuboramiz — ular pastda alohida tugma orqali chiqadi
        if (!empty($info['unikal'])) continue;

        $somPrice  = $prices[$key] ?? $info['default_som'];
        $emojiId   = $info['emoji_id'] ?? '';
        if ($key === 'ring') {
            $row[] = btn(
                '💍 — ' . fmtMoney($somPrice) . " so'm",
                "gift_select_{$key}_{$targetUsername}",
                '',
                ''
            );
        } else {
            $row[] = btn(
                '— ' . fmtMoney($somPrice) . " so'm",
                "gift_select_{$key}_{$targetUsername}",
                '',
                $emojiId
            );
        }
        if (count($row) === 2) { $rows[] = $row; $row = []; }
    }
    if ($row) $rows[] = $row;

    // ─── 😎 Unikal Giftlar tugmasi (eng pastda, alohida qator) ──────────────
    // Faqat premium emoji_id ishlatiladi — oddiy emoji yo'q (ikkilanishning oldi olinadi)
    $rows[] = [btn(
        '— Unikal Giftlar',
        "gift_unikal_{$targetUsername}",
        '',
        '5472096095280569232'   // premium emoji id
    )];

    $rows[] = [btn('Orqaga', 'back_main', '', E_BACK)];
    return inlineKb($rows);
}

// Unikal giftlar ro'yxati
function giftUnikalListKb(string $targetUsername): array {
    $meta   = getGiftMeta();
    $prices = getGiftPrices();
    $rows   = [];

    // Emoji_id → gift kalit xaritasi (har biri o'z emoji_id bilan)
    $unikalPairs = [
        'unikal1' => '5379850840691476775',
        'unikal2' => '5226661632259691727',
        'unikal3' => '5289761157173775507',
        'unikal4' => '5317000922096769303',
        'unikal5' => '5359736160224586485',
        'unikal6' => '5393309541620291208',
    ];

    foreach ($unikalPairs as $key => $emojiId) {
        $info     = $meta[$key] ?? null;
        if (!$info) continue;
        $somPrice = $prices[$key] ?? $info['default_som'];
        $rows[]   = [btn(
            '— ' . fmtMoney($somPrice) . " so'm",
            "gift_select_{$key}_{$targetUsername}",
            '',
            $emojiId
        )];
    }

    $rows[] = [btn('Orqaga', "gift_menu_{$targetUsername}", '', E_BACK)];
    return inlineKb($rows);
}

// Tasdiqlash
function giftConfirmKb(string $giftName, string $targetUsername): array {
    return inlineKb([
        [btn('Ha, yuborish',  "gift_confirm_{$giftName}_{$targetUsername}", '', '', 'success')],
        [btn('Boshqa gift',   "gift_menu_{$targetUsername}",                '', E_GIFT)],
        [btn('Orqaga',        'back_main',                                  '', E_BACK)],
    ]);
}

// Loading
function giftSendingKb(): array {
    return inlineKb([[btn('Yuborilmoqda...', 'noop', '', E_CLOCK)]]);
}

// Muvaffaqiyat
function giftSuccessKb(): array {
    return inlineKb([
        [btn('Yana gift yuborish', 'send_gift',    '', E_GIFT,       'primary')],
        [btn('Gift tarixim',       'gift_history', '', E_TROPHY)],
        [btn('Bosh menyu',         'back_main',    '', E_BACK)],
    ]);
}

// Xato
function giftErrorKb(): array {
    return inlineKb([
        [btn('Qayta urinish', 'send_gift',  '', E_WARN,  'danger')],
        [btn('Bosh menyu',    'back_main',  '', E_BACK)],
    ]);
}

// Username kiriting
function giftUsernameKb(): array {
    return inlineKb([
        [btn("O'zim uchun", 'gift_self', '', E_USER, 'primary')],
        [btn('Orqaga',      'back_main', '', E_BACK)],
    ]);
}

// Gift tarixi
function giftHistoryKb(): array {
    return inlineKb([
        [btn('Yangi gift', 'send_gift',  '', E_ROCKET)],
        [btn('Bosh menyu', 'back_main',  '', E_BACK)],
    ]);
}

// Admin: gift narxlari
function adminGiftPricesKb(): array {
    $meta   = getGiftMeta();
    $prices = getGiftPrices();
    $rows   = [];
    foreach ($meta as $key => $info) {
        $som     = $prices[$key] ?? $info['default_som'];
        $emojiId = $info['emoji_id'] ?? '';
        // Unikal giftlar uchun label: faqat premium emoji + narx (oddiy emoji yo'q)
        $label   = !empty($info['unikal'])
            ? '— ' . fmtMoney($som) . " so'm"
            : $info['emoji'] . ' — ' . fmtMoney($som) . " so'm";
        $rows[]  = [btn(
            $label,
            "adm_gift_price_{$key}",
            '',
            $emojiId
        )];
    }
    $rows[] = [btn('Gift statistika',  'adm_gift_stats',   '', E_TROPHY)];
    $rows[] = [btn('Haftalik hisobot', 'adm_gift_weekly',  '', E_CAKE)];
    $rows[] = [btn('Orqaga',           'adm_main',         '', E_BACK)];
    return inlineKb($rows);
}

// Admin: gift statistika
function adminGiftStatsKb(): array {
    return inlineKb([
        [btn('Kunlik hisobot',       'adm_gift_daily',   '', E_RING)],
        [btn('Haftalik hisobot',     'adm_gift_weekly',  '', E_TROPHY)],
        [btn('Top giftlar',          'adm_gift_top',     '', E_DIAMOND)],
        [btn("So'nggi buyurtmalar",  'adm_gift_orders',  '', E_GIFT)],
        [btn('Orqaga', 'adm_gifts', '', E_BACK)],
    ]);
}
// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN PREMIUM KLAVIATURALAR
// ═══════════════════════════════════════════════════════════════════════════════

function adminPremiumKb(): array {
    $packages = getPremiumPackages();
    $rows = [];
    foreach ($packages as $months => $price) {
        $label = premiumMonthsLabel($months);
        $rows[] = [btn(
            "{$label} narxi: " . fmtMoney($price) . " so'm — O'zgartirish",
            "adm_premium_price_{$months}",
            '', E_PREMIUM
        )];
    }
    $rows[] = [btn("Premium buyurtmalar", 'adm_premium_orders', '', E_STAR)];
    $rows[] = [btn('Orqaga', 'adm_main', '', E_BACK)];
    return inlineKb($rows);
}

function adminPremiumBackKb(): array {
    return inlineKb([[btn('Orqaga', 'adm_premium', '', E_BACK)]]);
}
