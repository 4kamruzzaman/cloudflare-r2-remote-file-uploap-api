<?php
/**
 * setup_db.php — Creates the required database table
 * Run this once after fresh installation
 */

require_once __DIR__ . '/db.php';

$pdo = db();

$sql = "
CREATE TABLE IF NOT EXISTS `uploads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `object_key` VARCHAR(500) NOT NULL COMMENT 'Filename/key in R2 bucket',
    `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `file_url` VARCHAR(1000) DEFAULT NULL COMMENT 'Public URL after successful upload',
    `message` VARCHAR(500) DEFAULT NULL COMMENT 'Status message or error description',
    `retries` INT UNSIGNED NOT NULL DEFAULT 0,
    `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `original_url` VARCHAR(2000) DEFAULT NULL COMMENT 'Source URL for remote uploads',
    `download_time_sec` INT UNSIGNED NOT NULL DEFAULT 0,
    `upload_time_sec` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_object_key` (`object_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "✅ Table 'uploads' created successfully!\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
