<?php
require_once 'config/google.php';
require_once 'config/linkedin.php';

$signupGoogleEnabled = GoogleAuthConfig::configurationProblem() === null;
$signupGoogleClientId = $signupGoogleEnabled ? GoogleAuthConfig::clientId() : '';
$signupGoogleState = '';
$signupLinkedInEnabled = LinkedInAuthConfig::configurationProblem() === null;

if ($signupGoogleEnabled) {
    try {
        $signupGoogleState = bin2hex(random_bytes(32));
        $_SESSION['google_oauth_state'] = $signupGoogleState;
        $_SESSION['google_oauth_started_at'] = time();
    } catch (Throwable $e) {
        error_log('Unable to create Google sign-up state: ' . $e->getMessage());
        $signupGoogleEnabled = false;
    }
}
?>

<?php if ($signupGoogleEnabled): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<?php endif; ?>

<div class="social-login-stack" aria-label="Social account options">
    <?php if ($signupGoogleEnabled): ?>
        <div class="social-provider-shell google-provider-shell">
            <div id="googleSignUpButton" class="google-signin-button" aria-label="Sign up with Google"></div>
        </div>
    <?php else: ?>
        <button type="button" class="social-auth-button social-auth-unavailable" aria-disabled="true"
            data-signup-social-unavailable="Google sign-up is not configured. Please use the form below.">
            <span class="social-icon google-icon" aria-hidden="true">G</span>
            <span>Sign up with Google</span>
        </button>
    <?php endif; ?>

    <?php if ($signupLinkedInEnabled): ?>
        <a class="social-auth-button linkedin-auth-button" href="index.php?page=linkedin-login" aria-label="Sign up with LinkedIn">
            <span class="social-icon linkedin-icon" aria-hidden="true">in</span>
            <span>Sign up with LinkedIn</span>
        </a>
    <?php else: ?>
        <button type="button" class="social-auth-button social-auth-unavailable" aria-disabled="true"
            data-signup-social-unavailable="LinkedIn is ready in the app, but its Client ID and server-side Client Secret still need to be configured.">
            <span class="social-icon linkedin-icon" aria-hidden="true">in</span>
            <span>Sign up with LinkedIn</span>
        </button>
    <?php endif; ?>
</div>

<p id="signupSocialAuthStatus" class="social-auth-status" role="status" aria-live="polite"></p>
<div class="auth-divider auth-divider-center" aria-hidden="true"><span>or</span></div>

<script nonce="<?= cc_nonce(); ?>">
(function () {
    const status = document.getElementById('signupSocialAuthStatus');
    document.querySelectorAll('[data-signup-social-unavailable]').forEach(function (button) {
        button.addEventListener('click', function () {
            status.textContent = button.getAttribute('data-signup-social-unavailable');
            status.classList.add('is-error');
        });
    });

    <?php if ($signupGoogleEnabled): ?>
    const clientId = <?= json_encode($signupGoogleClientId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const expectedState = <?= json_encode($signupGoogleState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const host = document.getElementById('googleSignUpButton');
    let initialized = false;
    let resizeTimer = null;

    function showStatus(message, isError) {
        status.textContent = message || '';
        status.classList.toggle('is-error', Boolean(isError));
    }

    function submitCredential(response) {
        if (!response || !response.credential || response.state !== expectedState) {
            showStatus('Google sign-up could not be verified. Please try again.', true);
            return;
        }
        showStatus('Creating your account securely…', false);
        const form = document.createElement('form');
        form.method = 'POST'; form.action = 'index.php?page=google-login'; form.hidden = true;
        [['credential', response.credential], ['state', response.state], ['_csrf', document.querySelector('meta[name="cc-csrf"]').content]].forEach(function (item) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = item[0]; input.value = item[1]; form.appendChild(input);
        });
        document.body.appendChild(form); form.submit();
    }

    function renderButton() {
        if (!host) return;
        if (!window.google || !google.accounts || !google.accounts.id) {
            showStatus('Google sign-up could not load. Check your connection and try again.', true);
            return;
        }
        if (!initialized) {
            google.accounts.id.initialize({client_id: clientId, nonce: expectedState, callback: submitCredential, auto_select: false});
            initialized = true;
        }
        const width = Math.max(240, Math.min(420, Math.floor(host.getBoundingClientRect().width || 380)));
        host.replaceChildren();
        google.accounts.id.renderButton(host, {type: 'standard', theme: 'outline', size: 'large', text: 'signup_with', shape: 'rectangular', logo_alignment: 'left', width: width, state: expectedState});
        showStatus('', false);
    }

    window.addEventListener('load', renderButton, {once: true});
    window.addEventListener('resize', function () {
        window.clearTimeout(resizeTimer); resizeTimer = window.setTimeout(renderButton, 180);
    });
    <?php endif; ?>
})();
</script>
