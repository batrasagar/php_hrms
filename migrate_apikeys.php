<?php
/**
 * One-shot migration: creates tblApiKeys and optionally seeds a first key.
 * DELETE THIS FILE after running.
 */
require_once __DIR__ . '/config/db.php';

$db = getDb();

$db->exec("CREATE TABLE IF NOT EXISTS `tblApiKeys` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `Label`     VARCHAR(100) NOT NULL,
    `Company`   VARCHAR(150) NOT NULL,
    `KeyHash`   CHAR(64)     NOT NULL UNIQUE COMMENT 'SHA-256 hex of the raw key',
    `IsActive`  TINYINT(1)   NOT NULL DEFAULT 1,
    `CreatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Add columns if migrating an existing table
foreach ([
    "ALTER TABLE `tblApiKeys` ADD COLUMN `Company` VARCHAR(150) NOT NULL DEFAULT '' AFTER `Label`",
    "ALTER TABLE `tblApiKeys` ADD COLUMN `RawKey`  CHAR(64)     NOT NULL DEFAULT '' AFTER `Company`",
] as $alter) {
    try {
        $db->exec($alter);
        preg_match('/ADD COLUMN `(\w+)`/', $alter, $m);
        echo "<p>Added {$m[1]} column to tblApiKeys.</p>";
    } catch (\PDOException $e) {
        if (!str_contains($e->getMessage(), 'Duplicate column')) throw $e;
    }
}

echo "<p>tblApiKeys table ready.</p>";

// Seed a first key if none exist
$count = (int) $db->query("SELECT COUNT(*) FROM `tblApiKeys`")->fetchColumn();
if ($count === 0) {
    $rawKey  = bin2hex(random_bytes(32));   // 64-char hex key
    $keyHash = hash('sha256', $rawKey);

    $company = 'Demo Company';   // ← change before running
    $db->prepare("INSERT INTO `tblApiKeys` (`Label`, `Company`, `KeyHash`) VALUES (?, ?, ?)")
       ->execute(['Default key', $company, $keyHash]);

    echo "<p><strong>Your first API key for company '{$company}' (save it — shown only once):</strong></p>";
    echo "<pre>{$rawKey}</pre>";
    echo "<p>Pass it as the <code>X-Api-Key</code> header on every request.</p>";
} else {
    echo "<p>Keys already exist — no new key generated.</p>";
}

echo "<p style='color:red'><strong>Delete migrate_apikeys.php now.</strong></p>";
