<?php
require_once "app/Models/Category.php";
$categories = (new Category())->getActiveCategories();
?>

<div class="cc-editor-page">
    <div class="cc-editor-header">
        <div><span class="cc-editor-kicker"><i class="bi bi-calendar2-event"></i> Event studio</span><h1>Add New Event</h1><p>Enter the event data once, then verify the public card in the live preview before saving.</p></div>
        <a href="index.php?page=admin-events" class="cc-editor-back"><i class="bi bi-arrow-left"></i> Back to Events</a>
    </div>

    <div class="cc-editor-layout">
        <form action="index.php?page=save-event" method="POST" enctype="multipart/form-data" class="cc-editor-form" id="eventEditorForm"><?= cc_csrf_field(); ?>
            <section class="cc-editor-section"><div class="cc-editor-section-title"><span>01</span><div><h2>Event identity</h2><p>Name, category and publication details.</p></div></div>
                <div class="cc-form-grid">
                    <label>Event title<input type="text" name="title" id="eventTitle" required placeholder="e.g. AI Innovation Workshop"></label>
                    <label>Category<select name="category_id" id="eventCategory" required><option value="">Select category</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id']; ?>"><?= htmlspecialchars($category['category_name']); ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="cc-form-grid"><?= cc_authorizer_field('', 'authorizedBy'); ?></div>
            </section>
            <section class="cc-editor-section"><div class="cc-editor-section-title"><span>02</span><div><h2>Schedule & venue</h2><p>Make the public timing and location unambiguous.</p></div></div>
                <div class="cc-form-grid four"><label>Start date<input type="date" name="event_date" id="eventDate" required></label><label>Start time<input type="time" name="event_time" id="eventTime" required></label><label>End date<input type="date" name="end_date" id="eventEndDate" required></label><label>End time<input type="time" name="end_time" id="eventEndTime" required></label></div>
                <div class="cc-form-grid"><label>Venue<input type="text" name="venue" id="eventVenue" required placeholder="Auditorium / Lab / Campus"></label><label>Google Maps link<input type="url" name="google_map" placeholder="https://maps.google.com/..."></label></div>
            </section>
            <section class="cc-editor-section"><div class="cc-editor-section-title"><span>03</span><div><h2>Event content</h2><p>What students need to know before registering.</p></div></div>
                <label>Description<textarea name="description" id="eventDescription" rows="5" placeholder="Describe the event..."></textarea></label>
                <div class="cc-form-grid"><label>Rules<textarea name="rules" rows="5" placeholder="One rule per line"></textarea></label><label>Prizes<textarea name="prizes" rows="5" placeholder="Prize details"></textarea></label></div>
                <label>Schedule / agenda<textarea name="schedule" rows="5" placeholder="09:00 — Registration&#10;10:00 — Session 1"></textarea></label>
            </section>
            <section class="cc-editor-section"><div class="cc-editor-section-title"><span>04</span><div><h2>Media & registration</h2><p>Upload the assets that belong to the event—not page backgrounds.</p></div></div>
                <div class="cc-form-grid"><label>Event banner<input type="file" name="banner" id="eventBanner" accept="image/jpeg,image/png,image/webp,image/gif"></label><label>External registration link<input type="url" name="registration_link" placeholder="Optional Google Form / partner link"></label></div>
                <div class="cc-form-grid"><label>Gallery photos<input type="file" name="gallery_images[]" id="eventPhotos" multiple accept="image/jpeg,image/png,image/webp,image/gif"></label><label>Event videos<input type="file" name="gallery_videos[]" id="eventVideos" multiple accept="video/mp4,video/webm,video/quicktime"></label></div>
                <div class="cc-form-grid"><label>Maximum registrations<input type="number" name="max_registrations" min="0" value="0"></label><label>Publish<select name="publish"><option value="Yes">Publish now</option><option value="No">Save as draft</option></select></label></div>
            </section>
            <section class="cc-editor-section"><div class="cc-editor-section-title"><span>05</span><div><h2>Coordinators</h2><p>Optional faculty and student points of contact.</p></div></div>
                <div class="cc-form-grid"><label>Faculty coordinator<input type="text" name="faculty_name"></label><label>Faculty photo<input type="file" name="faculty_photo" accept="image/jpeg,image/png,image/webp"></label><label>Student coordinator<input type="text" name="student_name"></label><label>Student photo<input type="file" name="student_photo" accept="image/jpeg,image/png,image/webp"></label></div>
            </section>
            <div class="cc-editor-actions"><a href="index.php?page=admin-events" class="cc-ghost-btn">Cancel</a><button type="reset" class="cc-ghost-btn" id="eventReset">Reset</button><button type="submit" class="cc-primary-btn"><i class="bi bi-check2-circle"></i> Save Event</button></div>
        </form>

        <aside class="cc-live-preview" aria-label="Live public event preview"><div class="cc-preview-label"><span><i class="bi bi-eye"></i> Live preview</span><small>Public view</small></div><article class="cc-event-preview-card"><div class="cc-event-preview-cover"><img id="eventPreviewImage" src="public/images/event/default-event.svg" alt="Event banner preview" data-fallback="public/images/event/default-event.svg"><span id="eventPreviewCategory">Category</span></div><div class="cc-event-preview-body"><h3 id="eventPreviewTitle">Your event title</h3><div class="cc-event-preview-meta"><span><i class="bi bi-calendar3"></i> <b id="eventPreviewDate">Choose a date</b></span><span><i class="bi bi-clock"></i> <b id="eventPreviewTime">Choose a time</b></span><span><i class="bi bi-geo-alt"></i> <b id="eventPreviewVenue">Add a venue</b></span><span><i class="bi bi-patch-check"></i> Authorized by <b id="eventPreviewAuthorizer">Add who authorized this</b></span></div><p id="eventPreviewDescription">Your description will appear here exactly as students will see the public event summary.</p><div class="cc-event-preview-files"><span><i class="bi bi-images"></i> <b id="eventPhotoCount">0</b> photos</span><span><i class="bi bi-camera-video"></i> <b id="eventVideoCount">0</b> videos</span></div><div class="cc-preview-media-row" id="eventPreviewMedia" aria-live="polite"><div class="cc-preview-media-empty">Selected photos and videos will preview here before saving.</div></div><div class="cc-event-preview-cta"><span>Registration</span><strong>Register Now <i class="bi bi-arrow-up-right"></i></strong></div></div></article><div class="cc-preview-note"><i class="bi bi-shield-check"></i> Preview is local-only. Nothing is uploaded until you press Save Event.</div></aside>
    </div>
</div>

<link rel="stylesheet" href="public/css/admin-editor.css">
<script src="public/js/admin-editor.js"></script>
<script nonce="<?= cc_nonce(); ?>">window.initCampusChronicleEventEditor && window.initCampusChronicleEventEditor();</script>
