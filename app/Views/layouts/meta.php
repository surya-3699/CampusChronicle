<?php
/*
|--------------------------------------------------------------------------
| Page meta: description, link-preview (Open Graph) tags and robots
|--------------------------------------------------------------------------
| Link previews (WhatsApp, LinkedIn, Telegram, Slack, Google) read these tags.
| Set in the environment:
|   APP_URL      public site address, e.g. https://campus.example.edu
|   CC_OG_IMAGE  preview image: an absolute https URL, or a path under APP_URL
|                (e.g. public/images/preview.jpg). 1200x630 px and under ~300 KB
|                works best on WhatsApp.
| Sign-in, student and admin pages are marked noindex so search engines skip them.
*/
$ccMetaPage = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'welcome';
$ccMetaPrivate = strpos($ccMetaPage, 'admin') === 0
    || in_array($ccMetaPage, ['login', 'signup', 'forgot-password', 'two-factor', 'dashboard', 'profile', 'my-events', 'certificates', 'view-certificates', 'download-certificate', 'notifications', 'event-register', 'add-event', 'edit-event', 'add-gallery', 'edit-gallery', 'manage-media', 'edit-media'], true);
$ccMetaBase = rtrim(cc_env('APP_URL'), '/');
$ccMetaDesc = 'Campus Chronicle: discover campus events, register in one tap, relive the moments in the photo and video gallery, and earn verified certificates.';
$ccMetaImage = trim(cc_env('CC_OG_IMAGE'));
if ($ccMetaImage !== '' && stripos($ccMetaImage, 'https://') !== 0 && $ccMetaBase !== '') {
    $ccMetaImage = $ccMetaBase . '/' . ltrim($ccMetaImage, '/');
}
if (stripos($ccMetaImage, 'https://') !== 0) {
    $ccMetaImage = '';
}
?>
<meta name="description" content="<?= htmlspecialchars($ccMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($ccMetaPrivate): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="Campus Chronicle">
<meta property="og:title" content="Campus Chronicle">
<meta property="og:description" content="<?= htmlspecialchars($ccMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($ccMetaBase !== ''): ?>
<meta property="og:url" content="<?= htmlspecialchars($ccMetaBase . '/', ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
<?php if ($ccMetaImage !== ''): ?>
<meta property="og:image" content="<?= htmlspecialchars($ccMetaImage, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="twitter:card" content="summary_large_image">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="twitter:title" content="Campus Chronicle">
<meta name="twitter:description" content="<?= htmlspecialchars($ccMetaDesc, ENT_QUOTES, 'UTF-8'); ?>">
