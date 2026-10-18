<?php

/**
 * LinkedIn OpenID Connect configuration.
 *
 * Set these environment variables in Apache/PHP or the hosting provider:
 *   LINKEDIN_CLIENT_ID
 *   LINKEDIN_CLIENT_SECRET
 *   LINKEDIN_REDIRECT_URI (optional when using the local default below)
 *
 * Credentials are deliberately never read from source-code constants. The
 * client secret is server-side only: never print it into HTML or JavaScript
 * and never commit it to a repository or include it in a release archive.
 */
final class LinkedInAuthConfig
{
    private const LOCAL_REDIRECT_URI = 'http://localhost/CampusChronicle/index.php?page=linkedin-callback';

    public static function clientId(): string
    {
        return trim((string) getenv('LINKEDIN_CLIENT_ID'));
    }

    public static function clientSecret(): string
    {
        return trim((string) getenv('LINKEDIN_CLIENT_SECRET'));
    }

    public static function redirectUri(): string
    {
        $environment = trim((string) getenv('LINKEDIN_REDIRECT_URI'));
        return $environment !== '' ? $environment : (getenv('APP_ENV') === 'development' ? self::LOCAL_REDIRECT_URI : '');
    }

    /**
     * Return a safe configuration problem or null when the provider is ready.
     * Placeholder values are rejected so links such as
     * ?client_id=YOUR_LINKEDIN_CLIENT_ID can never be sent to LinkedIn.
     */
    public static function configurationProblem(): ?string
    {
        $clientId = self::clientId();
        $clientSecret = self::clientSecret();
        $redirectUri = self::redirectUri();

        if ($clientId === '') {
            return 'LinkedIn sign-in needs a LinkedIn Client ID.';
        }
        if (self::looksLikePlaceholder($clientId)) {
            return 'LinkedIn sign-in still contains a placeholder Client ID. Add the real Client ID from the LinkedIn Developer Portal.';
        }

        if ($clientSecret === '') {
            return 'LinkedIn sign-in needs a LinkedIn Client Secret on the PHP server.';
        }
        if (self::looksLikePlaceholder($clientSecret)) {
            return 'LinkedIn sign-in still contains a placeholder Client Secret. Add the real secret only on the PHP server.';
        }

        if (filter_var($redirectUri, FILTER_VALIDATE_URL) === false) {
            return 'LinkedIn sign-in has an invalid redirect URL.';
        }

        $parts = parse_url($redirectUri);
        if (!is_array($parts)) {
            return 'LinkedIn sign-in has an invalid redirect URL.';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $isLoopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        // HTTPS is required for normal web deployments. Plain HTTP is accepted
        // only for local loopback development, which matches the XAMPP project.
        if ($scheme !== 'https' && !($scheme === 'http' && $isLoopback)) {
            return 'LinkedIn sign-in requires an HTTPS redirect URL, except for local loopback development.';
        }

        return null;
    }

    public static function isConfigured(): bool
    {
        return self::configurationProblem() === null;
    }

    private static function looksLikePlaceholder(string $value): bool
    {
        $upper = strtoupper(trim($value));
        $markers = [
            'YOUR_LINKEDIN_CLIENT_ID',
            'YOUR_LINKEDIN_CLIENT_SECRET',
            'YOUR_CLIENT_ID',
            'YOUR_CLIENT_SECRET',
            'LINKEDIN_CLIENT_ID_HERE',
            'LINKEDIN_CLIENT_SECRET_HERE',
            'PASTE_CLIENT_ID',
            'PASTE_CLIENT_SECRET',
            'REPLACE_ME',
            'CHANGE_ME',
            'EXAMPLE_CLIENT_ID',
            'EXAMPLE_CLIENT_SECRET',
            '<CLIENT_ID>',
            '<CLIENT_SECRET>',
            '{CLIENT_ID}',
            '{CLIENT_SECRET}',
        ];

        foreach ($markers as $marker) {
            if (str_contains($upper, $marker)) {
                return true;
            }
        }

        return false;
    }
}
