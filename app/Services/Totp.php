<?php

/**
 * Time-based one-time passwords (RFC 6238) for administrator two-factor sign-in,
 * plus at-rest encryption of the shared secret and one-time recovery codes.
 *
 * Works with Google Authenticator, Microsoft Authenticator, Authy, 1Password, etc.
 * (SHA-1, 6 digits, 30-second steps).
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const PERIOD = 30;

    /** New random 160-bit secret, base32 (32 characters). */
    public static function newSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function base32Encode(string $binary): string
    {
        $bits = '';
        $length = strlen($binary);
        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    /** @return string|null binary, or null when the text is not valid base32 */
    public static function base32Decode(string $text): ?string
    {
        $text = strtoupper(str_replace([' ', '-', '='], '', $text));
        if ($text === '' || strspn($text, self::ALPHABET) !== strlen($text)) {
            return null;
        }
        $bits = '';
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $text[$i])), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    /** The 6-digit code for a given 30-second step number. */
    public static function codeAt(string $secretBase32, int $step): string
    {
        $key = self::base32Decode($secretBase32);
        if ($key === null) {
            throw new InvalidArgumentException('Invalid authenticator secret.');
        }
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Checks a code, allowing one step of clock drift either way. A code can only be
     * used once: its step must be newer than the last accepted step (replay protection).
     *
     * @return int|null the accepted step (store it as the new "last step"), or null if invalid
     */
    public static function verify(string $secretBase32, string $code, ?int $lastStep, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/D', (string)$code)) {
            return null;
        }
        $current = intdiv($now ?? time(), self::PERIOD);
        for ($drift = -1; $drift <= 1; $drift++) {
            $step = $current + $drift;
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            if (hash_equals(self::codeAt($secretBase32, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    /** otpauth:// link; tapping it on a phone opens the authenticator app. */
    public static function otpauthUri(string $account, string $secretBase32, string $issuer = 'Campus Chronicle'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secretBase32
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=' . self::PERIOD;
    }

    /* ------------------------------------------------------------------ */
    /* Secret encryption at rest (AES-256-GCM, key from CC_APP_KEY)        */
    /* ------------------------------------------------------------------ */

    public static function isConfigured(): bool
    {
        return self::key() !== null;
    }

    private static function key(): ?string
    {
        $raw = base64_decode((string)getenv('CC_APP_KEY'), true);
        return ($raw !== false && strlen($raw) === 32) ? $raw : null;
    }

    public static function encrypt(string $plain): string
    {
        $key = self::key();
        if ($key === null) {
            throw new RuntimeException('CC_APP_KEY is not set to a base64-encoded 32-byte key.');
        }
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Could not encrypt the authenticator secret.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): ?string
    {
        $key = self::key();
        $raw = base64_decode($stored, true);
        if ($key === null || $raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }

    /* ------------------------------------------------------------------ */
    /* Recovery codes                                                      */
    /* ------------------------------------------------------------------ */

    /** @return string[] 8 single-use codes shaped like "a1b2-c3d4" */
    public static function newRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $hex = bin2hex(random_bytes(4));
            $codes[] = substr($hex, 0, 4) . '-' . substr($hex, 4, 4);
        }
        return $codes;
    }

    public static function hashRecoveryCode(string $code): string
    {
        return hash('sha256', strtolower(str_replace(['-', ' '], '', trim($code))));
    }
}
