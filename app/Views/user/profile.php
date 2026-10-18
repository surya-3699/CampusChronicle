<?php
$profileImage = cc_asset_url(
    $user['profile_image'] ?? '',
    'public/uploads/profile/default.png',
    'public/uploads/profile/'
);
?>
<img
    src="<?= htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8'); ?>"
    id="previewImage"
    class="profile-image"
    alt="Profile Picture"
    data-fallback="public/uploads/profile/default.png">
