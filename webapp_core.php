<?php
declare(strict_types=1);

// ─── Yordamchi funksiyalar ────────────────────────────────────────────────────

function wa_h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function wa_money(int $amount): string {
    return number_format($amount, 0, '.', ' ');
}

function wa_order_code(string $prefix): string {
    return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function wa_tone_class(string $tone): string {
    return in_array($tone, ['gold','blue','green','red','purple','cyan'], true) ? $tone : 'gold';
}

// ─── Foydalanuvchi funksiyalari (JSON asosida) ────────────────────────────────

function wa_get_user_by_telegram_id(int $telegramId): ?array {
    return getUser($telegramId);
}

function wa_create_user_if_not_exists(int $telegramId, string $username = '', string $fullName = ''): array {
    return getOrCreateUser($telegramId, $username, $fullName);
}

function wa_get_session_user(): ?array {
    return $_SESSION['wa_user'] ?? null;
}

function wa_set_session_user(array $user): void {
    $_SESSION['wa_user'] = $user;
}

function wa_get_telegram_user_from_request(): ?array {
    foreach (['tg_user_json'] as $key) {
        if (!empty($_POST[$key])) {
            $data = json_decode((string)$_POST[$key], true);
            if (is_array($data) && !empty($data['id'])) return $data;
        }
        if (!empty($_GET[$key])) {
            $data = json_decode((string)$_GET[$key], true);
            if (is_array($data) && !empty($data['id'])) return $data;
        }
    }
    return null;
}

function wa_current_user(): ?array {
    $reqUser = wa_get_telegram_user_from_request();

    if ($reqUser && !empty($reqUser['id'])) {
        $telegramId = (int)$reqUser['id'];
        $username   = (string)($reqUser['username'] ?? '');
        $fullName   = trim((string)($reqUser['first_name'] ?? '') . ' ' . (string)($reqUser['last_name'] ?? ''));
        $user = wa_create_user_if_not_exists($telegramId, $username, $fullName);
        wa_set_session_user($user);
        return $user;
    }

    $session = wa_get_session_user();
    if ($session && !empty($session['telegram_id'])) {
        $fresh = wa_get_user_by_telegram_id((int)$session['telegram_id']);
        if ($fresh) {
            wa_set_session_user($fresh);
            return $fresh;
        }
    }

    return null;
}

// ─── Oxirgi buyurtmalar (JSON asosida) ───────────────────────────────────────

function wa_gift_emoji(string $giftName): string {
    $map = [
        'bear' => '🧸', 'ayiqcha' => '🧸',
        'heart' => '💝', 'yurak' => '💝',
        'gift' => '🎁',
        'rose' => '🌹', 'atirgul' => '🌹',
        'rocket' => '🚀', 'raketa' => '🚀',
        'cola' => '🍾', 'shampan' => '🍾',
        'ring' => '💍', 'uzuk' => '💍',
        'diamond' => '💎', 'olmos' => '💎',
        'flower' => '💐', 'gul' => '💐',
        'final' => '🏆', 'kubok' => '🏆',
        'cake' => '🎂', 'tort' => '🎂',
    ];
    return $map[strtolower($giftName)] ?? '🎁';
}

function wa_recent_orders(int $telegramId, int $limit = 20): array {
    $merged = [];

    foreach (getUserOrders($telegramId) as $o) {
        $merged[] = [
            'row_key'         => 'order_' . $o['id'],
            'source_type'     => 'stars_premium',
            'id'              => $o['id'],
            'order_code'      => $o['id'],
            'order_kind'      => ((int)$o['stars_amount'] > 0) ? 'stars' : 'premium',
            'target_username' => $o['target_username'],
            'stars_amount'    => $o['stars_amount'],
            'premium_months'  => null,
            'premium_label'   => null,
            'amount'          => $o['price'],
            'status'          => $o['status'],
            'created_at'      => $o['created_at'],
            'gift_name'       => null,
            'gift_emoji'      => null,
        ];
    }

    foreach (getUserGiftHistory($telegramId, 100) as $o) {
        $merged[] = [
            'row_key'         => 'gift_' . $o['id'],
            'source_type'     => 'gift',
            'id'              => $o['id'],
            'order_code'      => null,
            'order_kind'      => 'gift',
            'target_username' => $o['target_username'],
            'stars_amount'    => $o['stars_amount'] ?? 0,
            'premium_months'  => null,
            'premium_label'   => null,
            'amount'          => $o['som_price'],
            'status'          => $o['status'],
            'created_at'      => $o['created_at'],
            'gift_name'       => $o['gift_name'],
            'gift_emoji'      => wa_gift_emoji($o['gift_name']),
        ];
    }

    usort($merged, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
    return array_slice($merged, 0, $limit);
}

// ─── Stars sotib olish ────────────────────────────────────────────────────────

function wa_process_stars_purchase(array $user, string $username, int $starsAmount, int $price): array {
    $tgId          = (int)$user['telegram_id'];
    $botId         = (int)($user['id'] ?? 0);
    $cleanUsername = ltrim($username, '@');

    try { deductBalance($tgId, $price); } catch (RuntimeException $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }

    $order = createOrder($tgId, $botId, $cleanUsername, $starsAmount, $price);
    $api   = buyStars('@' . $cleanUsername, $starsAmount, (string)$order['id']);

    if (!empty($api['ok'])) {
        updateOrderStatus($order['id'], 'completed');
        return ['ok' => true, 'message' => '✅ Stars muvaffaqiyatli yuborildi', 'order_id' => $order['id']];
    }

    updateOrderStatus($order['id'], 'failed', null, $api['message'] ?? '');
    try { addBalance($tgId, $price); } catch (RuntimeException $e) {}
    return ['ok' => false, 'message' => (string)($api['message'] ?? '❌ Stars yuborilmadi')];
}

// ─── Premium sotib olish ──────────────────────────────────────────────────────

function wa_process_premium_purchase(array $user, string $username, int $months, string $label, int $price): array {
    $tgId          = (int)$user['telegram_id'];
    $botId         = (int)($user['id'] ?? 0);
    $cleanUsername = ltrim($username, '@');

    try { deductBalance($tgId, $price); } catch (RuntimeException $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }

    $order = createOrder($tgId, $botId, $cleanUsername, 0, $price);
    $api   = buyPremium($cleanUsername, $months, (string)$order['id']);

    if (!empty($api['ok'])) {
        updateOrderStatus($order['id'], 'completed');
        return ['ok' => true, 'message' => '✅ Premium muvaffaqiyatli yuborildi', 'order_id' => $order['id']];
    }

    updateOrderStatus($order['id'], 'failed', null, $api['message'] ?? '');
    try { addBalance($tgId, $price); } catch (RuntimeException $e) {}
    return ['ok' => false, 'message' => (string)($api['message'] ?? '❌ Premium yuborilmadi')];
}

// ─── Gift sotib olish ─────────────────────────────────────────────────────────

function wa_process_gift_purchase(array $user, string $username, string $giftKey, int $starsAmount, int $price): array {
    $tgId          = (int)$user['telegram_id'];
    $botId         = (int)($user['id'] ?? 0);
    $cleanUsername = ltrim($username, '@');

    try { deductBalance($tgId, $price); } catch (RuntimeException $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }

    $order = createGiftOrder($tgId, $botId, $cleanUsername, $giftKey, $starsAmount, $price);
    $api   = sendGift($cleanUsername, $giftKey);

    if (!empty($api['ok'])) {
        updateGiftOrder($order['id'], 'completed');
        return ['ok' => true, 'message' => '✅ Gift muvaffaqiyatli yuborildi', 'gift_order_id' => $order['id']];
    }

    updateGiftOrder(
        $order['id'],
        'failed',
        (string)($api['error'] ?? 'SEND_GIFT_FAILED'),
        (string)($api['message'] ?? 'Gift yuborilmadi')
    );
    try { addBalance($tgId, $price); } catch (RuntimeException $e) {}
    return ['ok' => false, 'message' => (string)($api['message'] ?? '❌ Gift yuborilmadi')];
}

// ─── Tarjimalar ───────────────────────────────────────────────────────────────

function wa_translations(): array {
    return [
        'uz' => [
            'lang_display' => "O'zbekcha",
            'hero_title' => 'Doda Stars Bot',
            'hero_label' => 'Asosiy',
            'balance_unit' => "so'm",
            'tab_stars' => 'Stars',
            'tab_premium' => 'Premium',
            'tab_gifts' => 'Gifts',
            'stars_buy_desc' => "Qo'lda kiritish orqali miqdorni tanlang.",
            'premium_buy_desc' => 'Premium paketni tanlang.',
            'gifts_buy_desc' => 'Giftni tanlang va yuboring.',
            'send_to' => 'Kimga yuboramiz?',
            'to_myself' => "O'zimga",
            'to_myself_value' => "O'zimga",
            'username_placeholder' => 'Username nomini yozing',
            'stars_amount' => 'Stars miqdori',
            'stars_amount_hint' => "50 dan 10000 gacha kiriting yoki pastdagi tugmalardan tanlang",
            'stars_helper' => 'Minimal: 50 ta • Maksimal: 10000 ta',
            'payment_amount' => "To'lov summasi",
            'buy_stars_btn' => 'Stars sotib olish',
            'buy_premium_btn' => 'Premium sotib olish',
            'send_gift_btn' => 'Gift yuborish',
            'transactions_title' => '📋 Tranzaksiyalar',
            'transactions_sub' => 'WebApp orqali qabul qilingan buyurtmalar',
            'no_orders_title' => "Hali buyurtmalar yo'q",
            'no_orders_sub' => 'Birinchi buyurtma tushgandan keyin shu yerda chiqadi.',
            'profile_balance' => 'Balans',
            'profile_language' => 'Til',
            'profile_support' => "Qo'llab quvvatlash",
            'profile_news' => 'Yangiliklar kanali',
            'sheet_language_title' => 'Til',
            'save' => 'Saqlash',
            'nav_home' => 'Asosiy',
            'nav_market' => 'Market',
            'nav_history' => 'Tarix',
            'nav_profile' => 'Profil',
            'premium_title' => 'Telegram Premium',
            'gift_1_subtitle'  => "Premium yumshoq sovg'a",
            'gift_2_subtitle'  => 'Romantik kolleksiya',
            'gift_3_subtitle'  => 'Elegant syurpriz',
            'gift_4_subtitle'  => 'Klassik premium atirgul',
            'gift_5_subtitle'  => 'Jasur premium gift',
            'gift_6_subtitle'  => 'Bayramona nashr',
            'flash_login_required'      => 'WebApp foydalanuvchisi aniqlanmadi. Bot ichidan qayta oching.',
            'flash_enter_username'      => 'Username kiriting.',
            'flash_enter_valid_stars'   => "Stars miqdorini to'g'ri kiriting.",
            'flash_min_50'              => 'Minimum 50 ta stars kiritish kerak.',
            'flash_max_10000'           => 'Maksimum 10000 ta stars kiritish mumkin.',
            'flash_balance_low'         => 'Balans yetarli emas.',
            'flash_premium_not_selected'=> 'Premium paket tanlanmagan.',
            'flash_gift_not_selected'   => 'Gift tanlanmagan.',
            'order_stars'   => 'Stars',
            'order_gift'    => 'Gift',
            'order_premium' => 'Premium',
            'order_pending' => 'kutilmoqda',
            'js_currency_suffix' => "so'm",
            'js_error_enter_min' => 'Kamida 50 ta stars kiriting',
            'js_error_min'       => "Minimum 50 ta stars bo'lishi kerak",
            'js_error_max'       => 'Maksimum 10000 ta stars kiritish mumkin',
            'not_logged_title' => 'WebApp user topilmadi',
            'not_logged_sub'   => 'Bot ichidan WebApp ni qayta oching.',
        ],
        'ru' => [
            'lang_display' => 'Русский',
            'hero_title' => 'Doda Stars Bot',
            'hero_label' => 'Главная',
            'balance_unit' => 'сум',
            'tab_stars' => 'Stars',
            'tab_premium' => 'Premium',
            'tab_gifts' => 'Подарки',
            'stars_buy_desc' => 'Выберите количество вручную.',
            'premium_buy_desc' => 'Выберите пакет Premium.',
            'gifts_buy_desc' => 'Выберите подарок и отправьте.',
            'send_to' => 'Кому отправляем?',
            'to_myself' => 'Себе',
            'to_myself_value' => 'Себе',
            'username_placeholder' => 'Введите username',
            'stars_amount' => 'Количество Stars',
            'stars_amount_hint' => 'Введите от 50 до 10000 или выберите ниже',
            'stars_helper' => 'Минимум: 50 • Максимум: 10000',
            'payment_amount' => 'Сумма оплаты',
            'buy_stars_btn' => 'Купить Stars',
            'buy_premium_btn' => 'Купить Premium',
            'send_gift_btn' => 'Отправить подарок',
            'transactions_title' => '📋 Транзакции',
            'transactions_sub' => 'Заказы через WebApp',
            'no_orders_title' => 'Заказов пока нет',
            'no_orders_sub' => 'После первого заказа он появится здесь.',
            'profile_balance' => 'Баланс',
            'profile_language' => 'Язык',
            'profile_support' => 'Поддержка',
            'profile_news' => 'Новостной канал',
            'sheet_language_title' => 'Язык',
            'save' => 'Сохранить',
            'nav_home' => 'Главная',
            'nav_market' => 'Market',
            'nav_history' => 'История',
            'nav_profile' => 'Профиль',
            'premium_title' => 'Telegram Premium',
            'gift_1_subtitle'  => 'Премиальный мягкий подарок',
            'gift_2_subtitle'  => 'Романтическая коллекция',
            'gift_3_subtitle'  => 'Элегантный сюрприз',
            'gift_4_subtitle'  => 'Классическая премиум роза',
            'gift_5_subtitle'  => 'Смелый премиум подарок',
            'gift_6_subtitle'  => 'Праздничное издание',
            'flash_login_required'      => 'Пользователь WebApp не найден. Откройте через бота заново.',
            'flash_enter_username'      => 'Введите username.',
            'flash_enter_valid_stars'   => 'Введите корректное количество Stars.',
            'flash_min_50'              => 'Минимум 50 Stars.',
            'flash_max_10000'           => 'Максимум 10000 Stars.',
            'flash_balance_low'         => 'Недостаточно баланса.',
            'flash_premium_not_selected'=> 'Пакет Premium не выбран.',
            'flash_gift_not_selected'   => 'Подарок не выбран.',
            'order_stars'   => 'Stars',
            'order_gift'    => 'Подарок',
            'order_premium' => 'Premium',
            'order_pending' => 'в ожидании',
            'js_currency_suffix' => 'сум',
            'js_error_enter_min' => 'Введите минимум 50 Stars',
            'js_error_min'       => 'Минимум 50 Stars',
            'js_error_max'       => 'Можно ввести максимум 10000 Stars',
            'not_logged_title' => 'WebApp user не найден',
            'not_logged_sub'   => 'Откройте WebApp заново из бота.',
        ],
        'en' => [
            'lang_display' => 'English',
            'hero_title' => 'Doda Stars Bot',
            'hero_label' => 'Home',
            'balance_unit' => 'uzs',
            'tab_stars' => 'Stars',
            'tab_premium' => 'Premium',
            'tab_gifts' => 'Gifts',
            'stars_buy_desc' => 'Choose the amount manually.',
            'premium_buy_desc' => 'Choose a Premium package.',
            'gifts_buy_desc' => 'Choose a gift and send it.',
            'send_to' => 'Who are we sending to?',
            'to_myself' => 'To myself',
            'to_myself_value' => 'To myself',
            'username_placeholder' => 'Enter username',
            'stars_amount' => 'Stars amount',
            'stars_amount_hint' => 'Enter from 50 to 10000 or choose below',
            'stars_helper' => 'Minimum: 50 • Maximum: 10000',
            'payment_amount' => 'Payment amount',
            'buy_stars_btn' => 'Buy Stars',
            'buy_premium_btn' => 'Buy Premium',
            'send_gift_btn' => 'Send Gift',
            'transactions_title' => '📋 Transactions',
            'transactions_sub' => 'Orders received through WebApp',
            'no_orders_title' => 'No orders yet',
            'no_orders_sub' => 'After the first order, it will appear here.',
            'profile_balance' => 'Balance',
            'profile_language' => 'Language',
            'profile_support' => 'Support',
            'profile_news' => 'News channel',
            'sheet_language_title' => 'Language',
            'save' => 'Save',
            'nav_home' => 'Home',
            'nav_market' => 'Market',
            'nav_history' => 'History',
            'nav_profile' => 'Profile',
            'premium_title' => 'Telegram Premium',
            'gift_1_subtitle'  => 'Premium soft gift',
            'gift_2_subtitle'  => 'Romantic collection',
            'gift_3_subtitle'  => 'Elegant surprise',
            'gift_4_subtitle'  => 'Classic premium rose',
            'gift_5_subtitle'  => 'Bold premium gift',
            'gift_6_subtitle'  => 'Celebration edition',
            'flash_login_required'      => 'WebApp user not found. Re-open from bot.',
            'flash_enter_username'      => 'Enter username.',
            'flash_enter_valid_stars'   => 'Enter a valid Stars amount.',
            'flash_min_50'              => 'Minimum is 50 Stars.',
            'flash_max_10000'           => 'Maximum is 10000 Stars.',
            'flash_balance_low'         => 'Not enough balance.',
            'flash_premium_not_selected'=> 'Premium package not selected.',
            'flash_gift_not_selected'   => 'Gift not selected.',
            'order_stars'   => 'Stars',
            'order_gift'    => 'Gift',
            'order_premium' => 'Premium',
            'order_pending' => 'pending',
            'js_currency_suffix' => 'uzs',
            'js_error_enter_min' => 'Enter at least 50 Stars',
            'js_error_min'       => 'Minimum is 50 Stars',
            'js_error_max'       => 'Maximum allowed is 10000 Stars',
            'not_logged_title' => 'WebApp user not found',
            'not_logged_sub'   => 'Re-open the WebApp from the bot.',
        ],
    ];
}

function wa_lang_data(string $lang, array $translations): array {
    return $translations[$lang] ?? $translations['uz'];
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

function webapp_bootstrap(): array {
    $translations   = wa_translations();
    $supportedLangs = ['uz', 'ru', 'en'];

    if (!isset($_SESSION['webapp_lang_code']) || !in_array($_SESSION['webapp_lang_code'], $supportedLangs, true)) {
        $_SESSION['webapp_lang_code'] = 'uz';
    }

    $langCode = $_SESSION['webapp_lang_code'];
    $tr       = wa_lang_data($langCode, $translations);
    $user     = wa_current_user();

    $page = $_GET['page'] ?? 'home';
    if (!in_array($page, ['home', 'market', 'transactions', 'profile'], true)) $page = 'home';

    $shop = $_GET['shop'] ?? 'stars';
    if (!in_array($shop, ['stars', 'premium', 'gifts'], true)) $shop = 'stars';

    // Narxlar — settings.json dan (db.php getSetting)
    $starsPrice     = (int)getSetting('stars_price_som',    215);
    $premium1Price  = (int)getSetting('premium_price_1',  75000);
    $premium3Price  = (int)getSetting('premium_price_3',  200000);
    $premium12Price = (int)getSetting('premium_price_12', 700000);

    // Gift narxlari — settings.json dan (db.php getGiftPrice)
    $giftItems = [
        ['id' => 1,  'gift_key' => 'bear',    'emoji' => '🧸', 'title' => 'Ayiqcha',  'subtitle' => $tr['gift_1_subtitle'], 'price' => getGiftPrice('bear'),    'stars_amount' => 0, 'badge' => 'CLASSIC', 'tone' => 'gold'],
        ['id' => 2,  'gift_key' => 'heart',   'emoji' => '💝', 'title' => 'Yurak',    'subtitle' => $tr['gift_2_subtitle'], 'price' => getGiftPrice('heart'),   'stars_amount' => 0, 'badge' => 'LOVE',    'tone' => 'purple'],
        ['id' => 3,  'gift_key' => 'gift',    'emoji' => '🎁', 'title' => "Sovg'a",   'subtitle' => $tr['gift_3_subtitle'], 'price' => getGiftPrice('gift'),    'stars_amount' => 0, 'badge' => 'GIFT',    'tone' => 'gold'],
        ['id' => 4,  'gift_key' => 'rose',    'emoji' => '🌹', 'title' => 'Atirgul',  'subtitle' => $tr['gift_4_subtitle'], 'price' => getGiftPrice('rose'),    'stars_amount' => 0, 'badge' => 'ROMANCE', 'tone' => 'red'],
        ['id' => 5,  'gift_key' => 'rocket',  'emoji' => '🚀', 'title' => 'Raketa',   'subtitle' => $tr['gift_5_subtitle'], 'price' => getGiftPrice('rocket'),  'stars_amount' => 0, 'badge' => 'FAST',    'tone' => 'blue'],
        ['id' => 6,  'gift_key' => 'cola',    'emoji' => '🍾', 'title' => 'Shampan',  'subtitle' => $tr['gift_6_subtitle'], 'price' => getGiftPrice('cola'),    'stars_amount' => 0, 'badge' => 'PARTY',   'tone' => 'green'],
        ['id' => 7,  'gift_key' => 'ring',    'emoji' => '💍', 'title' => 'Uzuk',     'subtitle' => 'Premium uzuk',         'price' => getGiftPrice('ring'),    'stars_amount' => 0, 'badge' => 'LUXURY',  'tone' => 'gold'],
        ['id' => 8,  'gift_key' => 'diamond', 'emoji' => '💎', 'title' => 'Olmos',    'subtitle' => 'Eng premium gift',     'price' => getGiftPrice('diamond'), 'stars_amount' => 0, 'badge' => 'VIP',     'tone' => 'cyan'],
        ['id' => 9,  'gift_key' => 'flower',  'emoji' => '💐', 'title' => 'Gul',      'subtitle' => 'Chiroyli guldasta',    'price' => getGiftPrice('flower'),  'stars_amount' => 0, 'badge' => 'FLORA',   'tone' => 'green'],
        ['id' => 10, 'gift_key' => 'final',   'emoji' => '🏆', 'title' => 'Kubok',    'subtitle' => 'Champion gift',        'price' => getGiftPrice('final'),   'stars_amount' => 0, 'badge' => 'CHAMP',   'tone' => 'gold'],
        ['id' => 11, 'gift_key' => 'cake',    'emoji' => '🎂', 'title' => 'Tort',     'subtitle' => "Tug'ilgan kun uchun",  'price' => getGiftPrice('cake'),    'stars_amount' => 0, 'badge' => 'BDAY',    'tone' => 'purple'],
    ];

    $premiumItems = [
        ['id' => 'p1',  'months' => 1,  'label' => $langCode === 'ru' ? '1 мес'  : ($langCode === 'en' ? '1 month'   : '1 oy'),  'title' => $tr['premium_title'], 'price' => $premium1Price],
        ['id' => 'p3',  'months' => 3,  'label' => $langCode === 'ru' ? '3 мес'  : ($langCode === 'en' ? '3 months'  : '3 oy'),  'title' => $tr['premium_title'], 'price' => $premium3Price],
        ['id' => 'p12', 'months' => 12, 'label' => $langCode === 'ru' ? '12 мес' : ($langCode === 'en' ? '12 months' : '12 oy'), 'title' => $tr['premium_title'], 'price' => $premium12Price],
    ];

    $recentOrders = $user ? wa_recent_orders((int)$user['telegram_id'], 20) : [];

    $profile = [
        'name'     => $user['full_name'] ?? 'User',
        'username' => !empty($user['username']) ? '@' . ltrim((string)$user['username'], '@') : '@unknown',
        'balance'  => (int)($user['balance'] ?? 0),
        'lang'     => $langCode,
        'avatar'   => 'https://placehold.co/160x160/png?text=' . urlencode(substr((string)($user['full_name'] ?? 'U'), 0, 1)),
    ];

    return [
        'translations'   => $translations,
        'supportedLangs' => $supportedLangs,
        'langCode'       => $langCode,
        'tr'             => $tr,
        'user'           => $user,
        'page'           => $page,
        'shop'           => $shop,
        'starsPrice'     => $starsPrice,
        'giftItems'      => $giftItems,
        'premiumItems'   => $premiumItems,
        'recentOrders'   => $recentOrders,
        'profile'        => $profile,
        'flash'          => '',
        'flashType'      => 'success',
        'recipient'      => trim((string)($_POST['recipient'] ?? '')),
        'selfMode'       => ((string)($_POST['self_mode'] ?? '0') === '1') ? '1' : '0',
        'giftId'         => (int)($_POST['gift_id'] ?? 1),
        'premiumId'      => (string)($_POST['premium_id'] ?? 'p1'),
        'starsQty'       => trim((string)($_POST['stars_qty'] ?? '50')),
    ];
}

// ─── So'rov qayta ishlash ─────────────────────────────────────────────────────

function webapp_handle_request(array $state): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $state;

    $type = $_POST['type'] ?? '';
    $user = $state['user'];
    $tr   = $state['tr'];

    if ($type === 'set_lang') {
        $lang = (string)($_POST['lang'] ?? '');
        if (in_array($lang, $state['supportedLangs'], true)) $_SESSION['webapp_lang_code'] = $lang;
        return webapp_bootstrap();
    }

    if (!in_array($type, ['buy_stars', 'buy_premium', 'buy_gift'], true)) return $state;

    if (!$user) {
        $state['flash'] = $tr['flash_login_required'];
        $state['flashType'] = 'error';
        return $state;
    }

    if (!empty($user['is_banned'])) {
        $state['flash'] = '🚫 Siz bloklangansiz!';
        $state['flashType'] = 'error';
        return $state;
    }

    $recipient = $state['recipient'];
    $selfMode  = $state['selfMode'];
    $target    = $selfMode === '1'
        ? (!empty($user['username']) ? '@' . ltrim((string)$user['username'], '@') : $tr['to_myself_value'])
        : '@' . ltrim($recipient, '@');

    try {
        if ($type === 'buy_stars') {
            $starsQtyRaw = trim((string)($_POST['stars_qty'] ?? ''));
            $starsQtyInt = (int)preg_replace('/\D+/', '', $starsQtyRaw);

            if ($selfMode !== '1' && $recipient === '') throw new RuntimeException($tr['flash_enter_username']);
            if ($starsQtyRaw === '' || !preg_match('/^\d+$/', $starsQtyRaw)) throw new RuntimeException($tr['flash_enter_valid_stars']);
            if ($starsQtyInt < 50)    throw new RuntimeException($tr['flash_min_50']);
            if ($starsQtyInt > 10000) throw new RuntimeException($tr['flash_max_10000']);
            if ($selfMode === '1' && empty($user['username'])) throw new RuntimeException("Telegram username topilmadi. Username o'rnatib qayta urinib ko'ring.");

            $amount = $starsQtyInt * (int)$state['starsPrice'];
            $result = wa_process_stars_purchase($user, $target, $starsQtyInt, $amount);
            if (empty($result['ok'])) throw new RuntimeException((string)($result['message'] ?? '❌ Stars yuborilmadi'));
            $state['flash'] = '✅ Stars muvaffaqiyatli yuborildi';
            $state['flashType'] = 'success';
        }

        if ($type === 'buy_premium') {
            $premium = null;
            foreach ($state['premiumItems'] as $p) {
                if ($p['id'] === $state['premiumId']) { $premium = $p; break; }
            }
            if ($selfMode !== '1' && $recipient === '') throw new RuntimeException($tr['flash_enter_username']);
            if (!$premium) throw new RuntimeException($tr['flash_premium_not_selected']);
            if ($selfMode === '1' && empty($user['username'])) throw new RuntimeException("Telegram username topilmadi. Username o'rnatib qayta urinib ko'ring.");

            $result = wa_process_premium_purchase($user, $target, (int)$premium['months'], (string)$premium['label'], (int)$premium['price']);
            if (empty($result['ok'])) throw new RuntimeException((string)($result['message'] ?? '❌ Premium yuborilmadi'));
            $state['flash'] = '✅ Premium muvaffaqiyatli yuborildi';
            $state['flashType'] = 'success';
        }

        if ($type === 'buy_gift') {
            $gift = null;
            foreach ($state['giftItems'] as $g) {
                if ((int)$g['id'] === (int)$state['giftId']) { $gift = $g; break; }
            }
            if ($selfMode !== '1' && $recipient === '') throw new RuntimeException($tr['flash_enter_username']);
            if (!$gift) throw new RuntimeException($tr['flash_gift_not_selected']);
            if ($selfMode === '1' && empty($user['username'])) throw new RuntimeException("Telegram username topilmadi. Username o'rnatib qayta urinib ko'ring.");

            $result = wa_process_gift_purchase($user, $target, (string)$gift['gift_key'], (int)$gift['stars_amount'], (int)$gift['price']);
            if (empty($result['ok'])) throw new RuntimeException((string)($result['message'] ?? '❌ Gift yuborilmadi'));
            $state['flash'] = '✅ Gift muvaffaqiyatli yuborildi';
            $state['flashType'] = 'success';
        }

        $fresh = webapp_bootstrap();
        $fresh['flash']     = $state['flash'];
        $fresh['flashType'] = $state['flashType'];
        return $fresh;

    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $state['flash'] = (stripos($msg, 'Balans') !== false || stripos($msg, 'balance') !== false)
            ? $tr['flash_balance_low']
            : $msg;
        $state['flashType'] = 'error';
        return $state;
    }
}
