<?php
require_once __DIR__ . '/../config/db.php';

/**
 * WhatsApp Business API integration (Meta Cloud API, with AiSensy / GupShup config
 * slots). Settings live in tblWhatsappSettings — one row per company plus a global
 * default row at CompanyId = 0. A company's own enabled+usable channel wins,
 * otherwise the global default (if enabled) is used — "exactly one active channel".
 */

function waGetSettings(PDO $db, int $companyId): ?array {
    try {
        $s = $db->prepare("SELECT * FROM tblWhatsappSettings WHERE CompanyId=?");
        $s->execute([$companyId]);
        return $s->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

/** Whether a channel row has the credentials it needs to send. */
function waIsUsable(array $c): bool {
    return match ($c['Provider'] ?? '') {
        'meta'    => !empty($c['MetaPhoneNumberId']) && !empty($c['MetaAccessToken']),
        'aisensy' => !empty($c['AisensyApiKey']),
        'gupshup' => !empty($c['GupshupApiKey']),
        default   => false,
    };
}

/** The active channel for a company: its own enabled+usable row, else the enabled global default. */
function waActiveFor(PDO $db, int $companyId): ?array {
    if ($companyId) {
        $own = waGetSettings($db, $companyId);
        if ($own && (int)$own['Enabled'] === 1 && waIsUsable($own)) return $own;
    }
    $def = waGetSettings($db, 0);
    return ($def && (int)$def['Enabled'] === 1 && waIsUsable($def)) ? $def : null;
}

/** Normalise a phone to WhatsApp form: digits, +91 assumed for 10-digit numbers. */
function waNormalizePhone(string $p): string {
    $p = preg_replace('/\D+/', '', $p);
    if (strlen($p) === 10) $p = '91' . $p;
    return $p;
}

/** POST JSON to the Meta Graph API. Returns ['ok','message','message_id']. */
function waMetaPost(string $url, string $token, array $payload): array {
    $body = json_encode($payload);
    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $token];
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(['http' => ['method'=>'POST','header'=>implode("\r\n",$headers),'content'=>$body,'timeout'=>30,'ignore_errors'=>true]]);
        $resp = @file_get_contents($url, false, $ctx);
        return waMetaInterpret($resp === false ? null : $resp);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false) return ['ok'=>false,'message'=>'HTTP request failed: '.$err,'message_id'=>null];
    return waMetaInterpret($resp);
}
function waMetaInterpret(?string $resp): array {
    if ($resp === null) return ['ok'=>false,'message'=>'No response from Meta.','message_id'=>null];
    $j = json_decode($resp, true);
    $mid = $j['messages'][0]['id'] ?? null;
    if ($mid) return ['ok'=>true,'message'=>"Sent (id: $mid).",'message_id'=>$mid];
    $err = $j['error']['message'] ?? ('Meta API error: ' . substr($resp, 0, 200));
    return ['ok'=>false,'message'=>$err,'message_id'=>null];
}

/**
 * Send an approved template via the given channel config.
 * @param array $cfg  a tblWhatsappSettings row
 */
function waSendTemplate(array $cfg, string $phone, string $template, string $language, array $params = [], array $buttonParams = []): array {
    if (($cfg['Provider'] ?? 'meta') !== 'meta') {
        return ['ok'=>false,'message'=>'Test sending is currently implemented for the Meta (WABA) provider only.'];
    }
    if (empty($cfg['MetaPhoneNumberId'])) return ['ok'=>false,'message'=>'Meta Phone Number ID is not configured.'];
    if (empty($cfg['MetaAccessToken']))   return ['ok'=>false,'message'=>'Meta access token is not configured.'];
    $to = waNormalizePhone($phone);
    if (strlen($to) < 10) return ['ok'=>false,'message'=>'Enter a valid phone number.'];

    $ver = $cfg['MetaApiVersion'] ?: 'v25.0';
    $url = "https://graph.facebook.com/{$ver}/{$cfg['MetaPhoneNumberId']}/messages";
    $txt = fn($p) => ['type'=>'text','text'=>($p===''||$p===null)?'-':(string)$p];

    $components = [];
    if ($params) $components[] = ['type'=>'body','parameters'=>array_map($txt, array_values($params))];
    foreach (array_values($buttonParams) as $i => $bp) {
        $components[] = ['type'=>'button','sub_type'=>'url','index'=>(string)$i,'parameters'=>[['type'=>'text','text'=>(string)$bp]]];
    }
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'template',
        'template'          => array_filter([
            'name'       => $template,
            'language'   => ['code' => $language ?: 'en'],
            'components' => $components ?: null,
        ]),
    ];
    return waMetaPost($url, $cfg['MetaAccessToken'], $payload);
}

/** Send a free-form text (Meta only; allowed inside the 24h customer-service window). */
function waSendText(array $cfg, string $phone, string $body): array {
    if (($cfg['Provider'] ?? 'meta') !== 'meta') return ['ok'=>false,'message'=>'Free-form text is supported on the Meta channel only.'];
    if (empty($cfg['MetaPhoneNumberId']) || empty($cfg['MetaAccessToken'])) return ['ok'=>false,'message'=>'Meta channel is not fully configured.'];
    $to = waNormalizePhone($phone);
    if (strlen($to) < 10) return ['ok'=>false,'message'=>'Invalid recipient number.'];
    $ver = $cfg['MetaApiVersion'] ?: 'v25.0';
    $url = "https://graph.facebook.com/{$ver}/{$cfg['MetaPhoneNumberId']}/messages";
    $payload = ['messaging_product'=>'whatsapp','recipient_type'=>'individual','to'=>$to,'type'=>'text','text'=>['preview_url'=>false,'body'=>$body]];
    return waMetaPost($url, $cfg['MetaAccessToken'], $payload);
}
