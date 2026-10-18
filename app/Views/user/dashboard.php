<section class="dashboard-page">

    <div class="dashboard-welcome">

        <h1>Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['fullname'])[0]); ?> 👋</h1>
        <p>Here's what's happening with your Campus Chronicle account.</p>

    </div>

    <div class="stats" style="margin-top:30px;">

        <div class="stat-box">
            <h1><?= count($myUpcoming); ?></h1>
            <p>Upcoming Events</p>
        </div>

        <div class="stat-box">
            <h1><?= count($myRegistrations); ?></h1>
            <p>Total Registrations</p>
        </div>

        <div class="stat-box">
            <h1><?= count($myCertificates); ?></h1>
            <p>Certificates Earned</p>
        </div>

        <div class="stat-box">
            <h1><?= count($myNotifications); ?></h1>
            <p>Recent Notifications</p>
        </div>

    </div>

    <div class="dashboard-grid">

        <div class="dashboard-panel">

            <h3>Your Upcoming Events</h3>

            <?php if (empty($myUpcoming)): ?>

                <p class="dashboard-empty">You haven't registered for any upcoming events yet.</p>
                <a href="index.php?page=events" class="btn">Browse Events</a>

            <?php else: ?>

                <?php foreach (array_slice($myUpcoming, 0, 4) as $reg): ?>
                    <a href="index.php?page=event-details&id=<?= $reg['event_id']; ?>" class="dashboard-list-item">
                        <span class="dashboard-list-title"><?= htmlspecialchars($reg['title']); ?></span>
                        <span class="dashboard-list-meta">
                            <?= date('d M Y', strtotime($reg['event_date'])); ?> &middot;
                            <span class="status-badge status-<?= strtolower($reg['status']); ?>"><?= htmlspecialchars($reg['status']); ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>

                <a href="index.php?page=my-events" class="dashboard-see-all">See all my events &rarr;</a>

            <?php endif; ?>

        </div>

        <div class="dashboard-panel">

            <h3>Recent Notifications</h3>

            <?php if (empty($myNotifications)): ?>

                <p class="dashboard-empty">Nothing new right now &mdash; you're all caught up.</p>

            <?php else: ?>

                <?php foreach ($myNotifications as $note): ?>
                    <div class="dashboard-list-item" style="cursor:default;">
                        <span class="dashboard-list-title"><?= htmlspecialchars($note['title'] ?? $note['message'] ?? 'Notification'); ?></span>
                        <?php if (!empty($note['message']) && !empty($note['title'])): ?>
                            <span class="dashboard-list-meta"><?= htmlspecialchars($note['message']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

    <div class="dashboard-quicklinks">

        <a href="index.php?page=events" class="btn">Browse Events</a>
        <a href="index.php?page=my-events" class="btn">My Events</a>
        <a href="index.php?page=certificates" class="btn">Certificates</a>
        <a href="index.php?page=profile" class="btn">Edit Profile</a>

    </div>

</section>
