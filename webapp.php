<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';          // JSON fayl bazasi
require_once __DIR__ . '/stars_api.php';
require_once __DIR__ . '/premium_api.php';
require_once __DIR__ . '/sendGift.php';
require_once __DIR__ . '/webapp_core.php'; // JSON asosida qayta yozilgan
require_once __DIR__ . '/webapp_view.php';

// gifts registry ni qayta yuklash
$GLOBALS['_gifts'] = require __DIR__ . '/gifts.php';

$state = webapp_bootstrap();
$state = webapp_handle_request($state);
echo render_webapp($state);
