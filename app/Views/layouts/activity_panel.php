<div class="activity-panel" id="activityPanel" aria-hidden="true">

    <?php if (isLoggedIn()): ?>

        <div class="activity-header">
            <div class="activity-header-left">
                <span class="activity-kicker"><i class="bi bi-bell"></i> Activity</span>
                <h3>Activity Center</h3>
                <p>Stay updated with your latest notifications.</p>
            </div>

            <span class="activity-total" aria-live="polite">
                <?= (int)$unreadCount; ?> <?= $unreadCount === 1 ? 'New' : 'New'; ?>
            </span>
        </div>

        <div class="activity-list">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <a
                        href="<?= htmlspecialchars($notification['redirect_url']); ?>"
                        class="activity-card <?= $notification['is_read'] ? 'read' : 'unread'; ?>"
                        data-id="<?= (int)$notification['id']; ?>"
                    >
                        <div class="activity-card-top">
                            <div class="activity-title">
                                <?= htmlspecialchars($notification['title']); ?>
                                <?php if (!$notification['is_read']): ?>
                                    <span class="activity-dot" aria-label="Unread"></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="activity-message">
                            <?= htmlspecialchars($notification['message']); ?>
                        </div>

                        <div class="activity-time">
                            <?= date("d M Y • h:i A", strtotime($notification['created_at'])); ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="activity-empty">
                    <div class="activity-empty-icon"><i class="bi bi-check2-circle"></i></div>
                    <h4>You're all caught up</h4>
                    <p>There are no new notifications right now.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="activity-footer">
            <a href="index.php?page=notifications">View All Activity <i class="bi bi-arrow-right"></i></a>
        </div>

    <?php else: ?>

        <div class="activity-guest">
            <div class="activity-guest-icon"><i class="bi bi-bell"></i></div>
            <span class="activity-kicker">Stay in the loop</span>
            <h3>Activity Center</h3>
            <p>Sign in to see event updates, registration messages and your latest notifications.</p>
            <a class="activity-login-btn" href="index.php?page=login">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </a>
        </div>

    <?php endif; ?>

</div>
