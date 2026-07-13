<?php
require_once __DIR__ . '/../config/db.php';

/**
 * MSG91 SMS integration (v5 Flow API). Auth key / sender / DLT-approved template
 * are stored globally in tblSettings (CompanyId = 0), same table the SMTP config
 * uses. The HR-Manager mobile is stored per company on tblPayrollSettings.
 *
 * Flow is template-based: register a DLT template that contains one variable
 * (default named "var", e.g. "HRMS: ##var##"); the dynamic message text is sent
 * into that variable. Set the template id + variable name under SMS Settings.
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
            'msg91_authkey'     => '',
            'msg91_sender'      => 'HRMSAP',
            'msg91_template_id' => '',      // MSG91 Flow template id (DLT approved)
            'msg91_var'         => 'var',   // variable name inside the template
        ] as $k => $v) $ins->execute([$k, $v]);
        $rows = $db->query("SELECT SettingKey, SettingValue FROM tblSettings WHERE CompanyId = 0")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
        $cfg = [
            'authkey'     => $rows['msg91_authkey']     ?? '',
            'sender'      => $rows['msg91_sender']      ?? 'HRMSAP',
            'template_id' => $rows['msg91_template_id'] ?? '',
            'var'         => $rows['msg91_var']         ?: 'var',
        ];
    } catch (\Throwable $e) {
        error_log('[hrms] getSmsCfg failed: ' . $e->getMessage());
        $cfg = ['authkey'=>'','sender'=>'HRMSAP','template_id'=>'','var'=>'var'];
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
 * Send an SMS via the MSG91 v5 Flow API (template based).
 *
 * @param string $mobile  Recipient mobile (10-digit assumed India, or with country code).
 * @param string $message Dynamic text placed into the template variable (used when $vars is empty).
 * @param array  $vars    Optional explicit template variables ['VarName' => 'value', ...];
 *                        overrides the single-variable mapping of $message.
 * @return array ['ok' => bool, 'error' => string]
 */
function sendSms(string $mobile, string $message, array $vars = []): array {
    $cfg = getSmsCfg();
    if (empty($cfg['authkey']))     return ['ok' => false, 'error' => 'SMS not configured (missing MSG91 auth key).'];
    if (empty($cfg['template_id'])) return ['ok' => false, 'error' => 'SMS not configured (missing MSG91 Flow template id).'];
    $to = smsNormalizeMobile($mobile);
    if (strlen($to) < 10) return ['ok' => false, 'error' => 'Invalid mobile number.'];

    // Build the recipient: explicit vars, or the message mapped to the configured variable.
    $recipient = ['mobiles' => $to];
    if ($vars) {
        foreach ($vars as $k => $v) $recipient[$k] = (string)$v;
    } else {
        $recipient[$cfg['var'] ?: 'var'] = $message;
    }
    $payload = ['template_id' => $cfg['template_id'], 'recipients' => [$recipient]];
    if (!empty($cfg['sender'])) $payload['sender'] = $cfg['sender'];
    $body = json_encode($payload);

    $url     = 'https://control.msg91.com/api/v5/flow/';
    $headers = ['Content-Type: application/json', 'Accept: application/json', 'authkey: ' . $cfg['authkey']];

    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $body,
            'timeout'       => 15,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        return smsInterpretResponse($resp === false ? null : $resp, null);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) return ['ok' => false, 'error' => 'MSG91 request failed: ' . $err];
    return smsInterpretResponse($resp, $code);
}

/** Interpret an MSG91 v5 Flow JSON response into ['ok','error']. */
function smsInterpretResponse(?string $resp, ?int $httpCode): array {
    if ($resp === null)                 return ['ok' => false, 'error' => 'MSG91 HTTP request failed.'];
    if ($httpCode !== null && $httpCode >= 400) {
        return ['ok' => false, 'error' => "MSG91 HTTP $httpCode: " . substr($resp, 0, 200)];
    }
    $json = json_decode($resp, true);
    if (is_array($json)) {
        $type = strtolower((string)($json['type'] ?? ''));
        if ($type === 'success') return ['ok' => true, 'error' => ''];
        if ($type === 'error' || isset($json['errors'])) {
            $m = $json['message'] ?? ($json['errors'] ?? 'rejected');
            return ['ok' => false, 'error' => 'MSG91: ' . (is_string($m) ? $m : json_encode($m))];
        }
        // Some success responses only return a request id in "message".
        if (!empty($json['message'])) return ['ok' => true, 'error' => ''];
    }
    // Non-JSON body — treat text containing "error" as failure.
    if (stripos($resp, 'error') !== false) return ['ok' => false, 'error' => 'MSG91: ' . substr($resp, 0, 200)];
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
