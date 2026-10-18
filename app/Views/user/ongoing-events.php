<?php

require_once "app/Controllers/EventController.php";

$eventController = new EventController();

$events = $eventController->getOngoingEvents();

?>

<section class="about-section">

    <div class="section-title">

        <h1>Ongoing Events</h1>

        <p>Explore all ongoing campus events.</p>

    </div>

    <div class="features">

        <?php if (!empty($events)) : ?>

            <?php foreach ($events as $event) : ?>

                <div class="feature-card">

                    <h3><?= htmlspecialchars($event['title']); ?></h3>

                    <p><strong>Category:</strong> <?= htmlspecialchars($event['category_name'] ?? 'N/A'); ?></p>

                    <p><strong>Date:</strong> <?= date('d M Y', strtotime($event['event_date'])); ?></p>

                    <p><strong>Venue:</strong> <?= htmlspecialchars($event['venue']); ?></p>

                    <?php if (!empty($event['registration_link'])) : ?>

                        <a href="<?= htmlspecialchars($event['registration_link']); ?>" target="_blank">
                            <button class="btn-primary">Register</button>
                        </a>

                    <?php else : ?>

                        <button class="btn-primary" disabled>
                            Registration Closed
                        </button>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php else : ?>

            <div class="feature-card">

                <h2>No Ongoing Events</h2>

                <p>There are currently no ongoing events available.</p>

            </div>

        <?php endif; ?>

    </div>

</section>