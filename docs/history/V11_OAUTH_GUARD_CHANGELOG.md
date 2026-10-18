# Campus Chronicle V11 — OAuth configuration guard

## Why this update exists

The browser screenshots showed two provider-side failures:

- Google: `Error 401: invalid_client` / `The OAuth client was not found.`
- LinkedIn: the authorization request literally contained `client_id=YOUR_LINKEDIN_CLIENT_ID`.

Those errors are caused by invalid or placeholder OAuth application credentials, not by the Campus Chronicle database or password form.

## Changes

1. Hardened `config/google.php`
   - Rejects blank IDs.
   - Rejects common documentation placeholders.
   - Rejects malformed Google Web client IDs.
   - Exposes a safe `configurationProblem()` message for local UI/diagnostics.

2. Hardened `config/linkedin.php`
   - Rejects blank IDs and secrets.
   - Rejects common placeholder IDs/secrets.
   - Validates redirect URLs.
   - Allows plain HTTP only for loopback development; requires HTTPS otherwise.
   - Exposes a safe `configurationProblem()` message.

3. Updated the login UI
   - Invalid provider configuration no longer sends the browser to a broken Google/LinkedIn error page.
   - The button remains keyboard-focusable and explains the local setup problem in an ARIA live status message.
   - Properly configured providers retain their normal real OAuth behavior.

4. Added `tools/oauth_diagnostics.php`
   - CLI-only configuration checker.
   - Never prints the LinkedIn Client Secret.
   - Masks client IDs.
   - Returns non-zero until both providers are structurally ready.

5. Updated setup/troubleshooting documentation
   - Explicitly warns not to paste placeholder values.
   - Includes the PowerShell diagnostics command.
   - Maps the exact screenshot errors to their configuration causes.

## Important

No real Google or LinkedIn credentials are bundled in this ZIP. They belong to the developer's own provider apps. The project cannot manufacture or safely share those credentials.
