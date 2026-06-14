<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/mailer.php';

function getSmtpCfg(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    try {
        $db = getDb();
        $db->exec("CREATE TABLE IF NOT EXISTS tblSettings (
            id           INT PRIMARY KEY AUTO_INCREMENT,
            CompanyId    INT          NOT NULL DEFAULT 0,
            SettingKey   VARCHAR(100) NOT NULL,
            SettingValue VARCHAR(500) NOT NULL DEFAULT '',
            UpdatedAt    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_cs (CompanyId, SettingKey)
        )");
        $ins = $db->prepare("INSERT IGNORE INTO tblSettings (CompanyId, SettingKey, SettingValue) VALUES (0, ?, ?)");
        foreach ([
            'smtp_host'      => 'smtp.hostinger.com',
            'smtp_port'      => '587',
            'smtp_user'      => 'support@attnlog.in',
            'smtp_pass'      => '1707Sp@!',
            'smtp_from'      => 'support@attnlog.in',
            'smtp_from_name' => 'AttnLog HRMS',
            'app_url'        => 'https://hr.attnlog.in',
        ] as $k => $v) {
            $ins->execute([$k, $v]);
        }
        $rows = $db->query("SELECT SettingKey, SettingValue FROM tblSettings WHERE CompanyId = 0")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
        $cfg = [
            'host'       => $rows['smtp_host']      ?? '',
            'port'       => (int)($rows['smtp_port'] ?? 587),
            'encryption' => 'tls',
            'user'       => $rows['smtp_user']      ?? '',
            'pass'       => $rows['smtp_pass']      ?? '',
            'from_email' => $rows['smtp_from']      ?? '',
            'from_name'  => $rows['smtp_from_name'] ?? 'AttnLog HRMS',
            '_app_url'   => $rows['app_url']        ?? '',
        ];
    } catch (\Throwable $e) {
        error_log('[hrms] getSmtpCfg failed: ' . $e->getMessage());
        $cfg = ['host'=>'','port'=>587,'encryption'=>'tls','user'=>'','pass'=>'','from_email'=>'','from_name'=>'AttnLog HRMS','_app_url'=>''];
    }
    return $cfg;
}

function sendSystemMail(string $to, string $subject, string $html): bool {
    $cfg      = getSmtpCfg();
    $fromName = $cfg['from_name'] ?: 'AttnLog HRMS';
    $wrapped  = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f0f4ff;padding:24px;">'
              . '<div style="max-width:480px;margin:auto;background:#fff;border-radius:12px;padding:32px;border:1px solid #e3e6ef;">'
              . '<div style="text-align:center;font-size:1.5rem;font-weight:700;color:#1e2a3a;margin-bottom:20px;">&#128176; HRMS</div>'
              . $html
              . '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">'
              . '<p style="color:#bbb;font-size:11px;text-align:center;margin:0;">' . htmlspecialchars($fromName) . ' &mdash; Human Resource Management</p>'
              . '</div></body></html>';
    $result = SimpleMailer::send($cfg, $to, $subject, $wrapped);
    if (!$result['ok']) {
        error_log('[hrms] sendSystemMail to ' . $to . ' failed: ' . $result['error']);
        return false;
    }
    return true;
}
