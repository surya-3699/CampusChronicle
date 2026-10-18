# Campus Chronicle — V4 Upgrade

This version preserves the V3 visual foundation and adds the requested workflow improvements.

## Notification workflow
- Published events broadcast an unread notification to every user.
- Draft events do not notify until published.
- Published galleries broadcast an unread notification to every user.
- Draft galleries do not notify until published.
- Academic announcements are admin-only to create and broadcast to every user.
- Activity Center shows unread notifications only.
- Clicking a notification marks only the signed-in user's notification as read and navigates to the target.

## Event → Gallery workflow
- Event galleries created during event setup remain Draft/hidden.
- When an event's real end date/time has passed, its gallery is automatically published when the gallery surface is opened/admin gallery is loaded.
- If no event gallery exists, one is created automatically at completion using the event banner/default cover.

## Professional student experience
- Profile upgraded with academic/professional fields, links, skills, interests and visibility.
- My Events grouped into Upcoming, Ongoing and Completed.
- Certificates presented as a dedicated academic-recognition surface.

## Admin
- Added Academic Announcements.
- Added Template Center for Event, Gallery and Certificate templates.
- Existing admin authorization remains required for event/gallery/template/announcement management.

## Events UI
- Sidebar remains an overlay and no longer introduces a permanent layout rail.
- Sidebar typography and controls use the V3 dark glass language.
- Empty results use a compact intentional state instead of a large blank region.
- Event cards/search controls are aligned with the cinematic glass theme.

## Preservation
- V3 welcome page files are intentionally unchanged.
- Existing video routing is preserved.
