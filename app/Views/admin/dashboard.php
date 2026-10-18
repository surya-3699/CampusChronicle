<?php

require_once "app/Controllers/DashboardController.php";

$controller = new DashboardController();
$dashboard = $controller->index();

?>

<section class="admin-dashboard">
    <div class="admin-dashboard__inner">

        <div class="admin-hero">
            <div>
                <p class="admin-eyebrow"><i class="bi bi-grid-1x2-fill"></i> Administration</p>
                <h1>Welcome, <?= htmlspecialchars($_SESSION['fullname']); ?></h1>
                <p>Manage events, galleries, users and notifications from one place.</p>
            </div>
            <div class="admin-hero-badge">
                <i class="bi bi-shield-check"></i>
                <span>Admin access</span>
            </div>
        </div>

        <div class="admin-section-heading">
            <div>
                <span>Overview</span>
                <h2>Dashboard</h2>
            </div>
            <a href="index.php?page=admin-events" class="admin-outline-btn">
                <i class="bi bi-calendar3"></i> Manage events
            </a>
        </div>

        <!-- Event summary -->
        <div class="admin-stat-grid admin-stat-grid--events">
            <article class="admin-stat-card">
                <div class="admin-stat-icon"><i class="bi bi-calendar-event"></i></div>
                <div>
                    <span>Total Events</span>
                    <strong><?= (int)$dashboard['totalEvents']; ?></strong>
                </div>
            </article>

            <article class="admin-stat-card">
                <div class="admin-stat-icon"><i class="bi bi-clock-history"></i></div>
                <div>
                    <span>Upcoming Events</span>
                    <strong><?= (int)$dashboard['upcomingEvents']; ?></strong>
                </div>
            </article>

            <article class="admin-stat-card">
                <div class="admin-stat-icon"><i class="bi bi-play-circle"></i></div>
                <div>
                    <span>Ongoing Events</span>
                    <strong><?= (int)$dashboard['ongoingEvents']; ?></strong>
                </div>
            </article>

            <article class="admin-stat-card">
                <div class="admin-stat-icon"><i class="bi bi-check-circle"></i></div>
                <div>
                    <span>Completed Events</span>
                    <strong><?= (int)$dashboard['completedEvents']; ?></strong>
                </div>
            </article>
        </div>

        <!-- Platform summary -->
        <div class="admin-stat-grid admin-stat-grid--platform">
            <article class="admin-stat-card">
                <div class="admin-stat-icon"><i class="bi bi-images"></i></div>
                <div>
                    <span>Total Galleries</span>
                    <strong><?= (int)$dashboard['totalGalleries']; ?></strong>
                </div>
            </article>

            <article class="admin-stat-card">
                <div class="admin-stat-icon"><i class="bi bi-image"></i></div>
                <div>
                    <span>Total Photos</span>
                    <strong><?= (int)$dashboard['totalPhotos']; ?></strong>
                </div>
            </article>

            <article class="admin-stat-card">
                <div class="admin-stat-icon"><i class="bi bi-camera-video"></i></div>
                <div>
                    <span>Total Videos</span>
                    <strong><?= (int)$dashboard['totalVideos']; ?></strong>
                </div>
            </article>

            <article class="admin-stat-card">
                <div class="admin-stat-icon"><i class="bi bi-people"></i></div>
                <div>
                    <span>Total Users</span>
                    <strong><?= (int)$dashboard['totalUsers']; ?></strong>
                </div>
            </article>

            <article class="admin-stat-card admin-stat-card--visitors">
                <div class="admin-stat-icon"><i class="bi bi-bar-chart-line"></i></div>
                <div>
                    <span>Total Visitors</span>
                    <strong><?= (int)$dashboard['totalVisitors']; ?></strong>
                    <small>Unique browser sessions</small>
                </div>
            </article>
        </div>

        <!-- Recent events -->
        <section class="admin-panel">
            <div class="admin-panel__header">
                <div>
                    <span class="admin-panel__eyebrow">Latest content</span>
                    <h3>Recent Events</h3>
                </div>
                <a href="index.php?page=admin-events" class="admin-panel-link">View all <i class="bi bi-arrow-up-right"></i></a>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($dashboard['recentEvents'])): ?>
                        <?php foreach ($dashboard['recentEvents'] as $index => $event): ?>
                            <?php $status = Event::computeStatus($event); ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td class="admin-table__primary"><?= htmlspecialchars($event['title']); ?></td>
                                <td><?= htmlspecialchars($event['event_date']); ?></td>
                                <td><?= htmlspecialchars($event['venue']); ?></td>
                                <td><span class="admin-status admin-status--<?= strtolower($status); ?>"><?= htmlspecialchars($status); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="admin-table__empty">No events found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Recent platform data -->
        <div class="admin-content-grid">
            <section class="admin-panel admin-panel--compact">
                <div class="admin-panel__header">
                    <div>
                        <span class="admin-panel__eyebrow">Content</span>
                        <h3>Recent Galleries</h3>
                    </div>
                    <a href="index.php?page=admin-gallery" class="admin-panel-link">Manage <i class="bi bi-arrow-up-right"></i></a>
                </div>

                <div class="admin-list">
                    <?php if (!empty($dashboard['recentGallery'])): ?>
                        <?php foreach ($dashboard['recentGallery'] as $gallery): ?>
                            <div class="admin-list__item">
                                <div class="admin-list__icon"><i class="bi bi-images"></i></div>
                                <div>
                                    <strong><?= htmlspecialchars($gallery['title']); ?></strong>
                                    <span><?= htmlspecialchars($gallery['gallery_type']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="admin-empty-state">No galleries available.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="admin-panel admin-panel--compact">
                <div class="admin-panel__header">
                    <div>
                        <span class="admin-panel__eyebrow">Uploads</span>
                        <h3>Recent Media</h3>
                    </div>
                    <a href="index.php?page=admin-gallery" class="admin-panel-link">Manage <i class="bi bi-arrow-up-right"></i></a>
                </div>

                <div class="admin-list">
                    <?php if (!empty($dashboard['recentMedia'])): ?>
                        <?php foreach ($dashboard['recentMedia'] as $media): ?>
                            <div class="admin-list__item">
                                <div class="admin-list__icon"><i class="bi bi-<?= strtolower($media['media_type']) === 'video' ? 'camera-video' : 'image'; ?>"></i></div>
                                <div>
                                    <strong><?= htmlspecialchars($media['file_name']); ?></strong>
                                    <span><?= strtoupper(htmlspecialchars($media['media_type'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="admin-empty-state">No media uploaded.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="admin-panel admin-panel--compact">
                <div class="admin-panel__header">
                    <div>
                        <span class="admin-panel__eyebrow">People</span>
                        <h3>Recent Users</h3>
                    </div>
                    <a href="index.php?page=admin-users" class="admin-panel-link">Manage <i class="bi bi-arrow-up-right"></i></a>
                </div>

                <div class="admin-list">
                    <?php if (!empty($dashboard['recentUsers'])): ?>
                        <?php foreach ($dashboard['recentUsers'] as $user): ?>
                            <div class="admin-list__item admin-user-item">
                                <img
                                    src="<?= htmlspecialchars(cc_asset_url($user['profile_image'] ?? '', 'public/uploads/profile/default.png', 'public/uploads/profile/'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-fallback="public/uploads/profile/default.png"
                                    alt="<?= htmlspecialchars($user['fullname']); ?>"
                                    class="admin-user-avatar">
                                <div>
                                    <strong><?= htmlspecialchars($user['fullname']); ?></strong>
                                    <span><?= htmlspecialchars($user['email']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="admin-empty-state">No users found.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Quick actions -->
        <section class="admin-quick-actions">
            <div class="admin-panel__header">
                <div>
                    <span class="admin-panel__eyebrow">Shortcuts</span>
                    <h3>Quick Actions</h3>
                </div>
            </div>
            <div class="admin-action-grid">
                <a href="index.php?page=add-event" class="admin-action-btn">
                    <i class="bi bi-calendar-plus"></i>
                    <span>Add Event</span>
                </a>
                <a href="index.php?page=add-gallery" class="admin-action-btn">
                    <i class="bi bi-images"></i>
                    <span>Add Gallery</span>
                </a>
                <a href="index.php?page=admin-contacts" class="admin-action-btn">
                    <i class="bi bi-envelope"></i>
                    <span>Messages</span>
                </a>
                <a href="index.php?page=admin-users" class="admin-action-btn">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
                <a href="index.php?page=admin-announcements" class="admin-action-btn">
                    <i class="bi bi-megaphone"></i>
                    <span>Announcements</span>
                </a>
                <a href="index.php?page=admin-templates" class="admin-action-btn">
                    <i class="bi bi-layout-text-window-reverse"></i>
                    <span>Templates</span>
                </a>
                <a href="index.php?page=admin-security" class="admin-action-btn">
                    <i class="bi bi-shield-lock"></i>
                    <span>Security (2FA)</span>
                </a>
            </div>
        </section>

    </div>
</section>
