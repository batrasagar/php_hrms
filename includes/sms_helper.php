<?php
require_once __DIR__ . '/../config/db.php';

/**
 * MSG91 SMS integration. Auth key / sender / route are stored globally in
 * tblSettings (CompanyId = 0), same table the SMTP config uses. The HR-Manager
 * mobile is stored per company on tblPayrollSettings.
 */
function getSmsCfg(): array {
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
            'msg91_authkey'  => '',
            'msg91_sender'   => 'HRMSAP',
            'msg91_route'    => '4',   // 4 = transactional
            'msg91_dlt_tpl'  => '',    // optional DLT template id
        ] as $k => $v) $ins->execute([$k, $v]);
        $rows = $db->query("SELECT SettingKey, SettingValue FROM tblSettings WHERE CompanyId = 0")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
        $cfg = [
            'authkey' => $rows['msg91_authkey'] ?? '',
            'sender'  => $rows['msg91_sender']  ?? 'HRMSAP',
            'route'   => $rows['msg91_route']   ?? '4',
            'dlt_tpl' => $rows['msg91_dlt_tpl'] ?? '',
        ];
    } catch (\Throwable $e) {
        error_log('[hrms] getSmsCfg failed: ' . $e->getMessage());
        $cfg = ['authkey'=>'','sender'=>'HRMSAP','route'=>'4','dlt_tpl'=>''];
    }
    return $cfg;
}

/** Normalise a mobile to MSG91 form: digits, +91 assumed for 10-digit numbers. */
function smsNormalizeMobile(string $m): string {
    $m = preg_replace('/\D+/', '', $m);
    if (strlen($m) === 10) $m = '91' . $m;
    return $m;
}

/**
 * Send an SMS via MSG91 (legacy sendhttp API).
 * @return array ['ok' => bool, 'error' => string]
 */
function sendSms(string $mobile, string $message): array {
    $cfg = getSmsCfg();
    if (empty($cfg['authkey'])) {
        return ['ok' => false, 'error' => 'SMS not configured (missing MSG91 auth key).'];
    }
    $to = smsNormalizeMobile($mobile);
    if (strlen($to) < 10) return ['ok' => false, 'error' => 'Invalid mobile number.'];

    $params = [
        'authkey'  => $cfg['authkey'],
        'mobiles'  => $to,
        'message'  => $message,
        'sender'   => $cfg['sender'] ?: 'HRMSAP',
        'route'    => $cfg['route']  ?: '4',
        'country'  => '91',
    ];
    if (!empty($cfg['dlt_tpl'])) $params['DLT_TE_ID'] = $cfg['dlt_tpl'];

    $url = 'https://api.msg91.com/api/sendhttp.php?' . http_build_query($params);
    if (!function_exists('curl_init')) {
        $resp = @file_get_contents($url);
        return ['ok' => $resp !== false, 'error' => $resp === false ? 'HTTP request failed.' : ''];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false)        return ['ok' => false, 'error' => 'MSG91 request failed: ' . $err];
    if ($code >= 400)           return ['ok' => false, 'error' => "MSG91 HTTP $code: " . substr((string)$resp, 0, 160)];
    // MSG91 returns a request id on success, or a JSON/text error containing "error"
    if (stripos((string)$resp, 'error') !== false) {
        return ['ok' => false, 'error' => 'MSG91: ' . substr((string)$resp, 0, 160)];
    }
    return ['ok' => true, 'error' => ''];
}

/** HR-Manager mobile for a company (from payroll settings), or '' if none. */
function hrManagerMobile(PDO $db, int $companyId): string {
    try {
        $s = $db->prepare("SELECT HRManagerMobile FROM tblPayrollSettings WHERE CompanyId=? LIMIT 1");
        $s->execute([$companyId]);
        return trim((string)($s->fetchColumn() ?: ''));
    } catch (\Throwable $e) { return ''; }
}
