# Change summary

## 1. User background: video -> 3D animation
* **Removed** the user-side background video (`campus.mp4`, `campus-poster.jpg` deleted; they were only used by user-facing pages). **Admin pages keep their ambient video.**
* **Added** `public/js/scene3d.js`: a dependency-free WebGL scene (rotating campus-network constellation, depth dust, two wireframe crystals) on a CSS aurora gradient.
* Used on public pages, signed-in student pages and login / sign-up / forgot-password through one partial, `app/Views/layouts/background.php`.
* Safeguards: starts after first paint, non-blocking shader compile, pauses in hidden tabs, adaptive quality (resolution -> particles -> 30 fps -> still frame), still frame for reduced-motion / Save-Data, CSS fallback without WebGL, context-loss recovery.
* **Your PeachWeb link:** it opens a Google sign-in page, so I could not fetch the scene behind it. The built-in scene stands in for it. To use the PeachWeb scene, publish/export it to a public https embed URL and set `CC_3D_SCENE_URL` (see `production.env.example`); pages then show it in a sandboxed iframe instead.

## 2. "Authorized by" on events and gallery
* New field in **Add Event, Edit Event, Add Gallery, Edit Gallery** (required, 2-150 chars, letters/numbers/. , ' ( ) & / -; type-ahead of previously used names; included in each page's live preview).
* Shown in **Admin > Events** (new column) and **Admin > Gallery** (card line). Missing values display "Not recorded".
* Auto-created event albums inherit the event's authorizer. Editing an event does not overwrite its album's authorizer.
* Database: `events.authorized_by`, `gallery.authorized_by` (VARCHAR 150, NULL). **Run `php tools/migrate.php` once** (or set `CC_AUTO_MIGRATE=1` for one deploy). Until then saves show a clear "upgrade pending" message instead of crashing. `tools/preflight.php` now checks both columns.

## 3. Server, network and workflow fixes; 500+ concurrent; middle manager
* New **Request Gateway** `config/gateway.php` (see `docs/SCALING.md`).
* New stack: **Apache event MPM (768 workers) + PHP-FPM** (`Dockerfile`, `deploy/*`); previous image kept as `Dockerfile.prefork`. Worker pool auto-sized from memory. `render.yaml` gains a health check and gateway settings.
* **Existing problems fixed:** per-IP limits would have been effectively global behind Render's proxy (now `mod_remoteip`, trusting `X-Forwarded-For` only from private proxy addresses); DB outage / "too many connections" returned a bare 500 (now short retry, then 503 + `Retry-After`, 5 s connect timeout); every crawler/monitor hit wrote a visitor row (now skipped); broadcast notifications did one INSERT per user (now one statement); registration rushes could fail on deadlock victims (now retried); admin Events status showed "Completed" from the moment an event without an end date started (now `Event::computeStatus`); stray `</div>` on Edit Event (page wrapper restored); no cache headers on static files.
* **Migration pitfalls handled for the new stack:** PHP-FPM strips environment variables unless `clear_env = no` (set), and `php_flag` in `.htaccess` does not work under FPM (none are used).
* New `tools/loadtest.py` to verify 500+ simultaneous clients on the live site.

## 4. Security audit (see SECURITY.md)
Fixed two reflected XSS holes (public Events page, admin Manage Media), escaped remaining flash messages, hardened the contact form, two-layer login throttling, login timing, a full Content-Security-Policy with extra headers and a `__Host-` session cookie, FPM restrictions, optional DB TLS, link-preview (Open Graph) tags, `noindex` on private pages. New env switches: `CC_CSP_MODE`, `DB_SSL`, `DB_SSL_CA`, `APP_URL`, `CC_OG_IMAGE`.

## 5. Launch readiness (see docs/DEPLOYMENT.md)
* **Admin two-step verification (TOTP)**: Admin > Security (2FA); `CC_APP_KEY`, `CC_ADMIN_2FA_REQUIRED`; recovery via `tools/reset-2fa.php`.
* **Nonce-based CSP** (no inline scripts); failure-only login throttling.
* **Self-hosted Bootstrap** (build-time download, CDN fallback) via `tools/fetch-vendor.sh`.
* **Contact-form email notifications** (Resend API; optional).
* **Compressed assets**: images 7.5 MB -> 1.7 MB, gallery video 40 MB -> 5 MB (same names, same resolution); team photo files renamed; filename-as-alt text fixed.
* **Scheduled jobs in the container**: maintenance (issues certificates, publishes galleries) every 10 minutes; optional daily **encrypted off-site backup** (`tools/backup-offsite.sh`).
* **Sitemap** at `index.php?page=sitemap`; link-preview tags; deployment guide `docs/DEPLOYMENT.md`.

## 6. Three-platform deployment (see docs/DEPLOY-3-PLATFORMS.md)
* **`schema.sql` now imports on real MySQL 8** (it contained MariaDB-only `ADD COLUMN IF NOT EXISTS`; the columns are now in the CREATE TABLE).
* **Database time zone aligned with `APP_TIMEZONE`** on connect. Without this, a UTC managed database made event status, gallery publishing and automatic certificates 5.5 hours wrong.
* `render.yaml` pinned to the **Singapore** region; Cloudflare ranges trusted for real-IP detection; `healthz` shows the client address the app sees.
* **HTTPS everywhere:** safety-net `http -> https` redirect in Apache (explicit-http only), HTTPS section + proof commands in the deployment guide, preflight now connects with TLS and warns when `DB_SSL` is off.
