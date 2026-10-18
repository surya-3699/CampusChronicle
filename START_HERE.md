# Campus Chronicle — final package

This folder is the application. Open the website through XAMPP/Apache, not by double-clicking PHP files. The main entry point is `index.php`. Everything needed for this release is inside this one ZIP.

For Render hosting, start with **RENDER-DEPLOYMENT.md**. It explains the required persistent disk, private MySQL database and Google setup.

## Update your existing XAMPP copy

1. Stop using the site while updating. Back up your current `C:\xampp\htdocs\CampusChronicle` folder and export your existing `campus_chronicle` database in phpMyAdmin.
2. Extract this ZIP into a separate folder first. Copy the application files into `C:\xampp\htdocs\CampusChronicle`. **Keep your existing database and every file in `public\uploads`.** Merge uploads; do not delete the old upload folder. Preserve your working local environment settings and OAuth settings separately. Never replace your database with the fresh schema.
3. For local HTTP testing only, retain these lines in the root `.htaccess` alongside its existing security rules:

```apache
SetEnv APP_ENV development
SetEnv DB_HOST 127.0.0.1
SetEnv DB_PORT 3306
SetEnv DB_NAME campus_chronicle
SetEnv DB_USER root
SetEnv CC_AUTO_MIGRATE 1
```

If your database has a password, configure `DB_PASSWORD` in your local server environment too. Do not share it. Remove development settings and configure production values before public hosting.

4. Start MySQL in XAMPP. Open PowerShell in `C:\xampp\htdocs\CampusChronicle` and run the migration using your database settings:

```powershell
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_NAME='campus_chronicle'
$env:DB_USER='root'
& 'C:\xampp\php\php.exe' tools\migrate.php
```

The migration must finish successfully. It adds participation/winner certificate support and preserves existing certificate codes. Run it again safely if already completed. If it reports duplicate records or another error, keep the site offline, read the private error log, and use `ROLLBACK.md` if needed. Do not delete data to silence a migration error.

5. Start/restart Apache in XAMPP and visit `http://localhost/CampusChronicle/`. Log out and back in, then refresh once to load the new scripts.

## Test the changes

**Profile:** choose a photo and banner, click **Save profile**, wait for the saved message, then reload. Image selection alone is a preview. Unsaved edits now trigger a leave-page warning.

**Certificates:** as admin, open **Events → Registrations** for an event. The **Event certificates** section includes a ready-to-use professional default design. Enter the college/organization and signatory details, save, and preview participation and winner certificates.

Approve an actual registration, then choose **Mark Attended** after confirming participation. A participation certificate is issued immediately, with an in-app notification. Registration or approval alone does not issue one. Students open **Certificates** to view or print/save it as PDF.

For an attended student, choose **1st, 2nd or 3rd prize** and select **Award winner certificate**. Winners keep their participation certificate and receive a separate winner certificate. Each student can have one prize per event; each place can be assigned once. This release does not support ties or automatic evaluation of competition results. Admins choose the winners.

For attendance recorded before upgrading, use **Issue missing participation certificates**, or run the maintenance job. Repeating it does not duplicate certificates or notifications.

**College templates later:** upload a published certificate JPG/PNG/WEBP under **Templates**, then select it in the event's certificate settings. Use an A4 landscape border/letterhead (297:210 ratio), with the centre blank. Names, recognition, event details and signatory text are added automatically in fixed positions. PDF uploads are reference files, not printable backgrounds. Preview before issuing. Existing certificates preserve the settings and details used at issuance; changing an event's template affects future certificates. Referenced templates cannot be deleted.

**Printing:** use A4 landscape, 100% scale, and turn off browser headers/footers. The default design uses local/system fonts and no external QR/image service. On a phone, the certificate document scrolls horizontally to retain its print layout. Verify certificates using their code on the public verification page.

## New installation or public hosting

For a new empty database only, import `database/schema.sql`, then run `tools/migrate.php`. Create an account through signup and promote the intended operator with `php tools/bootstrap-admin.php operator@example.edu` from a private console. No default administrator password is shipped.

Read `DEPLOYMENT-GUIDE.md` for hosting, secrets, HTTPS, OAuth, scheduled jobs and backups. Read `VERIFICATION-REPORT.md` for executed checks and remaining hosting/browser checks. `production.env.example` lists settings; it is a template and is not automatically loaded.

The original layouts and stylesheets remain intact. Performance changes use smaller local copies of the same video footage, deferred playback, hidden-tab pausing and an additional stylesheet for compositing and drawer overflow.
