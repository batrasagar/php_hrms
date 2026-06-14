<?php
class SimpleMailer
{
    /**
     * Send an HTML email.
     * @param array  $cfg  Keys: host, port, encryption (tls|ssl|none), user, pass, from_email, from_name
     * @param string $to
     * @param string $subject
     * @param string $html
     * @return array ['ok' => bool, 'error' => string]
     */
    public static function send(array $cfg, string $to, string $subject, string $html): array
    {
        if (empty($cfg['host'])) {
            return self::sendBuiltin($cfg, $to, $subject, $html);
        }
        try {
            return self::sendSmtp($cfg, $to, $subject, $html);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private static function sendBuiltin(array $cfg, string $to, string $subject, string $html): array
    {
        $from = $cfg['from_email'] ?? '';
        $name = $cfg['from_name']  ?? '';
        $h  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $h .= 'From: ' . ($name !== '' ? "{$name} <{$from}>" : $from) . "\r\n";
        $ok = @mail($to, $subject, $html, $h);
        return ['ok' => $ok, 'error' => $ok ? '' : 'PHP mail() returned false — check server sendmail config or configure SMTP.'];
    }

    private static function sendSmtp(array $cfg, string $to, string $subject, string $html): array
    {
        $host = $cfg['host'];
        $port = (int)($cfg['port'] ?? 587);
        $enc  = strtolower($cfg['encryption'] ?? 'tls');
        $user = $cfg['user']       ?? '';
        $pass = $cfg['pass']       ?? '';
        $from = $cfg['from_email'] ?? $user;
        $name = $cfg['from_name']  ?? '';

        $addr = ($enc === 'ssl') ? "ssl://{$host}" : $host;
        $ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $sock = @stream_socket_client("{$addr}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            return ['ok' => false, 'error' => "Cannot connect to {$host}:{$port} — {$errstr}"];
        }
        stream_set_timeout($sock, 15);

        $rx = static function () use ($sock): string {
            $buf = '';
            while (!feof($sock)) {
                $line = fgets($sock, 1024);
                if ($line === false) break;
                $buf .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') break;
            }
            return $buf;
        };
        $tx = static function (string $s) use ($sock): void {
            fputs($sock, $s . "\r\n");
        };

        $rx(); // 220 banner
        $domain = gethostname() ?: 'mail.local';
        $tx("EHLO {$domain}"); $rx();

        if ($enc === 'tls') {
            $tx('STARTTLS'); $rx();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $tx("EHLO {$domain}"); $rx();
        }

        if ($user !== '' && $pass !== '') {
            $tx('AUTH LOGIN'); $rx();
            $tx(base64_encode($user)); $rx();
            $tx(base64_encode($pass));
            $r = $rx();
            if (substr(trim($r), 0, 3) !== '235') {
                fclose($sock);
                return ['ok' => false, 'error' => 'SMTP authentication failed: ' . trim($r)];
            }
        }

        $tx("MAIL FROM:<{$from}>"); $rx();
        $tx("RCPT TO:<{$to}>"); $rx();
        $tx('DATA'); $rx();

        $fromHdr = $name !== '' ? "{$name} <{$from}>" : $from;
        $subj64  = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $body    = "From: {$fromHdr}\r\n";
        $body   .= "To: {$to}\r\n";
        $body   .= "Subject: {$subj64}\r\n";
        $body   .= "MIME-Version: 1.0\r\n";
        $body   .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body   .= "\r\n";
        $body   .= $html . "\r\n.";
        $tx($body);
        $r = $rx();
        $tx('QUIT'); @$rx();
        fclose($sock);

        if (substr(trim($r), 0, 3) !== '250') {
            return ['ok' => false, 'error' => 'Message rejected by server: ' . trim($r)];
        }
        return ['ok' => true, 'error' => ''];
    }
}
