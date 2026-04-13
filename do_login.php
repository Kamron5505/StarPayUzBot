<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

$settings = new Settings;
$settings->setAppInfo(
    (new AppInfo)
        ->setApiId(TG_API_ID)
        ->setApiHash(TG_API_HASH)
);

// session.madeline fayli shu yerda yaratiladi
$MadelineProto = new API(__DIR__ . '/session.madeline', $settings);

// start() — birinchi marta interaktiv login so'raydi
$MadelineProto->start();

echo "Login muvaffaqiyatli! session.madeline fayli yaratildi.\n";