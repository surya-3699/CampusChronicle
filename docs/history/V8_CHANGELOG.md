# Campus Chronicle V8 — Event & Media Management Hardening

## Fixes
- Added `GalleryMediaController::getRecentMediaWithGallery()` controller delegation so the public gallery page no longer calls an undefined controller method.
- Added `cc_asset_url()` to normalize legacy/public/upload asset paths and fall back cleanly when a stale database filename is missing on disk.
- Fixed event featured-card banner URLs when the database stores a full `public/uploads/event_banners/...` path instead of a bare filename.
- Fixed event-card banner URL normalization.
- Fixed gallery cover URL fallback for missing/stale cover records.
- Fixed gallery-album media URL normalization for the actual `gallery/images` and `gallery/videos` storage paths.
- Hardened admin gallery add/edit cover previews with safe fallback assets.

## Workflow preserved
- Admin event add/edit live preview remains local-only until save.
- Admin gallery add/edit live preview remains local-only until save.
- Uploaded event/gallery media remains database-backed content; decorative site background video is not treated as gallery content.
- Event deletion transaction removes dependent certificates, media, galleries and registrations before deleting the parent event, supporting older RESTRICT-style local schemas.

## QA
- All PHP files linted successfully.
- All project JavaScript files checked with Node syntax validation.
- Static checks repeated three times with no failures.
- Required fallback and uploaded asset files verified.
- ZIP archive integrity verified with `unzip -t`.

## Important environment note
The container does not provide the project's MySQL PDO driver, so a live XAMPP/MySQL transaction test could not be honestly performed here. The database behavior was validated statically against the reported foreign-key failure and the implemented transaction/delete order.
