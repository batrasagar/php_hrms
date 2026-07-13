<?php
require_once __DIR__ . '/../config/db.php';

/**
 * WhatsApp Business API integration. Settings live in tblWhatsappSettings — one
 * row per company plus a global default row at CompanyId = 0. A company's own
 * enabled+usable channel wins, otherwise the enabled global default is used —
 * "exactly one active channel". Providers: Meta Cloud API, AiSensy, GupShup.
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
        'gupshup' => !empty($c['GupshupApiKey']) && !empty($c['GupshupSource']),
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

/**
 * Low-level HTTP POST. $body is a JSON string (asForm=false) or an array (asForm=true).
 * @return array ['raw' => ?string response body, 'err' => ?string transport error]
 */
function waHttp(string $url, array $headers, $body, bool $asForm): array {
    $content = $asForm ? http_build_query($body) : $body;
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(['http' => ['method'=>'POST','header'=>implode("\r\n",$headers),'content'=>$content,'timeout'=>30,'ignore_errors'=>true]]);
        $r = @file_get_contents($url, false, $ctx);
        return ['raw' => $r === false ? null : $r, 'err' => $r === false ? 'HTTP request failed' : null];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $content,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $r = curl_exec($ch);
    $e = curl_error($ch);
    curl_close($ch);
    return ['raw' => $r === false ? null : $r, 'err' => $r === false ? $e : null];
}

/**
 * Send an approved template via the channel's provider.
 * @param array $cfg a tblWhatsappSettings row
 */
function waSendTemplate(array $cfg, string $phone, string $template, string $language, array $params = [], array $buttonParams = []): array {
    $to = waNormalizePhone($phone);
    if (strlen($to) < 10) return ['ok'=>false,'message'=>'Enter a valid phone number.','message_id'=>null];
    if ($template === '') return ['ok'=>false,'message'=>'Template name is required.','message_id'=>null];
    return match ($cfg['Provider'] ?? 'meta') {
        'aisensy' => waSendAisensy($cfg, $to, $template, $params),
        'gupshup' => waSendGupshup($cfg, $to, $template, $params),
        default   => waSendMeta($cfg, $to, $template, $language, $params, $buttonParams),
    };
}

/** Meta Cloud API template send. */
function waSendMeta(array $cfg, string $to, string $template, string $language, array $params = [], array $buttonParams = []): array {
    if (empty($cfg['MetaPhoneNumberId'])) return ['ok'=>false,'message'=>'Meta Phone Number ID is not configured.','message_id'=>null];
    if (empty($cfg['MetaAccessToken']))   return ['ok'=>false,'message'=>'Meta access token is not configured.','message_id'=>null];
    $ver = $cfg['MetaApiVersion'] ?: 'v25.0';
    $url = "https://graph.facebook.com/{$ver}/{$cfg['MetaPhoneNumberId']}/messages";
    $txt = fn($p) => ['type'=>'text','text'=>($p===''||$p===null)?'-':(string)$p];
    $components = [];
    if ($params) $components[] = ['type'=>'body','parameters'=>array_map($txt, array_values($params))];
    foreach (array_values($buttonParams) as $i => $bp) {
        $components[] = ['type'=>'button','sub_type'=>'url','index'=>(string)$i,'parameters'=>[['type'=>'text','text'=>(string)$bp]]];
    }
    $payload = [
        'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
        'template' => array_filter([
            'name' => $template, 'language' => ['code' => $language ?: 'en'], 'components' => $components ?: null,
        ]),
    ];
    $res = waHttp($url, ['Content-Type: application/json','Authorization: Bearer '.$cfg['MetaAccessToken']], json_encode($payload), false);
    if ($res['raw'] === null) return ['ok'=>false,'message'=>'HTTP request failed: '.$res['err'],'message_id'=>null];
    $j = json_decode($res['raw'], true) ?: [];
    $mid = $j['messages'][0]['id'] ?? null;
    if ($mid) return ['ok'=>true,'message'=>"Sent via Meta (id: $mid).",'message_id'=>$mid];
    return ['ok'=>false,'message'=>$j['error']['message'] ?? ('Meta API error: '.substr($res['raw'],0,200)),'message_id'=>null];
}

/** AiSensy campaign API template send. */
function waSendAisensy(array $cfg, string $to, string $template, array $params = []): array {
    if (empty($cfg['AisensyApiKey'])) return ['ok'=>false,'message'=>'AiSensy API key is not configured.','message_id'=>null];
    $payload = [
        'apiKey'         => $cfg['AisensyApiKey'],
        'campaignName'   => $template,
        'destination'    => $to,
        'userName'       => $cfg['AisensySourceName'] ?: 'HRMS',
        'templateParams' => array_map('strval', array_values($params)),
    ];
    $res = waHttp('https://backend.aisensy.com/campaign/t1/api/v2', ['Content-Type: application/json'], json_encode($payload), false);
    if ($res['raw'] === null) return ['ok'=>false,'message'=>'HTTP request failed: '.$res['err'],'message_id'=>null];
    $j = json_decode($res['raw'], true) ?: [];
    $ok = (($j['success'] ?? false) === true) || (($j['status'] ?? '') === 'success');
    $mid = $j['messageId'] ?? ($j['data']['messageId'] ?? null);
    if ($ok) return ['ok'=>true,'message'=>'Sent via AiSensy'.($mid?" (id: $mid).":'.'),'message_id'=>$mid];
    return ['ok'=>false,'message'=>'AiSensy: '.($j['message'] ?? substr($res['raw'],0,200)),'message_id'=>null];
}

/** GupShup template API send. */
function waSendGupshup(array $cfg, string $to, string $template, array $params = []): array {
    if (empty($cfg['GupshupApiKey'])) return ['ok'=>false,'message'=>'GupShup API key is not configured.','message_id'=>null];
    if (empty($cfg['GupshupSource'])) return ['ok'=>false,'message'=>'GupShup source (sender number) is not configured.','message_id'=>null];
    $tpl = json_encode(['id' => $template, 'params' => array_map('strval', array_values($params))]);
    $form = [
        'channel'     => 'whatsapp',
        'source'      => $cfg['GupshupSource'],
        'destination' => $to,
        'src.name'    => $cfg['GupshupAppName'] ?? '',
        'template'    => $tpl,
    ];
    $res = waHttp('https://api.gupshup.io/wa/api/v1/template/msg',
        ['apikey: '.$cfg['GupshupApiKey'], 'Content-Type: application/x-www-form-urlencoded'], $form, true);
    if ($res['raw'] === null) return ['ok'=>false,'message'=>'HTTP request failed: '.$res['err'],'message_id'=>null];
    $j = json_decode($res['raw'], true) ?: [];
    $ok = in_array($j['status'] ?? '', ['submitted','success'], true);
    $mid = $j['messageId'] ?? null;
    if ($ok) return ['ok'=>true,'message'=>'Sent via GupShup'.($mid?" (id: $mid).":'.'),'message_id'=>$mid];
    return ['ok'=>false,'message'=>'GupShup: '.($j['message'] ?? substr($res['raw'],0,200)),'message_id'=>null];
}

/** Send a free-form text (Meta only; allowed inside the 24h customer-service window). */
function waSendText(array $cfg, string $phone, string $body): array {
    if (($cfg['Provider'] ?? 'meta') !== 'meta') return ['ok'=>false,'message'=>'Free-form text is supported on the Meta channel only.','message_id'=>null];
    if (empty($cfg['MetaPhoneNumberId']) || empty($cfg['MetaAccessToken'])) return ['ok'=>false,'message'=>'Meta channel is not fully configured.','message_id'=>null];
    $to = waNormalizePhone($phone);
    if (strlen($to) < 10) return ['ok'=>false,'message'=>'Invalid recipient number.','message_id'=>null];
    $ver = $cfg['MetaApiVersion'] ?: 'v25.0';
    $url = "https://graph.facebook.com/{$ver}/{$cfg['MetaPhoneNumberId']}/messages";
    $payload = ['messaging_product'=>'whatsapp','recipient_type'=>'individual','to'=>$to,'type'=>'text','text'=>['preview_url'=>false,'body'=>$body]];
    $res = waHttp($url, ['Content-Type: application/json','Authorization: Bearer '.$cfg['MetaAccessToken']], json_encode($payload), false);
    if ($res['raw'] === null) return ['ok'=>false,'message'=>'HTTP request failed: '.$res['err'],'message_id'=>null];
    $j = json_decode($res['raw'], true) ?: [];
    $mid = $j['messages'][0]['id'] ?? null;
    if ($mid) return ['ok'=>true,'message'=>'Reply sent.','message_id'=>$mid];
    return ['ok'=>false,'message'=>$j['error']['message'] ?? 'Meta API error','message_id'=>null];
}
