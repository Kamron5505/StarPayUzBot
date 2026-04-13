<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║     ELDER STARS — Barcha Handler Funksiyalar (To'liq)        ║
 * ║  Stars + Gift tizimi birlashtirilgan                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 */

// ═══════════════════════════════════════════════════════════════════════════════
// DISPATCHER
// ═══════════════════════════════════════════════════════════════════════════════

function handleUpdate(array $update): void {
    if (isset($update['message']))         handleMessage($update['message']);
    elseif (isset($update['callback_query'])) handleCallback($update['callback_query']);
}

// ─── XABAR ────────────────────────────────────────────────────────────────────

function handleMessage(array $msg): void {
    $chatId    = (int)$msg['chat']['id'];
    $userId    = (int)$msg['from']['id'];
    $text      = trim($msg['text'] ?? '');
    $messageId = (int)$msg['message_id'];
    $username  = $msg['from']['username'] ?? '';
    $fullName  = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? ''));
    $user = getOrCreateUser($userId, $username, $fullName);
    $st   = getState($userId);
    if ($text === '/start') { clearState($userId); cmdStart($chatId, $userId, $user); return; }
    if ($text === '/admin')  { clearState($userId); cmdAdmin($chatId, $userId); return; }
    switch ($st['state']) {
        case 'waiting_username':
            $sub = checkAllChannels($userId);
            if (!$sub['ok']) { sendSubscribeMessage($chatId, $sub['channels']); return; }
            stateWaitingUsername($chatId, $userId, $text); break;
        case 'waiting_topup_amount':   stateWaitingTopupAmount($chatId, $userId, $user, $text); break;
        case 'waiting_stars_amount':   stateWaitingStarsAmount($chatId, $userId, $text, $st['data']['username'] ?? ''); break;
        // ── PREMIUM ──
        case 'waiting_premium_username': stateWaitingPremiumUsername($chatId, $userId, $user, $text); break;
        // ── GIFT ──
        case 'waiting_gift_username':  stateWaitingGiftUsername($chatId, $userId, $user, $text); break;
        case 'adm_waiting_gift_price': admStateWaitingGiftPrice($chatId, $userId, $st['data'], $text); break;
        // ── PREMIUM ADMIN ──
        case 'adm_waiting_premium_price': admStateWaitingPremiumPrice($chatId, $userId, $st['data'], $text); break;
        // ── ADMIN ──
        case 'adm_waiting_user_id':          admStateWaitingUserId($chatId, $userId, $text); break;
        case 'adm_waiting_balance_user_id':  admStateWaitingBalanceUserId($chatId, $userId, $text); break;
        case 'adm_waiting_add_amount':       admStateWaitingAddAmount($chatId, $userId, $st['data'], $text); break;
        case 'adm_waiting_sub_amount':       admStateWaitingSubAmount($chatId, $userId, $st['data'], $text); break;
        case 'adm_waiting_broadcast':        admStateWaitingBroadcast($chatId, $userId, $messageId); break;
        case 'adm_waiting_set_price':        admStateWaitingSetPrice($chatId, $userId, $text); break;
        case 'adm_waiting_set_seed':         admStateWaitingSetSeed($chatId, $userId, $messageId, $text); break;
        case 'adm_waiting_add_channel':      admStateWaitingAddChannel($chatId, $userId, $text); break;
        case 'adm_waiting_orders_channel':   admStateWaitingOrdersChannel($chatId, $userId, $text); break;
        default: sendMessage($chatId, "❓ Noma'lum buyruq. /start bosing.", mainMenuKb());
    }
}

// ─── CALLBACK ─────────────────────────────────────────────────────────────────

function handleCallback(array $cb): void {
    $chatId = (int)$cb['message']['chat']['id'];
    $msgId  = (int)$cb['message']['message_id'];
    $userId = (int)$cb['from']['id'];
    $cbId   = $cb['id'];
    $data   = $cb['data'] ?? '';
    $user   = getUser($userId);
    if (!$user) { answerCallback($cbId, 'Avval /start bosing', true); return; }

    // ── OBUNA ──
    if ($data === 'check_subscribe') {
        $sub = checkAllChannels($userId);
        if ($sub['ok']) {
            answerCallback($cbId, "✅ Rahmat! Endi botdan foydalanishingiz mumkin.", true);
            editMessage($chatId, $msgId, welcomeText($user), mainMenuKb());
        } else {
            answerCallback($cbId, "❌ Hali barcha kanallarga a'zo bo'lmadingiz!", true);
            editMessage($chatId, $msgId,
                "📢 <b>Hali quyidagi kanallarga a'zo bo'lmadingiz:</b>\n\n"
                . implode("\n", array_map(fn($ch) => "• <b>{$ch['title']}</b>", $sub['channels'])),
                subscribeRetryKb($sub['channels'])
            );
        }
        return;
    }
    if ($data === 'back_main') { clearState($userId); editMessage($chatId, $msgId, welcomeText(getUser($userId)), mainMenuKb()); answerCallback($cbId); return; }

    // ════════ PREMIUM CALLBACKLAR ════════
    if ($data === 'buy_premium')                     { cbPremiumMenu($chatId, $msgId, $cbId, $userId, $user);           return; }
    if ($data === 'premium_self')                    { cbPremiumSelf($chatId, $msgId, $cbId, $userId, $user);           return; }
    if (strpos($data, 'premium_pkg_') === 0)         { cbPremiumPackage($chatId, $msgId, $cbId, $userId, $user, $data); return; }
    if (strpos($data, 'premium_confirm_') === 0)     { cbPremiumConfirm($chatId, $msgId, $cbId, $userId, $user, $data); return; }

    // ════════ GIFT CALLBACKLAR ════════
    if ($data === 'send_gift')                { cbGiftMenu($chatId, $msgId, $cbId, $userId, $user);           return; }
    if ($data === 'gift_self')                { cbGiftSelf($chatId, $msgId, $cbId, $userId, $user);           return; }
    if ($data === 'gift_history')             { cbGiftHistory($chatId, $msgId, $cbId, $userId);               return; }
    if (strpos($data, 'gift_menu_') === 0)    { cbGiftMenuFor($chatId, $msgId, $cbId, $userId, $user, $data); return; }
    if (strpos($data, 'gift_unikal_') === 0)  { cbGiftUnikalMenu($chatId, $msgId, $cbId, $userId, $user, $data); return; }
    if (strpos($data, 'gift_select_') === 0)  { cbGiftSelect($chatId, $msgId, $cbId, $userId, $user, $data); return; }
    if (strpos($data, 'gift_confirm_') === 0) { cbGiftConfirm($chatId, $msgId, $cbId, $userId, $user, $data); return; }

    // ════════ STARS CALLBACKLAR ════════
    if ($data === 'buy_stars') {
        $sub = checkAllChannels($userId);
        if (!$sub['ok']) { answerCallback($cbId); editMessage($chatId, $msgId, "📢 <b>Botdan foydalanish uchun kanallarga a'zo bo'ling:</b>", subscribeRetryKb($sub['channels'])); return; }
        clearState($userId); setState($userId, 'waiting_username');
        editMessage($chatId, $msgId, eST() . " <b>Stars xarid qilish</b>\n\n🔎 Stars yuborilishi kerak bo'lgan foydalanuvchi username'ini kiriting:\n" . e('5231102735817918643', '👇') . " Misol: <code>@sizdaemas</code>", starsTargetKb());
        answerCallback($cbId); return;
    }
    if ($data === 'stars_for_self') {
        $sub = checkAllChannels($userId);
        if (!$sub['ok']) { answerCallback($cbId, "❌ Avval kanallarga a'zo bo'ling!", true); return; }
        if (!$user['username']) { answerCallback($cbId, "Username'ingiz yo'q. Telegram sozlamalaridan o'rnating.", true); return; }
        clearState($userId); $price = getStarsPrice();
        editMessage($chatId, $msgId, eST() . " <b>Stars xarid qilish</b>\n\n" . eUS() . " Qabul qiluvchi: @{$user['username']}\n" . eMN() . " 1 Stars = " . fmtMoney($price) . " so'm\n\n📦 Paketni tanlang:", starsPackagesKb($user['username'], $price));
        answerCallback($cbId); return;
    }
    if (strpos($data, 'pkg_custom_') === 0) {
        $targetUsername = substr($data, strlen('pkg_custom_')); setState($userId, 'waiting_stars_amount', ['username' => $targetUsername]); $price = getStarsPrice();
        editMessage($chatId, $msgId, eST() . " <b>Stars xarid qilish</b>\n\n" . eUS() . " Qabul qiluvchi: @{$targetUsername}\n" . eMN() . " 1 Stars = " . fmtMoney($price) . " so'm\n\nNecha stars xarid qilmoqchisiz?\n<i>Minimal: 50, Maksimal: 10000</i>\n\nMiqdorni kiriting:", inlineKb([[btn('Orqaga', 'buy_stars', '', E_BACK)]]));
        answerCallback($cbId); return;
    }
    if (strpos($data, 'pkg_') === 0)     { cbSelectPackage($chatId, $msgId, $cbId, $user, $data); return; }
    if (strpos($data, 'confirm_') === 0) { cbConfirmOrder($chatId, $msgId, $cbId, $userId, $user, $data); return; }
    if ($data === 'topup') {
        $sub = checkAllChannels($userId);
        if (!$sub['ok']) { answerCallback($cbId, "❌ Avval kanallarga a'zo bo'ling!", true); return; }
        setState($userId, 'waiting_topup_amount');
        editMessage($chatId, $msgId, eMN() . " <b>Balansni to'ldirish</b>\n\nQuyidagi miqdorni kiriting:\n\n🔻Minimal: <b>" . fmtMoney(MIN_TOPUP) . " so'm</b>\n🔺Maksimal: <b>" . fmtMoney(MAX_TOPUP) . " so'm</b>", topupAmountKb());
        answerCallback($cbId); return;
    }
    if (strpos($data, 'cancel_pay^') === 0) {
        $orderCode = substr($data, strlen('cancel_pay^')); cancelPayment($orderCode); updateTopupOrder($orderCode, 'cancelled');
        editMessage($chatId, $msgId, eWN() . " To'lov bekor qilindi.", backMainKb()); answerCallback($cbId); return;
    }
    if ($data === 'cabinet')   { cbCabinet($chatId, $msgId, $cbId, getUser($userId)); return; }
    if ($data === 'my_orders') { cbMyOrders($chatId, $msgId, $cbId, $userId); return; }
    if ($data === 'help') {
        $price = getStarsPrice();
        $premPkgs = getPremiumPackages();
        $premLines = '';
        foreach ($premPkgs as $m => $p) {
            $premLines .= "   • " . premiumMonthsLabel($m) . " — <b>" . fmtMoney($p) . " so'm</b>\n";
        }
        editMessage($chatId, $msgId,
            eIN() . " <b>Yordam</b>\n\n" .
            "Bu bot orqali Telegram Stars xarid qilish, Premium olish va Gift yuborish mumkin.\n\n" .
            eMN() . " <b>Stars narxi:</b> 1 Stars = <b>" . fmtMoney($price) . " so'm</b>\n\n" .
            ePR() . " <b>Premium narxlar:</b>\n{$premLines}\n" .
            "📌 <b>Stars olish:</b>\n1. Stars olish tugmasini bosing\n2. Username kiriting\n3. Paketni tanlang va tasdiqlang\n\n" .
            ePR() . " <b>Premium olish:</b>\n1. Premium olish tugmasini bosing\n2. Username kiriting\n3. Muddatni tanlang va tasdiqlang\n\n" .
            "🎁 <b>Gift yuborish:</b>\n1. Gift yuborish tugmasini bosing\n2. Username kiriting\n3. Gift tanlang va tasdiqlang\n\n" .
            "❓ Muammo bo'lsa admin bilan bog'laning.",
            helpKb()
        );
        answerCallback($cbId); return;
    }

    // ════════ ADMIN CALLBACKLAR ════════
    if (!isAdmin($userId)) { answerCallback($cbId, '❌ Ruxsat yo\'q', true); return; }

    if ($data === 'adm_main') { clearState($userId); editMessage($chatId, $msgId, '🛠 <b>Admin panel</b>', adminMainKb()); answerCallback($cbId); return; }
    if ($data === 'adm_stats')    { cbAdmStats($chatId, $msgId, $cbId); return; }
    if ($data === 'adm_settings') { clearState($userId); cbAdmSettings($chatId, $msgId, $cbId); return; }

    // ── GIFT ADMIN ──
    if ($data === 'adm_gifts')                      { cbAdmGifts($chatId, $msgId, $cbId);                        return; }
    if ($data === 'adm_gift_stats')                  { cbAdmGiftStats($chatId, $msgId, $cbId);                    return; }
    if ($data === 'adm_gift_daily')                  { cbAdmGiftDaily($chatId, $msgId, $cbId);                    return; }
    if ($data === 'adm_gift_weekly')                 { cbAdmGiftWeekly($chatId, $msgId, $cbId);                   return; }
    if ($data === 'adm_gift_top')                    { cbAdmGiftTop($chatId, $msgId, $cbId);                      return; }
    if ($data === 'adm_gift_orders')                 { cbAdmGiftOrders($chatId, $msgId, $cbId);                   return; }
    if (strpos($data, 'adm_gift_price_') === 0)      { cbAdmGiftSetPrice($chatId, $msgId, $cbId, $userId, $data); return; }

    // ── PREMIUM ADMIN ──
    if ($data === 'adm_premium')                          { cbAdmPremium($chatId, $msgId, $cbId);                              return; }
    if ($data === 'adm_premium_orders')                   { cbAdmPremiumOrders($chatId, $msgId, $cbId);                        return; }
    if (strpos($data, 'adm_premium_price_') === 0)        { cbAdmPremiumSetPrice($chatId, $msgId, $cbId, $userId, $data);      return; }

    if ($data === 'adm_set_price') { setState($userId, 'adm_waiting_set_price'); editMessage($chatId, $msgId, eMN() . " <b>1 Stars narxini o'zgartirish</b>\n\nHozirgi narx: <b>" . getStarsPrice() . " so'm</b>\n\nYangi narxni so'mda kiriting:", adminSettingsBackKb()); answerCallback($cbId); return; }
    if ($data === 'adm_set_seed')  { setState($userId, 'adm_waiting_set_seed'); editMessage($chatId, $msgId, "🔑 <b>Fragment API kalitini kiritish</b>\n\n" . eWN() . " fragment-api.uz dan olingan API kalitingizni kiriting:", adminSettingsBackKb()); answerCallback($cbId); return; }
    if ($data === 'adm_view_seed') {
        $key = getFragmentApiKey(); if (!$key) { answerCallback($cbId, '❌ Fragment API kaliti kiritilmagan', true); return; }
        $masked = substr($key, 0, 8) . '...' . substr($key, -4);
        $keyMsg = sendMessage($chatId, "🔑 <b>Fragment API kaliti:</b>\n\n<code>{$key}</code>\n\nMasked: <b>{$masked}</b>\n\n" . eWN() . " Bu xabar 30 soniyada o'chadi!");
        answerCallback($cbId); if ($keyMsg) { sleep(30); deleteMessage($chatId, (int)$keyMsg['message_id']); } return;
    }
    if ($data === 'adm_set_orders_channel') {
        setState($userId, 'adm_waiting_orders_channel'); $current = getOrdersChannelLink();
        editMessage($chatId, $msgId, "💬 <b>Buyurtmalar kanalini sozlash</b>\n\nKanal havolasini yuboring:\n<code>https://t.me/username</code>\n\n" . ($current ? eOK() . " Joriy havola: {$current}\n\n" : '') . eWN() . " O'chirish uchun <code>-</code> yuboring.", inlineKb([[btn('Orqaga', 'adm_settings', '', E_BACK)]]));
        answerCallback($cbId); return;
    }
    if ($data === 'adm_channels') { cbAdmChannels($chatId, $msgId, $cbId); return; }
    if ($data === 'adm_add_channel') {
        setState($userId, 'adm_waiting_add_channel');
        editMessage($chatId, $msgId, "📢 <b>Kanal qo'shish</b>\n\nQuyidagi formatda yuboring:\n\n<code>@username | Kanal nomi | https://t.me/username</code>\n\nYoki ID bilan:\n<code>-1001234567890 | Kanal nomi | https://t.me/username</code>\n\n" . eWN() . " Bot kanalda <b>admin</b> bo'lishi kerak!", inlineKb([[btn('Orqaga', 'adm_channels', '', E_BACK)]]));
        answerCallback($cbId); return;
    }
    if (strpos($data, 'adm_del_channel_') === 0) { $channelId = base64_decode(substr($data, strlen('adm_del_channel_'))); removeChannel($channelId) ? answerCallback($cbId, "✅ Kanal o'chirildi", true) : answerCallback($cbId, '❌ Topilmadi', true); cbAdmChannels($chatId, $msgId, $cbId); return; }
    if ($data === 'adm_users')     { clearState($userId); editMessage($chatId, $msgId, eUS() . ' <b>Foydalanuvchilar boshqaruvi</b>', adminUsersKb()); answerCallback($cbId); return; }
    if ($data === 'adm_all_users') { cbAdmAllUsers($chatId, $msgId, $cbId); return; }
    if ($data === 'adm_find_user') { setState($userId, 'adm_waiting_user_id'); editMessage($chatId, $msgId, eID() . ' Bot ID kiriting (1, 2, 3...):', adminBackKb()); answerCallback($cbId); return; }
    if ($data === 'adm_balance')   { setState($userId, 'adm_waiting_balance_user_id'); editMessage($chatId, $msgId, eMN() . " <b>Balans boshqaruv</b>\n\nBot ID kiriting:", adminBackKb()); answerCallback($cbId); return; }
    if (strpos($data, 'adm_add_bal_') === 0) { $tgId = (int)substr($data, strlen('adm_add_bal_')); setState($userId, 'adm_waiting_add_amount', ['target_tg_id' => $tgId]); editMessage($chatId, $msgId, "➕ " . eMN() . " Qancha so'm qo'shmoqchisiz?", adminBackKb()); answerCallback($cbId); return; }
    if (strpos($data, 'adm_sub_bal_') === 0) { $tgId = (int)substr($data, strlen('adm_sub_bal_')); setState($userId, 'adm_waiting_sub_amount', ['target_tg_id' => $tgId]); editMessage($chatId, $msgId, "➖ " . eMN() . " Qancha so'm ayirmoqchisiz?", adminBackKb()); answerCallback($cbId); return; }
    if (strpos($data, 'adm_ban_') === 0)   { $tgId = (int)substr($data, strlen('adm_ban_'));   $u = getUser($tgId); if ($u) { $u['is_banned'] = true;  saveUser($u); answerCallback($cbId, '🚫 Banlandi', true); showUserInfo($chatId, $msgId, $u); } return; }
    if (strpos($data, 'adm_unban_') === 0) { $tgId = (int)substr($data, strlen('adm_unban_')); $u = getUser($tgId); if ($u) { $u['is_banned'] = false; saveUser($u); answerCallback($cbId, '✅ Chiqarildi', true); showUserInfo($chatId, $msgId, $u); } return; }
    if ($data === 'adm_orders') { editMessage($chatId, $msgId, eST() . ' <b>Stars buyurtmalar</b>', adminOrdersKb()); answerCallback($cbId); return; }
    if (in_array($data, ['adm_orders_pending', 'adm_orders_done', 'adm_orders_all'])) { cbAdmOrdersList($chatId, $msgId, $cbId, $data); return; }
    if (strpos($data, 'adm_ord_done_') === 0) { updateOrderStatus((int)substr($data, strlen('adm_ord_done_')), 'completed'); answerCallback($cbId, '✅ Bajarildi', true); editMessage($chatId, $msgId, eST() . ' <b>Stars buyurtmalar</b>', adminOrdersKb()); return; }
    if (strpos($data, 'adm_ord_cancel_') === 0) { $ordId = (int)substr($data, strlen('adm_ord_cancel_')); $ord = getOrder($ordId); if ($ord && !in_array($ord['status'], ['completed','cancelled'])) { updateOrderStatus($ordId,'cancelled'); addBalance((int)$ord['buyer_telegram_id'],(int)$ord['price']); answerCallback($cbId,'✅ Bekor, pul qaytarildi',true); } else { answerCallback($cbId,"❌ Bekor qilib bo'lmaydi",true); } editMessage($chatId,$msgId,eST().' <b>Stars buyurtmalar</b>',adminOrdersKb()); return; }
    if ($data === 'adm_topup_orders') { cbAdmTopupOrders($chatId, $msgId, $cbId); return; }
    if ($data === 'adm_shop_info')    { cbAdmShopInfo($chatId, $msgId, $cbId);    return; }
    if ($data === 'adm_broadcast') { setState($userId, 'adm_waiting_broadcast'); editMessage($chatId, $msgId, "📢 <b>Broadcast xabar yuborish</b>\n\nBarcha foydalanuvchilarga yubormoqchi bo'lgan xabarni yuboring.\n\n" . eST() . " Matn, rasm, video, sticker — hammasi qo'llab-quvvatlanadi.\n" . eOK() . " Emoji va shriftlar saqlanib qoladi (forward bez «Kimdan»).", adminBackKb()); answerCallback($cbId); return; }
    answerCallback($cbId, "❓ Noma'lum amal", true);
}

// ═══════════════════════════════════════════════════════════════════════════════
// OBUNA
// ═══════════════════════════════════════════════════════════════════════════════
function subscribeRetryKb(array $notJoined): array {
    $rows = [];
    foreach ($notJoined as $ch) $rows[] = [btn("➡️ {$ch['title']}", '', $ch['link'])];
    $rows[] = [btn("✅ A'zo bo'ldim, tekshirish", 'check_subscribe', '', '', 'success')];
    return inlineKb($rows);
}

// ═══════════════════════════════════════════════════════════════════════════════
// USER FUNKSIYALAR
// ═══════════════════════════════════════════════════════════════════════════════
function welcomeText(array $user): string {
    $uname = $user['username'] ? '@'.$user['username'] : $user['full_name'];
    return eW()." Assalomu alaykum, <b>{$uname}</b>\n".eID()." User ID: <code>{$user['id']}</code>\n┗".eWL()." Balans: <b>".fmtMoney((int)$user['balance'])." so'm</b>";
}
function cmdStart(int $chatId, int $userId, array $user): void {
    if ($user['is_banned']) { sendMessage($chatId,'🚫 Siz bloklangansiz. Admin bilan bog\'laning.'); return; }
    $sub = checkAllChannels($userId); if (!$sub['ok']) { sendSubscribeMessage($chatId,$sub['channels']); return; }
    sendMessage($chatId, welcomeText($user), mainMenuKb());
}
function stateWaitingUsername(int $chatId, int $userId, string $text): void {
    $username = ltrim(trim($text),'@'); if (!$username || strlen($username) < 3) { sendMessage($chatId, eWN()." Username noto'g'ri. Kamida 3 ta belgi:"); return; }
    clearState($userId); $price = getStarsPrice();
    sendMessage($chatId, eST()." <b>Stars xarid qilish</b>\n\n".eUS()." Qabul qiluvchi: @{$username}\n".eMN()." 1 Stars = ".fmtMoney($price)." so'm\n\n📦 Paketni tanlang:", starsPackagesKb($username,$price));
}
function stateWaitingTopupAmount(int $chatId, int $userId, array $user, string $text): void {
    $amount = (int)preg_replace('/\D/','',$text);
    if ($amount < MIN_TOPUP || $amount > MAX_TOPUP) { sendMessage($chatId, eWN()." Miqdor chegaradan tashqarida!\n⬇️ Minimal: <b>".fmtMoney(MIN_TOPUP)." so'm</b>\n⬆️ Maksimal: <b>".fmtMoney(MAX_TOPUP)." so'm</b>"); return; }
    clearState($userId); $waitMsg = sendMessage($chatId, eCL()." To'lov yaratilmoqda..."); $result = createPayment($amount);
    if (!$result['ok']) { if ($waitMsg) editMessage($chatId,(int)$waitMsg['message_id'],eWN()." Xatolik:\n".$result['message'],backMainKb()); return; }
    $orderCode = $result['order']; $amountStr = fmtMoney($amount); $now = time(); $expireAt = $now+300;
    $tz = new DateTimeZone('Asia/Tashkent');
    $expireStr = (new DateTime('@'.$expireAt))->setTimezone($tz)->format('H:i:s');
    $startStr  = (new DateTime('@'.$now))->setTimezone($tz)->format('H:i:s');
    createTopupOrder($userId,(int)$user['id'],$orderCode,$amount,$expireAt);
    if ($waitMsg) editMessage($chatId,(int)$waitMsg['message_id'], eOK()." <b>To'lov so'rovi yaratildi!</b>\n\n".eID()." Buyurtma: <code>{$orderCode}</code>\n".eMN()." Miqdori: <b>{$amountStr} so'm</b>\n\n".eCD()." To'lov uchun karta:\n<code>".CARD_NUMBER."</code>\n\n".eCL()." To'lov amalga oshirilgach, bot avtomatik aniqlaydi.\n\n".eWN()." <b>Muddat:</b> <b>{$startStr} — {$expireStr}</b> (Toshkent)\nAniq <b>5 daqiqa</b>. Undan keyin <b>avtomatik bekor qilinadi!</b>");
}
function stateWaitingStarsAmount(int $chatId, int $userId, string $text, string $targetUsername): void {
    $stars = (int)preg_replace('/\D/','',$text);
    if ($stars < 50 || $stars > 10000) { sendMessage($chatId, eWN()." Miqdor noto'g'ri!\n⬇️ Minimal: <b>50 Stars</b>\n⬆️ Maksimal: <b>10 000 Stars</b>\n\nQayta kiriting:"); return; }
    if (!$targetUsername) { sendMessage($chatId, eWN()." Xatolik: username topilmadi. /start bosing."); clearState($userId); return; }
    clearState($userId); $price = calculatePrice($stars); $user = getUser($userId);
    $text2 = eST()." <b>Buyurtmani tasdiqlang</b>\n\n".eUS()." Qabul qiluvchi: @{$targetUsername}\n".eST()." Stars: <b>{$stars}</b>\n".eMN()." Narxi: <b>".fmtMoney($price)." so'm</b>\n".eWL()." Balansingiz: <b>".fmtMoney((int)$user['balance'])." so'm</b>\n";
    if ((int)$user['balance'] < $price) $text2 .= "\n".eWN()." Balans yetarli emas. <b>".fmtMoney($price-(int)$user['balance'])." so'm</b> qo'shishingiz kerak.";
    sendMessage($chatId, $text2, confirmOrderKb($stars,$targetUsername));
}
function cbSelectPackage(int $chatId, int $msgId, string $cbId, array $user, string $data): void {
    $parts = explode('_',$data,3); $stars = (int)$parts[1]; $targetUsername = $parts[2] ?? ''; $price = calculatePrice($stars); $priceStr = fmtMoney($price);
    $text = eST()." <b>Buyurtmani tasdiqlang</b>\n\n".eUS()." Qabul qiluvchi: @{$targetUsername}\n".eST()." Stars: <b>{$stars}</b>\n".eMN()." Narxi: <b>{$priceStr} so'm</b>\n".eWL()." Balansingiz: <b>".fmtMoney((int)$user['balance'])." so'm</b>\n";
    if ((int)$user['balance'] < $price) $text .= "\n".eWN()." Balans yetarli emas. <b>".fmtMoney($price-(int)$user['balance'])." so'm</b> qo'shishingiz kerak.";
    editMessage($chatId,$msgId,$text,confirmOrderKb($stars,$targetUsername)); answerCallback($cbId);
}
function cbConfirmOrder(int $chatId, int $msgId, string $cbId, int $userId, array $user, string $data): void {
    $parts = explode('_',$data,3); $stars = (int)$parts[1]; $targetUsername = $parts[2] ?? ''; $price = calculatePrice($stars);
    if ($user['is_banned']) { answerCallback($cbId,'🚫 Siz bloklangansiz!',true); return; }
    if ((int)$user['balance'] < $price) { answerCallback($cbId,"❌ Balans yetarli emas!",true); return; }
    deductBalance($userId,$price); $order = createOrder($userId,(int)$user['id'],$targetUsername,$stars,$price);
    $orderId = (int)$order['id']; $priceStr = fmtMoney($price); $uname = $user['username'] ? '@'.$user['username'] : $user['full_name'];
    answerCallback($cbId);
    editMessage($chatId,$msgId, eCL()." <b>Bajarilmoqda...</b>\n\n📦 Buyurtma: <b>#{$orderId}</b>\n".eUS()." Qabul qiluvchi: <b>@{$targetUsername}</b>\n".eST()." Stars: <b>{$stars}</b>\n".eMN()." Narxi: <b>{$priceStr} so'm</b>");
    updateOrderStatus($orderId,'processing'); $result = buyStars($targetUsername,$stars,'ORD-'.$orderId);
    if ($result['ok']) {
        updateOrderStatus($orderId,'completed',$result['order_id'] ?? '');
        $costTon = $result['cost_ton'] ?? 0; $newBal = fmtMoney((int)getUser($userId)['balance']); $apiOrderId = $result['order_id'] ?? '—'; $rawResp = $result['raw'] ?? '—'; $http = $result['http'] ?? '—';
        editMessage($chatId,$msgId, eOK()." <b>Stars muvaffaqiyatli yuborildi!</b>\n\n📦 Buyurtma: <b>#{$orderId}</b>\n".eUS()." Qabul qiluvchi: <b>@{$targetUsername}</b>\n".eST()." Stars: <b>{$stars}</b>\n".eMN()." To'langan: <b>{$priceStr} so'm</b>\n".eWL()." Qoldiq balans: <b>{$newBal} so'm</b>", successOrderKb());
        foreach (ADMIN_IDS as $adminId) sendMessage((int)$adminId, eOK()." <b>Buyurtma bajarildi!</b>\n━━━━━━━━━━━━━━━━━━━━\n📦 Buyurtma: <b>#{$orderId}</b>\n".eUS()." Foydalanuvchi: {$uname} (<b>#{$user['id']}</b>)\n🎯 Qabul qiluvchi: <b>@{$targetUsername}</b>\n".eST()." Stars: <b>{$stars}</b>\n".eMN()." So'm: <b>{$priceStr} so'm</b>\n⛓ TON sarflandi: <b>{$costTon} TON</b>\n🔗 API order ID: <code>{$apiOrderId}</code>\n🌐 HTTP: <code>{$http}</code>\n📡 API javob:\n<code>".htmlspecialchars(mb_substr($rawResp,0,500))."</code>");
        $chId = getOrdersChannelId();
        if ($chId) sendMessage((int)$chId, eOK()." <b>Yangi buyurtma bajarildi!</b>\n━━━━━━━━━━━━━━━━━━━━\n".eST()." Stars buyurtma\n📦 Buyurtma: <b>#{$orderId}</b>\n".eUS()." Yuboruvchi: {$uname}\n".eUS()." Qabul qiluvchi: <b>@{$targetUsername}</b>\n".eST()." Stars: <b>{$stars}</b>\n".eMN()." To'lov: <b>{$priceStr} so'm</b>\n📅 ".date('Y-m-d H:i:s'), ['inline_keyboard'=>[[['text'=>"🤖 Botga o'tish",'url'=>'https://t.me/elderstars_bot']]]]);
    } else {
        addBalance($userId,$price); $errorCode = $result['error'] ?? 'UNKNOWN'; $errorMsg = $result['message'] ?? "Noma'lum xato"; $newBal = fmtMoney((int)getUser($userId)['balance']); $rawResp = $result['raw'] ?? '—'; $http = $result['http'] ?? '—';
        updateOrderStatus($orderId,'failed','',$errorCode);
        editMessage($chatId,$msgId, eWN()." <b>Buyurtma bajarilmadi!</b>\n\n📦 Buyurtma: <b>#{$orderId}</b>\n".eUS()." Username: <b>@{$targetUsername}</b>\n".eST()." Stars: <b>{$stars}</b>\n\n📋 Sabab: {$errorMsg}\n\n".eOK()." <b>{$priceStr} so'm</b> balansingizga qaytarildi.\n".eWL()." Joriy balans: <b>{$newBal} so'm</b>", mainMenuKb());
        foreach (ADMIN_IDS as $adminId) sendMessage((int)$adminId, eWN()." <b>Buyurtma bajarilmadi!</b>\n━━━━━━━━━━━━━━━━━━━━\n📦 Buyurtma: <b>#{$orderId}</b>\n".eUS()." Foydalanuvchi: {$uname} (<b>#{$user['id']}</b>)\n🎯 Qabul qiluvchi: <b>@{$targetUsername}</b>\n".eST()." Stars: <b>{$stars}</b>\n".eMN()." So'm: <b>{$priceStr} so'm</b> (qaytarildi)\n🔴 Xato kodi: <code>{$errorCode}</code>\n📋 Sabab: {$errorMsg}\n🌐 HTTP: <code>{$http}</code>\n📡 API javob:\n<code>".htmlspecialchars(mb_substr($rawResp,0,500))."</code>");
    }
}
function cbCabinet(int $chatId, int $msgId, string $cbId, array $user): void {
    editMessage($chatId,$msgId, eUS()." <b>Kabinet</b>\n\n".eID()." User ID: <code>{$user['id']}</code>\n┣".eWL()." Balans: <b>".fmtMoney((int)$user['balance'])." so'm</b>\n┣📜 Buyurtmalar: <b>{$user['orders_count']} ta</b>\n┗".eMN()." Sarflangan: <b>".fmtMoney((int)$user['total_spent'])." so'm</b>", cabinetKb());
    answerCallback($cbId);
}
function cbMyOrders(int $chatId, int $msgId, string $cbId, int $userId): void {
    $orders = getUserOrders($userId); if (!$orders) { answerCallback($cbId,"Hali buyurtmalar yo'q",true); return; }
    $emap = ['pending'=>'⏳','processing'=>'🔄','completed'=>'✅','failed'=>'❌','cancelled'=>'🚫']; $text = "📋 <b>Buyurtmalaringiz:</b>\n\n";
    foreach (array_slice($orders,0,10) as $o) $text .= ($emap[$o['status']] ?? '❓')." <b>#{$o['id']}</b> — {$o['stars_amount']} stars @{$o['target_username']} (".fmtMoney((int)$o['price'])." so'm)\n";
    if (count($orders) > 10) $text .= "\n... va yana ".(count($orders)-10)." ta";
    editMessage($chatId,$msgId,$text,ordersBackKb()); answerCallback($cbId);
}

// ═══════════════════════════════════════════════════════════════════════════════
// GIFT — USER HANDLERLAR
// ═══════════════════════════════════════════════════════════════════════════════

function cbGiftMenu(int $chatId, int $msgId, string $cbId, int $userId, array $user): void {
    $sub = checkAllChannels($userId);
    if (!$sub['ok']) { answerCallback($cbId); editMessage($chatId,$msgId,"📢 <b>Botdan foydalanish uchun kanallarga a'zo bo'ling:</b>",subscribeRetryKb($sub['channels'])); return; }
    clearState($userId); setState($userId,'waiting_gift_username');
    editMessage($chatId,$msgId,"🎁 <b>Gift yuborish</b>\n\n🔎 Gift yubormoqchi bo'lgan foydalanuvchi username'ini kiriting:\n👇 Misol: <code>@doston</code>\n\n<i>O'zingizga yubormoqchi bo'lsangiz — pastdagi tugmani bosing.</i>",giftUsernameKb());
    answerCallback($cbId);
}
function cbGiftSelf(int $chatId, int $msgId, string $cbId, int $userId, array $user): void {
    if (empty($user['username'])) { answerCallback($cbId,"❌ Username'ingiz yo'q. Telegram sozlamalarida o'rnating.",true); return; }
    clearState($userId); _showGiftList($chatId,$msgId,$user['username']); answerCallback($cbId);
}
function cbGiftMenuFor(int $chatId, int $msgId, string $cbId, int $userId, array $user, string $data): void {
    $username = substr($data,strlen('gift_menu_')); if (empty($username)) { answerCallback($cbId,'❌ Xato',true); return; }
    _showGiftList($chatId,$msgId,$username); answerCallback($cbId);
}
function stateWaitingGiftUsername(int $chatId, int $userId, array $user, string $text): void {
    $username = ltrim(trim($text),'@');
    if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/',$username)) { sendMessage($chatId,"⚠️ Username noto'g'ri. Faqat harf, raqam va _ ishlatilsin.\nMisol: <code>@doston</code>",inlineKb([[btn('Orqaga','back_main','',E_BACK)]])); return; }
    clearState($userId); sendMessage($chatId,"🎁 <b>Gift tanlang</b>\n\n👤 Qabul qiluvchi: @{$username}",giftListKb($username));
}
function cbGiftSelect(int $chatId, int $msgId, string $cbId, int $userId, array $user, string $data): void {
    [$giftName,$username] = _parseGiftCbData($data,'gift_select_');
    if (!$giftName || !$username) { answerCallback($cbId,'❌ Xato',true); return; }
    $meta = getGiftMeta(); $info = $meta[$giftName] ?? null;
    if (!$info) { answerCallback($cbId,"❌ Noma'lum gift",true); return; }
    $somPrice = getGiftPrice($giftName); $balance = (int)$user['balance'];
    $text = "🎁 <b>Gift tasdiqlash</b>\n\n👤 Qabul qiluvchi: @{$username}\n{$info['emoji']} Gift: <b>{$info['name']}</b>\n💰 Narxi: <b>".fmtMoney($somPrice)." so'm</b>\n👛 Balansingiz: <b>".fmtMoney($balance)." so'm</b>\n";
    if ($balance < $somPrice) $text .= "\n⚠️ <b>Balans yetarli emas!</b>\nKerak: <b>".fmtMoney($somPrice-$balance)." so'm</b> qo'shimcha.";
    editMessage($chatId,$msgId,$text,giftConfirmKb($giftName,$username)); answerCallback($cbId);
}
function cbGiftConfirm(int $chatId, int $msgId, string $cbId, int $userId, array $user, string $data): void {
    [$giftName,$username] = _parseGiftCbData($data,'gift_confirm_');
    if (!$giftName || !$username) { answerCallback($cbId,'❌ Xato',true); return; }
    $meta = getGiftMeta(); $info = $meta[$giftName] ?? null;
    if (!$info) { answerCallback($cbId,"❌ Noma'lum gift",true); return; }
    $somPrice = getGiftPrice($giftName);
    if ($user['is_banned']) { answerCallback($cbId,'🚫 Siz bloklangansiz!',true); return; }
    if ((int)$user['balance'] < $somPrice) { answerCallback($cbId,"❌ Balans yetarli emas! Kerak: ".fmtMoney($somPrice)." so'm",true); return; }
    deductBalance($userId,$somPrice);
    $order = createGiftOrder($userId,(int)$user['id'],$username,$giftName,0,$somPrice); $orderId = (int)$order['id'];
    answerCallback($cbId);
    editMessage($chatId,$msgId,"⏳ <b>Gift yuborilmoqda...</b>\n\n👤 @{$username} ga {$info['emoji']} {$info['name']} yuborilmoqda.\nBiroz kuting...",giftSendingKb());
    updateGiftOrder($orderId,'processing');

    // MadelineProto orqali yuborish
    $result = _sendGiftViaMadeline($username,$giftName);
    $uname  = $user['username'] ? '@'.$user['username'] : $user['full_name'];
    $somStr = fmtMoney($somPrice); $newBal = fmtMoney((int)getUser($userId)['balance']);

    if ($result['ok']) {
        updateGiftOrder($orderId,'completed');
        // Unikal gift uchun premium emoji, oddiy gift uchun oddiy emoji
        $giftEmoji = !empty($info['unikal'])
            ? e($info['emoji_id'], '😎')
            : $info['emoji'];
        editMessage($chatId,$msgId,"✅ <b>Gift yuborildi!</b>\n\n🎫 Buyurtma: <b>#{$orderId}</b>\n👤 Qabul qiluvchi: @{$username}\n{$giftEmoji} Gift: <b>{$info['name']}</b>\n💰 To'langan: <b>{$somStr} so'm</b>\n👛 Qoldiq balans: <b>{$newBal} so'm</b>",giftSuccessKb());
        foreach (ADMIN_IDS as $adminId) sendMessage((int)$adminId,"✅ <b>Gift yuborildi!</b>\n━━━━━━━━━━━━━━━━━━━━\n🎫 Buyurtma: <b>#{$orderId}</b>\n👤 Yuboruvchi: {$uname} (<b>#{$user['id']}</b>)\n🎯 Qabul qiluvchi: @{$username}\n{$giftEmoji} Gift: <b>{$info['name']}</b>\n💰 To'lov: <b>{$somStr} so'm</b>\n📅 ".date('Y-m-d H:i:s'));
        $chId = getOrdersChannelId();
        if ($chId) sendMessage((int)$chId,"✅ <b>Gift buyurtma bajarildi!</b>\n━━━━━━━━━━━━━━━━━━━━\n🎫 Buyurtma: <b>#{$orderId}</b>\n👤 Yuboruvchi: {$uname}\n🎯 Qabul qiluvchi: @{$username}\n{$giftEmoji} Gift: <b>{$info['name']}</b>\n💰 To'lov: <b>{$somStr} so'm</b>\n📅 ".date('Y-m-d H:i:s'),['inline_keyboard'=>[[['text'=>"🤖 Botga o'tish",'url'=>'https://t.me/elderstars_bot']]]]);
        $receiver = getUserByUsername($username);
        if ($receiver && !$receiver['is_banned']) sendMessage((int)$receiver['telegram_id'],"🎁 <b>Sizga gift yuborildi!</b>\n\n{$giftEmoji} Gift: <b>{$info['name']}</b>\n👤 Yuboruvchi: {$uname}\n\n🎉 Telegram'da ko'rishingiz mumkin!");
    } else {
        addBalance($userId,$somPrice); updateGiftOrder($orderId,'failed',$result['error'] ?? 'UNKNOWN',$result['raw'] ?? '');
        $newBal = fmtMoney((int)getUser($userId)['balance']); $errCode = $result['error'] ?? 'UNKNOWN'; $errMsg = $result['message'] ?? "Noma'lum xato"; $raw = mb_substr($result['raw'] ?? '',0,300);
        editMessage($chatId,$msgId,"❌ <b>Gift yuborilmadi!</b>\n\n🎫 Buyurtma: <b>#{$orderId}</b>\n👤 Username: @{$username}\n{$info['emoji']} Gift: {$info['name']}\n\n📋 Sabab: {$errMsg}\n\n✅ <b>{$somStr} so'm</b> balansingizga qaytarildi.\n👛 Joriy balans: <b>{$newBal} so'm</b>",giftErrorKb());
        foreach (ADMIN_IDS as $adminId) sendMessage((int)$adminId,"⚠️ <b>Gift YUBORILMADI!</b>\n━━━━━━━━━━━━━━━━━━━━\n🎫 Buyurtma: <b>#{$orderId}</b>\n👤 Yuboruvchi: {$uname} (<b>#{$user['id']}</b>)\n🎯 Qabul qiluvchi: @{$username}\n{$info['emoji']} Gift: <b>{$info['name']}</b>\n💰 Pul qaytarildi: <b>{$somStr} so'm</b>\n\n🔴 Xato: <code>{$errCode}</code>\n📋 Sabab: {$errMsg}\n".($raw ? "📡 Raw:\n<code>".htmlspecialchars($raw)."</code>" : ''));
    }
}
function cbGiftHistory(int $chatId, int $msgId, string $cbId, int $userId): void {
    $history = getUserGiftHistory($userId,15); $meta = getGiftMeta();
    if (!$history) { answerCallback($cbId,"Hali gift yuborilmagan.",true); return; }
    $emap = ['pending'=>'⏳','processing'=>'🔄','completed'=>'✅','failed'=>'❌']; $text = "📋 <b>Gift tarixingiz:</b>\n\n";
    foreach ($history as $o) { $em = $emap[$o['status']] ?? '❓'; $ginfo = $meta[$o['gift_name']] ?? ['emoji'=>'🎁','name'=>$o['gift_name']]; $text .= "{$em} {$ginfo['emoji']} <b>{$ginfo['name']}</b> → @{$o['target_username']} (".fmtMoney((int)$o['som_price'])." so'm)\n   📅 ".substr($o['created_at'],0,16)."\n\n"; }
    editMessage($chatId,$msgId,$text,giftHistoryKb()); answerCallback($cbId);
}

// ═══════════════════════════════════════════════════════════════════════════════
// GIFT — ADMIN HANDLERLAR
// ═══════════════════════════════════════════════════════════════════════════════

function cbAdmGifts(int $chatId, int $msgId, string $cbId): void {
    $stats = getGiftStats();
    editMessage($chatId,$msgId,"🎁 <b>Gift Boshqaruv</b>\n\n📦 Jami yuborilgan: <b>{$stats['total_sent']}</b>\n✅ Bajarilgan: <b>{$stats['total_completed']}</b>\n❌ Muvaffaqiyatsiz: <b>{$stats['total_failed']}</b>\n\n💰 Har bir gift narxini alohida belgilashingiz mumkin:",adminGiftPricesKb());
    answerCallback($cbId);
}
function cbAdmGiftSetPrice(int $chatId, int $msgId, string $cbId, int $userId, string $data): void {
    $giftName = substr($data,strlen('adm_gift_price_')); $meta = getGiftMeta(); $info = $meta[$giftName] ?? null;
    if (!$info) { answerCallback($cbId,"❌ Noma'lum gift",true); return; }
    $current = getGiftPrice($giftName); setState($userId,'adm_waiting_gift_price',['gift_name'=>$giftName]);
    editMessage($chatId,$msgId,"💰 <b>{$info['emoji']} {$info['name']} narxini o'zgartirish</b>\n\nHozirgi narx: <b>".fmtMoney($current)." so'm</b>\n\nYangi narxni <b>so'mda</b> kiriting:\n<i>Misol: 5000, 10000, 25000</i>",inlineKb([[btn('Orqaga','adm_gifts','',E_BACK)]]));
    answerCallback($cbId);
}
function admStateWaitingGiftPrice(int $chatId, int $userId, array $stData, string $text): void {
    $giftName = $stData['gift_name'] ?? ''; $som = (int)preg_replace('/\D/','',$text);
    if ($som < 100 || $som > 10000000) { sendMessage($chatId,"⚠️ Narx noto'g'ri. 100 dan 10,000,000 gacha so'm kiriting.",inlineKb([[btn('Orqaga','adm_gifts','',E_BACK)]])); return; }
    $meta = getGiftMeta(); $info = $meta[$giftName] ?? ['emoji'=>'🎁','name'=>$giftName]; setGiftPrice($giftName,$som); clearState($userId);
    sendMessage($chatId,"✅ <b>{$info['emoji']} {$info['name']}</b> narxi yangilandi!\n\n💰 Yangi narx: <b>".fmtMoney($som)." so'm</b>",adminGiftPricesKb());
}
function cbAdmGiftStats(int $chatId, int $msgId, string $cbId): void { editMessage($chatId,$msgId,"📊 <b>Gift Statistika</b>\n\nQuyidagi bo'limlardan birini tanlang:",adminGiftStatsKb()); answerCallback($cbId); }
function cbAdmGiftDaily(int $chatId, int $msgId, string $cbId): void { editMessage($chatId,$msgId,giftDailyReport(),inlineKb([[btn('Haftalik hisobot','adm_gift_weekly','',E_STAR)],[btn('Orqaga','adm_gift_stats','',E_BACK)]])); answerCallback($cbId); }
function cbAdmGiftWeekly(int $chatId, int $msgId, string $cbId): void { editMessage($chatId,$msgId,giftWeeklyReport(),inlineKb([[btn('Kunlik hisobot','adm_gift_daily')],[btn('Orqaga','adm_gift_stats','',E_BACK)]])); answerCallback($cbId); }
function cbAdmGiftTop(int $chatId, int $msgId, string $cbId): void {
    $top = getTopGifts(11); $text = "🏆 <b>Eng ko'p yuborilgan Giftlar</b>\n\n";
    if (!$top) { $text .= "Hali gift yuborilmagan."; }
    else foreach ($top as $i => $t) { $medal = $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':($i+1).'.')); $text .= "{$medal} {$t['emoji']} <b>{$t['label']}</b> — {$t['count']} ta\n"; }
    editMessage($chatId,$msgId,$text,inlineKb([[btn('Orqaga','adm_gift_stats','',E_BACK)]])); answerCallback($cbId);
}
function cbAdmGiftOrders(int $chatId, int $msgId, string $cbId): void {
    $orders = array_slice(getAllGiftOrders(),0,20); $meta = getGiftMeta(); $emap = ['pending'=>'⏳','processing'=>'🔄','completed'=>'✅','failed'=>'❌'];
    if (!$orders) { answerCallback($cbId,"Hali gift buyurtmalari yo'q.",true); return; }
    $text = "📋 <b>So'nggi Gift Buyurtmalar</b>\n\n";
    foreach ($orders as $o) { $em = $emap[$o['status']] ?? '❓'; $ginfo = $meta[$o['gift_name']] ?? ['emoji'=>'🎁','name'=>$o['gift_name']]; $text .= "{$em} <b>#{$o['id']}</b> | #{$o['buyer_bot_id']} → @{$o['target_username']}\n   {$ginfo['emoji']} {$ginfo['name']} | ".fmtMoney((int)$o['som_price'])." so'm\n   📅 ".substr($o['created_at'],0,16)."\n\n"; }
    editMessage($chatId,$msgId,$text,inlineKb([[btn('Orqaga','adm_gift_stats','',E_BACK)]])); answerCallback($cbId);
}

// ─── Gift ichki yordamchilar ──────────────────────────────────────────────────
function _showGiftList(int $chatId, int $msgId, string $username): void {
    editMessage($chatId,$msgId,"🎁 <b>Gift tanlang</b>\n\n👤 Qabul qiluvchi: @{$username}\n⭐ 1 Stars = ".fmtMoney(getStarsPrice())." so'm\n\nGift va uning narxini tanlang:",giftListKb($username));
}
function _parseGiftCbData(string $data, string $prefix): array {
    $payload = substr($data,strlen($prefix)); $parts = explode('_',$payload,2); return [$parts[0] ?? '',$parts[1] ?? ''];
}
function _sendGiftViaMadeline(string $username, string $giftName): array {
    if (!class_exists(\danog\MadelineProto\API::class)) return ['ok'=>false,'error'=>'NO_MADELINE','message'=>"❌ MadelineProto o'rnatilmagan. 'composer require danog/madelineproto' bajaring.",'raw'=>''];
    $giftId = getGiftTgId($giftName);
    if (!$giftId) return ['ok'=>false,'error'=>'UNKNOWN_GIFT','message'=>"❌ «{$giftName}» gift topilmadi.",'raw'=>''];
    $target = '@'.ltrim($username,'@'); $logDir = DATA_DIR; if (!is_dir($logDir)) mkdir($logDir,0755,true);
    file_put_contents($logDir.'/gift_log.txt',"[".date('Y-m-d H:i:s')."] [INFO] Yuborilmoqda: {$giftName} → {$target}\n",FILE_APPEND|LOCK_EX);
    try {
        static $ml = null;
        if ($ml === null) {
            $settings = new \danog\MadelineProto\Settings();
            $settings->getAppInfo()->setApiId((int)TG_API_ID)->setApiHash(TG_API_HASH);
            $ml = new \danog\MadelineProto\API(__DIR__.'/session.madeline',$settings);
            $ml->start();
        }
        // 1. Invoice yaratish (inputInvoiceStarGift)
        $invoice = [
            '_'       => 'inputInvoiceStarGift',
            'peer'    => $target,
            'gift_id' => $giftId,
        ];
        // 2. To'lov formasini olish
        $form = $ml->payments->getPaymentForm(invoice: $invoice);
        // 3. To'lovni amalga oshirish (Stars bilan)
        $ml->payments->sendStarsForm(
            form_id: $form['form_id'],
            invoice: $invoice,
        );
        file_put_contents($logDir.'/gift_log.txt',"[".date('Y-m-d H:i:s')."] [INFO] Muvaffaqiyatli: {$giftName} → {$target}\n",FILE_APPEND|LOCK_EX);
        return ['ok'=>true,'message'=>'✅ Gift yuborildi!'];
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        file_put_contents($logDir.'/gift_log.txt',"[".date('Y-m-d H:i:s')."] [ERROR] {$giftName} → {$target} | {$msg}\n",FILE_APPEND|LOCK_EX);
        $map = ['USER_NOT_FOUND'=>"Foydalanuvchi topilmadi.",'GIFT_SEND_DISALLOWED'=>"Bu foydalanuvchi gift qabul qilmaydi.",'STARS_NOT_ENOUGH'=>"Akkauntda yetarli Stars yo'q.",'PEER_ID_INVALID'=>"Username yoki ID noto'g'ri.",'FLOOD_WAIT'=>"Juda ko'p urinish. Biroz kuting.",'AUTH_KEY_UNREGISTERED'=>"Session eskirgan. Qayta login kerak.",'BALANCE_TOO_LOW'=>"Stars balansi yetarli emas.",'STARGIFT_USAGE_LIMITED'=>"Bu gift yuborish limiti tugagan."];
        $friendly = null; foreach ($map as $k=>$v) { if (stripos($msg,$k) !== false) { $friendly = $v; break; } }
        return ['ok'=>false,'error'=>'MADELINE_ERROR','message'=>'❌ '.($friendly ?? mb_substr($msg,0,200)),'raw'=>$msg];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN FUNKSIYALAR
// ═══════════════════════════════════════════════════════════════════════════════
function cmdAdmin(int $chatId, int $userId): void { if (!isAdmin($userId)) { sendMessage($chatId,"❌ Admin huquqi yo'q."); return; } sendMessage($chatId,'🛠 <b>Admin panel</b>',adminMainKb()); }
function cbAdmStats(int $chatId, int $msgId, string $cbId): void {
    $s = getStats(); $price = getStarsPrice(); $seedOk = getFragmentApiKey() ? eOK() : '❌'; $chs = count(getChannels()); $gs = getGiftStats();
    editMessage($chatId,$msgId,"📊 <b>Statistika</b>\n\n".eUS()." Jami foydalanuvchilar: <b>{$s['total_users']}</b>\n".eOK()." Aktiv: <b>{$s['active_users']}</b>\n🚫 Banlangan: <b>{$s['banned_users']}</b>\n\n📦 Stars buyurtmalar: <b>{$s['total_orders']}</b>\n✅ Bajarilgan: <b>{$s['completed_orders']}</b>\n⏳ Kutayotgan: <b>{$s['pending_orders']}</b>\n\n🎁 Gift yuborilgan: <b>{$gs['total_sent']}</b>\n✅ Gift bajarilgan: <b>{$gs['total_completed']}</b>\n\n".eMN()." Jami daromad: <b>".fmtMoney($s['total_revenue'])." so'm</b>\n".eST()." Sotilgan Stars: <b>{$s['total_stars_sold']}</b>\n\n⚙️ 1 Stars = <b>{$price} so'm</b>\n🔑 Fragment API: {$seedOk}\n📢 Majburiy kanallar: <b>{$chs} ta</b>",adminBackKb());
    answerCallback($cbId);
}
function cbAdmSettings(int $chatId, int $msgId, string $cbId): void {
    $price = getStarsPrice(); $apiKey = getFragmentApiKey(); $chs = count(getChannels()); $chLink = getOrdersChannelLink();
    editMessage($chatId,$msgId,"⚙️ <b>Bot sozlamalari</b>\n\n".eMN()." 1 Stars: <b>{$price} so'm</b>\n🔑 Fragment API: ".($apiKey ? eOK().' <b>Kiritilgan</b>' : '❌ Kiritilmagan')."\n📢 Kanallar: <b>{$chs} ta</b>\n💬 Buyurtmalar kanali: ".($chLink ? "<b>✅ Kiritilgan</b>" : "❌ Kiritilmagan"),adminSettingsKb($price,$apiKey));
    answerCallback($cbId);
}
function cbAdmAllUsers(int $chatId, int $msgId, string $cbId): void {
    $users = getAllUsers(); if (!$users) { answerCallback($cbId,"Foydalanuvchilar yo'q",true); return; }
    $text = eUS()." Jami <b>".count($users)."</b> ta:\n\n";
    foreach (array_slice($users,0,20) as $u) { $mark = $u['is_banned']?'🚫':'✅'; $uname = $u['username']?'@'.$u['username']:$u['full_name']; $text .= "{$mark} [<b>#{$u['id']}</b>] {$uname} — ".fmtMoney((int)$u['balance'])." so'm\n"; }
    if (count($users)>20) $text .= "\n... va yana ".(count($users)-20)." ta";
    editMessage($chatId,$msgId,$text,adminBackKb()); answerCallback($cbId);
}
function showUserInfo(int $chatId, int $msgId, array $user): void {
    $uname = $user['username']?'@'.$user['username']:'—';
    editMessage($chatId,$msgId,eUS()." <b>Foydalanuvchi</b>\n\n".eID()." Bot ID: <code>{$user['id']}</code>\n".eUS()." Username: {$uname}\n📛 Ism: ".($user['full_name']?:'—')."\n".eWL()." Balans: <b>".fmtMoney((int)$user['balance'])." so'm</b>\n".eMN()." Sarflangan: <b>".fmtMoney((int)$user['total_spent'])." so'm</b>\n📦 Buyurtmalar: <b>{$user['orders_count']} ta</b>\n📅 Ro'yxatdan: ".substr($user['created_at'],0,10)."\nStatus: ".($user['is_banned']?'🚫 <b>Banlangan</b>':eOK().' <b>Aktiv</b>'),adminUserActionsKb((int)$user['telegram_id'],(bool)$user['is_banned']));
}
function cbAdmOrdersList(int $chatId, int $msgId, string $cbId, string $filter): void {
    $all = getAllOrders(); $fmap = ['adm_orders_pending'=>['pending','⏳ Kutayotgan buyurtmalar'],'adm_orders_done'=>['completed','✅ Bajarilgan buyurtmalar'],'adm_orders_all'=>[null,'📋 Barcha buyurtmalar']]; [$sf,$title] = $fmap[$filter];
    $orders = $sf ? array_values(array_filter($all,fn($o)=>$o['status']===$sf)) : $all;
    if (!$orders) { answerCallback($cbId,"Buyurtmalar yo'q",true); return; }
    $emap = ['pending'=>'⏳','processing'=>'🔄','completed'=>'✅','failed'=>'❌','cancelled'=>'🚫']; $text = "<b>{$title}</b> (".count($orders)." ta):\n\n";
    foreach (array_slice($orders,0,15) as $o) $text .= ($emap[$o['status']]??'❓')." <b>#{$o['id']}</b> | #{$o['buyer_bot_id']} | {$o['stars_amount']} stars @{$o['target_username']} (".fmtMoney((int)$o['price'])." so'm)\n";
    if (count($orders)>15) $text .= "\n...va yana ".(count($orders)-15)." ta";
    editMessage($chatId,$msgId,$text,adminOrdersKb()); answerCallback($cbId);
}
function cbAdmTopupOrders(int $chatId, int $msgId, string $cbId): void {
    $orders = getAllTopupOrders(); if (!$orders) { answerCallback($cbId,"To'lov buyurtmalari yo'q",true); return; }
    $emap = ['pending'=>'⏳','paid'=>'✅','completed'=>'✅','cancelled'=>'❌','expired'=>'⌛']; $text = eMN()." <b>To'lov buyurtmalari</b> (".count($orders)." ta):\n\n";
    foreach (array_slice($orders,0,20) as $o) $text .= ($emap[$o['status']]??'❓')." <code>{$o['order_code']}</code> | #{$o['bot_id']} | ".fmtMoney((int)$o['amount'])." so'm\n";
    if (count($orders)>20) $text .= "\n...va yana ".(count($orders)-20)." ta";
    editMessage($chatId,$msgId,$text,inlineKb([[btn('Orqaga','adm_main','',E_BACK)]])); answerCallback($cbId);
}
function cbAdmShopInfo(int $chatId, int $msgId, string $cbId): void {
    $r = getShopInfo(); $text = $r['ok'] ? "🏪 <b>Do'kon ma'lumotlari</b>\n\n📛 Nomi: ".($r['shop_name']??'—')."\n".eMN()." Balans: <b>".fmtMoney((int)($r['balance']??0))." so'm</b>\n".eOK()." Status: ".($r['status']??'—') : "❌ Ma'lumot olinmadi:\n".($r['message']??'');
    editMessage($chatId,$msgId,$text,inlineKb([[btn('Orqaga','adm_main','',E_BACK)]])); answerCallback($cbId);
}
function cbAdmChannels(int $chatId, int $msgId, string $cbId): void {
    $channels = getChannels(); $count = count($channels);
    editMessage($chatId,$msgId,"📢 <b>Majburiy obuna kanallar</b>\n\nHozirda: <b>{$count} ta</b> kanal\n\n".($count?"Kanal o'chirish uchun 🗑 tugmasini bosing:":"Hali kanallar qo'shilmagan."),adminChannelsKb($channels));
    answerCallback($cbId);
}
function admStateWaitingAddChannel(int $chatId, int $userId, string $text): void {
    $parts = array_map('trim',explode('|',$text));
    if (count($parts) < 3) { sendMessage($chatId,eWN()." Format noto'g'ri!\n\nTo'g'ri format:\n<code>@username | Kanal nomi | https://t.me/username</code>"); return; }
    [$channelId,$title,$link] = $parts; $chatInfo = apiRequest('getChat',['chat_id'=>$channelId]);
    if (!$chatInfo) { sendMessage($chatId,eWN()." Kanal topilmadi!\n\n• Bot kanalga <b>admin</b> sifatida qo'shilganmi?\n• ID yoki username to'g'rimi?"); return; }
    $realTitle = $chatInfo['title'] ?? $title;
    if (addChannel($channelId,$realTitle,$link)) { clearState($userId); sendMessage($chatId,eOK()." Kanal qo'shildi!\n\n📢 <b>{$realTitle}</b>\n🆔 <code>{$channelId}</code>",inlineKb([[btn('Kanallar','adm_channels','',E_BACK)]])); }
    else { sendMessage($chatId,eWN()." Bu kanal allaqachon qo'shilgan!"); clearState($userId); }
}
function admStateWaitingBroadcast(int $chatId, int $userId, int $messageId): void {
    clearState($userId); $users = getAllUsers(); $total = 0; foreach ($users as $u) { if (!$u['is_banned']) $total++; }
    $progressMsg = sendMessage($chatId,"📢 <b>Broadcast boshlandi...</b>\n\nJami: <b>{$total} ta</b> foydalanuvchi\n".eCL()." Yuborilmoqda...");
    $sent = 0; $failed = 0;
    foreach ($users as $u) { if ($u['is_banned']) continue; copyMessage((int)$u['telegram_id'],$chatId,$messageId) ? $sent++ : $failed++; usleep(50000); }
    if ($progressMsg) editMessage($chatId,(int)$progressMsg['message_id'],"📢 <b>Broadcast yakunlandi!</b>\n\n".eOK()." Muvaffaqiyatli: <b>{$sent}</b>\n❌ Xato: <b>{$failed}</b>\n".eUS()." Jami: <b>".($sent+$failed)."</b>",adminMainKb());
}
function admStateWaitingUserId(int $chatId, int $userId, string $text): void {
    $botId = (int)trim($text); if (!$botId) { sendMessage($chatId,"❌ Raqam kiriting:"); return; }
    $u = getUserByBotId($botId); if (!$u) { sendMessage($chatId,"❌ Topilmadi.",adminBackKb()); clearState($userId); return; }
    clearState($userId); $msg = sendMessage($chatId,'...'); if ($msg) showUserInfo($chatId,(int)$msg['message_id'],$u);
}
function admStateWaitingBalanceUserId(int $chatId, int $userId, string $text): void {
    $botId = (int)trim($text); if (!$botId) { sendMessage($chatId,"❌ Raqam kiriting:"); return; }
    $u = getUserByBotId($botId); if (!$u) { sendMessage($chatId,"❌ Topilmadi."); clearState($userId); return; }
    clearState($userId); $msg = sendMessage($chatId,'...'); if ($msg) showUserInfo($chatId,(int)$msg['message_id'],$u);
}
function admStateWaitingAddAmount(int $chatId, int $userId, array $d, string $text): void {
    $amount = (int)preg_replace('/\D/','',$text); if ($amount <= 0) { sendMessage($chatId,"❌ Noto'g'ri miqdor:"); return; }
    $tgId = (int)($d['target_tg_id']??0); $u = addBalance($tgId,$amount); clearState($userId);
    $amtStr = fmtMoney($amount); $newBal = fmtMoney((int)$u['balance']);
    sendMessage($chatId,eOK()." {$amtStr} so'm qo'shildi!\n".eWL()." Yangi balans: <b>{$newBal} so'm</b>",adminMainKb());
    sendMessage($tgId,eMN()." Hisobingizga <b>{$amtStr} so'm</b> qo'shildi!\n".eWL()." Yangi balans: <b>{$newBal} so'm</b>");
}
function admStateWaitingSubAmount(int $chatId, int $userId, array $d, string $text): void {
    $amount = (int)preg_replace('/\D/','',$text); if ($amount <= 0) { sendMessage($chatId,"❌ Noto'g'ri miqdor:"); return; }
    $tgId = (int)($d['target_tg_id']??0);
    try { $u = deductBalance($tgId,$amount); $amtStr = fmtMoney($amount); $newBal = fmtMoney((int)$u['balance']); sendMessage($chatId,eOK()." {$amtStr} so'm ayirildi!\n".eWL()." Yangi balans: <b>{$newBal} so'm</b>",adminMainKb()); }
    catch (RuntimeException $e) { sendMessage($chatId,"❌ Xato: ".$e->getMessage()); }
    clearState($userId);
}
function admStateWaitingOrdersChannel(int $chatId, int $userId, string $text): void {
    clearState($userId); $text = trim($text);
    if ($text === '-') { setOrdersChannelLink(''); sendMessage($chatId,eOK()." <b>Buyurtmalar kanali o'chirildi.</b>",inlineKb([[btn('Sozlamalar','adm_settings','',E_BACK)]])); return; }
    if (!preg_match('#^https?://t\.me/#i',$text)) { sendMessage($chatId,eWN()." Noto'g'ri havola!\n\nFormat: <code>https://t.me/username</code>\n\nO'chirish uchun <code>-</code> yuboring."); return; }
    $username = preg_replace('#^https?://t\.me/#i','',rtrim($text,'/')); $chatInfo = apiRequest('getChat',['chat_id'=>'@'.$username]);
    if (!empty($chatInfo['ok'])) setOrdersChannelId((string)$chatInfo['result']['id']);
    setOrdersChannelLink($text); sendMessage($chatId,eOK()." <b>Buyurtmalar kanali saqlandi!</b>\n\n💬 Havola: {$text}",inlineKb([[btn('Sozlamalar','adm_settings','',E_BACK)]]));
}
function admStateWaitingSetPrice(int $chatId, int $userId, string $text): void {
    $price = (int)preg_replace('/\D/','',$text); if ($price < 1) { sendMessage($chatId,"❌ Noto'g'ri qiymat:"); return; }
    setStarsPrice($price); clearState($userId); sendMessage($chatId,eOK()." 1 Stars narxi <b>{$price} so'm</b> ga o'zgartirildi!",adminMainKb());
}
function admStateWaitingSetSeed(int $chatId, int $userId, int $msgId, string $text): void {
    $key = trim($text);
    if (strlen($key) < 10) { sendMessage($chatId, "❌ API kalit juda qisqa. fragment-api.uz dan to'g'ri kalit nusxa oling:"); return; }
    setSetting('fragment_api_key', $key); clearState($userId); if ($msgId) deleteMessage($chatId, $msgId);
    sendMessage($chatId, eOK() . " Fragment API kaliti saqlandi!\n🔒 Xabar o'chirildi.", adminMainKb());
}

// ─── EXPIRE CHECK ─────────────────────────────────────────────────────────────
function checkExpiredTopups(): void {
    $orders = getAllTopupOrders();
    foreach ($orders as $o) {
        if ($o['status'] !== 'pending' || !isset($o['expire_at'])) continue;
        $tgId = (int)$o['telegram_id']; $orderCode = $o['order_code']; $amtStr = fmtMoney((int)$o['amount']);
        if (time() > (int)$o['expire_at']) {
            cancelPayment($orderCode); updateTopupOrder($orderCode,'cancelled');
            sendMessage($tgId,eWN()." <b>To'lov muddati tugadi!</b>\n\n".eCL()." <b>5 daqiqa</b> ichida to'lov amalga oshirilmaganligi sababli\n".eID()." <code>{$orderCode}</code> buyurtmangiz\n<b>avtomatik bekor qilindi.</b>\n\nQaytadan urinib ko'ring.",backMainKb());
            foreach (ADMIN_IDS as $adminId) sendMessage((int)$adminId,eCL()." <b>To'lov muddati tugadi!</b>\n━━━━━━━━━━━━━━━━━━━━\n".eUS()." TG ID: <code>{$tgId}</code>\n".eMN()." Miqdor: <b>{$amtStr} so'm</b>\n".eID()." Buyurtma: <code>{$orderCode}</code>");
            continue;
        }
        $result = checkPayment($orderCode); if (!$result['ok']) continue;
        $status = $result['status']; $amount = (int)$result['amount']; if ($amount <= 0) $amount = (int)$o['amount'];
        if (in_array($status, ['paid','completed'])) {
            $fresh = getTopupOrder($orderCode); if ($fresh && in_array($fresh['status'],['paid','completed'])) continue;
            addBalance($tgId,$amount); updateTopupOrder($orderCode,'completed');
            $newBal = fmtMoney((int)getUser($tgId)['balance']); $amountStr = fmtMoney($amount);
            sendMessage($tgId,eOK()." Hisobingizga <b>{$amountStr} so'm</b> qo'shildi!\n\n".eWL()." Yangi balansingiz: <b>{$newBal} so'm</b>",inlineKb([[btn('Bosh menyu','back_main','','','primary')]]));
            foreach (ADMIN_IDS as $adminId) sendMessage((int)$adminId,eOK()." To'lov qabul qilindi!\n\n".eUS()." TG ID: <code>{$tgId}</code>\n".eMN()." <b>{$amountStr} so'm</b>\n".eID()." <code>{$orderCode}</code>\n".eWL()." Yangi balans: <b>{$newBal} so'm</b>");
        } elseif ($status === 'cancel') {
            updateTopupOrder($orderCode,'cancelled'); sendMessage($tgId,eWN()." To'lovingiz bekor qilindi.\n".eMN()." <b>{$amtStr} so'm</b>",backMainKb());
        }
    }
}
// ═══════════════════════════════════════════════════════════════════════════════
// PREMIUM HANDLER FUNKSIYALAR
// ═══════════════════════════════════════════════════════════════════════════════

// Premium emoji yordamchi (premium emoji id bilan)
function ePR(): string {
    return '<tg-emoji emoji-id="5951802070607597636">💎</tg-emoji>';
}

// 1. Asosiy premium menyu — kim uchun?
function cbPremiumMenu(int $chatId, int $msgId, string $cbId, int $userId, array $user): void {
    $sub = checkAllChannels($userId);
    if (!$sub['ok']) {
        answerCallback($cbId);
        editMessage($chatId, $msgId, eIN() . " <b>Botdan foydalanish uchun kanallarga a'zo bo'ling:</b>", subscribeRetryKb($sub['channels']));
        return;
    }
    clearState($userId);
    setState($userId, 'waiting_premium_username');
    editMessage($chatId, $msgId,
        ePR() . " <b>Telegram Premium olish</b>\n\n" .
        e(E_USER, '👤') . " O'zingiz uchun yoki boshqa foydalanuvchi uchun\n\n" .
        e('5231102735817918643', '👇') . " Username kiriting:\n<code>@username</code>",
        premiumTargetKb()
    );
    answerCallback($cbId);
}

// 2. O'zim uchun tezkor
function cbPremiumSelf(int $chatId, int $msgId, string $cbId, int $userId, array $user): void {
    if (!$user['username']) {
        answerCallback($cbId, "Username'ingiz yo'q. Telegram sozlamalaridan o'rnating.", true);
        return;
    }
    clearState($userId);
    $balance = (int)$user['balance'];
    editMessage($chatId, $msgId,
        ePR() . " <b>Telegram Premium</b>\n\n" .
        e(E_USER,   '👤') . " Qabul qiluvchi: <b>@{$user['username']}</b>\n" .
        e(E_WALLET, '👛') . " Balansingiz: <b>" . fmtMoney($balance) . " so'm</b>\n\n" .
        e(E_CLOCK,  '⏰') . " Muddatni tanlang:",
        premiumPackagesKb($user['username'])
    );
    answerCallback($cbId);
}

// 3. Username state handler
function stateWaitingPremiumUsername(int $chatId, int $userId, array $user, string $text): void {
    $username = ltrim(trim($text), '@');
    if (!$username || strlen($username) < 3) {
        sendMessage($chatId, eWN() . " Username noto'g'ri. Kamida 3 ta belgi bo'lishi kerak.");
        return;
    }
    clearState($userId);
    $balance = (int)$user['balance'];
    sendMessage($chatId,
        ePR() . " <b>Telegram Premium</b>\n\n" .
        e(E_USER,   '👤') . " Qabul qiluvchi: <b>@{$username}</b>\n" .
        e(E_WALLET, '👛') . " Balansingiz: <b>" . fmtMoney($balance) . " so'm</b>\n\n" .
        e(E_CLOCK,  '⏰') . " Muddatni tanlang:",
        premiumPackagesKb($username)
    );
}

// 4. Paket tanlash — premium_pkg_{months}_{username}
function cbPremiumPackage(int $chatId, int $msgId, string $cbId, int $userId, array $user, string $data): void {
    $parts = explode('_', substr($data, strlen('premium_pkg_')), 2);
    if (count($parts) < 2) { answerCallback($cbId, 'Xato', true); return; }
    [$months, $targetUsername] = $parts;
    $months = (int)$months;

    $validMonths = [3, 6, 12];
    if (!in_array($months, $validMonths)) { answerCallback($cbId, 'Noto\'g\'ri muddat', true); return; }

    $price   = getPremiumPrice($months);
    $balance = (int)$user['balance'];
    $label   = premiumMonthsLabel($months);

    if ($balance < $price) {
        $lack = $price - $balance;
        editMessage($chatId, $msgId,
            ePR() . " <b>Telegram Premium — {$label}</b>\n\n" .
            e(E_USER,   '👤') . " Qabul qiluvchi: <b>@{$targetUsername}</b>\n" .
            e(E_WALLET, '👛') . " Balansingiz: <b>" . fmtMoney($balance) . " so'm</b>\n" .
            e(E_MONEY,  '💰') . " Narx: <b>" . fmtMoney($price) . " so'm</b>\n\n" .
            eWN() . " Balansingiz yetarli emas!\n" .
            e(E_CARD,   '💳') . " Yetishmaydi: <b>" . fmtMoney($lack) . " so'm</b>\n\n" .
            "Avval balansni to'ldiring:",
            inlineKb([
                [btn("Balans to'ldirish", 'topup', '', E_MONEY, 'primary')],
                [btn('Orqaga', 'buy_premium', '', E_BACK)],
            ])
        );
        answerCallback($cbId);
        return;
    }

    editMessage($chatId, $msgId,
        ePR() . " <b>Telegram Premium — {$label}</b>\n\n" .
        e(E_USER,   '👤') . " Qabul qiluvchi: <b>@{$targetUsername}</b>\n" .
        e(E_WALLET, '👛') . " Balansingizdan ayiriladi: <b>" . fmtMoney($price) . " so'm</b>\n" .
        e(E_MONEY,  '💰') . " Joriy balans: <b>" . fmtMoney($balance) . " so'm</b>\n\n" .
        e(E_OK,     '✅') . " Tasdiqlaysizmi?",
        premiumConfirmKb($months, $targetUsername)
    );
    answerCallback($cbId);
}

// 5. Tasdiqlash va sotib olish — premium_confirm_{months}_{username}
function cbPremiumConfirm(int $chatId, int $msgId, string $cbId, int $userId, array $user, string $data): void {
    $parts = explode('_', substr($data, strlen('premium_confirm_')), 2);
    if (count($parts) < 2) { answerCallback($cbId, 'Xato', true); return; }
    [$months, $targetUsername] = $parts;
    $months = (int)$months;

    $validMonths = [3, 6, 12];
    if (!in_array($months, $validMonths)) { answerCallback($cbId, 'Noto\'g\'ri muddat', true); return; }

    $price   = getPremiumPrice($months);
    $balance = (int)$user['balance'];
    $label   = premiumMonthsLabel($months);

    if ($balance < $price) {
        answerCallback($cbId, 'Balans yetarli emas!', true);
        return;
    }

    // Loading xabari
    editMessage($chatId, $msgId,
        ePR() . " <b>Premium sotib olinmoqda...</b>\n\n" .
        e(E_CLOCK,  '⏰') . " Iltimos kuting, bu bir necha daqiqa olishi mumkin.\n\n" .
        e(E_USER,   '👤') . " Qabul qiluvchi: <b>@{$targetUsername}</b>\n" .
        e(E_PREMIUM,'💎') . " Muddat: <b>{$label}</b>",
        inlineKb([[btn('Bajarilmoqda...', 'noop', '', E_CLOCK)]])
    );
    answerCallback($cbId);

    // Buyurtma yaratish (balansdan ayiradi)
    $order   = createPremiumOrder($userId, (int)$user['id'], $targetUsername, $months, $price);
    $orderId = $order['id'];

    // API ga so'rov
    $result = buyPremium($targetUsername, $months, $orderId);

    if ($result['ok']) {
        updatePremiumOrderStatus($orderId, 'completed', $result);
        $newBal = (int)(getUser($userId)['balance'] ?? 0);
        $priceStr = fmtMoney($price);
        $unamePrem = $user['username'] ? '@'.$user['username'] : $user['full_name'];
        editMessage($chatId, $msgId,
            ePR() . " <b>Premium muvaffaqiyatli yuborildi!</b>\n\n" .
            e(E_OK,     '✅') . " Buyurtma: <code>{$orderId}</code>\n" .
            e(E_USER,   '👤') . " Qabul qiluvchi: <b>@{$targetUsername}</b>\n" .
            e(E_PREMIUM,'💎') . " Muddat: <b>{$label}</b>\n\n" .
            e(E_WALLET, '👛') . " Qolgan balans: <b>" . fmtMoney($newBal) . " so'm</b>",
            premiumSuccessKb()
        );
        foreach (ADMIN_IDS as $adminId) sendMessage((int)$adminId,
            ePR() . " <b>Premium yuborildi!</b>\n━━━━━━━━━━━━━━━━━━━━\n" .
            e(E_OK, '✅') . " Buyurtma: <code>{$orderId}</code>\n" .
            eUS() . " Yuboruvchi: {$unamePrem} (<b>#{$user['id']}</b>)\n" .
            eUS() . " Qabul qiluvchi: <b>@{$targetUsername}</b>\n" .
            ePR() . " Muddat: <b>{$label}</b>\n" .
            eMN() . " To'lov: <b>{$priceStr} so'm</b>\n" .
            "📅 ".date('Y-m-d H:i:s')
        );
        $chIdPrem = getOrdersChannelId();
        if ($chIdPrem) sendMessage((int)$chIdPrem,
            ePR() . " <b>Premium buyurtma bajarildi!</b>\n━━━━━━━━━━━━━━━━━━━━\n" .
            e(E_OK, '✅') . " Buyurtma: <code>{$orderId}</code>\n" .
            eUS() . " Yuboruvchi: {$unamePrem}\n" .
            eUS() . " Qabul qiluvchi: <b>@{$targetUsername}</b>\n" .
            ePR() . " Muddat: <b>{$label}</b>\n" .
            eMN() . " To'lov: <b>{$priceStr} so'm</b>\n" .
            "📅 ".date('Y-m-d H:i:s'),
            ['inline_keyboard'=>[[['text'=>"🤖 Botga o'tish",'url'=>'https://t.me/elderstars_bot']]]]
        );
    } else {
        // Muvaffaqiyatsiz — balansni qaytarish
        $curUser = getUser($userId);
        if ($curUser) {
            $curUser['balance']    += $price;
            $curUser['total_spent'] = max(0, ($curUser['total_spent'] ?? 0) - $price);
            saveUser($curUser);
        }
        updatePremiumOrderStatus($orderId, 'failed', $result);
        $newBal  = (int)(getUser($userId)['balance'] ?? 0);
        $errorMsg = $result['message'] ?? 'Noma\'lum xato';
        editMessage($chatId, $msgId,
            ePR() . " <b>Premium yuborilmadi!</b>\n\n" .
            eWN() . " Sabab: " . $errorMsg . "\n\n" .
            e(E_WALLET, '👛') . " <b>" . fmtMoney($price) . " so'm</b> balansingizga qaytarildi.\n" .
            e(E_WALLET, '👛') . " Joriy balans: <b>" . fmtMoney($newBal) . " so'm</b>",
            premiumErrorKb()
        );
    }
}

// ADMIN PREMIUM HANDLER FUNKSIYALAR
// ═══════════════════════════════════════════════════════════════════════════════

function cbAdmPremium(int $chatId, int $msgId, string $cbId): void {
    $orders  = getAllPremiumOrders();
    $total   = count($orders);
    $done    = count(array_filter($orders, fn($o) => $o['status'] === 'completed'));
    $pending = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
    $failed  = count(array_filter($orders, fn($o) => $o['status'] === 'failed'));

    editMessage($chatId, $msgId,
        ePR() . " <b>Premium Boshqaruv</b>\n\n" .
        "📊 Jami buyurtmalar: <b>{$total}</b>\n" .
        "✅ Bajarilgan: <b>{$done}</b>\n" .
        "⏳ Kutmoqda: <b>{$pending}</b>\n" .
        "❌ Muvaffaqiyatsiz: <b>{$failed}</b>\n\n" .
        "💎 Narxlarni o'zgartirish yoki buyurtmalarni ko'rish:",
        adminPremiumKb()
    );
    answerCallback($cbId);
}

function cbAdmPremiumOrders(int $chatId, int $msgId, string $cbId): void {
    $orders = array_slice(getAllPremiumOrders(), 0, 15);
    if (!$orders) {
        answerCallback($cbId, 'Hali buyurtma yo\'q', true);
        return;
    }
    $text = ePR() . " <b>So'nggi Premium buyurtmalar:</b>\n\n";
    foreach ($orders as $o) {
        $icon   = match($o['status']) { 'completed' => '✅', 'failed' => '❌', default => '⏳' };
        $label  = premiumMonthsLabel((int)$o['months']);
        $text  .= "{$icon} <code>{$o['id']}</code>\n";
        $text  .= "👤 @{$o['target_username']} — {$label}\n";
        $text  .= "💰 " . fmtMoney((int)$o['price']) . " so'm | {$o['created_at']}\n\n";
    }
    editMessage($chatId, $msgId, $text, adminPremiumBackKb());
    answerCallback($cbId);
}

function cbAdmPremiumSetPrice(int $chatId, int $msgId, string $cbId, int $userId, string $data): void {
    // adm_premium_price_3
    $months = (int)substr($data, strlen('adm_premium_price_'));
    $validMonths = [3, 6, 12];
    if (!in_array($months, $validMonths)) { answerCallback($cbId, '❌ Xato', true); return; }
    $label   = premiumMonthsLabel($months);
    $current = getPremiumPrice($months);
    setState($userId, 'adm_waiting_premium_price', ['months' => $months]);
    editMessage($chatId, $msgId,
        ePR() . " <b>{$label} Premium narxini o'zgartirish</b>\n\n" .
        "Hozirgi narx: <b>" . fmtMoney($current) . " so'm</b>\n\n" .
        "Yangi narxni <b>so'mda</b> kiriting:\n<i>Misol: 69000, 179000</i>",
        adminPremiumBackKb()
    );
    answerCallback($cbId);
}

function admStateWaitingPremiumPrice(int $chatId, int $userId, array $data, string $text): void {
    $months = (int)($data['months'] ?? 0);
    $price  = (int)preg_replace('/\D/', '', $text);
    if ($price < 1000 || $price > 10000000) {
        sendMessage($chatId, eWN() . " Narx noto'g'ri! 1 000 — 10 000 000 so'm oraliqda kiriting:");
        return;
    }
    setPremiumPrice($months, $price);
    clearState($userId);
    $label = premiumMonthsLabel($months);
    sendMessage($chatId,
        eOK() . " <b>{$label} Premium narxi yangilandi:</b>\n\n" .
        ePR() . " Yangi narx: <b>" . fmtMoney($price) . " so'm</b>",
        inlineKb([[btn('Premium boshqaruv', 'adm_premium', '', E_PREMIUM, 'primary')]])
    );
}
