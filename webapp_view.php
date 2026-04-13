<?php
declare(strict_types=1);

function render_webapp(array $state): string {
    $langCode = $state['langCode'];
    $tr = $state['tr'];
    $page = $state['page'];
    $shop = $state['shop'];
    $user = $state['user'];
    $profile = $state['profile'];
    $giftItems = $state['giftItems'];
    $premiumItems = $state['premiumItems'];
    $recentOrders = $state['recentOrders'];
    $flash = $state['flash'];
    $flashType = $state['flashType'];
    $recipient = $state['recipient'];
    $selfMode = $state['selfMode'];
    $giftId = $state['giftId'];
    $premiumId = $state['premiumId'];
    $starsQty = $state['starsQty'];
    $starsPrice = (int)$state['starsPrice'];
    $translations = $state['translations'];

    ob_start();
?>
<!doctype html>
<html lang="<?= wa_h($langCode) ?>">
<head>
    
    
<meta charset="utf-8">
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>
const tg = window.Telegram.WebApp;
tg.ready();
</script>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Doda Stars Bot</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#06080d;--surface:#0c1017;--card:#101722;--card2:#0c131d;--border:#1a2330;--border2:#2a3442;
  --text:#f5f7ff;--muted:#8b96a8;--muted2:#a9b2c2;--accent:#fbbf24;--accent2:#f59e0b;
  --purple:#c084fc;--green:#34d399;--red:#f87171;--blue:#60a5fa;
  --font-body:'DM Sans',sans-serif;--font-mono:'Space Mono',monospace;--radius:22px;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{
  font-family:var(--font-body);color:var(--text);
  background:
    radial-gradient(ellipse 75% 45% at 50% -10%, rgba(251,191,36,.10), transparent 55%),
    radial-gradient(circle at 85% 10%, rgba(96,165,250,.08), transparent 25%),
    linear-gradient(180deg,#06080d,#090d14 38%,#06080d 100%);
}
a{text-decoration:none;color:inherit} input,button{font-family:inherit}
.app{width:100%;max-width:700px;margin:0 auto;min-height:100vh;padding:34px 10px 130px}
.hero{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px}
.hero-left h2{font-size:21px;font-weight:800;line-height:1.08}
.hero-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1.6px;margin-top:6px;font-family:var(--font-mono)}
.balance-chip{display:inline-flex;align-items:center;gap:8px;padding:7px 8px 7px 10px;border-radius:999px;background:rgba(255,255,255,.05);border:1px solid var(--border2);font-size:13px;font-weight:800}
.switcher{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;padding:6px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:18px;margin-bottom:14px}
.switcher a{display:flex;align-items:center;justify-content:center;gap:6px;padding:12px 8px;border-radius:14px;color:var(--muted2);font-size:13px;font-weight:800}
.switcher a.active{color:var(--text);background:linear-gradient(135deg, rgba(255,255,255,.13), rgba(255,255,255,.05))}
.box,.profile-list,.profile-hero{background:linear-gradient(180deg,var(--card),var(--card2));border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.box-head{padding:16px 16px 13px;border-bottom:1px solid rgba(255,255,255,.05)}
.box-head h3{font-size:18px;font-weight:800;line-height:1.2}
.box-head p,.box-sub{font-size:12px;color:var(--muted2);margin-top:4px}
.gold{color:var(--accent)} .purple{color:var(--purple)} .green{color:var(--green)}
.box-body{padding:16px}
.notice{padding:11px 12px;border-radius:14px;font-size:12px;font-weight:700;margin-bottom:12px}
.notice.success{background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.16);color:var(--green)}
.notice.error{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.16);color:var(--red)}
.ask-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
.ask-row h4{font-size:16px;font-weight:800}
.self-btn{border:none;cursor:pointer;padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.06);color:var(--text);font-size:12px;font-weight:800}
.search-wrap{position:relative;margin-bottom:12px}
.search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:16px}
.inp{width:100%;border:none;outline:none;color:var(--text);background:linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.035));border:1px solid rgba(255,255,255,.09);border-radius:17px;padding:14px 14px 14px 42px;font-size:16px}
.inp.small{padding:12px 13px;font-size:16px}
.card-list{display:grid;gap:10px}
.select-card{position:relative;display:block;cursor:pointer;background:linear-gradient(135deg,#0a1220,#10192a);border:1px solid #223047;border-radius:18px;padding:14px}
.select-card.active{border-color:rgba(251,191,36,.55)}
.select-card.simple{text-align:center}
.select-card.simple .iconbox{width:52px;height:52px;border-radius:16px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:24px;background:linear-gradient(135deg, rgba(192,132,252,.10), rgba(255,255,255,.04));border:1px solid rgba(192,132,252,.15)}
.select-card.simple .big{font-size:18px;font-weight:900;line-height:1.1}
.select-card.simple .sub{font-size:12px;color:var(--muted2);margin-top:6px}
.select-card.simple .price{font-size:15px;font-weight:900;margin-top:10px}
.grid-compact{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.badge{display:inline-flex;align-items:center;justify-content:center;padding:5px 9px;border-radius:999px;font-size:10px;font-weight:900;border:1px solid transparent;text-transform:uppercase}
.badge.gold{background:rgba(251,191,36,.09);border-color:rgba(251,191,36,.18);color:var(--accent)}
.badge.purple{background:rgba(192,132,252,.09);border-color:rgba(192,132,252,.18);color:var(--purple)}
.badge.blue{background:rgba(96,165,250,.09);border-color:rgba(96,165,250,.18);color:var(--blue)}
.badge.green{background:rgba(52,211,153,.09);border-color:rgba(52,211,153,.18);color:var(--green)}
.badge.red{background:rgba(248,113,113,.09);border-color:rgba(248,113,113,.18);color:var(--red)}
.gift-card{display:flex;align-items:center;gap:12px;padding:12px}
.gift-icon{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.06);flex-shrink:0}
.gift-main{flex:1;min-width:0}
.gift-top{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px}
.gift-title{font-size:15px;font-weight:800;line-height:1.15}
.gift-sub{font-size:12px;color:var(--muted2);line-height:1.35}
.gift-price{margin-top:8px;font-size:14px;font-weight:900}
.total-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px;padding-top:12px;border-top:1px dashed rgba(255,255,255,.14);font-size:15px;font-weight:900}
.main-btn{width:100%;border:none;cursor:pointer;margin-top:12px;padding:15px 15px;border-radius:16px;font-size:14px;font-weight:900;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#000}
.hidden-input{display:none}
.quick-stars{display:grid;grid-template-columns:repeat(4, minmax(0,1fr));gap:8px;margin-top:10px}
.quick-star-btn{border:none;cursor:pointer;padding:11px 12px;border-radius:13px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);color:var(--text);font-size:12px;font-weight:800}
.orders-list{padding:0 14px 14px;display:grid;gap:10px}
.order-card{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:16px;padding:12px}
.order-code{font-size:11px;color:var(--muted2);font-weight:800;font-family:var(--font-mono)}
.order-title{font-size:14px;font-weight:800;margin-top:6px}
.order-meta{font-size:12px;color:var(--muted2);line-height:1.45;margin-top:5px}
.profile-hero{padding:18px 14px;margin-bottom:12px}
.profile-top{display:flex;flex-direction:column;align-items:center}
.profile-avatar{width:74px;height:74px;border-radius:50%;overflow:hidden;border:1px solid var(--border2)}
.profile-avatar img{width:100%;height:100%;object-fit:cover}
.profile-name{font-size:19px;font-weight:900;margin-top:10px}
.profile-user{font-size:13px;color:var(--muted2);margin-top:4px}
.profile-list{padding:2px 14px}
.profile-item{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.profile-item:last-child{border-bottom:none}
.profile-left{display:flex;align-items:center;gap:12px}
.iconbox{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#202833;color:var(--accent);font-size:19px}
.profile-label{font-size:14px;font-weight:700}
.profile-value{font-size:13px;color:#dfe5ee}
.chev{font-size:18px;color:#c2cad6}
.sheet-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.62);display:none;align-items:flex-end;justify-content:center;z-index:60}
.sheet-backdrop.open{display:flex}
.sheet{width:min(700px, calc(100% - 20px));margin-bottom:92px;background:linear-gradient(180deg, #101722, #0c131d);border:1px solid rgba(255,255,255,.07);border-radius:26px;padding:16px}
.sheet-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.sheet-title{font-size:18px;font-weight:900}
.sheet-close{width:34px;height:34px;border:none;border-radius:50%;background:#2c3542;color:#fff;cursor:pointer;font-size:16px}
.lang-row{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;margin-bottom:10px;border-radius:18px;cursor:pointer;border:1px solid rgba(255,255,255,.05);background:linear-gradient(180deg, rgba(255,255,255,.025), rgba(255,255,255,.015))}
.lang-row.active{border-color:rgba(251,191,36,.22);background:linear-gradient(180deg, rgba(251,191,36,.06), rgba(255,255,255,.02))}
.lang-left{display:flex;align-items:center;gap:13px;min-width:0}
.flag{width:46px;height:46px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:900;color:#eef3ff;background:linear-gradient(180deg, rgba(255,255,255,.07), rgba(255,255,255,.035));border:1px solid rgba(255,255,255,.06)}
.lang-name{font-size:15px;font-weight:800;color:#f4f7ff;line-height:1.15}
.lang-sub{margin-top:4px;font-size:12px;color:#93a0b4;line-height:1.2}
.lang-check{width:24px;height:24px;border-radius:50%;border:2px solid rgba(160,174,194,.75)}
.lang-row.active .lang-check{border-color:#fbbf24;background:rgba(251,191,36,.2)}
.bottom-nav{position:fixed;left:0;right:0;bottom:0;z-index:80;padding:0 10px 10px}
.bottom-shell{max-width:700px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);gap:6px;padding:8px;border-radius:26px;background:linear-gradient(180deg, rgba(16,23,34,.96), rgba(10,16,26,.94));border:1px solid rgba(255,255,255,.06)}
.nav-link{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;min-height:74px;padding:8px 6px 9px;border-radius:20px;color:#8ea0b7;font-size:12px;font-weight:700}
.nav-link.active{color:#ffd15c;background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.025))}
.nav-icon-wrap{width:40px;height:40px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.05)}
.nav-svg{width:21px;height:21px;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;opacity:.95}
.nav-text{line-height:1;white-space:nowrap}
.market-box-pro{min-height:320px;border-radius:28px;overflow:hidden;padding:28px 22px;background:linear-gradient(180deg, rgba(8,18,35,.98), rgba(4,10,22,.98));border:1px solid rgba(255,255,255,.07)}
.market-title-pro{font-size:34px;line-height:1.05;font-weight:900}
.market-title-pro .accent{color:var(--accent)}
.market-desc{margin-top:14px;max-width:470px;font-size:14px;line-height:1.7;color:var(--muted2)}
.not-logged{padding:22px;border-radius:22px;background:linear-gradient(180deg,var(--card),var(--card2));border:1px solid var(--border);text-align:center}
.not-logged h3{font-size:18px;font-weight:800}
.not-logged p{font-size:13px;color:var(--muted2);margin-top:8px}
@media (max-width:560px){.app{padding:28px 8px 130px}.quick-stars{grid-template-columns:repeat(2, minmax(0,1fr))}.grid-compact{grid-template-columns:1fr}}
input[type="number"]::-webkit-inner-spin-button,input[type="number"]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
input[type="number"]{-moz-appearance:textfield}
</style>
</head>
<body>
<div class="app">
<?php if ($page === 'profile'): ?>
  <div class="profile-hero">
    <div class="profile-top">
      <div class="profile-avatar"><img src="<?= wa_h($profile['avatar']) ?>" alt="avatar"></div>
      <div class="profile-name"><?= wa_h($profile['name']) ?></div>
      <div class="profile-user"><?= wa_h($profile['username']) ?></div>
    </div>
  </div>
<?php else: ?>
  <div class="hero">
    <div class="hero-left">
      <h2><?= wa_h($tr['hero_title']) ?></h2>
      <div class="hero-label"><?= wa_h($tr['hero_label']) ?></div>
    </div>
    <div class="balance-chip">💳 <span><?= wa_money((int)$profile['balance']) ?> <?= wa_h($tr['balance_unit']) ?></span></div>
  </div>
<?php endif; ?>

<?php if (!$user): ?>
  <div class="not-logged">
    <h3><?= wa_h($tr['not_logged_title']) ?></h3>
    <p><?= wa_h($tr['not_logged_sub']) ?></p>
  </div>
<?php endif; ?>

<?php if ($page === 'home'): ?>
  <div class="switcher">
    <a href="?page=home&shop=stars" class="<?= $shop === 'stars' ? 'active' : '' ?>">⭐ <span><?= wa_h($tr['tab_stars']) ?></span></a>
    <a href="?page=home&shop=premium" class="<?= $shop === 'premium' ? 'active' : '' ?>">✦ <span><?= wa_h($tr['tab_premium']) ?></span></a>
    <a href="?page=home&shop=gifts" class="<?= $shop === 'gifts' ? 'active' : '' ?>">🎁 <span><?= wa_h($tr['tab_gifts']) ?></span></a>
  </div>

  <div class="box">
    <div class="box-head">
      <?php if ($shop === 'stars'): ?>
        <h3><span class="gold"><?= wa_h($tr['tab_stars']) ?></span></h3>
        <p><?= wa_h($tr['stars_buy_desc']) ?></p>
      <?php elseif ($shop === 'premium'): ?>
        <h3><span class="purple"><?= wa_h($tr['tab_premium']) ?></span></h3>
        <p><?= wa_h($tr['premium_buy_desc']) ?></p>
      <?php else: ?>
        <h3><span class="green"><?= wa_h($tr['tab_gifts']) ?></span></h3>
        <p><?= wa_h($tr['gifts_buy_desc']) ?></p>
      <?php endif; ?>
    </div>

    <div class="box-body">
      <?php if ($flash !== ''): ?>
        <div class="notice <?= wa_h($flashType) ?>"><?= wa_h($flash) ?></div>
      <?php endif; ?>

      <?php if ($shop === 'stars'): ?>
        <form method="post">
          <input type="hidden" name="type" value="buy_stars">
          <input type="hidden" name="tg_user_json" id="tg_user_json_stars">
          <input type="hidden" name="self_mode" id="self_mode_stars" value="<?= wa_h($selfMode) ?>">

          <div class="ask-row">
            <h4><?= wa_h($tr['send_to']) ?></h4>
            <button type="button" class="self-btn" onclick="toggleSelf('stars')"><?= wa_h($tr['to_myself']) ?></button>
          </div>

          <div class="search-wrap">
            <span class="search-icon">⌕</span>
            <input class="inp" type="text" id="recipient_stars" name="recipient" value="<?= wa_h($recipient) ?>" placeholder="<?= wa_h($tr['username_placeholder']) ?>">
          </div>

          <div class="select-card active">
            <div style="font-size:13px;font-weight:800;color:var(--text)"><?= wa_h($tr['stars_amount']) ?></div>
            <div style="font-size:12px;color:var(--muted2);margin-top:4px"><?= wa_h($tr['stars_amount_hint']) ?></div>
            <div style="margin-top:12px">
              <input class="inp small" id="starsQtyInput" type="number" name="stars_qty" min="50" max="10000" step="1" value="<?= wa_h($starsQty !== '' ? $starsQty : '50') ?>" placeholder="50">
            </div>
            <div style="font-size:12px;color:var(--muted2);margin-top:10px"><?= wa_h($tr['stars_helper']) ?></div>
            <div class="quick-stars">
              <button class="quick-star-btn" type="button" data-stars="50">50</button>
              <button class="quick-star-btn" type="button" data-stars="100">100</button>
              <button class="quick-star-btn" type="button" data-stars="500">500</button>
              <button class="quick-star-btn" type="button" data-stars="1000">1000</button>
            </div>
          </div>

          <div class="total-row"><span><?= wa_h($tr['payment_amount']) ?></span><span id="starsTotal">0 <?= wa_h($tr['balance_unit']) ?></span></div>
          <button class="main-btn" type="submit"><?= wa_h($tr['buy_stars_btn']) ?></button>
        </form>
      <?php elseif ($shop === 'premium'): ?>
        <form method="post">
          <input type="hidden" name="type" value="buy_premium">
          <input type="hidden" name="tg_user_json" id="tg_user_json_premium">
          <input type="hidden" name="self_mode" id="self_mode_premium" value="<?= wa_h($selfMode) ?>">

          <div class="ask-row">
            <h4><?= wa_h($tr['send_to']) ?></h4>
            <button type="button" class="self-btn" onclick="toggleSelf('premium')"><?= wa_h($tr['to_myself']) ?></button>
          </div>

          <div class="search-wrap">
            <span class="search-icon">⌕</span>
            <input class="inp" type="text" id="recipient_premium" name="recipient" value="<?= wa_h($recipient) ?>" placeholder="<?= wa_h($tr['username_placeholder']) ?>">
          </div>

          <div class="grid-compact">
            <?php foreach ($premiumItems as $p): ?>
              <label class="select-card simple <?= $premiumId === (string)$p['id'] ? 'active' : '' ?>">
                <input class="hidden-input" type="radio" name="premium_id" value="<?= wa_h((string)$p['id']) ?>" <?= $premiumId === (string)$p['id'] ? 'checked' : '' ?>>
                <div class="iconbox" style="border-color:rgba(192,132,252,.16);background:linear-gradient(135deg, rgba(192,132,252,.1), rgba(255,255,255,.04));color:var(--purple)">✦</div>
                <div class="big"><?= wa_h($p['label']) ?></div>
                <div class="sub"><?= wa_h($p['title']) ?></div>
                <div class="price"><?= wa_money((int)$p['price']) ?> <?= wa_h($tr['balance_unit']) ?></div>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="total-row"><span><?= wa_h($tr['payment_amount']) ?></span><span id="premiumTotal">0 <?= wa_h($tr['balance_unit']) ?></span></div>
          <button class="main-btn" type="submit"><?= wa_h($tr['buy_premium_btn']) ?></button>
        </form>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="type" value="buy_gift">
          <input type="hidden" name="tg_user_json" id="tg_user_json_gifts">
          <input type="hidden" name="gift_id" id="gift_id" value="<?= (int)$giftId ?>">
          <input type="hidden" name="self_mode" id="self_mode_gifts" value="<?= wa_h($selfMode) ?>">

          <div class="ask-row">
            <h4><?= wa_h($tr['send_to']) ?></h4>
            <button type="button" class="self-btn" onclick="toggleSelf('gifts')"><?= wa_h($tr['to_myself']) ?></button>
          </div>

          <div class="search-wrap">
            <span class="search-icon">⌕</span>
            <input class="inp" type="text" id="recipient_gifts" name="recipient" value="<?= wa_h($recipient) ?>" placeholder="<?= wa_h($tr['username_placeholder']) ?>">
          </div>

          <div class="card-list">
            <?php foreach ($giftItems as $g): ?>
              <div class="select-card gift-card gift-card-js <?= $giftId === (int)$g['id'] ? 'active' : '' ?>" data-gift-id="<?= (int)$g['id'] ?>">
                <div class="gift-icon"><?= wa_h($g['emoji']) ?></div>
                <div class="gift-main">
                  <div class="gift-top">
                    <div class="gift-title"><?= wa_h($g['title']) ?></div>
                    <span class="badge <?= wa_tone_class($g['tone']) ?>"><?= wa_h($g['badge']) ?></span>
                  </div>
                  <div class="gift-sub"><?= wa_h($g['subtitle']) ?></div>
                  <div class="gift-price"><?= wa_money((int)$g['price']) ?> <?= wa_h($tr['balance_unit']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="total-row"><span><?= wa_h($tr['payment_amount']) ?></span><span id="giftTotal">0 <?= wa_h($tr['balance_unit']) ?></span></div>
          <button class="main-btn" type="submit"><?= wa_h($tr['send_gift_btn']) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($page === 'market'): ?>
  <div class="market-box-pro">
    <div class="market-title-pro">New <span class="accent">Market</span></div>
    <div class="market-desc">Bu bo‘lim keyin to‘ldiriladi.</div>
  </div>
<?php endif; ?>

<?php if ($page === 'transactions'): ?>
  <div class="box">
    <div class="box-head">
      <div style="font-size:18px;font-weight:800"><?= wa_h($tr['transactions_title']) ?></div>
      <div class="box-sub"><?= wa_h($tr['transactions_sub']) ?></div>
    </div>
    <div class="orders-list">
      <?php if (!$recentOrders): ?>
        <div class="order-card">
          <div class="order-title"><?= wa_h($tr['no_orders_title']) ?></div>
          <div class="order-meta"><?= wa_h($tr['no_orders_sub']) ?></div>
        </div>
      <?php else: ?>
        <?php foreach ($recentOrders as $o): ?>
          <div class="order-card">
            <div class="order-code"><?= wa_h($o['order_code'] ?: ('ID-' . $o['id'])) ?></div>
            <div class="order-title">
              <?php if (($o['order_kind'] ?? '') === 'stars'): ?>
                ⭐ <?= (int)($o['stars_amount'] ?? 0) ?> <?= wa_h($tr['order_stars']) ?>
              <?php elseif (($o['order_kind'] ?? '') === 'premium'): ?>
                ✦ <?= wa_h(($o['premium_label'] ?? $tr['order_premium'])) ?>
              <?php else: ?>
                <?= wa_h(($o['gift_emoji'] ?? '🎁') . ' ' . ($o['gift_name'] ?? $tr['order_gift'])) ?>
              <?php endif; ?>
            </div>
            <div class="order-meta">
              <?= wa_h($o['target_username'] ?? '—') ?><br>
              <?php $statusText = match((string)($o['status'] ?? 'pending')) {
                'completed' => 'bajarildi',
                'failed' => 'bekor qilindi',
                'pending' => $tr['order_pending'],
                default => (string)($o['status'] ?? 'pending'),
              }; ?>
              <?= wa_money((int)($o['amount'] ?? 0)) ?> <?= wa_h($tr['balance_unit']) ?> · <?= wa_h($statusText) ?><br>
              <?= wa_h((string)($o['created_at'] ?? '')) ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($page === 'profile'): ?>
  <div class="profile-list">
    <div class="profile-item">
      <div class="profile-left"><div class="iconbox">💳</div><div class="profile-label"><?= wa_h($tr['profile_balance']) ?></div></div>
      <div style="display:flex;align-items:center;gap:10px"><div class="profile-value"><?= wa_money((int)$profile['balance']) ?> <?= wa_h($tr['balance_unit']) ?></div><div class="chev">›</div></div>
    </div>
    <div class="profile-item" onclick="openLangSheet()">
      <div class="profile-left"><div class="iconbox">🌍</div><div class="profile-label"><?= wa_h($tr['profile_language']) ?></div></div>
      <div style="display:flex;align-items:center;gap:10px"><div class="profile-value"><?= wa_h($translations[$langCode]['lang_display']) ?></div><div class="chev">›</div></div>
    </div>
    <div class="profile-item"><div class="profile-left"><div class="iconbox">🎧</div><div class="profile-label"><?= wa_h($tr['profile_support']) ?></div></div><div class="chev">›</div></div>
    <div class="profile-item"><div class="profile-left"><div class="iconbox">📣</div><div class="profile-label"><?= wa_h($tr['profile_news']) ?></div></div><div class="chev">›</div></div>
  </div>
<?php endif; ?>
</div>

<div class="sheet-backdrop" id="langSheet">
  <div class="sheet">
    <div class="sheet-head">
      <div class="sheet-title"><?= wa_h($tr['sheet_language_title']) ?></div>
      <button class="sheet-close" type="button" onclick="closeLangSheet()">✕</button>
    </div>
    <form method="post">
      <input type="hidden" name="type" value="set_lang">
      <input type="hidden" name="lang" id="langInput" value="<?= wa_h($langCode) ?>">
      <div class="lang-row <?= $langCode === 'uz' ? 'active' : '' ?>" onclick="setLang('uz', this)"><div class="lang-left"><div class="flag">UZ</div><div><div class="lang-name">O'zbekcha</div><div class="lang-sub">Uzbek language</div></div></div><div class="lang-check"></div></div>
      <div class="lang-row <?= $langCode === 'ru' ? 'active' : '' ?>" onclick="setLang('ru', this)"><div class="lang-left"><div class="flag">RU</div><div><div class="lang-name">Русский</div><div class="lang-sub">Russian language</div></div></div><div class="lang-check"></div></div>
      <div class="lang-row <?= $langCode === 'en' ? 'active' : '' ?>" onclick="setLang('en', this)"><div class="lang-left"><div class="flag">EN</div><div><div class="lang-name">English</div><div class="lang-sub">English language</div></div></div><div class="lang-check"></div></div>
      <button class="main-btn" style="margin-top:12px"><?= wa_h($tr['save']) ?></button>
    </form>
  </div>
</div>

<div class="bottom-nav">
  <div class="bottom-shell">
    <a class="nav-link <?= $page === 'home' ? 'active' : '' ?>" href="?page=home&shop=<?= wa_h($shop) ?>">
      <div class="nav-icon-wrap"><svg class="nav-svg" viewBox="0 0 24 24" fill="none"><path d="M4 10.5L12 4l8 6.5" /><path d="M7 10v8h10v-8" /></svg></div><div class="nav-text"><?= wa_h($tr['nav_home']) ?></div>
    </a>
    <a class="nav-link <?= $page === 'market' ? 'active' : '' ?>" href="?page=market&shop=<?= wa_h($shop) ?>">
      <div class="nav-icon-wrap"><svg class="nav-svg" viewBox="0 0 24 24" fill="none"><path d="M6 8h12l-1 10H7L6 8Z" /><path d="M9 8V6.8A3 3 0 0 1 12 4a3 3 0 0 1 3 2.8V8" /></svg></div><div class="nav-text"><?= wa_h($tr['nav_market']) ?></div>
    </a>
    <a class="nav-link <?= $page === 'transactions' ? 'active' : '' ?>" href="?page=transactions&shop=<?= wa_h($shop) ?>">
      <div class="nav-icon-wrap"><svg class="nav-svg" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" /><path d="M12 8v4l2.5 1.5" /></svg></div><div class="nav-text"><?= wa_h($tr['nav_history']) ?></div>
    </a>
    <a class="nav-link <?= $page === 'profile' ? 'active' : '' ?>" href="?page=profile&shop=<?= wa_h($shop) ?>">
      <div class="nav-icon-wrap"><svg class="nav-svg" viewBox="0 0 24 24" fill="none"><path d="M12 12a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" /><path d="M5 19c1.8-2.7 4.2-4 7-4s5.2 1.3 7 4" /></svg></div><div class="nav-text"><?= wa_h($tr['nav_profile']) ?></div>
    </a>
  </div>
</div>

<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>
const STARS_PRICE = <?= (int)$starsPrice ?>;
const giftMap = <?= json_encode(array_column($giftItems, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const premiumMap = <?= json_encode(array_column($premiumItems, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const tg = window.Telegram?.WebApp || null;
const I18N = <?= json_encode([
    'currency_suffix' => $tr['js_currency_suffix'],
    'username_placeholder' => $tr['username_placeholder'],
    'to_myself_value' => $tr['to_myself_value'],
], JSON_UNESCAPED_UNICODE) ?>;

function fmt(x){ return new Intl.NumberFormat('ru-RU').format(x).replace(/,/g,' ') + ' ' + I18N.currency_suffix; }

function toggleSelf(prefix){
  const selfInput = document.getElementById('self_mode_' + prefix);
  const recInput = document.getElementById('recipient_' + prefix);
  if (!selfInput || !recInput) return;
  if (selfInput.value === '1') {
    selfInput.value = '0';
    recInput.disabled = false;
    recInput.value = '';
    recInput.placeholder = I18N.username_placeholder;
  } else {
    selfInput.value = '1';
    recInput.disabled = true;
    recInput.value = I18N.to_myself_value;
  }
}

function updateTotals(){
  const starsQtyInput = document.getElementById('starsQtyInput');
  const starsTotal = document.getElementById('starsTotal');
  if (starsQtyInput && starsTotal) {
    const qty = parseInt(starsQtyInput.value || '0', 10) || 0;
    starsTotal.textContent = fmt(qty * STARS_PRICE);
  }
  const giftTotal = document.getElementById('giftTotal');
  const giftInput = document.getElementById('gift_id');
  if (giftTotal && giftInput && giftMap[giftInput.value]) {
    giftTotal.textContent = fmt(parseInt(giftMap[giftInput.value].price, 10) || 0);
  }
  const premiumTotal = document.getElementById('premiumTotal');
  const selectedPremium = document.querySelector('input[name="premium_id"]:checked');
  if (premiumTotal && selectedPremium && premiumMap[selectedPremium.value]) {
    premiumTotal.textContent = fmt(parseInt(premiumMap[selectedPremium.value].price, 10) || 0);
  }
}

function initGiftCards(){
  document.querySelectorAll('.gift-card-js').forEach(card => {
    card.addEventListener('click', function(){
      document.querySelectorAll('.gift-card-js').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('gift_id').value = this.getAttribute('data-gift-id');
      updateTotals();
    });
  });
}

function initPremiumCards(){
  document.querySelectorAll('label.select-card').forEach(card => {
    card.addEventListener('click', function(){
      const parent = this.parentElement;
      if (parent) parent.querySelectorAll('label.select-card').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      const input = this.querySelector('input[type="radio"]');
      if (input) input.checked = true;
      updateTotals();
    });
  });
}

function initQuickStars(){
  const qtyInput = document.getElementById('starsQtyInput');
  if (qtyInput) qtyInput.addEventListener('input', updateTotals);
  document.querySelectorAll('.quick-star-btn').forEach(btn => {
    btn.addEventListener('click', function(){
      if (qtyInput) {
        qtyInput.value = this.getAttribute('data-stars');
        updateTotals();
      }
    });
  });
}

function openLangSheet(){ document.getElementById('langSheet')?.classList.add('open'); }
function closeLangSheet(){ document.getElementById('langSheet')?.classList.remove('open'); }
function setLang(code, row){
  document.getElementById('langInput').value = code;
  document.querySelectorAll('.lang-row').forEach(r => r.classList.remove('active'));
  row.classList.add('active');
}
function setTelegramUserJson(){
  if (!tg) return;
  try {
    tg.ready(); tg.expand();
    const user = tg.initDataUnsafe?.user || null;
    if (!user) return;
    const json = JSON.stringify(user);
    ['tg_user_json_stars','tg_user_json_premium','tg_user_json_gifts'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = json;
    });
  } catch (e) {}
}
document.getElementById('langSheet')?.addEventListener('click', function(e){ if (e.target === this) closeLangSheet(); });
setTelegramUserJson(); initGiftCards(); initPremiumCards(); initQuickStars(); updateTotals();
</script>
<script>
const user = Telegram.WebApp.initDataUnsafe?.user;

if (user) {

    fetch("/tg_user.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            id: user.id,
            username: user.username ?? "",
            first_name: user.first_name ?? ""
        })
    });

}
</script>
</body>
</html>
<?php
    return (string)ob_get_clean();
}
