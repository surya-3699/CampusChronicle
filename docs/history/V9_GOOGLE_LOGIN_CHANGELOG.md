# V9 — Google Identity login polish

## Added

- Official Google Identity Services **Continue with Google** button on the Login page.
- Dark, pill-shaped Google-rendered button matching the glass login UI.
- Responsive rendering from narrow mobile cards up to desktop width.
- Google ID-token backend verification with Google public certificates.
- Per-login anti-CSRF/state verification.
- Automatic Google account creation and safe Gmail/Workspace account linking.
- `google_sub` and `auth_provider` database fields with additive compatibility migration.
- Google profile photo support through the existing asset helper.
- Setup guidance in `GOOGLE_SIGNIN_SETUP.md`.

## Login UX polish

- Replaced the password visibility span with a keyboard-accessible button.
- Added ARIA labels/status behavior.
- Escaped login success/error messages before HTML output.
- Added a clean `or continue with` separator and restrained Google sign-in wrapper.

## Important setup requirement

A Google Web OAuth Client ID is account-specific and cannot be bundled safely or correctly for another developer. Add yours in `config/google.php` or through `GOOGLE_CLIENT_ID` before testing the real Google popup.
