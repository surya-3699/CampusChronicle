<?php

/**
 * Minimal transactional-email sender using the Resend HTTPS API (no SMTP: many hosts block SMTP ports).
 *
 * Environment:
 *   RESEND_API_KEY      API key from resend.com (keep it only in the host's environment settings)
 *   MAIL_FROM           e.g.  Campus Chronicle <no-reply@yourdomain.edu>   (the domain must be verified in Resend)
 *   CONTACT_NOTIFY_TO   comma-separated addresses that receive contact-form notifications
 *
 * When any of these is missing nothing is sent and nothing breaks: messages still appear in Admin > Messages.
 */
final class Mailer
{
    public static function configured(): bool
    {
        return getenv('RESEND_API_KEY') && getenv('MAIL_FROM') && getenv('CONTACT_NOTIFY_TO');
    }

    /** @param string[] $to */
    public static function send(array $to, string $subject, string $text, ?string $replyTo = null): bool
    {
        $key = (string)getenv('RESEND_API_KEY');
        $from = (string)getenv('MAIL_FROM');
        if ($key === '' || $from === '' || !$to || !function_exists('curl_init')) {
            return false;
        }

        $payload = [
            'from'    => preg_replace('/[\r\n]+/', ' ', $from),
            'to'      => array_values($to),
            'subject' => mb_substr(preg_replace('/[\r\n]+/', ' ', $subject), 0, 200),
            'text'    => $text,
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $payload['reply_to'] = $replyTo;
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            error_log('Mailer: Resend returned HTTP ' . $status . ($error !== '' ? ' (' . $error . ')' : '') . ' ' . substr((string)$response, 0, 200));
            return false;
        }
        return true;
    }

    /** Emails the site team about a new contact-form message. */
    public static function notifyContact(string $name, string $email, string $phone, string $subject, string $message): bool
    {
        if (!self::configured()) {
            return false;
        }
        $recipients = array_values(array_filter(array_map('trim', explode(',', (string)getenv('CONTACT_NOTIFY_TO'))), function ($a) {
            return filter_var($a, FILTER_VALIDATE_EMAIL);
        }));
        if (!$recipients) {
            return false;
        }
        $base = rtrim((string)getenv('APP_URL'), '/');
        $body = "A new message arrived through the Campus Chronicle contact form.\n\n"
            . "Name:    {$name}\nEmail:   {$email}\nPhone:   " . ($phone !== '' ? $phone : '-') . "\nSubject: {$subject}\n\n{$message}\n\n"
            . ($base !== '' ? "Open in the admin panel: {$base}/index.php?page=admin-contacts\n" : '')
            . "Reply to this email to answer the sender directly.\n";
        return self::send($recipients, '[Campus Chronicle] New message: ' . $subject, $body, $email);
    }
}
