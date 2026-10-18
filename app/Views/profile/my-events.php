<?php
require_once "app/Models/Registration.php";
require_once "app/Models/Event.php";

$registrations = (new Registration())->getUserRegistrations($_SESSION['user_id']);
$groups = ['upcoming' => [], 'ongoing' => [], 'completed' => []];

foreach ($registrations as $reg) {
    $status = Event::computeStatus($reg);
    if ($status === 'Completed') {
        $groups['completed'][] = $reg;
    } elseif ($status === 'Ongoing') {
        $groups['ongoing'][] = $reg;
    } else {
        $groups['upcoming'][] = $reg;
    }
}
?>

<section class="cc-profile">
    <div class="cc-profile-card">
        <span class="gallery-kicker">Student activity</span>
        <h1 class="mb-2 text-white">My Events</h1>
        <p class="text-white-50 mb-0">Your registered campus activities, organized by their current lifecycle.</p>
    </div>

    <?php foreach ($groups as $key => $items): ?>
        <section class="cc-profile-card mt-3">
            <h2><?= ucfirst($key); ?> <span class="gallery-total-pill"><?= count($items); ?></span></h2>

            <?php if (!$items): ?>
                <p class="text-white-50 mb-0">No <?= htmlspecialchars($key); ?> events.</p>
            <?php else: ?>
                <div class="gallery-grid">
                    <?php foreach ($items as $reg): ?>
                        <?php
                            $banner = cc_asset_url(
                                $reg['banner'] ?? '',
                                'public/images/event/default-event.svg',
                                'public/uploads/event_banners/'
                            );
                            $lifecycle = Event::computeStatus($reg);
                        ?>
                        <article class="gallery-card">
                            <div class="gallery-cover-wrap">
                                <img
                                    class="gallery-cover"
                                    src="<?= htmlspecialchars($banner, ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?= htmlspecialchars($reg['title']); ?>"
                                    loading="lazy"
                                    data-fallback="public/images/event/default-event.svg">
                                <span class="gallery-cover-overlay"></span>
                            </div>

                            <div class="gallery-content">
                                <div class="gallery-meta-row">
                                    <span class="gallery-type"><?= htmlspecialchars($lifecycle); ?></span>
                                    <span class="gallery-counts"><?= htmlspecialchars($reg['status'] ?? 'Registered'); ?></span>
                                </div>
                                <h2><?= htmlspecialchars($reg['title']); ?></h2>
                                <p>
                                    <i class="bi bi-calendar3"></i>
                                    <?= date('d M Y', strtotime($reg['event_date'])); ?>
                                    · <i class="bi bi-geo-alt"></i>
                                    <?= htmlspecialchars($reg['venue'] ?? 'Campus'); ?>
                                </p>
                                <a class="gallery-view-btn" href="index.php?page=event-details&id=<?= (int)$reg['event_id']; ?>">
                                    View event <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</section>
