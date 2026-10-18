<?php

/**
 * Google Identity Services configuration.
 *
 * Preferred: set GOOGLE_CLIENT_ID in the Apache/PHP environment.
 * For local XAMPP development, you can paste the Web OAuth client ID into
 * LOCAL_CLIENT_ID. A Google Web client ID is public configuration; do not put
 * an OAuth client secret in this file or browser code.
 */
final class GoogleAuthConfig
{
    private const LOCAL_CLIENT_ID = '70293926363-roqpk3tr7uf9evqsgnonrml448u4ahqm.apps.googleusercontent.com';

    public static function clientId(): string
    {
        $environment = trim((string) getenv('GOOGLE_CLIENT_ID'));
        if ($environment !== '') {
            return $environment;
        }

        return trim(self::LOCAL_CLIENT_ID);
    }

    /**
     * Return a safe, user-facing configuration problem or null when ready.
     * This intentionally rejects copy/paste placeholders such as
     * YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com so the application
     * never opens a Google 401 invalid_client page for a fake credential.
     */
    public static function configurationProblem(): ?string
    {
        $clientId = self::clientId();

        if ($clientId === '') {
            return 'Google sign-in needs a Google Web OAuth client ID.';
        }

        if (self::looksLikePlaceholder($clientId)) {
            return 'Google sign-in still contains a placeholder client ID. Add the real Web OAuth client ID from Google Cloud.';
        }

        if (preg_match('/^[A-Za-z0-9._-]+\.apps\.googleusercontent\.com$/', $clientId) !== 1) {
            return 'Google sign-in has an invalid Web OAuth client ID format.';
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
            'YOUR_GOOGLE_CLIENT_ID',
            'YOUR_CLIENT_ID',
            'GOOGLE_CLIENT_ID_HERE',
            'PASTE_GOOGLE_CLIENT_ID',
            'REPLACE_ME',
            'CHANGE_ME',
            'EXAMPLE_CLIENT_ID',
            '<CLIENT_ID>',
            '{CLIENT_ID}',
        ];

        foreach ($markers as $marker) {
            if (str_contains($upper, $marker)) {
                return true;
            }
        }

        return false;
    }
}
