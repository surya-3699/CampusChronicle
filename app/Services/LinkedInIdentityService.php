<?php

/**
 * LinkedIn OpenID Connect helper for the standalone Campus Chronicle project.
 *
 * Flow:
 * 1. Build the authorization URL with openid/profile/email scopes.
 * 2. Exchange the short-lived authorization code on the server.
 * 3. Call LinkedIn's OIDC userinfo endpoint with the access token.
 *
 * The OAuth client secret never leaves the PHP server.
 */
final class LinkedInIdentityService
{
    private const AUTHORIZATION_ENDPOINT = 'https://www.linkedin.com/oauth/v2/authorization';
    private const TOKEN_ENDPOINT = 'https://www.linkedin.com/oauth/v2/accessToken';
    private const USERINFO_ENDPOINT = 'https://api.linkedin.com/v2/userinfo';

    public static function authorizationUrl(string $clientId, string $redirectUri, string $state): string
    {
        if ($clientId === '' || $redirectUri === '' || $state === '') {
            throw new RuntimeException('LinkedIn sign-in is not configured correctly.');
        }

        return self::AUTHORIZATION_ENDPOINT . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'openid profile email',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function exchangeCodeForProfile(
        string $code,
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ): array {
        $code = trim($code);
        if ($code === '') {
            throw new RuntimeException('LinkedIn did not return an authorization code.');
        }

        $token = self::postForm(self::TOKEN_ENDPOINT, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $accessToken = trim((string) ($token['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException('LinkedIn did not return an access token.');
        }

        $profile = self::getJson(self::USERINFO_ENDPOINT, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        return self::validateProfile($profile);
    }

    /**
     * Kept public so the profile-validation rules can be regression-tested
     * without making live LinkedIn network calls.
     */
    public static function validateProfile(array $profile): array
    {
        $sub = trim((string) ($profile['sub'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $name = trim((string) ($profile['name'] ?? ''));
        $picture = trim((string) ($profile['picture'] ?? ''));

        if ($sub === '') {
            throw new RuntimeException('LinkedIn account identifier is missing.');
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('LinkedIn did not provide a valid email address.');
        }

        if (!self::isTruthy($profile['email_verified'] ?? false)) {
            throw new RuntimeException('Your LinkedIn email address must be verified.');
        }

        if ($name === '') {
            $given = trim((string) ($profile['given_name'] ?? ''));
            $family = trim((string) ($profile['family_name'] ?? ''));
            $name = trim($given . ' ' . $family);
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => $name,
            'picture' => $picture,
            'email_verified' => $profile['email_verified'] ?? null,
        ];
    }

    private static function isTruthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    private static function postForm(string $url, array $fields): array
    {
        $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        return self::requestJson($url, 'POST', [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ], $body);
    }

    private static function getJson(string $url, array $headers = []): array
    {
        $headers[] = 'Accept: application/json';
        return self::requestJson($url, 'GET', $headers, null);
    }

    private static function requestJson(string $url, string $method, array $headers, ?string $body): array
    {
        $raw = false;
        $status = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Unable to initialize LinkedIn sign-in request.');
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_USERAGENT => 'CampusChronicle-LinkedInAuth/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $error !== '') {
                throw new RuntimeException('Unable to contact LinkedIn for sign-in verification.');
            }
        } else {
            $headerText = implode("\r\n", $headers)
                . "\r\nUser-Agent: CampusChronicle-LinkedInAuth/1.0\r\n";

            $contextOptions = [
                'http' => [
                    'method' => $method,
                    'timeout' => 15,
                    'ignore_errors' => true,
                    'header' => $headerText,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ];

            if ($body !== null) {
                $contextOptions['http']['content'] = $body;
            }

            $context = stream_context_create($contextOptions);
            $raw = @file_get_contents($url, false, $context);
            $responseHeaders = $http_response_header ?? [];
            $statusLine = (string) ($responseHeaders[0] ?? '');

            if (preg_match('/\s(\d{3})\s/', $statusLine, $match)) {
                $status = (int) $match[1];
            }

            if ($raw === false) {
                throw new RuntimeException('Unable to contact LinkedIn for sign-in verification.');
            }
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('LinkedIn returned an invalid response.');
        }

        if ($status < 200 || $status >= 300) {
            $description = trim((string) ($decoded['error_description'] ?? $decoded['message'] ?? $decoded['error'] ?? ''));
            throw new RuntimeException($description !== '' ? 'LinkedIn sign-in failed: ' . $description : 'LinkedIn sign-in failed.');
        }

        return $decoded;
    }
}
