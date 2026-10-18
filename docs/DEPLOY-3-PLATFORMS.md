# Deploy in three places: database, backend, frontend delivery

## The picture

```
Visitors ──► CLOUDFLARE (frontend delivery) ──► RENDER (backend: PHP + the pages it builds) ──► DIGITALOCEAN MANAGED MYSQL (database)
             DNS, HTTPS, CDN for CSS/JS/images,   Docker container + persistent disk (uploads)      real managed MySQL: backups, TLS, firewall
             DDoS/WAF rules                       Apache + PHP-FPM + request gateway
```

| Role | Platform | Why it fits |
|---|---|---|
| **Database** | **DigitalOcean Managed MySQL** (Singapore) | A true managed MySQL 8: automated backups, point-in-time restore, TLS, firewall. Render has no managed MySQL. |
| **Backend** | **Render** Docker web service (Singapore) + persistent disk | Runs this exact container, has a disk for uploads, one-click HTTPS, auto-deploys. (DigitalOcean App Platform is **not** suitable: its disk is wiped on every deploy.) |
| **Frontend delivery** | **Cloudflare** (free plan) | DNS, CDN for the static files, DDoS/WAF. |

**About "frontend":** this project has no separate frontend. PHP builds every page on the server, so there is nothing to host on Netlify/Vercel.
The part that *can* live on a "frontend" platform is the delivery layer (DNS, caching of CSS/JS/images, DDoS protection), which is what Cloudflare does here.
Splitting the pages themselves into a static site would mean rewriting the app as an API plus a new frontend: not for tomorrow.

Allow about 2-3 hours the first time. **Do the phases in order: A, B, C, D.**

## Before you start
* Accounts: GitHub, DigitalOcean, Render, Cloudflare (free). Optional: Resend (email), Cloudflare R2 or Backblaze B2 (off-site backups).
* A domain name you control (you must be able to change its nameservers or DNS records).
* On your computer: `git`, a MySQL client (`mysql`), `openssl`.
* Pick **Singapore** in both DigitalOcean and Render so the app and database are in the same city (every page makes several queries).

---

## Phase A: Database on DigitalOcean

1. **Create the cluster.** DigitalOcean > Databases > Create > **MySQL 8**, region **Singapore**, the smallest plan that suits you (check the current price on the page).
2. **Create the database.** In the cluster: Users & Databases > Databases > add `campus_chronicle`. (`database/schema.sql` already says `CREATE DATABASE IF NOT EXISTS campus_chronicle; USE campus_chronicle;` so use exactly this name.)
3. **Download the CA certificate** (cluster Overview > Connection details > *Download CA certificate*). Save it in your project as `config/certs/ca-certificate.crt` (it is a public certificate, not a secret; the `config/` folder is blocked from the web).
4. **Note the connection details:** host, **port (not 3306; usually 25060)**, the `doadmin` user and its password. Use the *public* host (Render is not inside DigitalOcean's network).
5. **Firewall (Trusted Sources).** Settings > Trusted Sources: add your own IP, and Render's outbound IP ranges (Render dashboard > your service > **Connect > Outbound**; do this after Phase B step 3).
   *Be clear about what this does:* Render's ranges are shared by other Render customers in that region, so this keeps out the general internet but not them. The strong protection is TLS plus a long random password.
6. **Import the schema and create the two accounts** from your computer:
   ```bash
   mysql --host=HOST --port=PORT --user=doadmin --password --ssl-ca=config/certs/ca-certificate.crt campus_chronicle < database/schema.sql
   mysql --host=HOST --port=PORT --user=doadmin --password --ssl-ca=config/certs/ca-certificate.crt campus_chronicle
   ```
   ```sql
   CREATE USER 'cc_app'@'%'     IDENTIFIED BY 'LONG-RANDOM-1';      -- used by the running site
   CREATE USER 'cc_migrate'@'%' IDENTIFIED BY 'LONG-RANDOM-2';      -- used only for tools/migrate.php
   GRANT SELECT, INSERT, UPDATE, DELETE ON campus_chronicle.* TO 'cc_app'@'%';
   GRANT ALL PRIVILEGES ON campus_chronicle.* TO 'cc_migrate'@'%';
   SHOW TABLES;     -- expect 12 tables
   ```
   If DigitalOcean refuses `GRANT`, create the users in the Users & Databases tab instead and use `doadmin` for migrations.
   Do **not** run `migration.sql` or `production.sql` on MySQL: they use MariaDB-only syntax. `schema.sql` is already complete, and `php tools/migrate.php` handles future upgrades.
7. Commit `config/certs/ca-certificate.crt`. **Phase A is done when** `SHOW TABLES` lists 12 tables.

## Phase B: Backend on Render

1. **Push to a private GitHub repository** (the folder that contains `Dockerfile` is the root). Run `gitleaks detect --source .` first.
2. **Create the service:** Render > New > **Blueprint** > pick the repo. `render.yaml` sets Docker, the `singapore` region, a 5 GB disk at `/var/data`, and the health check. Review the price shown before confirming.
3. **Set environment variables** (Render > service > Environment):

   | Variable | Value |
   |---|---|
   | `APP_URL` | `https://yourdomain.com` |
   | `APP_MAINTENANCE` | leave `1` until Phase D |
   | `DB_HOST` / `DB_PORT` | from DigitalOcean (port is usually 25060) |
   | `DB_NAME` / `DB_USER` / `DB_PASSWORD` | `campus_chronicle` / `cc_app` / its password |
   | `DB_SSL` | `1` |
   | `DB_SSL_CA` | `/var/www/html/config/certs/ca-certificate.crt` |
   | `CC_APP_KEY` | output of `openssl rand -base64 32` (store a copy safely) |
   | `LINKEDIN_CLIENT_ID/SECRET/REDIRECT_URI` | if you use LinkedIn sign-in |
   | `RESEND_API_KEY`, `MAIL_FROM`, `CONTACT_NOTIFY_TO` | optional, for contact-form emails |
   | `BACKUP_REMOTE`, `BACKUP_PASSPHRASE`, `RCLONE_CONFIG_OFFSITE_*` | off-site backups (see `docs/DEPLOYMENT.md`) |

4. Now go back to DigitalOcean (Phase A step 5) and add Render's Outbound ranges to Trusted Sources.
5. **Wait for the first deploy.** In the Render logs you should see `[campus] memory=... fpm_children=...` and no database errors.
   Open `https://YOUR-SERVICE.onrender.com/index.php?page=healthz`. Expect `{"status":"ok",...}` (this answers even in maintenance mode).

## Phase C: Frontend delivery on Cloudflare

**Launch-day path (safest): Cloudflare for DNS only.** Turn the CDN proxy on later.
1. Cloudflare > Add a site > your domain > Free plan. At your registrar, change the **nameservers** to the two Cloudflare gives you.
2. In Render > service > Settings > **Custom Domains**, add `yourdomain.com` and `www.yourdomain.com`. Render shows the DNS target.
3. In Cloudflare > DNS add `CNAME  @ -> your-service.onrender.com` and `CNAME  www -> your-service.onrender.com`.
   Set **Proxy status = DNS only (grey cloud)**. Delete any `AAAA` records for these names. (Per Render's Cloudflare guide: DNS-only lets Render verify the domain and issue its certificate.)
4. Wait until Render shows both domains **Verified** with a certificate. The HTTPS padlock is then live.
5. Cloudflare > SSL/TLS: mode **Full (strict)**; Edge Certificates: **Always Use HTTPS** on.
6. Set `APP_URL` in Render to `https://yourdomain.com`; add the domain to your **Google OAuth** authorised JavaScript origins and update the LinkedIn redirect URI.

**Optional later: turn on the CDN (orange cloud).** Only after everything works.
* Switch the two records to **Proxied**. Render documents this "orange cloud" setup, and Render itself already uses Cloudflare for DDoS protection, so you are adding a second layer.
* Keep these Cloudflare features **OFF**: *Rocket Loader*, *Email Address Obfuscation*, auto-injected *Web Analytics*, and any *Cache Everything* rule. They rewrite or inject scripts (breaking the nonce-based Content-Security-Policy) or would cache pages that contain per-visitor tokens.
* Caching: the default already caches CSS/JS/images and respects the site's headers; HTML is never cached (and must not be).
* Never use SSL mode *Flexible*: it causes an endless redirect loop.

## Phase D: Connect everything and go live
In the Render **Shell** for the web service:
```bash
DB_USER=cc_migrate DB_PASSWORD='LONG-RANDOM-2' php tools/migrate.php
php tools/preflight.php            # must show no blocking failures
```
1. Open the site, **sign up** your own account, then in the Shell: `php tools/bootstrap-admin.php you@yourdomain.com`
2. Sign in > Admin > **Security (2FA)** > set up the authenticator > **save the recovery codes**. Then set `CC_ADMIN_2FA_REQUIRED=1`.
3. Set **`APP_MAINTENANCE=0`** and let it redeploy. Until you do, visitors only see the maintenance page.
4. Run the 10-step test script in `docs/DEPLOYMENT.md` section 6.

## Verify each layer (copy-paste)
```bash
# Backend alive + what IP does the app think you are?  (open on phone AND laptop: they should differ and be your real IPs)
curl https://yourdomain.com/index.php?page=healthz

# Database over TLS from the app: Render Shell
php tools/preflight.php

# Security headers present?
curl -sI https://yourdomain.com/ | grep -i -E "strict-transport|content-security|x-frame|referrer|permissions"

# Self-hosted Bootstrap loaded (not the CDN)?
curl -sI https://yourdomain.com/public/vendor/bootstrap/bootstrap.min.css | head -1

# Capacity
python3 tools/loadtest.py https://yourdomain.com -c 300 -n 1500
```
**Time check:** create an event that ends 10 minutes from now (India time). After it ends it must show **Completed**. This proves the database clock and the app clock agree (the app sets the database session time zone from `APP_TIMEZONE`).

## HTTPS: the site always starts on https://
"HTTPS" means visitors' browsers talk to your site over an encrypted connection (TLS), proven by a certificate: the address starts with `https://` and the browser shows the padlock.
Five layers make sure no visitor ever uses plain `http://`:

| Layer | What it does | Where |
|---|---|---|
| Certificate | Render issues and renews a free certificate for your domain once DNS is verified (Phase C step 4) | Render |
| Redirect at the edge | `http://yourdomain` is redirected to `https://`. Render does this itself; with the Cloudflare proxy on, also set **Always Use HTTPS** | Render / Cloudflare |
| Redirect in the app server | If a proxy ever passes on a request marked plain-http, Apache answers `301` to the https address (only on an explicit "http", so health checks cannot loop) | `deploy/apache.conf` |
| App refuses non-HTTPS | In production any request that is not HTTPS gets "HTTPS is required"; cookies are `Secure` (and `__Host-` prefixed); CSP adds `upgrade-insecure-requests` | `config/security.php` |
| Browser memory (HSTS) | After the first visit the browser refuses to use http for a year (`Strict-Transport-Security`) | `config/security.php` |

Also required: `APP_URL` must start with `https://` (preflight fails if not), and the Google/LinkedIn sign-in addresses must be the https ones. The app never builds links from the visitor's Host header.

**Prove it (run these after Phase C):**
```bash
# 1. http must redirect to https (expect: HTTP/1.1 301 ... Location: https://yourdomain.com/)
curl -sI http://yourdomain.com/ | head -3

# 2. https answers and sends HSTS
curl -sI https://yourdomain.com/ | grep -i -E "^HTTP|strict-transport-security"

# 3. certificate details (issuer, expiry)
echo | openssl s_client -connect yourdomain.com:443 -servername yourdomain.com 2>/dev/null | openssl x509 -noout -issuer -dates
```
Then in a browser: type `yourdomain.com` (no https), confirm it lands on `https://` with a padlock, and open DevTools > Console to confirm there is **no "mixed content" warning**.
For an outside opinion run https://www.ssllabs.com/ssltest/ (aim for an A grade).
**Later, optional:** once everything has worked for a few weeks you can submit the domain to the browser HSTS preload list; do not do this on launch day because it is very hard to undo.

## Troubleshooting
| Symptom | Likely cause | Fix |
|---|---|---|
| "trouble connecting to the database" | Render's IP not in DigitalOcean Trusted Sources, wrong port, or wrong password | Add Outbound ranges; use the cluster **port** (not 3306); check `DB_*` |
| SSL / certificate error from the app to MySQL | `DB_SSL_CA` path wrong or file not committed | Path must be `/var/www/html/config/certs/ca-certificate.crt` |
| Browser says "Not secure" / no padlock | DNS not verified yet, certificate not issued, or you opened an `http://` link | Wait for Render to show the domain **Verified**; use `https://`; check Phase C |
| Site shows "maintenance" | `APP_MAINTENANCE=1` | Set to `0` (Phase D step 3) |
| Cloudflare error 525/526 or redirect loop | SSL mode wrong, or Render certificate not issued yet | Use *Full (strict)* after Render shows Verified; never *Flexible*; set records to DNS-only first |
| Every visitor has the same IP (rate limits hit everyone) | Proxy chain not trusted | `healthz` should show *your* IP. If it shows a Cloudflare/10.x address, tell me the value it shows |
| Unstyled page / broken icons | Bootstrap download failed at build and the CDN is blocked | Check the build log line `vendor download failed`; open the browser console |
| A page misbehaves and the console mentions "Content Security Policy" | A script or style source I did not anticipate | Set `CC_CSP_MODE=report-only` immediately, then send me the console message |
| Cannot sign in as admin after enabling 2FA | Lost phone | Use a recovery code, or Shell: `php tools/reset-2fa.php you@yourdomain.com` |
| Certificates not appearing for finished events | Maintenance job not running | Render logs should show `[maintenance]` output every 10 minutes |
| Event status wrong by 5.5 hours | Time-zone mismatch | Confirm `APP_TIMEZONE` (default `Asia/Kolkata`) |

## Costs and risks (be realistic)
* Three vendors means three bills and three places to look when something breaks. Prices change: check each pricing page before you commit.
* The DigitalOcean database is the one part that is *managed* (it backs itself up). Render's disk is snapshotted daily, and `BACKUP_*` gives you an independent encrypted copy; test a restore once.
* Render with a disk restarts for a few seconds on each deploy and runs a single instance. That is fine for a campus site; for much higher traffic you would need a different architecture.
* Cheaper fallback: Render for the app **and** database in one place (`docs/DEPLOYMENT.md`), with Cloudflare for DNS only.
