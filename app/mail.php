<?php
declare(strict_types=1);

/**
 * Send a plain-text email. Uses Hostinger SMTP when set up in Settings, otherwise PHP mail().
 * Returns null on success, or an error message.
 */
function send_mail_raw(string $to, string $subject, string $body): ?string
{
    $from = setting('mail_from') ?: setting('contact_email');
    if ($from === '') {
        return 'No "send from" email address in Settings → Email.';
    }
    $fromName = setting('mail_from_name') ?: setting('site_name', 'D.R. Made It');
    $headers = [
        'Date' => date('r'),
        'From' => mb_encode_mimeheader($fromName, 'UTF-8') . " <$from>",
        'To' => $to,
        'Subject' => mb_encode_mimeheader($subject, 'UTF-8'),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'quoted-printable',
    ];
    $body = quoted_printable_encode(str_replace(["\r\n", "\r"], "\n", $body));

    if (setting('smtp_host') === '') {
        $h = $headers;
        unset($h['To'], $h['Subject']);
        $lines = implode("\r\n", array_map(fn ($k, $v) => "$k: $v", array_keys($h), $h));
        return @mail($to, $headers['Subject'], $body, $lines, '-f' . $from) ? null : 'PHP mail() failed. Set up SMTP in Settings → Email.';
    }
    try {
        smtp_send($from, $to, $headers, $body);
        return null;
    } catch (RuntimeException $ex) {
        error_log('SMTP: ' . $ex->getMessage());
        return $ex->getMessage();
    }
}

/** Minimal SMTP client: SSL on 465 or STARTTLS on 587, AUTH LOGIN. */
function smtp_send(string $from, string $to, array $headers, string $body): void
{
    $host = setting('smtp_host');
    $port = (int) (setting('smtp_port') ?: '465');
    $ssl = $port === 465 || str_starts_with($host, 'ssl://'); // 465 = SSL, 587 = STARTTLS
    $host = preg_replace('#^(ssl|tls)://#', '', $host);
    $sock = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15);
    if (!$sock) {
        throw new RuntimeException("Can't connect to $host:$port ($errstr)");
    }
    stream_set_timeout($sock, 15);
    $read = function () use ($sock): string {
        $data = '';
        while (($line = fgets($sock, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (?string $line, int $expect) use ($sock, $read): string {
        if ($line !== null) {
            fwrite($sock, $line . "\r\n");
        }
        $resp = $read();
        if ((int) substr($resp, 0, 3) !== $expect) {
            throw new RuntimeException('Mail server said: ' . trim($resp ?: 'nothing'));
        }
        return $resp;
    };
    $me = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $cmd(null, 220);
    $cmd("EHLO $me", 250);
    if (!$ssl) {
        $cmd('STARTTLS', 220);
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS failed');
        }
        $cmd("EHLO $me", 250);
    }
    $cmd('AUTH LOGIN', 334);
    $cmd(base64_encode(setting('smtp_user') ?: $from), 334);
    $cmd(base64_encode(setting('smtp_pass')), 235);
    $cmd("MAIL FROM:<$from>", 250);
    $cmd("RCPT TO:<$to>", 250);
    $cmd('DATA', 354);
    $msg = implode("\r\n", array_map(fn ($k, $v) => "$k: $v", array_keys($headers), $headers)) . "\r\n\r\n"
        . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body)));
    $cmd($msg . "\r\n.", 250);
    fwrite($sock, "QUIT\r\n");
    fclose($sock);
}

/**
 * Send an email and remember how it went, so the admin can see a failure right away
 * (dashboard + Settings → Email). Returns null on success, or the error.
 */
function send_mail(string $to, string $subject, string $body): ?string
{
    $err = send_mail_raw($to, $subject, $body);
    if ($err === null) {
        if (setting('mail_error') !== '') {
            set_setting('mail_error', '');
        }
    } else {
        set_setting('mail_error', date('M j, g:ia') . ' — ' . $err);
    }
    return $err;
}
