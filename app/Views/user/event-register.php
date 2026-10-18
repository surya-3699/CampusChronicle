<?php
require_once "app/Models/User.php";
$currentUser = (new User())->getUserById($_SESSION['user_id']);
$banner = cc_asset_url($event['banner'] ?? '', 'public/images/event/default-event.svg', 'public/uploads/event_banners/');
$statusLower = strtolower($eventStatus);
?>
<section class="event-register-page">
    <div class="event-register-hero" style="background-image:linear-gradient(180deg,rgba(4,10,22,.20),rgba(4,10,22,.92)),url('<?= htmlspecialchars($banner, ENT_QUOTES, 'UTF-8') ?>')">
        <div class="container">
            <a href="index.php?page=event-details&id=<?= (int)$event['id'] ?>" class="back-btn"><i class="bi bi-arrow-left"></i> Back to Event</a>
            <span class="event-register-kicker"><i class="bi bi-person-plus"></i> Registration</span>
            <h1><?= htmlspecialchars($event['title']) ?></h1>
            <div class="event-register-meta">
                <span><i class="bi bi-calendar3"></i> <?= date('d M Y', strtotime($event['event_date'])) ?></span>
                <span><i class="bi bi-clock"></i> <?= date('h:i A', strtotime($event['event_time'])) ?></span>
                <?php if (!empty($event['venue'])): ?><span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['venue']) ?></span><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container event-register-layout">
        <main class="event-register-card">
            <div class="event-register-heading">
                <div><span class="gallery-kicker">Secure your place</span><h2>Event Registration Form</h2><p>Submit your participation request. Organizers can review it before approval.</p></div>
                <span class="event-register-status"><?= htmlspecialchars(ucfirst($eventStatus)) ?></span>
            </div>

            <form method="post" action="index.php?page=register-event" class="event-register-form"><?= cc_csrf_field(); ?>
                <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">

                <div class="event-register-grid">
                    <label>Full name<input value="<?= htmlspecialchars($currentUser['fullname'] ?? '') ?>" readonly></label>
                    <label>Email<input value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" readonly></label>
                    <label>Student / User ID<input value="<?= htmlspecialchars($currentUser['student_id'] ?? 'Not added') ?>" readonly></label>
                    <?php $courseDepartment = trim(($currentUser['course'] ?? '') . (!empty($currentUser['department']) ? ' · ' . $currentUser['department'] : '')); ?>
                    <label>Course / Department<input value="<?= htmlspecialchars($courseDepartment) ?>" readonly></label>
                </div>

                <label>Participation note <span class="optional">Optional</span>
                    <textarea name="notes" rows="5" maxlength="1000" placeholder="Tell the organizers anything useful about your participation, team, accessibility, or expectations."></textarea>
                </label>

                <label class="event-register-check">
                    <input type="checkbox" name="confirm" value="1" required>
                    <span>I confirm that the information above is accurate and I agree to the event rules and organizer approval process.</span>
                </label>

                <div class="event-register-actions">
                    <a class="btn-secondary" href="index.php?page=event-details&id=<?= (int)$event['id'] ?>">Cancel</a>
                    <button class="btn-primary" type="submit"><i class="bi bi-send-check"></i> Submit Registration</button>
                </div>
            </form>
        </main>

        <aside class="event-register-side">
            <article class="event-register-card compact">
                <span class="gallery-kicker">What happens next</span>
                <div class="register-step"><span>01</span><div><strong>Request sent</strong><small>Your registration is stored as pending.</small></div></div>
                <div class="register-step"><span>02</span><div><strong>Organizer review</strong><small>Faculty / event admins approve or reject the request.</small></div></div>
                <div class="register-step"><span>03</span><div><strong>Confirmation</strong><small>You can track status from My Events and notifications.</small></div></div>
                <div class="register-step"><span>04</span><div><strong>Certificate</strong><small>Eligible participation can receive a verifiable e-certificate after completion.</small></div></div>
            </article>

            <?php if (!empty($event['registration_link'])): ?>
                <article class="event-register-card compact external-form-card">
                    <span class="gallery-kicker">Organizer form</span>
                    <p>This event also has an external registration form.</p>
                    <a class="btn-secondary reg-btn-full" href="<?= htmlspecialchars($event['registration_link']) ?>" target="_blank" rel="noopener">Open External Form <i class="bi bi-box-arrow-up-right"></i></a>
                </article>
            <?php endif; ?>
        </aside>
    </div>
</section>
