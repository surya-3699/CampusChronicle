# Campus Chronicle V10 — Social Login Update

## Login redesign

- Moved social authentication above the email/password form to match the requested clean modal-style login hierarchy.
- Added a full-width **Continue with LinkedIn** button.
- Kept **Continue with Google** and changed its official Google Identity Services button to an outlined rectangular style.
- Removed the developer-facing Google configuration notice from the public login UI.
- Added clean disabled states when a provider is not configured.
- No GitHub authentication button or route was added.

## LinkedIn OpenID Connect

- Added `config/linkedin.php` with environment-variable support.
- Added `app/Services/LinkedInIdentityService.php`.
- Added `linkedin-login` and `linkedin-callback` routes.
- Uses authorization-code flow with CSRF `state`, a 10-minute login-state expiry, server-side token exchange, and LinkedIn OIDC `userinfo`.
- Uses only `openid profile email` scopes.
- Keeps the LinkedIn client secret server-side.
- Does not silently merge accounts by matching only email addresses.

## Database

- Added nullable unique `linkedin_sub`.
- Expanded `auth_provider` to support `linkedin` and `multiple` while preserving existing Google/password values.
- Updated fresh schema, migration, and compatibility upgrade paths.

## Compatibility

- Added HTTPS verification and a PHP stream fallback when cURL is not available.
