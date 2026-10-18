# Campus Chronicle deployment guide — final package

This release hardens the supplied CampusChronicle_oauth_v11.zip while preserving the existing design. It is a **staging-tested candidate, not an already deployed or production-certified site**. Complete the target-host gates in VERIFICATION-REPORT.md before opening public traffic. Current work was verified against an isolated test database. The public domain, TLS endpoint and real OAuth credentials were not exercised.

## Release contents

One archive, `CampusChronicle_final.zip`, contains the application, local background media, default certificate design, migrations, production environment template, CLI preflight/backup/maintenance tools, deployment/rollback instructions and verification evidence. Start with `START_HERE.md` for the existing XAMPP upgrade. There are no test accounts, live database dumps, synthetic uploads or real secrets in this package.

## 1. Prepare a private staging installation

Use PHP 8.3 or later with PDO MySQL, curl, OpenSSL, fileinfo, mbstring and ZipArchive. The local tests used PHP 8.0.30 and MariaDB 10.4.32; rerun integration checks on your chosen supported PHP/database versions. Use InnoDB and utf8mb4. Configure PHP and database timezone consistently (the application default is Asia/Kolkata). The application uses database NOW() for lifecycle decisions.

Use Apache 2.4 with `.htaccess` enabled (`AllowOverride All` for this application) and the authorization, MIME and headers modules. The document root is the application directory containing index.php. Alternative servers must implement equivalent directory and file denial rules; do not assume they read `.htaccess`.

Configure valid TLS, a canonical HTTPS host, and HTTP-to-HTTPS redirect in the web server. The application refuses production HTTP; it does not manufacture a redirect from an untrusted Host header. Behind a proxy, configure the trusted web server to supply HTTPS correctly; do not trust arbitrary client forwarding headers. Test HSTS and Secure cookies on the real TLS endpoint.

Create private storage outside the document root, owned by the PHP service account, and set `CC_STORAGE_PATH`. Keep code read-only to that account except `public/uploads`; make uploads and private storage writable. Use a private PHP session directory, `display_errors=Off`, `log_errors=On`, a private error log and suitable upload/request limits. Uploaded media is publicly addressable by URL; this release does not provide confidential asset storage or malware scanning.

## 2. Configure secrets

Inject values from production.env.example through the host's protected environment configuration. Do not put credentials into PHP files or a publicly served environment file. Use a dedicated database account and a strong random password. The web worker requires DB_HOST, DB_NAME, DB_USER and DB_PASSWORD and rejects the root account in production. Grant only normal application DML privileges; use a separate migration account for DDL.

Set `APP_ENV=production`, canonical HTTPS `APP_URL`, `CC_AUTO_MIGRATE=0`, timezone and private storage. Keep provider variables empty until configured. PHP-FPM installations may clear inherited environment variables; verify what the worker receives without printing secrets publicly. Limit this release to one application instance unless shared rate-limit storage/session handling is configured and tested: throttling is file-based per client IP, not a distributed service.

## 3. Back up and migrate

Enable maintenance on all web workers and pause scheduled jobs before migration or a consistent file/database backup. Back up the current live database, code, uploads and separately encrypted environment configuration. Confirm a restore into an isolated database before proceeding.

Fresh installation only: import `database/schema.sql`. It explicitly selects `campus_chronicle`; review and change that name if needed. **Never import the fresh schema into an existing live database.** Existing v11 databases: retain all existing data and take the backup first.

With the migration account/environment selected, run:

```text
php tools/migrate.php
```

This runs the bundled compatibility upgrade followed by database/production.sql and database/certificates_v13.php. The latter adds separate participation/winner records, prize constraints, immutable issuance details and per-event certificate settings. The standalone production.sql alone does not replace the compatibility upgrade for older schemas. Duplicate registrations/certificates cause constraint creation to fail rather than deleting records. Review duplicates with the campus data owner, resolve deliberately, then rerun. MySQL/MariaDB DDL may commit independently; failure does not imply the migration was rolled back. Restore the snapshot when necessary. Compatibility code contains legacy best-effort adjustments, so review private logs and require a successful preflight.

Register the verified operator account, then use the private console:

```text
php tools/bootstrap-admin.php operator@example.edu
php tools/preflight.php
```

Public signup always creates a student. Reauthenticate after promotion. The preflight must exit zero and its pending manual checks must also be completed; zero is not proof of provider authorization or TLS correctness.

## 4. Configure and verify OAuth

Google: create the correct Web OAuth client, set GOOGLE_CLIENT_ID, authorize the exact HTTPS origin and configure consent-screen access. This implementation uses Google Identity Services ID tokens and no browser client secret. Test fresh Google signup, returning login, denial, expiry, wrong audience, state/nonce replay and disabled-provider behavior. Existing password accounts are not silently linked by matching email; they must use their existing sign-in method. A separate account-linking interface is not included.

LinkedIn: enable Sign In with LinkedIn using OpenID Connect, set the client ID, server-side secret and exact HTTPS callback `APP_URL/index.php?page=linkedin-callback`. Authorize `openid profile email`. Test success, denial, callback mismatch, missing/unverified email and existing-account collision. Real provider success was not executed locally.

References: [Google token verification](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token), [Google JavaScript reference](https://developers.google.com/identity/gsi/web/reference/js-reference), [LinkedIn OpenID Connect](https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin-v2).

## 5. Password recovery

The original unauthenticated email/password replacement was unsafe. Reset now requires an expiring single-use token. Automatic reset-email delivery is **not implemented**. Until a verified mail flow is integrated, support must verify the requester's identity through the campus's established process and privately deliver the link produced by:

```text
php tools/issue-reset.php verified-student@example.edu
```

Set HTTPS APP_URL first. Treat the output as a credential, not a log entry. Links expire after 20 minutes and cannot be reused. Password changes invalidate existing sessions. Do not reopen the original email-only reset behavior.

## 6. Scheduled maintenance and backups

Schedule `php /absolute/application/tools/maintenance.php` every five minutes under the service account with the correct environment. It publishes completed-event galleries, removes expired reset tokens, and clears stale rate-limit counters. A database advisory lock prevents overlapping executions. GET requests no longer perform this maintenance. Monitor nonzero exit status and private logs. Consider whether automatically publishing completed-event galleries matches campus policy before scheduling.

For a consistent backup, pause cron and put every web worker into maintenance first. Provide a private MariaDB client options file (restrict access to the backup account):

```ini
[client]
host=127.0.0.1
port=3306
user=campus_backup
password=REPLACE_WITH_BACKUP_SECRET
```

Set `CC_DB_OPTIONS_FILE` to that file, `CC_DUMP_BIN` to mariadb-dump/mysqldump, DB_NAME, and APP_MAINTENANCE=1 in the backup process. Run:

```text
php tools/backup.php /private/backups
```

A complete bundle has database.sql, application.zip and COMPLETE.json hashes. Incomplete bundles must not be used. Uploads are included; storage/session files and external environment secrets are not. Encrypt and copy completed backups off-host, apply retention policy, and regularly restore into an isolated environment. Resume workers and cron only after completion. The script cannot prove other workers or external DB writers were paused.

## 7. Target-host acceptance

Use synthetic student, other-student and admin accounts. Verify login/logout, admin restrictions, profile update, event create/edit/delete, registration/cancellation, approval/rejection/attendance, certificate eligibility/download ownership, gallery publication/uploads, notifications, templates and contact submission. Check expired sessions and invalid CSRF responses. Repeat last-seat concurrency and failure rollback on staging.

Request app/, config/, database/, storage/, tools/, dotfiles and backup files directly: all must be denied. Test a harmless disposable PHP file in uploads: it must neither execute nor disclose source. Confirm normal images/media still load. Do this only in staging and remove the test file afterwards.

Inspect phone/tablet/desktop layouts and keyboard use on the actual browsers you support, including Safari/iOS and Android if applicable. Verify CDN fonts/icons/scripts, video/PDF formats, certificates and printing. No design redesign was performed. Only then disable maintenance and open traffic, with rollback available.

## Certificate operations

Confirmed attendance means an administrator has marked an approved registration as attended. It automatically issues a participation certificate. Competition results are not inferred: an admin awards first, second or third place, which issues a separate winner certificate. One student may hold one place per event; ties are not supported. Registration or approval alone never qualifies.

The scheduled maintenance task also repairs missing participation certificates for older attended registrations. It uses the same transaction and idempotency rules. The event registrations screen has the equivalent manual repair button. Notifications are in-app; this release does not automatically email certificate attachments.

Choose organization, signatory and separate participation/winner designs before attendance/awards. Built-in defaults require no external service. Custom backgrounds support published raster images with a blank centre, in A4 landscape ratio. PDF templates remain reference downloads. Already issued records retain a snapshot of the design settings and, for new v13 records, recipient/event details. Old certificates retain their old code and are classified as participation. Referenced template files must be included in backups; certificate template files are retained when an unused library entry is removed.

Export uses the browser Print / Save PDF command. Test A4 landscape, 100% scale, disabled headers/footers and background graphics on your target browser/printer. In-app preview rendering was checked locally; physical printing and iOS/Safari PDF export still require target-device verification.

## Performance changes

The same background footage is included locally as 1280×720, 24 fps H.264 without audio, with fast-start metadata and posters. Public video: 8,046,030 → 444,134 bytes. Ambient video: 3,589,756 → 420,751 bytes. Playback starts after the initial render, pauses when hidden and respects reduced-motion/data-saving preferences. Posters remain available when autoplay is blocked. Original stylesheets are unchanged; an additional stylesheet isolates the background and fixes closed-drawer overflow. No universal frame-rate improvement is claimed; check performance on the actual host and devices.
