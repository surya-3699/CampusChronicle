# Campus Chronicle — LinkedIn Sign-In Setup

Campus Chronicle supports **Sign in with LinkedIn using OpenID Connect** using the authorization-code flow. The client secret stays on the PHP server.

## Important: do not use placeholders

These are examples only and are invalid:

- `YOUR_LINKEDIN_CLIENT_ID`
- `YOUR_LINKEDIN_CLIENT_SECRET`
- `REPLACE_ME`

The V11 build detects common placeholder values and blocks the outgoing OAuth request, preventing the LinkedIn error shown when `client_id=YOUR_LINKEDIN_CLIENT_ID` is sent to LinkedIn.

## 1. Create or open a LinkedIn Developer app

Enable the LinkedIn product that provides OpenID Connect sign-in. The project requests:

- `openid`
- `profile`
- `email`

Copy the real **Client ID** and **Client Secret** from the app's Auth/credentials area.

## 2. Register the callback URL

For this XAMPP project, the local callback used by the code is:

`http://localhost/CampusChronicle/index.php?page=linkedin-callback`

The configured callback sent by Campus Chronicle must exactly match an Authorized Redirect URL in the LinkedIn app.

LinkedIn's current 3-legged OAuth documentation recommends HTTPS for normal web-server callbacks. LinkedIn's own localhost sample documentation also demonstrates `http://localhost` for local development. This V11 build therefore accepts plain HTTP only for loopback hosts (`localhost`, `127.0.0.1`, or `::1`) and requires HTTPS everywhere else.

## 3. Add the credentials locally

Configure these values in the Apache/PHP server environment:

- `LINKEDIN_CLIENT_ID`
- `LINKEDIN_CLIENT_SECRET`
- `LINKEDIN_REDIRECT_URI` (optional if the default is correct)

Keep the Client Secret private. Do not put it in JavaScript, HTML, screenshots, GitHub, or chat messages.
Do not save it in `config/linkedin.php` or any other project file. Restart Apache after changing its environment.
If a Client Secret has been pasted into a chat, screenshot or source file, rotate it in the LinkedIn Developer Portal before continuing.

For Render, add the same three names under the web service's **Environment** page. Use the public HTTPS callback URL, for example:

`https://YOUR-SERVICE.onrender.com/index.php?page=linkedin-callback`

Register that exact URL under **Authorized redirect URLs** on the LinkedIn app's **Auth** tab.

## 4. Run diagnostics before clicking the button

From PowerShell in the CampusChronicle folder:

```powershell
C:\xampp\php\php.exe tools\oauth_diagnostics.php
```

LinkedIn must show `READY` before you test the browser flow.

## 5. Test

1. Start Apache and MySQL.
2. Open `http://localhost/CampusChronicle/index.php?page=login`.
3. Confirm that **Continue with LinkedIn** is enabled, then click it.
4. Approve the LinkedIn sign-in prompt.
5. LinkedIn returns to `index.php?page=linkedin-callback`.
6. Campus Chronicle exchanges the code server-side and obtains the OIDC profile.

### If LinkedIn says the `client_id` is invalid

Check the browser address bar. If it contains `client_id=YOUR_LINKEDIN_CLIENT_ID`, the placeholder was never replaced with the real LinkedIn app Client ID. Re-copy the Client ID from the LinkedIn Developer Portal. Do not change the OAuth URL manually.

### If LinkedIn says the redirect URI is invalid

Make sure the exact redirect URI in `config/linkedin.php` or `LINKEDIN_REDIRECT_URI` is registered in the LinkedIn app. Scheme, host, path, and query string must match the registered callback.

## Security behavior

- The Client Secret is used only by server-side PHP.
- OAuth `state` is random, stored in the session, compared with `hash_equals`, and expires after 10 minutes.
- LinkedIn-only accounts do not use the project's local password-reset flow.
- Existing local accounts are not silently merged solely because LinkedIn returns the same email address.
