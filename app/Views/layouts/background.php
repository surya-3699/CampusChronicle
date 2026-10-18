<?php
/*
|--------------------------------------------------------------------------
| Page background
|--------------------------------------------------------------------------
| Admin pages keep the ambient video. Every user-facing page (public pages,
| signed-in student pages and login/sign-up/forgot-password) renders the 3D
| animated scene from public/js/scene3d.js into a <canvas>.
|
| To show a scene exported from a hosted 3D builder instead, set the
| CC_3D_SCENE_URL environment variable to its public https embed URL; the page
| then uses a sandboxed, non-interactive <iframe> in place of the canvas.
*/
$ccBgAdmin = !empty($ccIsAdminPage);
$ccBgEmbed = $ccBgAdmin ? '' : cc_scene_embed_url();
?>
<?php if ($ccBgAdmin): ?>
<div class="cc-video-background" aria-hidden="true">
    <video muted loop playsinline preload="none" poster="public/media/ambient-poster.jpg">
        <source data-src="public/media/ambient.mp4" type="video/mp4">
    </video>
</div>
<?php else: ?>
<div class="cc-video-background cc-3d-background" aria-hidden="true">
<?php if ($ccBgEmbed !== ''): ?>
    <iframe class="cc-3d-embed" src="<?= htmlspecialchars($ccBgEmbed, ENT_QUOTES, 'UTF-8'); ?>" title="" tabindex="-1" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin"></iframe>
<?php else: ?>
    <canvas></canvas>
<?php endif; ?>
</div>
<?php endif; ?>
