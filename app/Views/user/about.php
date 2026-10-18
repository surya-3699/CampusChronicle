<section class="about-section">

<div class="section-title">

<h1>About Campus Chronicle</h1>

<p align="justify">

 Campus Chronicle is a centralized platform that enables students to discover, register for, and participate in various campus events.
 It provides a seamless experience for accessing workshops, hackathons, seminars, technical fests, sports competitions, and cultural activities.
 The platform simplifies event management while fostering student engagement and strengthening the campus community.
</p>

</div>

<div class="stats">

    <div class="stat-box">
        <h1><?= (int)$aboutStats['events']; ?>+</h1>
        <p>Events Hosted</p>
    </div>

    <div class="stat-box">
        <h1><?= (int)$aboutStats['students']; ?>+</h1>
        <p>Students Onboard</p>
    </div>

    <div class="stat-box">
        <h1><?= (int)$aboutStats['participation']; ?>+</h1>
        <p>Event Registrations</p>
    </div>

    <div class="stat-box">
        <h1><?= (int)$aboutStats['galleries']; ?>+</h1>
        <p>Photo &amp; Video Galleries</p>
    </div>

</div>

<div class="mission">

<div class="mission-card">

<div class="icon">🎓</div>

<h2>Our Mission</h2>

<p>

Provide every student with a single platform
to explore all campus opportunities.

</p>

</div>

<div class="mission-card">

<div class="icon">💡</div>

<h2>Our Vision</h2>

<p>

Create a smart digital campus where
students never miss an important event.

</p>

</div>

<div class="mission-card">

<div class="icon">🏛️</div>

<h2>Community</h2>

<p>

Connect students, faculty and clubs
through one modern event platform.

</p>

</div>

</div>

<?php if (!empty($aboutCategories)): ?>

<div class="section-title" style="margin-top:80px;">

    <h1>What You'll Find Here</h1>

    <p>Every event on campus is organized under these categories.</p>

</div>

<div class="category-chip-row">

    <?php foreach ($aboutCategories as $cat): ?>

        <a href="index.php?page=events&category=<?= $cat['id']; ?>" class="category-chip">
            <?= htmlspecialchars($cat['category_name']); ?>
        </a>

    <?php endforeach; ?>

</div>

<?php endif; ?>

</section>
