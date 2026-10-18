<?php
require_once "app/Controllers/EventController.php";

$eventController = new EventController();
$events = $eventController->getAllEvents();
?>

<div class="admin-content">

    <div class="events-header">

        <div>
            <h1>Manage Events</h1>
            <p>Total Events : <?= count($events); ?></p>
        </div>

        <a href="index.php?page=add-event" class="add-event-btn">
            + Add Event
        </a>

    </div>

    <div class="table-toolbar">

        <input
            type="text"
            id="eventSearch"
            placeholder="Search events..."
            class="search-box">

    </div>

    <div class="table-wrapper">

        <table class="premium-table" id="eventTable">

            <thead>

            <tr>

                <th>ID</th>

                <th>Banner</th>

                <th>Event</th>

                <th>Category</th>

                <th>Date</th>

                <th>Venue</th>

                <th>Authorized by</th>

                <th>Status</th>

                <th>Publish</th>

                <th>Actions</th>

            </tr>

            </thead>

            <tbody>

            <?php if(!empty($events)): ?>

                <?php foreach($events as $event): ?>

                    <?php

                    // Event::computeStatus() also copes with events that have no end date/time.
                    $status = Event::computeStatus($event);
                    $statusClass = strtolower($status);

                    ?>

                    <tr>

                        <td><?= $event['id']; ?></td>

                        <td>

                            <?php $eventThumb = cc_asset_url($event['banner'] ?? '', 'public/images/event/default-event.svg', 'public/uploads/event_banners/'); ?>
                            <img
                                src="<?= htmlspecialchars($eventThumb, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?= htmlspecialchars($event['title'] ?? 'Event', ENT_QUOTES, 'UTF-8'); ?>"
                                class="event-thumb"
                                data-fallback="public/images/event/default-event.svg">

                        </td>

                        <td>

                            <strong><?= htmlspecialchars($event['title']); ?></strong>

                        </td>

                        <td><?= htmlspecialchars($event['category_name']); ?></td>

                        <td>

                            <?= date('d M Y',strtotime($event['event_date'])); ?>

                        </td>

                        <td><?= htmlspecialchars($event['venue']); ?></td>

                        <td class="authorized-by-cell"><?php $authorizedBy = trim((string)($event['authorized_by'] ?? '')); ?><?php if($authorizedBy !== ''): ?><span class="authorized-by"><i class="bi bi-patch-check-fill"></i> <?= htmlspecialchars($authorizedBy); ?></span><?php else: ?><span class="authorized-by missing">Not recorded</span><?php endif; ?></td>

                        <td>

                            <span class="status-badge <?= $statusClass; ?>">

                                <?= $status; ?>

                            </span>

                        </td>

                        <td>

                            <?php if($event['publish']=="Yes"): ?>

                                <span class="publish yes">Published</span>

                            <?php else: ?>

                                <span class="publish no">Hidden</span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <a href="index.php?page=admin-registrations&event_id=<?= $event['id']; ?>" class="action view">

                                Registrations

                            </a>

                            <a href="index.php?page=edit-event&id=<?= $event['id']; ?>" class="action edit">

                                Edit

                            </a>

                            <a
                                href="index.php?page=delete-event&id=<?= $event['id']; ?>"
                                class="action delete"
                                data-confirm="Delete this event?">

                                Delete

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="10" style="text-align:center">

                        No Events Found

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<script nonce="<?= cc_nonce(); ?>">

const search=document.getElementById("eventSearch");

search.addEventListener("keyup",function(){

const value=this.value.toLowerCase();

const rows=document.querySelectorAll("#eventTable tbody tr");

rows.forEach(row=>{

row.style.display=row.innerText.toLowerCase().includes(value)?"":"none";

});

});

</script>

<style>
.authorized-by{display:inline-flex;align-items:center;gap:6px;font-weight:600;overflow-wrap:anywhere}
.authorized-by i{color:#22c55e}
.authorized-by.missing{font-weight:500;font-style:italic;opacity:.6}
.authorized-by-cell{min-width:150px}
</style>
