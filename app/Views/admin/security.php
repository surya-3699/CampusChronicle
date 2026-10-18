<?php
/** @var array $twofa provided by the admin-security route */
$ccErr = $_SESSION['error'] ?? '';
$ccOk  = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
$ccSecretGrouped = $twofa['secret'] !== '' ? trim(chunk_split($twofa['secret'], 4, ' ')) : '';
?>
<style>
.cc-sec{max-width:760px;margin:0 auto;padding:110px 18px 50px;color:#eaf1ff}
.cc-sec h1{font-size:1.7rem;margin:0 0 6px}
.cc-sec .card{background:rgba(10,18,40,.72);border:1px solid rgba(255,255,255,.16);border-radius:20px;padding:24px;margin-top:18px;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
.cc-sec .msg{padding:10px 14px;border-radius:10px;margin-top:14px}
.cc-sec .msg.err{background:rgba(239,68,68,.18);border:1px solid rgba(239,68,68,.5)}
.cc-sec .msg.ok{background:rgba(34,197,94,.16);border:1px solid rgba(34,197,94,.5)}
.cc-sec .badge{display:inline-block;padding:4px 12px;border-radius:999px;font-weight:700;font-size:.85rem}
.cc-sec .on{background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.6)}
.cc-sec .off{background:rgba(245,158,11,.2);border:1px solid rgba(245,158,11,.6)}
.cc-sec ol{padding-left:20px;line-height:1.7}
.cc-sec .key{font:600 1.25rem/1.4 ui-monospace,Menlo,Consolas,monospace;letter-spacing:.08em;background:rgba(255,255,255,.1);padding:12px 16px;border-radius:12px;display:block;margin:10px 0;overflow-wrap:anywhere}
.cc-sec input[type=text]{padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.08);color:#fff;font-size:1.2rem;letter-spacing:.15em;width:190px;text-align:center}
.cc-sec button{padding:12px 20px;border:0;border-radius:12px;font-weight:700;cursor:pointer;color:#fff;background:linear-gradient(135deg,#3b82f6,#7c3aed);margin-left:8px}
.cc-sec button.danger{background:#b91c1c}
.cc-sec .codes{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin:12px 0}
.cc-sec .codes code{background:rgba(255,255,255,.12);padding:8px 10px;border-radius:8px;text-align:center;font-size:1.05rem}
.cc-sec a{color:#9db8ff}
.cc-sec small{opacity:.75}
</style>
<main class="cc-sec">
    <h1>Account security</h1>
    <p>Two-step verification protects the admin area even if your password is stolen.</p>

    <?php if ($ccErr !== ''): ?><div class="msg err" role="alert"><?= htmlspecialchars((string)$ccErr, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($ccOk !== ''): ?><div class="msg ok" role="status"><?= htmlspecialchars((string)$ccOk, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <?php if (!empty($twofa['recovery'])): ?>
    <section class="card">
        <h2>Save your recovery codes now</h2>
        <p>If you lose your phone, each of these codes lets you sign in once. They are shown <strong>only this one time</strong>. Store them somewhere safe (a password manager or a printout).</p>
        <div class="codes">
            <?php foreach ($twofa['recovery'] as $ccCode): ?><code><?= htmlspecialchars($ccCode, ENT_QUOTES, 'UTF-8'); ?></code><?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="card">
        <h2>Two-step verification
            <span class="badge <?= $twofa['enabled'] ? 'on' : 'off'; ?>"><?= $twofa['enabled'] ? 'ON' : 'OFF'; ?></span>
        </h2>

        <?php if ($twofa['enabled']): ?>
            <p>Signing in to the admin area needs a code from your authenticator app. Unused recovery codes: <strong><?= (int)$twofa['remaining']; ?></strong>.</p>
            <?php if ($twofa['required']): ?>
                <p><small>Two-step verification is required for every administrator on this site, so it cannot be turned off here.</small></p>
            <?php else: ?>
            <form method="post" action="index.php?page=two-factor-disable" autocomplete="off" data-confirm="Turn off two-step verification?">
                <?= cc_csrf_field(); ?>
                <p>To turn it off, enter a current code:</p>
                <input type="text" name="code" inputmode="numeric" maxlength="8" required placeholder="123456" autocomplete="one-time-code">
                <button type="submit" class="danger">Turn off</button>
            </form>
            <?php endif; ?>

        <?php elseif (!$twofa['configured']): ?>
            <p>This server is not ready for two-step verification yet. The site owner must set a secret key first:</p>
            <ol>
                <li>Generate one: <code>openssl rand -base64 32</code></li>
                <li>Save it as the environment variable <code>CC_APP_KEY</code> (in the Render dashboard) and redeploy.</li>
                <li>Reload this page.</li>
            </ol>

        <?php else: ?>
            <ol>
                <li>Install an authenticator app (Google Authenticator, Microsoft Authenticator or Authy).</li>
                <li>In the app choose <strong>Add account &rarr; Enter a setup key</strong> and type this key. Account: your email. Type: time-based.
                    <span class="key"><?= htmlspecialchars($ccSecretGrouped, ENT_QUOTES, 'UTF-8'); ?></span>
                    <small>On a phone you can also tap <a href="<?= htmlspecialchars($twofa['uri'], ENT_QUOTES, 'UTF-8'); ?>">this link</a> to open the app directly.</small></li>
                <li>Enter the 6-digit code the app shows to finish:</li>
            </ol>
            <form method="post" action="index.php?page=two-factor-enable" autocomplete="off">
                <?= cc_csrf_field(); ?>
                <input type="text" name="code" inputmode="numeric" maxlength="8" required placeholder="123456" autocomplete="one-time-code">
                <button type="submit">Turn on</button>
            </form>
            <?php if ($twofa['required']): ?><p><small>Two-step verification is required for administrators on this site.</small></p><?php endif; ?>
        <?php endif; ?>
    </section>
</main>
