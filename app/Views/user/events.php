<section class="events-page" id="eventsPage">

    <?php require_once "app/Views/user/partials/event-sidebar.php"; ?>

    <div class="events-content">

        <div class="events-header">

            <div class="events-title">

                <h1>Campus Events</h1>

                <p>
                    Discover workshops, hackathons, seminars and campus activities.
                </p>

            </div>

            <div class="event-search">

                <input
                    type="text"
                    placeholder="Search events..."
                    id="eventSearch">

            </div>

        </div>

        <?php $mediaFeatured = array_slice($events, 0, 3); ?>
        <section class="cc-events-media-strip" aria-labelledby="eventsMediaTitle">
            <div class="cc-events-media-head"><div><span class="events-media-kicker"><i class="bi bi-collection-play"></i> Event previews</span><h2 id="eventsMediaTitle">See the next events before you attend</h2><p>Explore campus activities, discover upcoming events and revisit memorable moments.</p></div><a href="index.php?page=gallery" class="btn-secondary">Open Gallery <i class="bi bi-arrow-right"></i></a></div>
            <div class="cc-events-media-items">
                <a href="index.php?page=gallery"><img src="public/uploads/gallery/covers/bvraju.jpg" alt="B. V. Raju College campus" loading="lazy"><span>Campus life</span></a>
                <?php foreach($mediaFeatured as $featured): ?>
                    <?php $banner = cc_asset_url($featured['banner'] ?? '', 'public/images/event/default-event.svg', 'public/uploads/event_banners/'); ?>
                    <a href="index.php?page=event-details&id=<?= (int)$featured['id']; ?>"><img src="<?= htmlspecialchars($banner, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($featured['title']); ?>" loading="lazy" data-fallback="public/images/event/default-event.svg"><span><?= htmlspecialchars($featured['title']); ?></span></a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if(!empty($events)): ?>

            <?php foreach($events as $event): ?>

                <?php
                    $status = strtolower($currentView);
                    include "app/Views/user/partials/event-card.php";
                ?>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-events">

                <h2>No events available</h2>

                <p>
                    Please check again later.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>

<script nonce="<?= cc_nonce(); ?>">

document.addEventListener("DOMContentLoaded",()=>{

    const toggle=document.getElementById("sidebarToggle");
    const sidebar=document.getElementById("eventSidebar");
    const overlay=document.getElementById("sidebarOverlay");
    const close = document.getElementById("sidebarClose");

    function openSidebar(){

        sidebar.classList.add("show");
        document.getElementById("eventsPage").classList.add("sidebar-open");
        overlay.classList.add("show");

        toggle.style.display="none";

    }

    function closeSidebar(){

        sidebar.classList.remove("show");
        document.getElementById("eventsPage").classList.remove("sidebar-open");
        overlay.classList.remove("show");

        toggle.style.display="flex";

    }

    toggle.addEventListener("click",openSidebar);

   if(close){
    close.addEventListener("click", closeSidebar);
}

    overlay.addEventListener("click",closeSidebar);

});

</script>
