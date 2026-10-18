<section class="hero">

<div class="left">

<h1>

Discover Every Event
On Your Campus

</h1>

<p>

Campus Chronicle connects students with workshops,
hackathons, technical fests, cultural events,
seminars, sports and student achievements.

</p>

<a href="index.php?page=events" class="btn">

Explore Events

</a>

</div>

<div class="login">

<h2>

Quick Access

</h2>

<p style="text-align:center;margin-bottom:20px;">

Explore everything happening on your campus.

</p>

<a href="index.php?page=about" class="btn" style="display:block;text-align:center;">

About Campus

</a>

<br>

<a href="index.php?page=gallery" class="btn" style="display:block;text-align:center;">

View Gallery

</a>

<br>

<a href="index.php?page=contact" class="btn" style="display:block;text-align:center;">

Contact Us

</a>

</div>

</section>

<!-- ===========================
     LIVE STATS
=========================== -->

<section class="about-section">

<div class="stats">

    <div class="stat-box">
        <h1><?= (int)$homeStats['events']; ?>+</h1>
        <p>Events Hosted</p>
    </div>

    <div class="stat-box">
        <h1><?= (int)$homeStats['students']; ?>+</h1>
        <p>Students Onboard</p>
    </div>

    <div class="stat-box">
        <h1><?= (int)$homeStats['participation']; ?>+</h1>
        <p>Event Registrations</p>
    </div>

    <div class="stat-box">
        <h1><?= (int)$homeStats['galleries']; ?>+</h1>
        <p>Photo &amp; Video Galleries</p>
    </div>

</div>

</section>

<!-- ===========================
     FEATURED EVENTS
=========================== -->

<section class="about-section">

<div class="section-title">

<h1>

Featured Events

</h1>

<p>

Discover the most exciting activities happening on campus right now.

</p>

</div>

<?php if (empty($featuredEvents)): ?>

    <div class="empty-events">
        <h2>No events are live just yet</h2>
        <p>Check back soon, or explore what's already in the calendar.</p>
        <a href="index.php?page=events" class="btn">Browse All Events</a>
    </div>

<?php else: ?>

    <div style="max-width:900px;margin:0 auto;">

        <?php foreach ($featuredEvents as $event):
            $status = Event::computeStatus($event);
            include "app/Views/user/partials/event-card.php";
        endforeach; ?>

    </div>

    <div style="text-align:center;margin-top:20px;">
        <a href="index.php?page=events" class="btn">View All Events</a>
    </div>

<?php endif; ?>

</section>
