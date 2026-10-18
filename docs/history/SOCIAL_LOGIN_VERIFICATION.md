# Campus Chronicle — Social Login Verification

Date: 11 September 2026

## Requested change

- Keep **Continue with Google**.
- Add **Continue with LinkedIn** instead of GitHub.
- Move the social buttons above the email/password form in a clean Lovable-style hierarchy while preserving Campus Chronicle's dark/glass branding.
- Remove developer setup notices from the public login UI.
- Test before and after the additions.

## Baseline before changes

- PHP files: **74**, syntax failures: **0**.
- Standalone JavaScript files: **6**, syntax failures: **0**.
- Existing Google route/config/database references were present and consistent.

## Implemented

- Redesigned login order: Google -> LinkedIn -> OR -> email/password -> Sign In -> Create Account.
- Official Google Identity Services button now uses an outline rectangular treatment.
- Added a full-width LinkedIn button with a matching visual style.
- Added disabled provider states when OAuth credentials are not configured; no developer configuration note is shown to students.
- Added LinkedIn OpenID Connect authorization-code flow using `openid profile email`.
- Added CSRF state verification and a 10-minute OAuth-state expiry.
- Added server-side token exchange and OIDC userinfo handling.
- LinkedIn client secret remains server-side only.
- Added `linkedin_sub` persistence and unique-index support.
- Expanded `auth_provider` for LinkedIn/multi-provider compatibility.
- Existing-account email matches are not silently merged across providers.
- Added PHP-stream HTTPS fallback when cURL is unavailable.
- No GitHub authentication button or route was added.

## Verification pass 1

- PHP files after feature addition: **76**, syntax failures: **0**.
- Standalone JS files: **6**, syntax failures: **0**.
- Login inline JavaScript parse: **PASS**.
- CSS brace/required-class check: **PASS**.
- Router uniqueness: **PASS — 67 unique routes**.
- Google + LinkedIn ordering and public-debug-note checks: **PASS**.
- LinkedIn database coverage across schema/migration/compatibility/model/controller: **PASS**.
- LinkedIn configuration environment-variable test: **PASS**.
- LinkedIn authorization URL/profile validation regression tests: **PASS**.

## Verification pass 2

- Literal PHP include/require path audit: **PASS**.
- Login local asset audit: **PASS**.
- Unexpected zero-byte file audit: **PASS**.
- Full PHP lint rerun: **PASS**.
- Standalone JS syntax rerun: **PASS**.
- Login inline JS syntax rerun: **PASS**.
- Router/social assertions rerun: **PASS**.
- LinkedIn profile/authorization regression rerun: **PASS**.
- Local PHP server smoke test:
  - Welcome route: **HTTP 200**.
  - `public/css/auth.css`: **HTTP 200**.

## Environment limitation

This container does not provide a running MySQL/MariaDB service or the PHP `pdo_mysql` extension, so a live database-backed OAuth callback cannot be completed here. The PHP/SQL integration paths were statically checked and the non-network LinkedIn service logic was regression-tested. A real LinkedIn login also requires your own LinkedIn Developer app credentials and exact redirect URL.

## Setup file

See `LINKEDIN_SIGNIN_SETUP.md` inside the project.
