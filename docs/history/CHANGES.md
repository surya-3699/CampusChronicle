# Campus Chronicle — What Changed

This document explains what was broken, what's fixed, and how to get the
updated project running on your XAMPP setup.

## 1. Getting it running

1. Copy this whole folder into `C:\xampp\htdocs\CampusChronicle` (replacing
   the old one — back it up first if you want to keep it).
2. Open phpMyAdmin.
   - **If you're starting fresh / don't need your old data:** drop your old
     `campus_chronicle` database, create a new one, and import
     `database/schema.sql`. It already seeds the 6 default categories.
   - **If you want to keep your existing events/users/etc:** back up your
     database first, then import `database/migration.sql` into your
     *existing* `campus_chronicle` database. It only adds what's missing
     (new columns, the new `certificates` table, etc.) and does not delete
     any of your data. It's safe to run more than once.
3. Open `config/database.php` and confirm the username/password match your
   MySQL setup (defaults to `root` / no password, which is standard XAMPP).
4. Visit `http://localhost/CampusChronicle/` as usual.

The very first account you register becomes the admin automatically — you
don't need to edit the database by hand.

## 2. The bugs from your screenshot and message — root causes

- **"Create event" crashed (your screenshot):** the code building the event
  data and the SQL statement saving it had drifted out of sync — one had a
  `max_registrations` field the other didn't know about. Fixed, and the two
  are now kept in one place so they can't drift apart again.
- **Admin gallery/media uploads crashed:** the code was calling
  `FileUpload::upload()`, a method that didn't exist anywhere in the
  codebase. Added it, with a consistent, well-documented return format.
- **"Create Account" silently failed:** the signup code was inserting into a
  `username` column that didn't exist in the database. The database now has
  it (auto-generated from the name/email, since your signup form doesn't
  ask for one), and I traced the rest of the signup/login/forgot-password
  flow end-to-end to confirm nothing else was blocking it.
- **Every event banner image was broken, site-wide:** uploads were saved to
  `public/uploads/event_banners/`, but 6 different pages were all looking in
  `public/uploads/events/` (a folder that only ever existed empty). Fixed
  everywhere.

## 3. Everything else that was fixed or added

**Admin**
- Category dropdowns (add/edit event) now pull from a real `categories`
  table instead of a hardcoded list.
- Added `max_registrations` and an external registration link field to the
  add-event form (the backend already supported them; the form just never
  asked).
- The event sidebar's category list is now clickable and actually filters.
- New **Registrations** admin page: approve/reject registrations, mark
  attendance, generate certificates — this wires up to logic that already
  existed in the code but had no page to use it from.
- New **Contacts** inbox and **Users** management admin pages, for the same
  reason — the models were fully built but had no admin page.
- Removed a dead, duplicate `AdminController.php` that called a method that
  didn't exist.

**Events**
- Built the event details page — it existed as an empty file with no route,
  so "View Details" silently sent people back to the loading screen. It now
  shows the full description, rules, prizes, schedule, coordinators, map
  link, and a working register/cancel button.
- Fixed a bug where event text (titles, descriptions, etc.) containing an
  `&`, quote, or similar character was being HTML-escaped twice, so it
  displayed literally as `&amp;` instead of `&`.

**Galleries**
- Fixed the gallery lightbox (click-to-enlarge) — the JavaScript for it
  existed but was never loaded on the page.
- Fixed a bug where "linked event" dropdown in gallery creation was
  filtering by the wrong column and would always show zero events.

**Account pages**
- "My Events" and "Certificates" always said "nothing here yet" regardless
  of your actual data — now they show your real registrations/certificates.
- Built out a real personal dashboard (was a blank page before).
- Built a working certificate system: generate → download (a clean,
  print-ready certificate page) → public verification by code.
- Wired the Contact page's form to the backend that was already built for
  it (the form had no `name` attributes or JavaScript at all before).

**Look and feel**
- Welcome/loading screen now uses your campus photo as the background,
  fixed a broken CSS animation (the spinning ring had no duration set, so it
  never actually spun), and added a "skip intro" link.
- Fixed the navbar permanently: it used `position:fixed` with a hardcoded
  `padding-top` on the page that didn't match the navbar's real height, so
  it overlapped content. It now uses `position:sticky`, which can't drift
  out of sync like that again — and it's shorter, as asked.
- Added a real mobile menu (hamburger + slide-in panel) — there wasn't one
  before at any screen size.
- Replaced a mismatched, off-brand rainbow logo used on the login/signup
  screens with your actual Campus Chronicle logo (the one already used on
  the loading screen).
- Redesigned the footer (was a single copyright line) and the About page
  (now shows real, live stats pulled from your database).
- Removed several dead/unused files and a good chunk of leftover JavaScript
  from an earlier, abandoned version of the site.

## 4. How this was tested

Since I can't preview a live XAMPP install directly, I set up a real
MySQL-compatible database and PHP server in my working environment and
exercised the app end-to-end: signup, login, creating an event with a
banner upload, creating a gallery with a cover upload, uploading gallery
photos, registering for an event, approving it as admin, generating and
downloading a certificate, verifying it publicly, submitting the contact
form, and more — about 45 checks in total, all passing with no PHP errors,
plus a visual check of the key pages. All 63 PHP files pass a syntax check.

That said, I don't have a way to test against your *exact* MySQL/PHP
version on Windows, so it's worth clicking through the app once after you
install it. If anything looks off, tell me what you're seeing and I'll fix
it.

## Professional Video Glass v3 — September 2026

- Restored the exact welcome-screen experience from the Professional Video Glass v2 baseline.
- Added privacy-conscious total visitor counting using one anonymized session hash per browser session.
- Removed the Event Statistics and Gallery Statistics dashboard charts/cards.
- Rebuilt the admin dashboard with one consistent glass visual system and added Total Visitors.
- Reworked the public gallery and album media grid for clean, aligned responsive presentation.
- Normalized legacy gallery cover/media paths and added safe image fallbacks.
- Reworked the Activity Center for guests and signed-in users with consistent glass controls and reliable notification-read navigation.
- Made login, signup and forgot-password form targets explicit to prevent accidental submit-route mismatches.
# Current release

- Configured the Campus Chronicle Google Web OAuth client and verified the real Google Identity Services button on the authorized localhost origin.
- Shows LinkedIn on login and signup with a clear disabled setup state until its server-side credentials are configured; configured installations use the OpenID Connect authorization-code flow.
- Added an editable, database-backed institution name to student profiles, including idempotent upgrade and fresh-install schema support.
- Added Google and LinkedIn signup choices. Verified Gmail identities can safely link an existing matching password account; external-domain emails still require an explicit link rather than a silent merge.
- Added three-account identity regression coverage, repeat-sign-in checks, provider-collision checks and institution persistence coverage. Each complete pass reports 291 passing assertions.
