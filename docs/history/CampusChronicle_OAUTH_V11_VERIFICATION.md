# Campus Chronicle OAuth V11 — Verification Report

Date: 2026-09-11

## Root-cause analysis

The screenshots do not show a PHP routing failure:

- Google's page reports `401 invalid_client` and says the OAuth client was not found. This means the browser reached Google, but the supplied Google Web OAuth Client ID is not a valid active client.
- LinkedIn's page shows the literal placeholder `YOUR_LINKEDIN_CLIENT_ID` in the request and rejects it as invalid.

V11 prevents common placeholder credentials from ever being treated as configured.

## Pre-change baseline

- PHP syntax: 76 PHP files, 0 failures.
- JavaScript syntax: 6 standalone JS files, 0 failures.
- The original V10 validators incorrectly accepted:
  - `YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com`
  - `YOUR_LINKEDIN_CLIENT_ID` + `YOUR_LINKEDIN_CLIENT_SECRET`
  as configured values.

## Per-addition tests

### Google validation hardening

Passed:
- Blank Google Client ID is blocked.
- Placeholder Google Client ID is blocked.
- Malformed Google Client ID is blocked.
- A syntactically valid Google Web Client ID shape is accepted.

### LinkedIn validation hardening

Passed:
- Blank LinkedIn Client ID is blocked.
- Placeholder LinkedIn Client ID is blocked.
- Placeholder Client Secret is blocked.
- Non-loopback HTTP redirect is blocked.
- Localhost HTTP redirect is accepted for local development.
- HTTPS redirect URLs remain supported.

### Login-page protection

Passed:
- Invalid providers do not produce direct outbound OAuth links.
- Clicking an unavailable provider stays inside Campus Chronicle and reports the configuration problem in an accessible live-status area.
- Google runtime status reuses the same accessible status area.
- Login PHP syntax passes.
- Inline login JavaScript parses successfully after server-side substitutions.

### Diagnostics tool

Passed:
- Empty configuration reports both providers as NOT READY.
- Placeholder configuration reports both providers as NOT READY.
- Syntactically valid local test values report READY.
- Client Secret is never printed.

## Full verification pass

- PHP files checked: 77
- PHP syntax failures: 0
- Standalone JavaScript files checked: 6
- JavaScript syntax failures: 0
- Inline login JavaScript: PASS
- Routes: 67 total / 67 unique
- Required social routes present: login, google-login, linkedin-login, linkedin-callback
- Literal include/require path audit: 0 missing
- Placeholder rejection regression: PASS
- Valid-shape configuration regression: PASS
- LinkedIn authorization URL/state/scope regression: PASS
- Login unavailable-provider in-page guard: PASS
- Local credential constants remain empty in distributed ZIP: PASS
- Expected zero-byte file: `storage/cache/.gitkeep` only
- Welcome page local HTTP smoke test: 200
- Auth stylesheet local HTTP smoke test: 200

## What cannot be proven without your provider accounts

A real end-to-end Google or LinkedIn account sign-in cannot be completed with placeholder or invented credentials. Only the OAuth provider can confirm that a Client ID exists and belongs to your developer app. LinkedIn additionally requires the matching private Client Secret for the server-side code exchange.

Run this before browser testing:

```powershell
C:\xampp\php\php.exe tools\oauth_diagnostics.php
```

Both providers should show `READY`, then test the browser flow using the real provider applications.
