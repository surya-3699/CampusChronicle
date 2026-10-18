<div class="sidebar-toggle" id="sidebarToggle">
    ☰ <span>Events</span>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="event-sidebar" id="eventSidebar">

    <div class="sidebar-top">

        <div>
            <h2>Events</h2>
            <p>Campus Chronicle</p>
        </div>

    </div>

    <div class="sidebar-menu">

        <a href="index.php?page=events&view=upcoming<?= $currentCategory ? '&category=' . $currentCategory : '' ?>"
           class="<?= $currentView=='upcoming' ? 'active' : '' ?>">
             Upcoming events
        </a>

        <a href="index.php?page=events&view=ongoing<?= $currentCategory ? '&category=' . $currentCategory : '' ?>"
           class="<?= $currentView=='ongoing' ? 'active' : '' ?>">
             Ongoing events
        </a>

        <a href="index.php?page=events&view=completed<?= $currentCategory ? '&category=' . $currentCategory : '' ?>"
           class="<?= $currentView=='completed' ? 'active' : '' ?>">
             Completed events
        </a>

    </div>

    <hr>

    <div class="sidebar-section">

        <h4>Categories</h4>

        <ul>

            <li>
                <a href="index.php?page=events&view=<?= $currentView ?>"
                   class="<?= !$currentCategory ? 'active' : '' ?>">
                    All categories
                </a>
            </li>

            <?php foreach ($categories as $category): ?>
                <li>
                    <a href="index.php?page=events&view=<?= $currentView ?>&category=<?= $category['id'] ?>"
                       class="<?= $currentCategory == $category['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($category['category_name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>

        </ul>

    </div>

</aside>