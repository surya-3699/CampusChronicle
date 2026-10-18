# Campus Chronicle — V6 Professional Upgrade

## Approved changes implemented

- Fixed the About page category chip rail so it stays in the glass theme and the final chip cannot be clipped.
- Added a dedicated `event-register` route + professional registration form. The Event Details “Register Now” action now redirects to that form; submission returns the student to the Event Details page with a success/error message.
- Added LinkedIn buttons under the student team designations for Lahari, Surya and Sumanth.
- Added the supplied B. V. Raju College photo as the welcome-cover image without removing the existing video/glass behavior.
- Fixed the announcement `created_by` live-schema mismatch at both migration/schema level and model level.
- Added user profile banner upload + database field + live preview.
- Added event-gallery media previews directly on Event Details with a full-gallery redirect.
- Added professional media showcase strips to Events and Gallery using project assets and the two existing video sources.
- Made Template Center asset-ready for future college-supplied JPG/PNG/WEBP/PDF templates, with preview/open actions.
- Strengthened the e-certificate experience with an online verification CTA and QR verification on printable certificates. The existing certificate code verification flow remains the source of truth.

## Scope boundary

No unrelated feature area was redesigned. The only integration changes outside the visible UI are the database columns/routes/controllers required to make the requested workflows function end-to-end. Existing authentication, event management, cancellation, gallery album, and certificate routes were preserved unless a requested change required a small integration edit.

## Research direction

The event UX was benchmarked against current institutional patterns from MIT, Stanford, Harvard, IIT Bombay, IIT Madras, UCL, University of Melbourne and University of Toronto: searchable/filtered events, organization/category context, registration actions, rich event detail, campus-life media, and centralized student activity discovery.

For digital credentials, the implementation direction follows the concepts behind 1EdTech Open Badges 3.0 / Verifiable Credentials: a machine-verifiable credential should expose a stable identifier and verification path rather than being only a static image. Campus Chronicle currently provides the certificate code + public verification page + QR link; integrating full signed Open Badges/VC issuance can be a subsequent credential-provider phase after the college template and issuer metadata are finalized.
