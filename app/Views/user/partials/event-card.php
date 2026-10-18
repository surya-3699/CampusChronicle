<div class="event-card">

    <?php
        $banner = cc_asset_url(
            $event['banner'] ?? '',
            "public/images/event/default-event.svg",
            "public/uploads/event_banners/"
        );
    ?>

    <!-- Banner -->
    <div class="event-banner">
        <img src="<?= htmlspecialchars($banner, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($event['title']) ?>" loading="lazy" decoding="async" data-fallback="public/images/event/default-event.svg">
    </div>

    <!-- Content -->
    <div class="event-info">

        <!-- Header -->
        <div class="event-top">

            <div class="event-heading">

                <span class="status-badge status-<?= strtolower($status) ?>">
                    <?= ucfirst($status) ?>
                </span>

                <p class="event-category"><time datetime="<?= htmlspecialchars($event['event_date'], ENT_QUOTES, 'UTF-8') ?>"><?= date('d F Y', strtotime($event['event_date'])) ?></time></p>
                <h2><?= htmlspecialchars($event['title']) ?></h2>

                <div class="event-category">
                    <?= htmlspecialchars($event['category_name'] ?? 'General') ?>
                </div>

            </div>

        </div>


        <!-- Description -->

        <p class="event-description">

            <?php $description = trim(strip_tags($event['description'] ?? '')); ?>
            <?= htmlspecialchars(mb_strimwidth($description, 0, 180, '…'), ENT_QUOTES, 'UTF-8') ?>

        </p>


        <!-- Event Details -->

        <div class="event-meta">

            <div>
                📅
                <span><?= date('d M Y',strtotime($event['event_date'])) ?></span>
            </div>

            <div>
                🕒
                <span>
                    <?= date('h:i A',strtotime($event['event_time'])) ?>
                    -
                    <?= !empty($event['end_time']) ? date('h:i A',strtotime($event['end_time'])) : '—' ?>
                </span>
            </div>

            <div>
                📍
                <span><?= htmlspecialchars($event['venue']) ?></span>
            </div>

        </div>


        <!-- Buttons -->

        <div class="event-actions">

            <a href="index.php?page=event-details&id=<?= $event['id'] ?>"
               class="btn-primary">

                View Details

            </a>


            <?php if(strtolower($status)=="upcoming"): ?>

                <?php if(!empty($event['registration_link'])): ?>

                    <a href="<?= htmlspecialchars(cc_safe_url($event['registration_link'])) ?>"
                       target="_blank"
                       class="btn-secondary">

                        Register Now

                    </a>

                <?php endif; ?>


            <?php elseif(strtolower($status)=="completed"): ?>

                <a href="index.php?page=gallery-album&event_id=<?= $event['id'] ?>"
                   class="btn-secondary">

                    View Gallery

                </a>


            <?php else: ?>

                <button class="btn-secondary">

                    🔴 Live Now

                </button>

            <?php endif; ?>

        </div>

    </div>

</div>
