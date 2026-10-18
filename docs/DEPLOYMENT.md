# Deployment guide (launch checklist)

> **Three-platform version (database / backend / frontend delivery on separate services): see `docs/DEPLOY-3-PLATFORMS.md`.**

## 1. What you are deploying
Campus Chronicle is **one PHP application** that renders its own pages. There is no separate frontend to host:
the "frontend" is served by the same container. You need three things:

| Part | What it needs |
|---|---|
| **App server** (PHP + pages) | PHP 8.3 with `pdo_mysql, mbstring, zip, curl, openssl`; Apache; HTTPS; a **persistent disk** (uploads live on disk); a scheduler (built into the container: `deploy/maintenance-loop.sh`) |
| **Database** | **MySQL 8 or MariaDB 10.6+** (PostgreSQL will **not** work: the schema uses MySQL features) |
| **Off-site backup storage** | Any S3-compatible bucket (Cloudflare R2, Backblaze B2, DigitalOcean Spaces, AWS S3) |

No Composer or npm install is needed: the project has no package dependencies.

## 2. Where to host it

| Option | App | Database | Verdict |
|---|---|---|---|
| **A. Render (recommended: already configured)** | Docker web service + persistent disk (`render.yaml`) | MySQL **private service** in the same Render region (Render has no managed MySQL) | Simplest, one dashboard, lowest latency. DB is self-managed: rely on the nightly dumps below. |
| **B. DigitalOcean Droplet + Managed MySQL** | One Droplet (Bangalore) running the same Docker image behind HTTPS | **DigitalOcean Managed MySQL** (automated backups, point-in-time restore, SSL) | Most robust database; needs server admin (OS updates, firewall, Docker). Best if you can look after a server. |
| C. Railway | Docker deploy + volume | MySQL/MariaDB template on a volume (self-managed container) | Fast to set up; same self-managed-DB caveat as A. |
| **Do not use** | DigitalOcean **App Platform**: its filesystem is ephemeral and it has no volumes, so uploads would vanish on every deploy. Vercel/Netlify: no PHP. | | |
| Budget alternative | Shared cPanel PHP hosting with MySQL + cron | included | Works for a small site, but you lose the Docker/Gateway stack and must set environment variables through the host's panel. |

Facts that shaped this (checked in each provider's documentation): a Render persistent disk needs a paid service, allows
**one instance only**, and causes a few seconds of downtime on every deploy; Render warns **never to recover a database from a
disk snapshot**, so use the logical dumps below.

Keep the database in the **same region** as the app (for India, Render Singapore or DigitalOcean Bangalore). Every page makes several
queries; cross-provider latency adds up.

**Files and certificates:** certificates are generated from database records; uploaded images/videos are files on the persistent disk.
Moving uploads to object storage needs code changes and is not done; a single instance with a disk (plus the off-site backups) is the supported setup.

## 3. Deploy on Render (Option A), in order

1. **Repository.** Push the project folder to a **private** GitHub repo (the folder containing `Dockerfile` is the root).
   Scan for secrets first: `gitleaks detect --source .`
2. **Database.** Create a MySQL private service in the same region following https://render.com/docs/deploy-mysql, with its own disk.
   Then create two accounts (replace the passwords):
   ```sql
   CREATE DATABASE campus_chronicle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'cc_app'@'%'     IDENTIFIED BY 'LONG-RANDOM-1';   -- used by the running site
   CREATE USER 'cc_migrate'@'%' IDENTIFIED BY 'LONG-RANDOM-2';   -- only for tools/migrate.php
   GRANT SELECT, INSERT, UPDATE, DELETE ON campus_chronicle.* TO 'cc_app'@'%';
   GRANT ALL PRIVILEGES ON campus_chronicle.* TO 'cc_migrate'@'%';
   ```
   Import `database/schema.sql` (fresh database) with the migrate account. Never use `root` in the app.
3. **Web service.** In Render choose New > Blueprint and select the repo (`render.yaml`). Set these environment variables
   (see `production.env.example` for the full list):
   `APP_URL` (https://your-domain), `DB_HOST/DB_NAME/DB_USER/DB_PASSWORD` (use `cc_app`), `CC_APP_KEY` (`openssl rand -base64 32`),
   `CC_3D_SCENE_URL` (optional), `LINKEDIN_*`, `RESEND_API_KEY / MAIL_FROM / CONTACT_NOTIFY_TO`, and the `BACKUP_*` / `RCLONE_CONFIG_OFFSITE_*` variables.
   Leave `APP_MAINTENANCE=1` for now. If MySQL is **not** on Render's private network, also set `DB_SSL=1`.
4. **First boot checks** (Render Shell on the web service):
   ```
   DB_USER=cc_migrate DB_PASSWORD=LONG-RANDOM-2 php tools/migrate.php   # adds new columns (Authorized by, 2FA)
   php tools/preflight.php                                             # must show no blocking failures
   ```
5. **Create the first admin.** Open the site, sign up normally, then in the Shell:
   `php tools/bootstrap-admin.php you@yourdomain.edu`
6. **Turn on two-step verification.** Sign in > Admin > *Security (2FA)* > follow the steps > **save the recovery codes**.
   Then set `CC_ADMIN_2FA_REQUIRED=1` so every admin must use it.
7. **Go live.** Set `APP_MAINTENANCE=0` (the blueprint starts in maintenance mode, so visitors see a maintenance page until you do this).
8. **Domain and the HTTPS padlock.** Render > your service > Settings > Custom Domains > add your domain, create the DNS record Render shows
   (CNAME for `www`, ALIAS/ANAME or A record for the bare domain). Render issues and renews the certificate automatically; the padlock appears
   when DNS has propagated (minutes to a few hours). Then update `APP_URL`, add the domain to the **Google OAuth** authorised JavaScript origins,
   and update the LinkedIn redirect URI.

## 4. Things only you can do (accounts and DNS)
* **Domain email (mailboxes):** use a mailbox provider (Zoho Mail free tier, Google Workspace, Microsoft 365). They give you MX/SPF/DKIM records to add at your DNS host.
* **Contact-form notification emails:** create a Resend account, add your domain, add the DNS records it shows, then set `RESEND_API_KEY`, `MAIL_FROM` (an address on that domain), `CONTACT_NOTIFY_TO`. Until then messages still appear in Admin > Messages.
* **Google Search Console:** add your domain (DNS TXT verification), then submit the sitemap: `https://your-domain/index.php?page=sitemap`.
* **Link preview (WhatsApp/LinkedIn):** set `APP_URL` and `CC_OG_IMAGE` (1200x630, under ~300 KB), then test by sending the link to yourself; WhatsApp caches previews, so test with a fresh URL if you change the image.
* **Google Analytics:** not installed. If you want it, send the GA4 Measurement ID; it needs a small CSP change and a cookie notice.
* **WhatsApp chat button / payment page:** these features do not exist in this project.

## 5. Backups (turn on before launch, then TEST a restore)
Set `BACKUP_REMOTE`, `BACKUP_PASSPHRASE` and the `RCLONE_CONFIG_OFFSITE_*` variables (examples in `production.env.example`).
The container then makes an encrypted database dump + uploads archive every day (02:30 India time) and keeps 30 days.
Run one by hand to confirm: `bash tools/backup-offsite.sh`

**Restore drill** (do this once on a spare database):
```bash
rclone copy offsite:BUCKET/campus-chronicle/<timestamp> ./restore
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass env:BACKUP_PASSPHRASE -in restore/db-<timestamp>.sql.gz.enc | gunzip | mysql -h HOST -u cc_migrate -p campus_chronicle_test
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass env:BACKUP_PASSPHRASE -in restore/uploads-<timestamp>.tar.gz.enc | tar -xz -C /var/data
```
Keep the passphrase in a password manager **and** somewhere offline. Backups cannot be opened without it.

## 6. Pre-launch test script (about 30 minutes)
1. `php tools/preflight.php` shows no blocking failures.
2. Sign up a test student; sign in; register for an event; admin approves; mark attendance; run `php tools/maintenance.php`; the certificate appears and `index.php?page=verify-certificate` accepts its code.
3. Admin: add an event and a gallery album, filling **Authorized by**; confirm it shows in the admin lists.
4. Contact form: submit; the message appears in Admin > Messages (and the email arrives if configured).
5. Admin sign-in asks for the 6-digit code; a recovery code works once.
6. Check headers at https://securityheaders.com (expect an A-range grade) and look for CSP errors in the browser console on every page type, including Google sign-in. If any page misbehaves, set `CC_CSP_MODE=report-only` to roll back instantly.
7. Real devices: an Android phone on Chrome and an iPhone on Safari (the 3D background, login, events, gallery, certificate print).
8. Load test from your computer: `python3 tools/loadtest.py https://your-domain -c 600 -n 3000` (see `docs/SCALING.md`).
9. Confirm the logs show different visitor IPs (not one internal address) and that a 4th wrong password for one account from one device does not affect other devices.
10. Delete the sample gallery video from the live gallery (see the copyright note in SECURITY.md).

## 7. Rollback
Previous image: set `dockerfilePath: ./Dockerfile.prefork`. Gateway off: `GATEWAY_ENABLED=0`. CSP: `CC_CSP_MODE=report-only`.
2FA locked out: `php tools/reset-2fa.php you@yourdomain.edu` (needs shell access) or temporarily set `CC_ADMIN_2FA=off`.
