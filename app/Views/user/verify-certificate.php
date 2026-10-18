<?php
$cert = ($result && !empty($result['valid'])) ? $result['certificate'] : null;
$searched = $code !== '';
?>

<section class="verify-page">
    <div class="container verify-container">

        <div class="verify-head">
            <h1>Verify a Certificate</h1>
            <p>Enter the certificate code printed at the bottom of any Campus Chronicle certificate to confirm it's genuine.</p>
        </div>

        <form method="GET" action="index.php" class="verify-form">
            <input type="hidden" name="page" value="verify-certificate">
            <input
                type="text"
                name="code"
                value="<?= htmlspecialchars($code) ?>"
                placeholder="e.g. CC-00012-00034-1765432100-9F3A2B"
                autocomplete="off"
                required>
            <button type="submit">Verify</button>
        </form>

        <?php if ($searched): ?>

            <?php if ($cert): ?>

                <div class="verify-result valid">
                    <div class="verify-result-icon">✓</div>
                    <div class="verify-result-body">
                        <h3>Certificate Verified</h3>
                        <p>This is a genuine certificate issued by Campus Chronicle.</p>

                        <dl>
                            <dt>Recipient</dt>
                            <dd><?= htmlspecialchars($cert['fullname']) ?></dd>

                            <dt>Event</dt>
                            <dd><?= htmlspecialchars($cert['event_title']) ?></dd>

                            <?php if (!empty($cert['event_date'])): ?>
                                <dt>Event Date</dt>
                                <dd><?= date('d M Y', strtotime($cert['event_date'])) ?></dd>
                            <?php endif; ?>

                            <dt>Recognition</dt>
                            <dd><?= htmlspecialchars($cert['certificate_type'] ?? 'Participation') ?><?= !empty($cert['prize_rank'])?' — '.([1=>'First',2=>'Second',3=>'Third'][(int)$cert['prize_rank']] ?? '').' Prize':'' ?></dd>
                            <dt>Issued On</dt>
                            <dd><?= date('d M Y', strtotime($cert['generated_at'])) ?></dd>

                            <dt>Certificate Code</dt>
                            <dd><code><?= htmlspecialchars($cert['certificate_code']) ?></code></dd>
                        </dl>
                    </div>
                </div>

            <?php else: ?>

                <div class="verify-result invalid">
                    <div class="verify-result-icon">✕</div>
                    <div class="verify-result-body">
                        <h3>Not Found</h3>
                        <p>We couldn't match that code to a certificate. Double-check it and try again.</p>
                    </div>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</section>

<style>
.verify-page{ padding:70px 20px 100px; min-height:60vh; }
.verify-container{ max-width:640px; margin:0 auto; }
.verify-head{ text-align:center; margin-bottom:36px; }
.verify-head h1{ font-size:2.1rem; color:#fff; margin-bottom:10px; }
.verify-head p{ color:rgba(255,255,255,.72); line-height:1.6; }

.verify-form{
    display:flex;
    gap:10px;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.14);
    padding:10px;
    border-radius:14px;
    backdrop-filter:blur(10px);
}
.verify-form input{
    flex:1;
    background:transparent;
    border:none;
    outline:none;
    color:#fff;
    font-size:.95rem;
    padding:12px 14px;
}
.verify-form input::placeholder{ color:rgba(255,255,255,.4); }
.verify-form button{
    background:linear-gradient(135deg,#004e92,#00bcd4);
    color:#fff;
    border:none;
    padding:12px 26px;
    border-radius:10px;
    font-weight:600;
    cursor:pointer;
    transition:transform .2s ease, box-shadow .2s ease;
}
.verify-form button:hover{ transform:translateY(-2px); box-shadow:0 8px 22px rgba(0,197,223,.35); }

.verify-result{
    margin-top:30px;
    display:flex;
    gap:18px;
    padding:26px;
    border-radius:16px;
    align-items:flex-start;
}
.verify-result.valid{
    background:rgba(34,197,94,.1);
    border:1px solid rgba(34,197,94,.4);
}
.verify-result.invalid{
    background:rgba(239,68,68,.1);
    border:1px solid rgba(239,68,68,.4);
}
.verify-result-icon{
    width:44px; height:44px;
    min-width:44px;
    border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:1.3rem;
    font-weight:700;
}
.verify-result.valid .verify-result-icon{ background:#22c55e; color:#04140b; }
.verify-result.invalid .verify-result-icon{ background:#ef4444; color:#2a0505; }
.verify-result-body h3{ color:#fff; margin-bottom:6px; }
.verify-result-body p{ color:rgba(255,255,255,.75); margin-bottom:14px; line-height:1.5; }
.verify-result-body dl{ display:grid; grid-template-columns:auto 1fr; gap:8px 16px; }
.verify-result-body dt{ color:rgba(255,255,255,.5); font-size:.82rem; text-transform:uppercase; letter-spacing:.5px; }
.verify-result-body dd{ color:#fff; font-weight:500; }
.verify-result-body code{
    background:rgba(255,255,255,.1);
    padding:2px 8px;
    border-radius:6px;
    font-size:.85rem;
    color:#00e5ff;
}

@media (max-width:600px){
    .verify-form{ flex-direction:column; }
    .verify-result{ flex-direction:column; }
}
</style>
