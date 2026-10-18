# Campus Chronicle — Continue with Google setup

Campus Chronicle uses **Google Identity Services (GIS)** and renders Google's official **Continue with Google** control. The PHP backend independently verifies the returned Google ID token before starting a Campus Chronicle session.

## Important: do not use placeholder IDs

Values such as these are examples only and will not work:

- `YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com`
- `YOUR_CLIENT_ID.apps.googleusercontent.com`
- `EXAMPLE_CLIENT_ID.apps.googleusercontent.com`

The V11 build detects common placeholders and refuses to launch a broken Google sign-in flow. A real Client ID must be created in your own Google Cloud project.

## 1. Create a Google Web OAuth client

In Google Cloud Console:

1. Open **Google Auth Platform → Clients**.
2. Create a client with application type **Web application**.
3. Under **Authorized JavaScript origins**, add the exact origin used by Campus Chronicle.
   - XAMPP default: `http://localhost`
   - If you use a non-default port, add it exactly, for example `http://localhost:8080`.
   - For production, use your exact HTTPS origin.
4. Create the client and copy the real **Client ID** ending in `.apps.googleusercontent.com`.

This Campus Chronicle integration uses the GIS JavaScript callback/popup flow, so the button itself does not require a Google redirect URI.

## 2. Add the real Client ID

Open `config/google.php` and set:

```php
private const LOCAL_CLIENT_ID = 'REAL_CLIENT_ID.apps.googleusercontent.com';
```

Or set the Apache/PHP environment variable:

`GOOGLE_CLIENT_ID=REAL_CLIENT_ID.apps.googleusercontent.com`

The environment value takes priority over the local constant.

A Google client secret is **not** required by this GIS button implementation and must not be placed in browser code.

## 3. Run the local diagnostics check

From the CampusChronicle folder in PowerShell:

```powershell
C:\xampp\php\php.exe tools\oauth_diagnostics.php
```

Google must show `READY`. The diagnostics script validates only the local format/configuration. Google is the authority that determines whether the Client ID actually exists and belongs to your project.

## 4. Test

1. Start Apache and MySQL in XAMPP.
2. Open `http://localhost/CampusChronicle/index.php?page=login`.
3. Click **Continue with Google**.
4. Select a Google account.
5. After Google returns a credential, Campus Chronicle verifies it server-side and creates/loads the local account.

### If Google shows `Error 401: invalid_client` / `OAuth client was not found`

That means the Client ID sent to Google is not a real active OAuth Web client for the app. Re-copy the Client ID from your Google Cloud project's **Clients** page and make sure the local origin is authorized. Do not fix this by inventing or modifying the ID string.

## Security behavior

The backend verifies the Google ID token signature using Google's published certificates and checks issuer, audience, expiry, issue time, stable `sub`, and verified email. A per-login state value is also checked before the credential is accepted.
