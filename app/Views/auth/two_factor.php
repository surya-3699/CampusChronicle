<?php
$ccErr = $_SESSION['error'] ?? '';
$ccOk  = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<style>
.cc-2fa{min-height:100vh;display:grid;place-items:center;padding:110px 18px 40px}
.cc-2fa-card{width:100%;max-width:430px;padding:30px 28px;border-radius:22px;background:rgba(10,18,40,.72);border:1px solid rgba(255,255,255,.16);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);color:#eaf1ff;box-shadow:0 20px 60px rgba(0,0,0,.45)}
.cc-2fa-card h1{font-size:1.45rem;margin:0 0 6px}
.cc-2fa-card p{opacity:.82;margin:0 0 16px;line-height:1.5}
.cc-2fa-card label{display:block;font-weight:600;margin:6px 0}
.cc-2fa-card input[type=text]{width:100%;padding:14px 16px;border-radius:12px;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.08);color:#fff;font-size:1.35rem;letter-spacing:.18em;text-align:center}
.cc-2fa-card button.primary{width:100%;margin-top:14px;padding:13px;border:0;border-radius:12px;background:linear-gradient(135deg,#3b82f6,#7c3aed);color:#fff;font-weight:700;font-size:1rem;cursor:pointer}
.cc-2fa-card .msg{padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:.93rem}
.cc-2fa-card .msg.err{background:rgba(239,68,68,.18);border:1px solid rgba(239,68,68,.5)}
.cc-2fa-card .msg.ok{background:rgba(34,197,94,.16);border:1px solid rgba(34,197,94,.5)}
.cc-2fa-card details{margin-top:18px;font-size:.9rem;opacity:.9}
.cc-2fa-card code{background:rgba(255,255,255,.12);padding:1px 6px;border-radius:6px}
.cc-2fa-card button.link{background:none;border:0;color:#9db8ff;cursor:pointer;padding:0;margin-top:16px;text-decoration:underline;font-size:.9rem}
</style>
<main class="cc-2fa">
    <div class="cc-2fa-card">
        <h1>Two-step verification</h1>
        <p>Enter the 6-digit code from your authenticator app to continue to the admin area.</p>

        <?php if ($ccErr !== ''): ?><div class="msg err" role="alert"><?= htmlspecialchars((string)$ccErr, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($ccOk !== ''): ?><div class="msg ok" role="status"><?= htmlspecialchars((string)$ccOk, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

        <form method="post" action="index.php?page=two-factor" autocomplete="off">
            <?= cc_csrf_field(); ?>
            <label for="ccTotpCode">Authentication code</label>
            <input type="text" id="ccTotpCode" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required autofocus placeholder="123456">
            <button type="submit" class="primary">Verify</button>
        </form>

        <details>
            <summary>Lost your phone?</summary>
            <p>Type one of your saved recovery codes (like <code>a1b2-c3d4</code>) in the box above. Each code works once.
               If you have none left, ask the person who manages the server to run <code>php tools/reset-2fa.php</code> for your account.</p>
        </details>

        <form method="post" action="index.php?page=logout">
            <?= cc_csrf_field(); ?>
            <button type="submit" class="link">Sign out</button>
        </form>
    </div>
</main>
