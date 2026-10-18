# Campus Chronicle V7 — Event & Media Management Hardening

## User-vision corrections
- Background/cinematic videos are kept as page-background assets only; gallery and event content no longer inject decorative remote background videos.
- Gallery and event content surfaces now use real uploaded media records.
- Add/Edit Event and Add/Edit Gallery now include live public-facing previews.
- Selected local photo/video files are previewed before submission; files are not uploaded until the form is submitted.

## Event workflow
- Fixed event deletion failure caused by legacy `registrations.event_id` foreign key behavior by explicitly deleting dependent certificates, gallery media, galleries and registrations inside a transaction before deleting the event.
- Event update now appends newly selected gallery photos/videos to the event's existing album.
- Event add/update forms include media staging previews.

## Gallery workflow
- Add/Edit Gallery now accepts multiple photos/videos in the same form and saves them into the created/updated album.
- Gallery cover remains independently replaceable.
- Existing album media is preserved on edit; new media is appended.
- Fixed event selector in Edit Gallery to use the application's `events.publish` field.
- Gallery public media showcase is data-driven from published gallery media.

## QA notes
- PHP syntax lint: all project PHP files pass.
- JavaScript syntax check: admin editor script passes Node syntax validation.
- Static checks: no stale remote background video references in public Events/Gallery views; no stale string category links in user gallery views.
- ZIP integrity was verified for the prior package before producing this revision.
- Live MySQL browser smoke testing is not available in this build container because `pdo_mysql` is not installed; final verification should be performed once in the user's XAMPP/MySQL environment.
