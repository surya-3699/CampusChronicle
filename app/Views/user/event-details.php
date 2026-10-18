<?php
    $banner = cc_asset_url(
        $event['banner'] ?? '',
        'public/images/event/default-event.svg',
        'public/uploads/event_banners/'
    );

    $eventMediaAvailable = array_values(array_filter($eventMedia ?? [], function ($media) {
        $type = strtolower((string)($media['media_type'] ?? 'image'));
        $base = $type === 'video'
            ? 'public/uploads/gallery/videos/'
            : 'public/uploads/gallery/images/';
        return cc_asset_exists($media['file_path'] ?? '', $base);
    }));

    $statusLower = strtolower($eventStatus);
    $spotsFull = !empty($event['max_registrations']) && $event['current_registrations'] >= $event['max_registrations'];
?>

<section class="event-details-page">

    <div class="event-details-hero" style="background-image:linear-gradient(180deg, rgba(4,10,22,.35), rgba(4,10,22,.92)), url('<?= htmlspecialchars($banner) ?>')">
        <div class="container">
            <a href="index.php?page=events" class="back-btn">&larr; Back to Events</a>

            <div class="event-details-badges">
                <span class="status-badge status-<?= $statusLower ?>"><?= ucfirst($eventStatus) ?></span>
                <span class="event-category"><?= htmlspecialchars($event['category_name'] ?? 'General') ?></span>
            </div>

            <h1><?= htmlspecialchars($event['title']) ?></h1>

            <div class="event-details-quickmeta">
                <span>&#128197; <?= date('l, d M Y', strtotime($event['event_date'])) ?></span>
                <?php if (!empty($event['venue'])): ?>
                    <span>&#128205; <?= htmlspecialchars($event['venue']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container event-details-body">

        <div class="event-details-main">

            <?php if (!empty($event['description'])): ?>
                <div class="details-block">
                    <h3>About the Event</h3>
                    <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($event['schedule'])): ?>
                <div class="details-block">
                    <h3>Schedule</h3>
                    <p><?= nl2br(htmlspecialchars($event['schedule'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($event['rules'])): ?>
                <div class="details-block">
                    <h3>Rules &amp; Guidelines</h3>
                    <p><?= nl2br(htmlspecialchars($event['rules'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($event['prizes'])): ?>
                <div class="details-block">
                    <h3>Prizes</h3>
                    <p><?= nl2br(htmlspecialchars($event['prizes'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($event['faculty_name']) || !empty($event['student_name'])): ?>
                <div class="details-block">
                    <h3>Coordinators</h3>
                    <div class="coordinators-grid">

                        <?php if (!empty($event['faculty_name'])): ?>
                            <div class="coordinator-card">
                                <img src="<?= htmlspecialchars(cc_asset_url($event['faculty_photo'] ?? '', 'public/uploads/profile/default.png', 'public/uploads/event_banners/'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($event['faculty_name']) ?>">
                                <div>
                                    <strong><?= htmlspecialchars($event['faculty_name']) ?></strong>
                                    <span>Faculty Coordinator</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($event['student_name'])): ?>
                            <div class="coordinator-card">
                                <img src="<?= htmlspecialchars(cc_asset_url($event['student_photo'] ?? '', 'public/uploads/profile/default.png', 'public/uploads/event_banners/'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($event['student_name']) ?>">
                                <div>
                                    <strong><?= htmlspecialchars($event['student_name']) ?></strong>
                                    <span>Student Coordinator</span>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endif; ?>

            <?php if ($eventGallery && $eventGallery['status'] === 'Published'): ?>
                <div class="details-block event-media-preview">
                    <div class="event-media-heading"><div><h3>Event Gallery</h3><p>Photos and videos from this event are ready to view.</p></div><a href="index.php?page=gallery-album&id=<?= (int)$eventGallery['id'] ?>" class="btn-secondary">Open full gallery <i class="bi bi-arrow-right"></i></a></div>
                    <?php if (!empty($eventMediaAvailable)): ?>
                        <div class="event-media-grid">
                            <?php foreach (array_slice($eventMediaAvailable, 0, 6) as $media): ?>
                                <?php
                                    $type = strtolower((string)($media['media_type'] ?? 'image'));
                                    $base = $type === 'video'
                                        ? 'public/uploads/gallery/videos/'
                                        : 'public/uploads/gallery/images/';
                                    $mediaPath = cc_normalize_asset_path($media['file_path'] ?? '', $base);
                                ?>
                                <div class="event-media-tile">
                                    <?php if (strtolower($media['media_type'] ?? '') === 'image'): ?>
                                        <img src="<?= htmlspecialchars($mediaPath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($media['title'] ?? 'Event media') ?>" loading="lazy">
                                    <?php else: ?>
                                        <video controls preload="metadata"><source src="<?= htmlspecialchars($mediaPath, ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars($media['mime_type'] ?? 'video/mp4') ?>"></video>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (!empty($eventMedia)): ?>
                        <p class="reg-note">The gallery records exist, but the uploaded files are missing from this project copy. Re-upload them from the admin gallery manager.</p>
                    <?php endif; ?>
                    <a href="index.php?page=gallery-album&id=<?= (int)$eventGallery['id'] ?>" class="btn-secondary event-gallery-wide-link">View all photos &amp; videos <i class="bi bi-images"></i></a>
                </div>
            <?php endif; ?>

        </div>

        <aside class="event-details-sidebar">
            <div class="details-card">

                <div class="details-row">
                    <span class="details-icon">&#128197;</span>
                    <span><?= date('l, d M Y', strtotime($event['event_date'])) ?><?= (!empty($event['end_date']) && $event['end_date'] !== $event['event_date']) ? ' &ndash; ' . date('d M Y', strtotime($event['end_date'])) : '' ?></span>
                </div>

                <div class="details-row">
                    <span class="details-icon">&#128337;</span>
                    <span>
                        <?= date('h:i A', strtotime($event['event_time'])) ?>
                        <?= !empty($event['end_time']) ? ' &ndash; ' . date('h:i A', strtotime($event['end_time'])) : '' ?>
                    </span>
                </div>

                <?php if (!empty($event['venue'])): ?>
                    <div class="details-row">
                        <span class="details-icon">&#128205;</span>
                        <span><?= htmlspecialchars($event['venue']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($event['max_registrations'])): ?>
                    <div class="details-row">
                        <span class="details-icon">&#128101;</span>
                        <span><?= (int)$event['current_registrations'] ?> / <?= (int)$event['max_registrations'] ?> registered</span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($event['google_map'])): ?>
                    <a href="<?= htmlspecialchars(cc_safe_url($event['google_map'])) ?>" target="_blank" rel="noopener" class="map-link">View on map &rarr;</a>
                <?php endif; ?>

                <hr>

                <div id="registerArea" data-event-id="<?= $event['id'] ?>" data-status="<?= $statusLower ?>">

                    <?php if ($statusLower === 'completed'): ?>

                        <p class="reg-note">This event has ended.</p>

                    <?php elseif (!isLoggedIn()): ?>

                        <a href="index.php?page=login" class="btn-primary reg-btn-full">Log in to Register</a>

                    <?php elseif ($registrationStatus === 'Pending'): ?>

                        <p class="reg-note reg-pending">&#9203; Registration pending approval</p>
                        <button type="button" class="btn-secondary reg-btn-full" id="cancelRegBtn">Cancel Registration</button>

                    <?php elseif ($registrationStatus === 'Approved'): ?>

                        <p class="reg-note reg-approved">&#10003; You're registered</p>
                        <button type="button" class="btn-secondary reg-btn-full" id="cancelRegBtn">Cancel Registration</button>

                    <?php elseif ($registrationStatus === 'Attended'): ?>

                        <p class="reg-note reg-approved">&#10003; Attendance marked</p>

                    <?php elseif ($registrationStatus === 'Rejected'): ?>

                        <p class="reg-note reg-rejected">Registration was not approved</p>

                    <?php elseif ($registrationStatus === 'Cancelled'): ?>

                        <p class="reg-note">You cancelled your registration for this event.</p>

                    <?php elseif ($spotsFull): ?>

                        <p class="reg-note reg-rejected">Registration is full</p>

                    <?php else: ?>

                        <a href="index.php?page=event-register&id=<?= (int)$event['id'] ?>" class="btn-primary reg-btn-full">Register Now <i class="bi bi-arrow-right"></i></a>

                    <?php endif; ?>

                </div>

                <?php if (!empty($event['registration_link'])): ?>
                    <a href="<?= htmlspecialchars(cc_safe_url($event['registration_link'])) ?>" target="_blank" rel="noopener" class="btn-secondary reg-btn-full external-reg-link">
                        External Registration Form &#8599;
                    </a>
                <?php endif; ?>

            </div>
        </aside>

    </div>

</section>

<script nonce="<?= cc_nonce(); ?>">
(function () {
    var area = document.getElementById('registerArea');
    if (!area) return;

    var eventId = area.dataset.eventId;
    var cancelBtn = document.getElementById('cancelRegBtn');

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (!window.confirm('Cancel your registration for this event?')) return;
            cancelBtn.disabled = true;
            cancelBtn.textContent = 'Cancelling...';

            var body = new URLSearchParams();
            body.append('event_id', eventId);

            fetch('index.php?page=cancel-registration', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        cancelBtn.disabled = false;
                        cancelBtn.textContent = 'Cancel Registration';
                        window.alert(data.message || 'Unable to cancel registration.');
                    }
                })
                .catch(function () {
                    cancelBtn.disabled = false;
                    cancelBtn.textContent = 'Cancel Registration';
                    window.alert('Network error. Please try again.');
                });
        });
    }
})();
</script>
