# Campus Chronicle — Professional Video Background Routing

## Video 1 — Public experience
Used on the public-facing pages:
- Home
- About
- Events
- Event Details
- Gallery
- Gallery Album
- Team
- Contact

Video:
`https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260422_112520_ee819691-f2e8-4c54-bb77-3fb72c84eaa5.mp4`

## Video 2 — Account / management experience
Used on authentication and internal pages, including:
- Login
- Signup
- Forgot Password
- User Dashboard
- Profile / My Events / Certificates / Notifications
- Admin Dashboard and admin management pages

Video:
`https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260418_115655_b4d9cd77-feed-43cd-a198-af78ebdf1f7a.mp4`

## Welcome page
`app/Views/user/welcome.php` intentionally has **no video background**. Its existing animated welcome experience is preserved.

## Implementation
The shared `header.php` selects the video by the current `page` query parameter. Standalone authentication pages include the second video directly. The video system is scoped to dedicated classes so existing application styles and functionality are not globally overridden.

## Performance
The videos remain remote assets and are loaded with `preload="metadata"`; no video file is bundled into the ZIP. This keeps the application package small while preserving the requested video sources.
