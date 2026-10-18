<?php

/**
 * Minimal Google ID-token verifier for this standalone XAMPP project.
 *
 * It validates an RS256 JWT using Google's published PEM certificates, then
 * checks issuer, audience, expiry, issued-at time, subject and verified email.
 * Certificates are cached briefly on disk to avoid a network call per login.
 */
final class GoogleIdentityService
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';
    private const CLOCK_SKEW = 300;
    private const DEFAULT_CACHE_SECONDS = 3600;

    public static function verifyIdToken(string $idToken, string $clientId, string $expectedNonce = ''): array
    {
        $idToken = trim($idToken);
        $clientId = trim($clientId);

        if ($idToken === '' || $clientId === '') {
            throw new RuntimeException('Google sign-in is not configured correctly.');
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid Google credential.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = self::decodeJsonPart($encodedHeader);
        $payload = self::decodeJsonPart($encodedPayload);
        $signature = self::base64UrlDecode($encodedSignature);

        if (($header['alg'] ?? '') !== 'RS256') {
            throw new RuntimeException('Unsupported Google credential signature.');
        }

        $kid = trim((string) ($header['kid'] ?? ''));
        if ($kid === '') {
            throw new RuntimeException('Google credential is missing its signing key.');
        }

        $certificates = self::getGoogleCertificates();
        if (!isset($certificates[$kid]) || !is_string($certificates[$kid])) {
            // The signing keys may have rotated since the local cache was saved.
            $certificates = self::getGoogleCertificates(true);
        }

        $certificate = $certificates[$kid] ?? null;
        if (!is_string($certificate) || trim($certificate) === '') {
            throw new RuntimeException('Google signing key could not be found. Please try again.');
        }

        $verified = openssl_verify(
            $encodedHeader . '.' . $encodedPayload,
            $signature,
            $certificate,
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            throw new RuntimeException('Google credential signature verification failed.');
        }

        $now = time();
        $issuer = (string) ($payload['iss'] ?? '');
        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new RuntimeException('Google credential issuer is invalid.');
        }

        $audience = $payload['aud'] ?? null;
        $audienceMatches = is_array($audience)
            ? in_array($clientId, $audience, true)
            : hash_equals($clientId, (string) $audience);

        if (!$audienceMatches) {
            throw new RuntimeException('Google credential was issued for a different application.');
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);
        if ($expiresAt <= ($now - self::CLOCK_SKEW)) {
            throw new RuntimeException('Google credential has expired. Please sign in again.');
        }

        $issuedAt = (int) ($payload['iat'] ?? 0);
        if ($issuedAt <= 0 || $issuedAt > ($now + self::CLOCK_SKEW)) {
            throw new RuntimeException('Google credential issue time is invalid.');
        }

        $subject = trim((string) ($payload['sub'] ?? ''));
        if ($subject === '') {
            throw new RuntimeException('Google account identifier is missing.');
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Google did not provide a valid email address.');
        }

        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$emailVerified) {
            throw new RuntimeException('Your Google email address must be verified.');
        }

        if ($expectedNonce !== '' && !hash_equals($expectedNonce, (string)($payload['nonce'] ?? ''))) throw new RuntimeException('Google nonce mismatch.');
        if (isset($payload['azp']) && !hash_equals($clientId,(string)$payload['azp'])) throw new RuntimeException('Google authorized party mismatch.');
        return $payload;
    }

    public static function isGoogleAuthoritativeForEmail(array $payload): bool
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hostedDomain = trim((string) ($payload['hd'] ?? ''));

        if (!$emailVerified) {
            return false;
        }

        return str_ends_with($email, '@gmail.com') || $hostedDomain !== '';
    }

    private static function decodeJsonPart(string $encoded): array
    {
        $decoded = self::base64UrlDecode($encoded);
        $data = json_decode($decoded, true);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid Google credential payload.');
        }

        return $data;
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = (4 - (strlen($value) % 4)) % 4;
        $value .= str_repeat('=', $padding);
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Invalid Google credential encoding.');
        }

        return $decoded;
    }

    private static function getGoogleCertificates(bool $forceRefresh = false): array
    {
        $cacheFile = (getenv('CC_STORAGE_PATH') ?: dirname(__DIR__, 2) . '/storage') . '/cache/google_oauth_certs.json';

        if (!$forceRefresh && is_file($cacheFile)) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (
                is_array($cached)
                && (int) ($cached['expires_at'] ?? 0) > time()
                && is_array($cached['certs'] ?? null)
            ) {
                return $cached['certs'];
            }
        }

        [$body, $headers] = self::httpGet(self::CERTS_URL);
        $certificates = json_decode($body, true);

        if (!is_array($certificates) || $certificates === []) {
            throw new RuntimeException('Could not load Google signing keys. Please try again.');
        }

        foreach ($certificates as $kid => $certificate) {
            if (!is_string($kid) || !is_string($certificate) || !str_contains($certificate, 'BEGIN CERTIFICATE')) {
                unset($certificates[$kid]);
            }
        }

        if ($certificates === []) {
            throw new RuntimeException('Google returned unusable signing keys.');
        }

        $maxAge = self::extractMaxAge($headers) ?? self::DEFAULT_CACHE_SECONDS;
        $maxAge = max(300, min($maxAge, 86400));
        $cache = json_encode([
            'expires_at' => time() + $maxAge,
            'certs' => $certificates,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($cache !== false) {
            $directory = dirname($cacheFile);
            if (!is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }
            @file_put_contents($cacheFile, $cache, LOCK_EX);
        }

        return $certificates;
    }

    private static function httpGet(string $url): array
    {
        if (function_exists('curl_init')) {
            $responseHeaders = [];
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_USERAGENT => 'CampusChronicle-GoogleAuth/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                    $responseHeaders[] = trim($line);
                    return strlen($line);
                },
            ]);

            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($body === false || $status < 200 || $status >= 300) {
                throw new RuntimeException('Unable to contact Google for sign-in verification.' . ($error ? ' ' . $error : ''));
            }

            return [(string) $body, $responseHeaders];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => "User-Agent: CampusChronicle-GoogleAuth/1.0\r\nAccept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $statusLine = $headers[0] ?? '';

        if ($body === false || !preg_match('/\s2\d\d\s/', $statusLine)) {
            throw new RuntimeException('Unable to contact Google for sign-in verification.');
        }

        return [(string) $body, $headers];
    }

    private static function extractMaxAge(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Cache-Control:') !== 0) {
                continue;
            }
            if (preg_match('/max-age=(\d+)/i', $header, $match)) {
                return (int) $match[1];
            }
        }

        return null;
    }
}
