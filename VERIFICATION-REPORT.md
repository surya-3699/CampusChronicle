# Campus Chronicle — verification report

## Result

The supplied v11 project was hardened in the earlier release work, then updated for persistent profile uploads, lighter background media, automatic participation certificates and separate prize certificates. The application was tested locally against an isolated database, not deployed to public hosting.

Two complete verification passes each finished with **0 failed suites and 291 passing assertions**, plus syntax checks on **88 PHP files**. Both ran after safe Google account linking, direct social signup, the LinkedIn setup state and editable institution profile migration were added. Each pass included 16 checks across three isolated account paths and repeat sign-ins. A further fresh-install test passed eight checks, including two consecutive migrations, the institution column and the absence of default accounts. Machine-readable summaries and test output are in `docs/verification/`.

The deployment preflight intentionally remains blocked in the local test configuration by seven hosting requirements: supported production PHP version, production mode, HTTPS application URL, dedicated database account, database secret, private writable storage, and storage outside the application root. The test harness checked that these were exactly the expected blockers; it did not treat the development machine as production-ready.

## Executed checks

| Area | Executed result |
|---|---|
| Authentication and authorization | Guest/student/admin route checks; login/logout; password-reset boundary; expired/revoked sessions; role changes; malformed input; CSRF; mutation methods; throttling. |
| Student/admin events | Event creation, publication, visibility, registration, duplicate/capacity rules, approval, rejection, attendance, deletion and ownership checks. Eight concurrent registration requests competed for the last seat; only one succeeded. |
| Profiles | Reproduced broken saved-banner markup and stale-session photo overwrite before changing code. Both pass after the fix. Valid uploads persist; disguised uploads are rejected. Combined upload failure and an injected database failure preserve the existing profile and remove unsaved files. |
| Editable institution | Reproduced the missing form field and hard-coded display with two failing assertions. The database-backed institution name now saves, reloads and appears in the academic profile. Fresh and upgraded schemas were both checked. |
| Certificates | Registration and approval alone issue nothing. Confirmed attendance automatically issues participation. First/second/third prize issue separate winner certificates. Repeats do not duplicate records/notifications. Invalid places, duplicate places, multiple prizes for a student, student award attempts, missing CSRF and GET mutations are rejected. Ownership and public verification were checked. |
| Templates and certificate failure handling | Custom image preview and issuance; preservation after settings/event edits; protection from deleting a referenced template; idempotent repair; database rank constraints; certificate-storage failure rolls attendance back and retry succeeds. |
| Galleries/media/notifications | Published/draft visibility, cover/template/photo uploads, malicious uploads, media ownership, cross-user notification denial and notification read action. |
| Database/operations | Repeat migration, transaction rollback, counter reconciliation, scheduled maintenance idempotency, database outage, storage failure, maintenance mode, backup creation and restore checks. Fresh install adds all new certificate constraints without adding accounts. |
| OAuth | Local synthetic token/signature/claim/state error tests, provider setup states, direct signup, safe Gmail/password linking, three isolated identities, repeated sign-ins and subject-collision rejection. No real Google or LinkedIn account consent was performed. |
| Protected paths | Local test server denied private directories and dangerous upload extensions. Actual Apache/.htaccess enforcement remains a target-host check. |

## Browser inspection and performance evidence

The supplied 90.76-second recording was inspected using locally extracted frames. It shows the About, Events, category/filter and Gallery pages. It is supporting evidence of the affected flows; sampled frames are not a frame-rate benchmark.

Browser checks used the Codex Chromium-based in-app browser. The saved profile banner rendered correctly after reload. The default winner certificate, participation preview, admin certificate controls and settings save were inspected. At a 390×844 viewport, the menu opened/closed and the Events/admin settings pages had no horizontal page overflow. At 1366×900, the default certificate content fit its intended landscape document. The certificate itself deliberately scrolls horizontally inside its container on small screens. HTTP tests separately verified student access and cross-user denial.

The original background videos were repackaged as local 1280×720, 24 fps H.264 clips with posters and fast-start metadata. The footage is unchanged; encoding/resolution are lighter:

| Video | Original | Optimized | Reduction |
|---|---:|---:|---:|
| Campus | 8,046,030 bytes | 444,134 bytes | 94.5% |
| Ambient | 3,589,756 bytes | 420,751 bytes | 88.3% |

Browser inspection confirmed the local 1280×720 clip reached ready state 4 and played. Playback is deferred, pauses in hidden tabs and respects reduced-motion/data-saving preferences. Profile rendering no longer fetches unused dashboard statistics. A new stylesheet isolates video compositing and fixes closed-drawer overflow. Original CSS files and original external JavaScript files are hash-checked against the input ZIP during packaging. No universal FPS or latency improvement is claimed.

## Still requires the real deployment environment

- PHP 8.3+ and the chosen supported database version: local execution used XAMPP PHP 8.0.30/MariaDB 10.4.32.
- Domain/TLS, canonical redirect, proxy HTTPS handling, Secure cookies/HSTS, Apache protections, real upload permissions and private storage.
- Real Google/LinkedIn credentials, consent/redirect configuration and successful/cancelled provider login.
- Actual cron scheduling, off-host encrypted backups, restore drills, monitoring, and email delivery if later configured. Certificates currently notify in-app, not by emailed attachments.
- Physical phones, Safari/iOS, Android browser behavior, slow network/low-end GPU performance and accessibility audit. Only the stated Chromium viewport/browser checks were executed.
- Physical printing and target-browser PDF export. The print stylesheet and print action were inspected; a physical printout or exported PDF was not verified in the in-app browser.
- Real college background artwork must be previewed before issuance. It must leave the fixed text area clear. The template system does not provide a free-position drag-and-drop designer, digital signatures, prize ties or automatic selection of winners.

## Packaging and update safety

The final ZIP excludes test-created uploads, test sessions, runtime rate-limit files, test databases, private keys and real deployment secrets. Original bundled upload assets are retained. **It cannot contain uploads added to your live XAMPP folder after the original ZIP. Keep/merge your current `public/uploads` when updating.** Never overwrite the existing database with the fresh schema. Take a matching backup first.

The package includes the migration, environment template, preflight/backup/maintenance tools, default certificate implementation, local videos, `START_HERE.md`, deployment and rollback instructions. Archive entries are read back and compared with their SHA-256 manifest. Older documentation is preserved under `docs/history/` for reference; the root guides describe this release.


## 22 September update: college reference, media recovery and Render
- Examined the college event reference in the browser. Applied clearer dates and concise text to existing event cards, with lazy image loading and a broken-image fallback. Original stylesheet and external-script files remain unchanged; no college text or images were copied.
- Reproduced missing gallery originals with two failing regression assertions before the change; both pass afterwards in both final passes. Admin media cards now explain missing originals instead of requesting broken image/video sources. Upload success/error messages are now visible, and available videos offer a download link.
- Recovered the exact image and video from the user's previous application folder into this package. Live XAMPP files were not changed because write permission was not granted. Browser verification loaded the recovered video: 1920x1080, 16.481634 seconds, readyState 4, no media error. Restoring files does not insert database rows; these paths match the existing live gallery records.
- The Google Web client ID is configured. The real Google Identity Services script and button rendered on `http://localhost:8817`, and 15 configuration, signature and claim checks passed. Completing an actual account sign-in still requires an interactive Google account session. Add the final Render origin to this same client before live testing.
- LinkedIn is shown on login and signup with a disabled setup explanation until valid credentials and callback configuration are supplied; it becomes active automatically when the server environment is complete.
- Added Docker/Apache/PHP files, a Render blueprint and RENDER-DEPLOYMENT.md. Docker is unavailable locally: no image build, Linux container run, Render deployment or real domain/OAuth test was executed. The default Render configuration uses a persistent upload/session/storage disk and a separately provisioned private MySQL service. No hosting charges or accounts were created.
- Inspected event cards at desktop and 390-pixel viewport in the in-app Chromium browser; the mobile document did not overflow horizontally. This is not a complete physical-device certification. Existing real-device and print limitations below still apply.

## Remaining deployment and update risks
| Risk | Required check or mitigation |
|---|---|
| Missing uploads after an update | Merge all original uploads; retain the Render persistent mount. Test upload, restart and redeploy before launch. |
| Database lost or incompatible | Use a private MySQL/MariaDB service with persistent storage and tested logical backups; Render Postgres is not a drop-in replacement. |
| External MySQL TLS | Existing PDO settings do not configure provider CA certificates. Use the documented private database deployment; configure and test verified TLS before choosing an Internet database endpoint. |
| Reverse-proxy request handling | Render-specific Apache marks edge requests HTTPS. Validate redirects, cookies and per-client throttling on staging. The current limiter uses REMOTE_ADDR; users sharing a proxy/NAT can share its bucket. Do not blindly trust client-supplied forwarding headers. |
| Docker or PHP patch changes | First build and stage the supplied Dockerfile. Pin the tested image digest, schedule dependency updates and rerun regression checks. |
| Disk fills or service restarts | Monitor disk capacity; keep backups off-host; verify upload error messages and durable files. A single attached disk limits scaling and causes deployment downtime. |
| Background jobs not scheduled | Deploy and verify maintenance/backup schedules separately. Render cron cannot mount the web service's disk. |
| Google/LinkedIn configuration | Real provider credentials, allowed origins/callbacks and account tests remain required. Email sign-in stays available. |
| Unexpected future changes | No test can guarantee zero future bugs. Stage every update, back up before migration and retain matching code/database/upload rollback points. |
