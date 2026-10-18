<?php
require_once "app/Controllers/ProfileController.php";
$controller = new ProfileController();
$controller->update();
$data = $controller->profile();
$user = $data['user'];
$skills = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string)($user['skills'] ?? '')))));
$interests = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string)($user['academic_interests'] ?? '')))));
$avatar = cc_asset_url($user['profile_image'] ?? '', 'public/uploads/profile/default.png', 'public/uploads/profile/');
$profileBanner = cc_asset_exists($user['profile_banner'] ?? '', 'public/uploads/profile/')
    ? cc_normalize_asset_path($user['profile_banner'], 'public/uploads/profile/')
    : '';
$profileBannerStyle = $profileBanner ? "background-image:linear-gradient(120deg,rgba(4,10,22,.42),rgba(12,20,45,.40)),url('" . str_replace(["'", '"', '\\', "\n", "\r"], '', $profileBanner) . "')" : '';
?>
<section class="cc-profile">
        <div id="profileBannerPreview" class="cc-profile-cover"<?= $profileBannerStyle ? ' style="' . htmlspecialchars($profileBannerStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>></div>
        <div class="cc-profile-head">
            <img id="previewImage" class="cc-profile-avatar" src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="Profile photo" data-fallback="public/uploads/profile/default.png">
            <div class="cc-profile-title">
                <h1><?= htmlspecialchars($user['fullname']) ?></h1>
                <p><?= htmlspecialchars($user['department'] ?? 'Student') ?><?= !empty($user['course']) ? ' · ' . htmlspecialchars($user['course']) : '' ?></p>
                <span class="cc-profile-badge"><i class="bi bi-patch-check-fill"></i>&nbsp; Active student</span>
            </div>
        </div>

    <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success mt-3"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger mt-3"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

    <div class="cc-profile-grid">
        <div>
            <article class="cc-profile-card">
                <h2><i class="bi bi-mortarboard"></i> Academic Profile</h2>
                <div class="cc-profile-fields">
                    <div class="cc-profile-field"><span>Institution</span><strong><?= htmlspecialchars(($user['institution_name'] ?? '') ?: 'Not added') ?></strong></div>
                    <div class="cc-profile-field"><span>Student ID</span><strong><?= htmlspecialchars($user['student_id'] ?? 'Not added') ?></strong></div>
                    <div class="cc-profile-field"><span>Program</span><strong><?= htmlspecialchars($user['course'] ?? 'Not added') ?></strong></div>
                    <div class="cc-profile-field"><span>Department</span><strong><?= htmlspecialchars($user['department'] ?? 'Not added') ?></strong></div>
                    <div class="cc-profile-field"><span>Year</span><strong><?= htmlspecialchars($user['current_year'] ?? 'Not added') ?></strong></div>
                    <div class="cc-profile-field"><span>Semester</span><strong><?= htmlspecialchars($user['semester'] ?? 'Not added') ?></strong></div>
                    <div class="cc-profile-field"><span>Admission</span><strong><?= htmlspecialchars($user['admission_year'] ?? 'Not added') ?></strong></div>
                    <div class="cc-profile-field"><span>Graduation</span><strong><?= htmlspecialchars($user['expected_graduation'] ?? 'Not added') ?></strong></div>
                </div>
            </article>
            <article class="cc-profile-card mt-3">
                <h2><i class="bi bi-lightbulb"></i> Academic Interests</h2>
                <?php if($interests): ?><div class="cc-profile-tags"><?php foreach($interests as $item): ?><span class="cc-profile-tag"><?= htmlspecialchars($item) ?></span><?php endforeach; ?></div><?php else: ?><p class="text-white-50">Add your academic interests to make your profile more useful to faculty and peers.</p><?php endif; ?>
            </article>
            <article class="cc-profile-card mt-3">
                <h2><i class="bi bi-code-slash"></i> Skills</h2>
                <?php if($skills): ?><div class="cc-profile-tags"><?php foreach($skills as $item): ?><span class="cc-profile-tag"><?= htmlspecialchars($item) ?></span><?php endforeach; ?></div><?php else: ?><p class="text-white-50">No skills added yet.</p><?php endif; ?>
            </article>
        </div>
        <div>
            <article class="cc-profile-card">
                <h2><i class="bi bi-person-lines-fill"></i> Profile & Links</h2>
                <div class="cc-profile-fields">
                    <div class="cc-profile-field"><span>Email</span><strong><?= htmlspecialchars($user['email']) ?></strong></div>
                    <div class="cc-profile-field"><span>Phone</span><strong><?= htmlspecialchars($user['phone'] ?? 'Not added') ?></strong></div>
                    <div class="cc-profile-field"><span>Username</span><strong><?= !empty($user['username']) ? '@' . htmlspecialchars($user['username']) : 'Not added' ?></strong></div>
                    <div class="cc-profile-field"><span>Visibility</span><strong><?= htmlspecialchars($user['profile_visibility'] ?? 'Public') ?></strong></div>
                </div>
                <?php if(!empty($user['bio'])): ?><p class="mt-3 mb-0 text-white-50"><?= nl2br(htmlspecialchars($user['bio'])) ?></p><?php endif; ?>
                <div class="cc-profile-actions mt-3">
                    <?php foreach([['github_url','GitHub','bi-github'],['linkedin_url','LinkedIn','bi-linkedin'],['portfolio_url','Portfolio','bi-globe2']] as $link): if(!empty($user[$link[0]])): ?><a href="<?= htmlspecialchars($user[$link[0]]) ?>" target="_blank" rel="noopener"><i class="bi <?= $link[2] ?>"></i> <?= $link[1] ?></a><?php endif; endforeach; ?>
                </div>
            </article>
            <article class="cc-profile-card mt-3">
                <h2><i class="bi bi-pencil-square"></i> Update Profile</h2>
                <form id="profileForm" class="cc-profile-form" method="POST" enctype="multipart/form-data"><?= cc_csrf_field(); ?>
                    <div class="two"><label>Full name<input name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required></label><label>Institution<input name="institution_name" maxlength="150" value="<?= htmlspecialchars($user['institution_name'] ?? '') ?>" placeholder="College or university name"></label></div>
                    <div class="two"><label>Student ID<input name="student_id" value="<?= htmlspecialchars($user['student_id'] ?? '') ?>"></label><label>Course / Program<input name="course" value="<?= htmlspecialchars($user['course'] ?? '') ?>"></label></div>
                    <label>Department<input name="department" value="<?= htmlspecialchars($user['department'] ?? '') ?>"></label>
                    <div class="two"><label>Current year<input name="current_year" value="<?= htmlspecialchars($user['current_year'] ?? '') ?>"></label><label>Semester<input name="semester" value="<?= htmlspecialchars($user['semester'] ?? '') ?>"></label></div>
                    <div class="two"><label>Admission year<input name="admission_year" value="<?= htmlspecialchars($user['admission_year'] ?? '') ?>"></label><label>Expected graduation<input name="expected_graduation" value="<?= htmlspecialchars($user['expected_graduation'] ?? '') ?>"></label></div>
                    <div class="two"><label>Phone<input name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></label><label>Visibility<select name="profile_visibility"><option <?= (($user['profile_visibility'] ?? 'Public')==='Public')?'selected':'' ?>>Public</option><option <?= (($user['profile_visibility'] ?? '')==='Private')?'selected':'' ?>>Private</option></select></label></div>
                    <label>Bio<textarea name="bio" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea></label>
                    <label>Skills <input name="skills" value="<?= htmlspecialchars($user['skills'] ?? '') ?>" placeholder="Python, AI/ML, React"></label>
                    <label>Academic interests <input name="academic_interests" value="<?= htmlspecialchars($user['academic_interests'] ?? '') ?>" placeholder="Artificial Intelligence, Data Science"></label>
                    <div class="two"><label>GitHub<input type="url" name="github_url" value="<?= htmlspecialchars($user['github_url'] ?? '') ?>"></label><label>LinkedIn<input type="url" name="linkedin_url" value="<?= htmlspecialchars($user['linkedin_url'] ?? '') ?>"></label></div>
                    <label>Portfolio<input type="url" name="portfolio_url" value="<?= htmlspecialchars($user['portfolio_url'] ?? '') ?>"></label>
                    <label>Profile photo<input id="imageInput" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp"></label>
                    <label>Profile banner<input id="bannerInput" type="file" name="profile_banner" accept="image/jpeg,image/png,image/webp"><small class="cc-profile-help">Use a wide campus, event or academic image. Recommended: 1600×450 or wider.</small></label>
                    <p id="profileSaveStatus" role="status" class="cc-profile-help">Choose your images, then select Save profile to keep your changes.</p>
                    <button type="submit" class="admin-action-btn"><i class="bi bi-check2-circle"></i> Save profile</button>
                </form>
            </article>
        </div>
    </div>
</section>
<script nonce="<?= cc_nonce(); ?>">
(function(){
 const i=document.getElementById('imageInput'),p=document.getElementById('previewImage');
 const b=document.getElementById('bannerInput'),c=document.getElementById('profileBannerPreview');
 const status=document.getElementById('profileSaveStatus'),form=document.getElementById('profileForm');
 let photoUrl, bannerUrl, dirty=false;
 const pending=()=>{dirty=true;status.textContent='Preview only — select Save profile to keep your changes.';};
 form.addEventListener('input',pending);
 if(i&&p)i.addEventListener('change',()=>{if(i.files[0]){if(photoUrl)URL.revokeObjectURL(photoUrl);photoUrl=URL.createObjectURL(i.files[0]);p.src=photoUrl;pending();}});
 if(b&&c)b.addEventListener('change',()=>{if(b.files[0]){if(bannerUrl)URL.revokeObjectURL(bannerUrl);bannerUrl=URL.createObjectURL(b.files[0]);c.style.backgroundImage=`linear-gradient(120deg,rgba(4,10,22,.42),rgba(12,20,45,.40)),url("${bannerUrl}")`;pending();}});
 form.addEventListener('submit',()=>{dirty=false;status.textContent='Saving your profile…';});
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
})();
</script>
