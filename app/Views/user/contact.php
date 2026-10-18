<section class="about-section">

<div class="section-title">

<h1>Contact Us</h1>

<p>

Have questions, suggestions or need support?
We're here to help you.

</p>

</div>

<div class="features">

<!-- Contact Information -->

<div class="feature-card">

<h3>📍 Address</h3>

<p>

Campus Chronicle<br>

BV.RajuCollege(A)<br>

Vishnupur, Bhimavaram, WG.Dist, (534202)Andhra Pradesh<br>

</p>

<hr style="margin:20px 0;border:1px solid rgba(255,255,255,.2);">

<h3>📞 Phone</h3>

<p>

+91 9114336999

</p>

<hr style="margin:20px 0;border:1px solid rgba(255,255,255,.2);">

<h3><img src="public/images/profile/vecteezy_gmail-png-icon_16716465.png" alt="Email" style="width:20px; height:auto; margin-right:10px;"> Email</h3>

<p>
    📧
    <a href="mailto:campuschronicle@gmail.com">
        campuschronicle@gmail.com
    </a>
</p>
<hr style="margin:20px 0;border:1px solid rgba(255,255,255,.2);">

<h3>🌐 Website</h3>

<p>

www.campuschronicle.com

</p>

</div>

<!-- Contact Form -->

<div class="feature-card">

<h3>Send Message</h3>

<br>

<form id="contactForm"><?= cc_csrf_field(); ?>
<div style="position:absolute;left:-9999px;height:0;overflow:hidden" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

<input
type="text"
name="name"
required
placeholder="Full Name"
style="width:100%;padding:14px;margin-bottom:15px;border:none;border-radius:10px;">

<input
type="email"
name="email"
required
placeholder="Email Address"
style="width:100%;padding:14px;margin-bottom:15px;border:none;border-radius:10px;">

<input
type="tel"
name="phone"
placeholder="Phone Number (optional)"
style="width:100%;padding:14px;margin-bottom:15px;border:none;border-radius:10px;">

<input
type="text"
name="subject"
required
placeholder="Subject"
style="width:100%;padding:14px;margin-bottom:15px;border:none;border-radius:10px;">

<textarea
name="message"
required
rows="6"
placeholder="Type your message..."
style="width:100%;padding:14px;border:none;border-radius:10px;resize:none;"></textarea>

<br><br>

<button class="btn" type="submit" id="contactSubmitBtn">
Send Message
</button>

<p id="contactFeedback" style="margin-top:14px;font-weight:600;"></p>

</form>

</div>

</div>

</section>

<script nonce="<?= cc_nonce(); ?>">
(function () {
    var form = document.getElementById('contactForm');
    if (!form) return;

    var btn = document.getElementById('contactSubmitBtn');
    var feedback = document.getElementById('contactFeedback');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = 'Sending...';
        feedback.textContent = '';

        fetch('index.php?page=submit-contact', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: new URLSearchParams(new FormData(form)).toString()
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                feedback.style.color = data.success ? '#4ade80' : '#ff6b6b';
                feedback.textContent = data.message || (data.success ? 'Message sent!' : 'Something went wrong.');

                if (data.success) {
                    form.reset();
                }
            })
            .catch(function () {
                feedback.style.color = '#ff6b6b';
                feedback.textContent = 'Network error. Please try again in a moment.';
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = originalText;
            });
    });
})();
</script>