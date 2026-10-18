<?php

require_once "app/Controllers/GalleryController.php";
require_once "app/Controllers/GalleryMediaController.php";

$galleryController = new GalleryController();
$mediaController = new GalleryMediaController();

if (!empty($_GET['event_id'])) {
    $album = $galleryController->getGalleryByEvent((int)$_GET['event_id']);
    $id = $album['id'] ?? 0;
} else {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $album = $galleryController->getGalleryById($id);
}

if (!$album || (($album['status'] ?? '') !== 'Published' && ($_SESSION['role'] ?? '') !== 'admin')) {
    http_response_code(404);
    echo "<h2 style='padding:100px;text-align:center;'>Album not found.</h2>";
    return;
}

if (($album['gallery_type'] ?? '') === 'Event') {
    $eventEnd = strtotime(($album['end_date'] ?? $album['event_date'] ?? '') . ' ' . ($album['end_time'] ?? '23:59:59'));
    if ($eventEnd && time() <= $eventEnd) {
        echo "<div class='empty-gallery' style='margin:140px auto;max-width:760px'><div class='empty-gallery-icon'><i class='bi bi-hourglass-split'></i></div><h2>Gallery coming soon</h2><p>This event gallery will become available after the event is completed.</p><a class='gallery-view-btn' href='index.php?page=events'>Browse events</a></div>";
        return;
    }
}

$allMedia = $mediaController->getMediaByGallery($id);
$media = array_values(array_filter($allMedia, function ($item) {
    $type = strtolower((string)($item['media_type'] ?? 'image'));
    $base = $type === 'video'
        ? 'public/uploads/gallery/videos/'
        : 'public/uploads/gallery/images/';
    return cc_asset_exists($item['file_path'] ?? '', $base);
}));

?>

<section class="album-page">

    <div class="container">

        <a href="index.php?page=gallery" class="back-btn">
            ← Back to Gallery
        </a>

        <div class="album-header">

    <h1>

        <?= htmlspecialchars($album['title']); ?>

    </h1>

    <p>

        <?= !empty($album['description'])
            ? htmlspecialchars($album['description'])
            : "Gallery memories from this event."; ?>

    </p>

    <div class="album-info">

        <span>📅 <?= date("d M Y", strtotime($album['created_at'])); ?></span>

        <span>📷 <?= count(array_filter($media,function($m){
            return strtolower($m['media_type'])=="image";
        })); ?> Photos</span>

        <span>🎥 <?= count(array_filter($media,function($m){
            return strtolower($m['media_type'])=="video";
        })); ?> Videos</span>

    </div>

</div>

        <?php if(empty($media)): ?>

    <div class="empty-gallery">

        <h2><?= !empty($allMedia) ? 'Media Files Need Re-uploading' : 'No Media Uploaded'; ?></h2>

        <p>
            <?= !empty($allMedia)
                ? 'This album has media records, but the actual files are missing from this project copy. An administrator can re-upload them from Manage Media.'
                : 'Images and videos will appear here once uploaded.'; ?>
        </p>

    </div>

<?php else: ?>

    <div class="media-grid">

        <?php foreach($media as $item): ?>

            <?php
                $type = strtolower((string)($item['media_type'] ?? 'image'));
                $base = $type === 'video'
                    ? 'public/uploads/gallery/videos/'
                    : 'public/uploads/gallery/images/';
                $mediaPath = cc_normalize_asset_path($item['file_path'] ?? '', $base);
                $mediaPath = htmlspecialchars($mediaPath, ENT_QUOTES, 'UTF-8');
            ?>

            <?php if(strtolower($item['media_type'])=="image"): ?>

                <div class="media-card">
                    <a href="#"
                        class="lightbox-trigger"
                        data-image="<?= $mediaPath; ?>"
                        aria-label="Open gallery image">
                        <img
                            src="<?= $mediaPath; ?>"
                            alt="<?= htmlspecialchars($item['title'] ?? 'Gallery Image'); ?>"
                            class="gallery-photo"
                            loading="lazy"
                            data-fallback="public/images/gallery/default-cover.svg">
                    </a>
                </div>

            <?php else: ?>

                <div class="media-card">
                    <video controls preload="metadata">
                        <source src="<?= $mediaPath; ?>" type="<?= htmlspecialchars($item['mime_type'] ?? 'video/mp4'); ?>">
                        Your browser does not support the video element.
                    </video>
                </div>

            <?php endif; ?>

        <?php endforeach; ?>

    </div>

<?php endif; ?>
<div id="lightbox" class="lightbox">

    <span class="close-lightbox">&times;</span>

    <img id="lightbox-image">

</div>
</section>

<script src="public/js/gallery.js"></script>