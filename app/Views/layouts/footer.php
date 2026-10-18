<footer class="site-footer">

    <div class="footer-inner">

        <div class="footer-col footer-brand">

            <h3>Campus Chronicle</h3>

            <p>Every event. Every memory. One place.</p>

            <p class="footer-muted">
                A single home for campus workshops, hackathons, fests,
                seminars, sports and everything worth showing up for.
            </p>

        </div>

        <div class="footer-col">

            <h4>Explore</h4>

            <a href="index.php?page=home">Home</a>
            <a href="index.php?page=about">About</a>
            <a href="index.php?page=events">Events</a>
            <a href="index.php?page=announcements">Announcements</a>
            <a href="index.php?page=gallery">Gallery</a>
            <a href="index.php?page=team">Team</a>
            <a href="index.php?page=contact">Contact</a>

        </div>

        <div class="footer-col">

            <h4>Get in Touch</h4>

            <p>B.V. Raju College (A)<br>Vishnupur, Bhimavaram, W.G. Dist,<br>Andhra Pradesh &ndash; 534202</p>
            <p><a href="tel:+919114336999">+91 91143 36999</a></p>
            <p><a href="mailto:campuschronicle@gmail.com">campuschronicle@gmail.com</a></p>

        </div>

    </div>

    <div class="footer-bottom">

        <p>&copy; <?= date('Y'); ?> Campus Chronicle &mdash; Designed &amp; built by Team OM, BCA</p>

    </div>

</footer>

<!-- JavaScript -->
<script src="public/js/script.js"></script>

<?php if(file_exists("public/js/auth.js")): ?>
<script src="public/js/auth.js"></script>
<?php endif; ?>

</body>
</html>
