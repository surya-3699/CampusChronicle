# OAuth V11 troubleshooting — errors from the screenshots

## Google screenshot

Observed provider error:

- `Access blocked: Authorization Error`
- `The OAuth client was not found.`
- `Error 401: invalid_client`

Meaning: Google received a Client ID that does not identify an active OAuth Web client. This is a provider configuration problem, not a Campus Chronicle password/database problem.

V11 fix: common placeholder IDs are rejected locally before Google is opened. The login page stays on Campus Chronicle and explains that a real Web OAuth Client ID is required.

## LinkedIn screenshot

Observed address/request includes:

- `client_id=YOUR_LINKEDIN_CLIENT_ID`

Observed provider error:

- `The passed in client_id is invalid "YOUR_LINKEDIN_CLIENT_ID"`

Meaning: the literal documentation placeholder was sent to LinkedIn.

V11 fix: common placeholder Client IDs/Secrets are rejected locally before an authorization URL is generated.

## One command to verify local setup

```powershell
C:\xampp\php\php.exe tools\oauth_diagnostics.php
```

Both providers must show `READY` before a real browser sign-in can succeed.

`READY` means the local values are structurally valid; only the provider can confirm that the credentials actually exist and are enabled.
