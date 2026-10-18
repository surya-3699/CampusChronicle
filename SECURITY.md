# Security audit and hardening

Scope: static review of every PHP file, route, upload path, JavaScript file and deployment file in this package,
plus browser tests of the Content-Security-Policy. This is a code audit, **not** a penetration test, and the PHP
code could not be executed in the review environment (see "Not verified").

## Fixed in this release

| # | Severity | Finding | Fix |
|---|---|---|---|
| 1 | **High** | Reflected XSS on the public Events page: `?page=events&view=<payload>` was printed unescaped into links. | `view` is whitelisted to `upcoming/ongoing/completed` (`index.php`). |
| 2 | Medium | Reflected XSS on admin Manage Media: `?id=` printed unescaped. | Cast to integer (`manage-media.php`). |
| 3 | Low | Sign-up / forgot-password pages printed flash messages unescaped. | Escaped. |
| 4 | Medium | Contact form stored a client-supplied `X-Forwarded-For` value as the sender IP (forgeable) and had no length or phone validation (oversized input became a database error). | IP from `REMOTE_ADDR` only; length/phone limits that match the table; honeypot field; JSON-aware error handling. |
| 5 | Medium | Login/sign-up throttling was per IP only: a campus sharing one public IP could lock itself out, while one account could be attacked from many IPs. Throttle files were never cleaned up. | Two layers: generous per-IP ceiling plus a strict per-account limit (login 10 / 15 min). Expired files are removed automatically. |
| 6 | Low | Login timing revealed whether an account exists. | A decoy password hash is checked for unknown emails. |
| 7 | Medium | Content-Security-Policy had only 3 directives. | Full policy (see below), `Cross-Origin-Opener-Policy`, wider `Permissions-Policy`, `__Host-` session cookie over HTTPS. |
| 8 | Low | `mark-notification-read` had no explicit sign-in check. | Returns 401 when signed out. |
| 9 | Low | Dead code that would run `CREATE/ALTER TABLE` from a web request. | Removed (schema changes belong to `tools/migrate.php`). |
| 11 | **Medium** | Account lock-out could be abused, and admins relied on a password alone. | Failure-only throttling keyed per (account + address) with a high global ceiling; **TOTP two-step verification for admins** (encrypted secrets, recovery codes, replay protection) enforced on every admin page and action for all sign-in methods. |
| 12 | Medium | CSP allowed inline scripts. | Per-request nonce on every inline script; all 27 inline event handlers replaced with data-attribute behaviours; inline handlers now blocked (browser-tested). |
| 10 | Hardening | Web process could run shell commands; sessions could expire earlier than the app's 8 h limit; docs and notes were copied into the image. | PHP-FPM `disable_functions` (CLI tools unaffected); `session.gc_maxlifetime=28800`; `.dockerignore`; optional DB TLS (`DB_SSL=1`); private pages are `noindex`. |

Earlier in this project: real client IP behind the proxy (`mod_remoteip`), per-IP request rate limits and overload protection (`config/gateway.php`).

### Content-Security-Policy (enforced)
`default-src 'self'`; scripts from self + jsDelivr + Google sign-in; no `<object>`; `base-uri 'self'`;
`form-action` limited to self, Google and LinkedIn; `frame-ancestors 'self'`; `connect-src` self + Google.
Browser-tested: the site runs with zero violations and foreign scripts, frames, objects, form posts, fetches and `<base>` hijacks are blocked.
**Rollback without redeploying:** set `CC_CSP_MODE=report-only` (or `off`) in the Render dashboard.

## Verified as already sound (no change needed)
CSRF token on every POST · prepared statements everywhere (no SQL built from request data) · bcrypt via `password_hash` ·
session id regenerated at login, sessions invalidated when the password changes, 30 min idle / 8 h absolute limits ·
HTTPS enforced + HSTS · every admin page and every state-changing action I traced in the router checks sign-in/admin role ·
ownership checks on certificates, registrations and notifications · upload allow-list with content-type check, random names, no scripts executed from uploads ·
Google ID-token verification (signature, issuer, audience, expiry, nonce, verified email) · LinkedIn single-use `state` ·
password-reset tokens hashed, single-use, expiring · 128-bit certificate codes · admins cannot demote/delete themselves ·
no CORS headers (same-origin only) · `display_errors` off · no secrets, keys or default accounts in the repository ·
`app/ config/ database/ deploy/ docs/ storage/ tools/` denied by `.htaccess`.

## You must do (cannot be done from code)
1. **Git history:** the zip has no `.git` folder, so history could not be scanned. Run in your repo:
   `gitleaks detect --source .` or `trufflehog git file://. --only-verified`.
   If anything real is found, **rotate it** (deleting it from history is not enough).
2. **Rotate as a precaution** the database password and `LINKEDIN_CLIENT_SECRET`. Keep all secrets only in the Render dashboard. (The Google client ID is public by design.)
3. **Database:** use a dedicated account with only SELECT/INSERT/UPDATE/DELETE for the running site, and a separate admin account for `tools/migrate.php`. Never `root`. Do not expose MySQL publicly. For any remote MySQL set `DB_SSL=1`.
4. **Admin accounts:** the app has no two-factor sign-in. Keep admins few, use long unique passwords, and create them only with `tools/bootstrap-admin.php`.
5. **Bootstrap:** the Docker build now downloads pinned Bootstrap 5.3.3 / Icons 1.11.3 into `public/vendor/` and serves them from your own domain (falls back to the CDN if the download fails). After the first deploy confirm `https://your-domain/public/vendor/bootstrap/bootstrap.min.css` loads.
6. **Two-step verification:** set `CC_APP_KEY` (`openssl rand -base64 32`), enrol every admin at Admin > Security (2FA), save the recovery codes, then set `CC_ADMIN_2FA_REQUIRED=1`.

## Residual risks
* Inline `style=""` attributes are still allowed (`style-src 'unsafe-inline'`); inline **scripts** are no longer allowed.
* A distributed attack (60+ failures across many addresses in 15 minutes) can still pause password sign-in for one account; Google sign-in is unaffected.
* No web-application firewall. Two-step verification covers administrators only (students are not asked for it).
* The sample gallery video in the seed uploads is a screen recording of a Disney/Marvel film clip (third-party copyright). **Delete it from your live gallery before launch** and do not ship it in the public repository.

## Not verified
The PHP could not be executed here (no PHP/MySQL available). Structure was checked with a PHP lexer and logic by review.
Before launch run `php tools/preflight.php`, then sign in, save an event, save a gallery, submit the contact form, and register for an event.
