# Deploy Campus Chronicle on Render

Status: deployment files and the Google Web client ID are prepared; no Render service has been created. Docker is unavailable on the test computer, so the container build, real HTTPS and host behavior require staging validation. Add the final Render origin to the Google client before live Google testing.

## 1. Keep the current site safe
Export the current MySQL database and copy ALL of public/uploads before any update. Keep both together with the old application ZIP. The two missing gallery originals from CampusChronicle_before final are included in this ZIP at their existing paths. They have not been restored into the currently running XAMPP folder. Merge uploads, never replace/delete that folder. Do not import schema.sql over your existing database.

## 2. Choose the hosting components
This is PHP with MySQL/MariaDB, not a static website. Use a Render Docker web service and a paid persistent disk mounted at /var/data. Review the service and disk charges in Render before creating them. Free Render web services cannot preserve local uploads. The included render.yaml is a configuration template, not a completed deployment. It deliberately starts with APP_MAINTENANCE=1 and automatic deploys off.

Use a separate MySQL/MariaDB database with a dedicated application account. Render Postgres is not compatible with this application's SQL. A MySQL private service on Render is one option; maintain its own persistent disk and logical SQL backups. Prefer a managed MySQL service when operational support is needed. Internet database connections must use verified TLS: set DB_SSL=1 (and DB_SSL_CA if your provider uses a private CA; otherwise the system CA bundle is used) and confirm the connection works before launch. A same-region private MySQL service on the platform's private network is the simplest option. Never expose MySQL publicly to work around connectivity.

## 3. Prepare a private repository
Extract CampusChronicle and use that folder as the repository root so Dockerfile is at its root. Keep secrets, database exports and private backups out of Git. Do not publish real student uploads in a public repository. The release includes your recovered media, so use a private repository if including it. Inspect .dockerignore before building.

## 4. Create the web service
In Render choose New > Web Service, connect the repository, select Docker, and review the paid compute plan. Add a 5 GB or appropriately sized disk at /var/data. The process listens on port 10000. Configure the settings from render.yaml and production.env.example in Render's environment UI. Do not paste passwords into source files.

Set APP_ENV=production, APP_MAINTENANCE=1, PORT=10000, CC_AUTO_MIGRATE=0, CC_STORAGE_PATH=/var/data/storage. Set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD to the private database. Set APP_URL to the actual https://YOUR-SERVICE.onrender.com URL (or the final custom domain). Set LINKEDIN_CLIENT_ID, LINKEDIN_CLIENT_SECRET and LINKEDIN_REDIRECT_URI in Render's environment UI; use the newly rotated secret and the exact public HTTPS callback. The entrypoint refuses to run without the persistent mount. It seeds bundled uploads only on first start and never overwrites an existing upload; later releases preserve the disk.

The Apache configuration marks requests HTTPS because Render terminates TLS at its edge and redirects public HTTP to HTTPS. This container is specifically for that environment; do not expose its HTTP port directly. Local XAMPP continues using its existing configuration. /var/data/storage and /var/data/sessions are private; only uploads are linked into the public application.

## 5. Import or migrate while maintenance is enabled
Use a private database console to import your existing database export. For a genuinely new empty database only, import database/schema.sql; it names campus_chronicle explicitly, so change its CREATE DATABASE and USE statements to your chosen database if needed. Do not mix a fresh schema import with an existing site's database.

From Render Shell in /var/www/html run:

```sh
php tools/migrate.php
php tools/preflight.php
```

Resolve every blocking preflight failure. Provision the first admin as described in START_HERE.md. Migrations are deliberately manual. Do not run migrations during Docker build: the build must not have database or disk access. Back up before each future migration.

## 6. Configure Google sign-in
The release contains the public Google Web client ID created for Campus Chronicle. Its local authorized JavaScript origin is `http://localhost`. After Render creates the service, add the exact `https://YOUR-SERVICE.onrender.com` origin to that same client. An origin has no `/CampusChronicle` path. Add your custom-domain origin separately if using one. Add testing users when the consent configuration requires them.

Copy only the public client ID ending in .apps.googleusercontent.com into Render's GOOGLE_CLIENT_ID environment variable. This Google Identity Services popup flow verifies the returned ID token on the server; it does not use a client secret. Do not invent a client ID. Restart/redeploy after saving the setting, then test a real Google account. Local XAMPP can use LOCAL_CLIENT_ID in config/google.php or its Apache environment. Do not change token validation to bypass a provider error. Until configured, users can sign in with email/password and see a clear explanation when selecting Google.

LinkedIn is separate: configure its client ID, private secret and exact callback URL according to the included setup guide. Neither provider has been verified against a live account in this release.

## 7. Verify staging before opening the site
Set APP_MAINTENANCE=0 after import/migration, then test: public HTTP redirects to HTTPS; secure session cookies; student and admin logins; student cannot reach admin pages; register/approve/mark attendance; participant certificate and 1st/2nd/3rd winner certificates; profile photo save then reload; upload image/video then restart and redeploy and verify both still exist; certificate template preview; notifications; logout and session expiry. Confirm /config/, /database/, /tools/, /deploy/, dotfiles and uploaded executable extensions cannot be served. Test phones/tablets and video playback on the browsers students use. Test real OAuth separately after configuration.

Do not interpret the local passing tests as proof the Render container has built or these host checks have passed. Health checks must not target a route that returns 503 during planned maintenance; use Render's initial port readiness until a deliberate application readiness endpoint is configured.

## 8. Scheduled maintenance, backups and updates
Run php tools/maintenance.php periodically. A separate Render cron job cannot mount the web service's persistent disk. If using a separate maintenance job, give it the same private database settings and its own writable private storage; its certificate/gallery database operations work without the web upload disk. Configure scheduling explicitly and verify successful logs; no scheduler is installed by this release.

Use tools/backup.php with APP_MAINTENANCE=1 and a private MySQL option file (CC_DB_OPTIONS_FILE), and copy the resulting database/upload backup off the Render disk. A backup kept only on the same disk is not sufficient. Test a restore into an isolated database and upload directory. Set CC_DUMP_BIN to the installed compatible dump program if required. Provider disk snapshots do not replace consistent MySQL logical backups.

For each update: test staging, enable maintenance, make an off-host backup, manually deploy, run migrations and preflight, verify student/admin actions and upload persistence, then disable maintenance. Persistent-disk services have deployment downtime and cannot scale to multiple instances with the same disk. Budget maintenance windows. Pin a tested Docker image digest in your release process after the first successful build; the supplied PHP 8.3 tag receives patch updates.

Rollback: enable maintenance, restore the previous application image AND a matching database/upload backup when a migration is incompatible. Never restore only the code and assume the schema matches. See ROLLBACK.md. Keep new uploads made after the backup for reconciliation; never silently overwrite them.

## References checked 22 September 2026
- https://render.com/docs/docker
- https://render.com/docs/web-services
- https://render.com/docs/disks
- https://render.com/docs/deploy-mysql
- https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid
