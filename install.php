<?php
require_once __DIR__ . '/config.php';

$host = env('DB_HOST', 'localhost');
$port = env('DB_PORT', '3306');
$name = env('DB_NAME', '');
$user = env('DB_USER', '');
$pass = env('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die("❌ DB ulanish xatosi: " . $e->getMessage());
}

$tables = [
"CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(64) NOT NULL,
  `value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `telegram_id` BIGINT NOT NULL,
  `username` VARCHAR(64) NOT NULL DEFAULT '',
  `full_name` VARCHAR(255) NOT NULL DEFAULT '',
  `balance` INT NOT NULL DEFAULT 0,
  `total_spent` INT NOT NULL DEFAULT 0,
  `orders_count` INT NOT NULL DEFAULT 0,
  `is_banned` TINYINT(1) NOT NULL DEFAULT 0,
  `referred_by` VARCHAR(64) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `states` (
  `telegram_id` BIGINT NOT NULL,
  `state` VARCHAR(64) NOT NULL DEFAULT '',
  `data` JSON NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_bot_id` INT NOT NULL DEFAULT 0,
  `buyer_telegram_id` BIGINT NOT NULL,
  `target_username` VARCHAR(64) NOT NULL DEFAULT '',
  `stars_amount` INT NOT NULL DEFAULT 0,
  `price` INT NOT NULL DEFAULT 0,
  `status` ENUM('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `api_order_id` VARCHAR(128) NULL,
  `note` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `topup_orders` (
  `order_code` VARCHAR(64) NOT NULL,
  `telegram_id` BIGINT NOT NULL,
  `bot_id` INT NOT NULL DEFAULT 0,
  `amount` INT NOT NULL DEFAULT 0,
  `status` ENUM('pending','completed','expired','cancelled') NOT NULL DEFAULT 'pending',
  `expire_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `gift_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_telegram_id` BIGINT NOT NULL,
  `buyer_bot_id` INT NOT NULL DEFAULT 0,
  `target_username` VARCHAR(64) NOT NULL DEFAULT '',
  `gift_name` VARCHAR(32) NOT NULL DEFAULT '',
  `stars_amount` INT NOT NULL DEFAULT 0,
  `som_price` INT NOT NULL DEFAULT 0,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `error_code` VARCHAR(64) NOT NULL DEFAULT '',
  `error_message` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `premium_orders` (
  `id` VARCHAR(32) NOT NULL,
  `buyer_telegram_id` BIGINT NOT NULL,
  `buyer_bot_id` INT NOT NULL DEFAULT 0,
  `target_username` VARCHAR(64) NOT NULL DEFAULT '',
  `months` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `price` INT NOT NULL DEFAULT 0,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `api_response` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `channels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `link` VARCHAR(255) NOT NULL DEFAULT '',
  `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `gift_stats` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stat_key` VARCHAR(64) NOT NULL,
  `stat_date` DATE NULL,
  `value` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key_date` (`stat_key`, `stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($tables as $sql) {
    $pdo->exec($sql);
}

echo "✅ Barcha jadvallar muvaffaqiyatli yaratildi!";
