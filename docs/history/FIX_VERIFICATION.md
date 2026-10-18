# Campus Chronicle — Fix & Verification Report

Date: 11 September 2026

## Main fixes

- Fixed the live database/code mismatch behind the signup error (`users.username`) and forgot-password error (`users.updated_at`) by adding an additive, idempotent compatibility upgrade in `config/schema_compatibility.php`, invoked after PDO connects.
- Kept `database/schema.sql` as the fresh-install source and `database/migration.sql` as the full manual upgrade path.
- Fixed forgot-password double hashing: the controller now passes the new password once to `User::changePassword()`, which performs the hash.
- Added and hardened the Admin **Template Center → Upload template** workflow (JPG, PNG, WEBP or PDF, max 10 MB), including MIME checks, persistent file path/MIME metadata, previews, missing-file state, and POST-based deletion.
- Removed corrupt zero-byte event banner assets and changed all event/profile views to use verified assets plus real fallback SVG/default profile images.
- Hardened gallery/event uploads with MIME checks, file-size caps and detected MIME storage.
- Fixed gallery rendering so database records whose physical files are missing no longer show broken image placeholders or empty 0:00 video players. The album now clearly asks an admin to re-upload missing media.
- Fixed event lifecycle consistency: absent end times now mean end-of-day in both PHP and SQL queries, so Upcoming/Ongoing/Completed grouping is consistent.
- Fixed My Events banner fallback and included event end date/time in registration event data.
- Removed raw database exception details from certificate/contact user-facing errors.
- Added a writable certificate upload directory placeholder.

## Important note about the supplied gallery files

The provided ZIP does not contain the actual gallery photo/video files referenced by some database rows. Those missing media bytes cannot be reconstructed from PHP or SQL. The fixed application now handles that state cleanly. Re-upload the original media from the Admin Gallery/Media screen and the album will display it normally.

The `BCA EVENTS` event shown in the screenshots is dated **06 Sep 2026**. On 11 Sep 2026 it correctly belongs to Completed events, so an Upcoming + category filter can legitimately show “No events available.” No fake future event was inserted.

## Verification pass 1

- PHP syntax lint: 72 PHP files, 0 failures.
- JavaScript syntax: 6 JS files, 0 failures.
- Router: 64 cases, 64 unique; no duplicate route cases.
- Literal include/require paths: no missing project files.
- Known broken/legacy asset references: none remaining.
- CSS brace/static structure scan: no issues.
- Zero-byte project files: none remaining.

## Verification pass 2

Repeated independently after final changes:

- PHP syntax lint: 72 PHP files, 0 failures.
- JavaScript syntax: 6 JS files, 0 failures.
- Router: 64 cases, 64 unique.
- Literal public asset references checked: 39; no missing required referenced asset.
- Database schema tables parsed: 12; no model INSERT/qualified-column mismatches detected by the static SQL consistency scan.
- Regression assertion: missing/corrupt event banner falls back to `public/images/event/default-event.svg` — passed.
- Regression assertion: empty upload is rejected — passed.
- Local PHP server smoke test: welcome page HTTP 200; default event fallback SVG HTTP 200.

## Database runtime limitation of this verification environment

This container does not provide a running MySQL/MariaDB server or the `pdo_mysql` PHP extension, so database-backed pages cannot be live-executed here. The supplied XAMPP environment is expected to provide both. The two screenshot PDO failures were addressed at the schema compatibility layer and checked against all SQL columns referenced by the PHP source.

## Existing database upgrade

For the normal XAMPP defaults in `config/database.php`, the application automatically performs only additive compatibility upgrades on the first database-backed request. It does not delete existing rows.

For a controlled/manual upgrade instead:

1. Back up the `campus_chronicle` database.
2. Run `database/migration.sql` in phpMyAdmin/MySQL.
3. Optionally set environment variable `CC_AUTO_SCHEMA_UPGRADE=0` if you want to disable automatic additive checks.

For a brand-new database, import `database/schema.sql`.
