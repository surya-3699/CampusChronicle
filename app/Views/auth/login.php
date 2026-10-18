<?php
require_once "app/Controllers/AuthController.php";
require_once "config/google.php";
require_once "config/linkedin.php";

$auth = new AuthController();
$auth->login();

$googleProblem = GoogleAuthConfig::configurationProblem();
$googleEnabled = $googleProblem === null;
$googleClientId = $googleEnabled ? GoogleAuthConfig::clientId() : '';
$googleState = '';
$linkedinProblem = LinkedInAuthConfig::configurationProblem();
$linkedinEnabled = $linkedinProblem === null;

if ($googleEnabled) {
    try {
        $googleState = bin2hex(random_bytes(32));
        $_SESSION['google_oauth_state'] = $googleState;
        $_SESSION['google_oauth_started_at'] = time();
    } catch (Throwable $e) {
        error_log('Unable to create Google sign-in state: ' . $e->getMessage());
        $googleEnabled = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Campus Chronicle</title>
    <link rel="stylesheet" href="public/css/auth.css">
    <link rel="stylesheet" href="public/css/liquid-glass.css">
    <link rel="stylesheet" href="public/css/video-glass-modern.css">
    <?php if ($googleEnabled): ?>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
<meta name="cc-csrf" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><script src="public/js/security.js"></script><link rel="stylesheet" href="public/css/performance.css">
<script src="public/js/scene3d.js" defer></script>
</head>

<body class="auth-liquid-glass cc-auth-video-page">

<?php include "app/Views/layouts/background.php"; ?>

<div class="cc-video-overlay" aria-hidden="true"></div>

<div class="auth-container">
    <div class="brand-panel">
        <img
            src="public/images/logo/loading-logo.png"
            alt="Campus Chronicle"
            class="brand-logo">

        <h1 class="brand-title">Campus <span>Chronicle</span></h1>

        <p class="brand-subtitle">
            Every Event. Every Memory. One Platform.
            <br><br>
            Campus Chronicle helps students discover, register and participate
            in college events through one modern platform.
        </p>

        <ul class="brand-features">
            <li>Workshops &amp; Seminars</li>
            <li>Hackathons &amp; Competitions</li>
            <li>Technical &amp; Cultural Events</li>
            <li>Certificates &amp; Gallery</li>
        </ul>
    </div>

    <div class="auth-card auth-card-login">
        <div class="login-heading">
            <h2 class="auth-title">Welcome Back</h2>
            <p class="auth-subtitle">Sign in to continue your journey.</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success" role="status">
                <?= htmlspecialchars((string) $_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error" role="alert">
                <?= htmlspecialchars((string) $_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="social-login-stack" aria-label="Social sign in options">
            <?php if ($googleEnabled): ?>
                <div class="social-provider-shell google-provider-shell">
                    <div
                        id="googleSignInButton"
                        class="google-signin-button"
                        aria-label="Continue with Google"></div>
                </div>
            <?php else: ?>
                <button
                    type="button"
                    class="social-auth-button social-auth-unavailable"
                    aria-disabled="true"
                    data-social-unavailable="Google sign-in is not available yet. Please use your email and password."
                    title="Use email sign-in for now">
                    <span class="social-icon google-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="img" focusable="false">
                            <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.39-.18-2.04H12v3.86h5.39a4.61 4.61 0 0 1-2 3.02v2.51h3.24c1.9-1.75 2.97-4.33 2.97-7.35Z"/>
                            <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.63-2.42l-3.24-2.51c-.9.6-2.05.96-3.39.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.59A10 10 0 0 0 12 22Z"/>
                            <path fill="#FBBC05" d="M6.39 13.9A6.01 6.01 0 0 1 6.08 12c0-.66.11-1.3.31-1.9V7.51H3.04A10 10 0 0 0 2 12c0 1.61.38 3.14 1.04 4.49l3.35-2.59Z"/>
                            <path fill="#EA4335" d="M12 5.97c1.47 0 2.79.51 3.83 1.5l2.87-2.87A9.63 9.63 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.59C7.18 7.73 9.39 5.97 12 5.97Z"/>
                        </svg>
                    </span>
                    <span>Continue with Google</span>
                </button>
            <?php endif; ?>

            <?php if ($linkedinEnabled): ?>
                <a
                    class="social-auth-button linkedin-auth-button"
                    href="index.php?page=linkedin-login"
                    aria-label="Continue with LinkedIn">
                    <span class="social-icon linkedin-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="img" focusable="false">
                            <rect x="2" y="2" width="20" height="20" rx="4" fill="#0A66C2"/>
                            <path fill="#fff" d="M7.23 9.23H4.57V18h2.66V9.23ZM5.9 5A1.55 1.55 0 1 0 5.9 8.1 1.55 1.55 0 0 0 5.9 5ZM18.93 12.97c0-2.64-1.41-3.87-3.29-3.87-1.52 0-2.2.84-2.58 1.43v-1.3h-2.66V18h2.66v-4.34c0-1.14.22-2.25 1.64-2.25 1.4 0 1.42 1.31 1.42 2.32V18h2.66l.15-5.03Z"/>
                        </svg>
                    </span>
                    <span>Continue with LinkedIn</span>
                </a>
            <?php else: ?>
                <button
                    type="button"
                    class="social-auth-button social-auth-unavailable"
                    aria-disabled="true"
                    data-social-unavailable="LinkedIn is ready in the app, but its Client ID and server-side Client Secret still need to be configured."
                    title="Finish LinkedIn configuration">
                    <span class="social-icon linkedin-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="img" focusable="false">
                            <rect x="2" y="2" width="20" height="20" rx="4" fill="#0A66C2"/>
                            <path fill="#fff" d="M7.23 9.23H4.57V18h2.66V9.23ZM5.9 5A1.55 1.55 0 1 0 5.9 8.1 1.55 1.55 0 0 0 5.9 5ZM18.93 12.97c0-2.64-1.41-3.87-3.29-3.87-1.52 0-2.2.84-2.58 1.43v-1.3h-2.66V18h2.66v-4.34c0-1.14.22-2.25 1.64-2.25 1.4 0 1.42 1.31 1.42 2.32V18h2.66l.15-5.03Z"/>
                        </svg>
                    </span>
                    <span>Continue with LinkedIn</span>
                </button>
            <?php endif; ?>
        </div>

        <p id="socialAuthStatus" class="social-auth-status" role="status" aria-live="polite"></p>

        <div class="auth-divider auth-divider-center" aria-hidden="true">
            <span>or</span>
        </div>

        <form method="POST" action="index.php?page=login" autocomplete="off" class="login-form"><?= cc_csrf_field(); ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    autocomplete="email"
                    required>
            </div>

            <div class="form-group">
                <label for="loginPassword">Password</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="loginPassword"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required>

                    <button
                        type="button"
                        class="toggle-password"
                        id="toggleLoginPassword"
                        aria-label="Show password"
                        aria-pressed="false">👁</button>
                </div>
            </div>

            <div class="auth-options">
                <label>
                    <input type="checkbox" name="remember">
                    Remember Me
                </label>
                <a href="index.php?page=forgot-password">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="auth-btn">Sign In</button>
        </form>

        <div class="bottom-link login-bottom-link">
            Don't have an account?
            <a href="index.php?page=signup">Create Account</a>
        </div>
    </div>
</div>

<script nonce="<?= cc_nonce(); ?>">
(function () {
    const password = document.getElementById('loginPassword');
    const toggle = document.getElementById('toggleLoginPassword');

    if (password && toggle) {
        toggle.addEventListener('click', function () {
            const shouldShow = password.type === 'password';
            password.type = shouldShow ? 'text' : 'password';
            toggle.textContent = shouldShow ? '🙈' : '👁';
            toggle.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
        });
    }

    const socialStatus = document.getElementById('socialAuthStatus');
    document.querySelectorAll('[data-social-unavailable]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!socialStatus) return;
            socialStatus.textContent = button.getAttribute('data-social-unavailable') || 'This sign-in provider is not configured yet.';
            socialStatus.classList.add('is-error');
        });
    });

    <?php if ($googleEnabled): ?>
    const googleClientId = <?= json_encode($googleClientId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const expectedGoogleState = <?= json_encode($googleState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const buttonHost = document.getElementById('googleSignInButton');
    const status = document.getElementById('socialAuthStatus');
    let googleInitialized = false;
    let resizeTimer = null;

    function setGoogleStatus(message, isError) {
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('is-error', Boolean(isError));
    }

    function submitGoogleCredential(response) {
        if (!response || !response.credential || response.state !== expectedGoogleState) {
            setGoogleStatus('Google sign-in could not be verified. Please try again.', true);
            return;
        }

        setGoogleStatus('Signing you in securely…', false);

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'index.php?page=google-login';
        form.hidden = true;

        const credential = document.createElement('input');
        credential.type = 'hidden';
        credential.name = 'credential';
        credential.value = response.credential;

        const state = document.createElement('input');
        state.type = 'hidden';
        state.name = 'state';
        state.value = response.state;

        const csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = '_csrf'; csrf.value = document.querySelector('meta[name="cc-csrf"]').content;
        form.append(credential, state, csrf);
        document.body.appendChild(form);
        form.submit();
    }

    function renderGoogleButton() {
        if (!buttonHost) return;

        if (!window.google || !google.accounts || !google.accounts.id) {
            setGoogleStatus('Google sign-in could not load. Check your connection and try again.', true);
            return;
        }

        if (!googleInitialized) {
            google.accounts.id.initialize({
                client_id: googleClientId,
                nonce: expectedGoogleState,
                callback: submitGoogleCredential,
                auto_select: false,
                cancel_on_tap_outside: true
            });
            googleInitialized = true;
        }

        const availableWidth = Math.max(240, Math.min(420, Math.floor(buttonHost.getBoundingClientRect().width || 380)));
        buttonHost.replaceChildren();

        google.accounts.id.renderButton(buttonHost, {
            type: 'standard',
            theme: 'outline',
            size: 'large',
            text: 'continue_with',
            shape: 'rectangular',
            logo_alignment: 'left',
            width: availableWidth,
            state: expectedGoogleState
        });

        setGoogleStatus('', false);
    }

    window.addEventListener('load', renderGoogleButton, { once: true });
    window.addEventListener('resize', function () {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(renderGoogleButton, 180);
    });
    <?php endif; ?>
})();
</script>

</body>
</html>
