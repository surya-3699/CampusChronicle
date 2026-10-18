<?php

require_once "app/Controllers/GalleryController.php";
require_once "app/Controllers/GalleryMediaController.php";

$galleryController = new GalleryController();
$mediaController = new GalleryMediaController();
$albums = $galleryController->getPublishedGallery();
$recentMedia = $mediaController->getRecentMediaWithGallery(8);
$recentMedia = array_values(array_filter($recentMedia, function ($item) {
    $type = strtolower((string)($item['media_type'] ?? 'image'));
    $base = $type === 'video'
        ? 'public/uploads/gallery/videos/'
        : 'public/uploads/gallery/images/';
    return cc_asset_exists($item['file_path'] ?? '', $base);
}));

?>

<section class="gallery-section">
    <div class="gallery-shell">

        <div class="gallery-heading">
            <div>
                <span class="gallery-kicker"><i class="bi bi-camera3"></i> Campus memories</span>
                <h1>Campus Gallery</h1>
                <p>Explore memorable moments from workshops, hackathons, cultural events, sports meets and seminars.</p>
            </div>
            <?php if (!empty($albums)): ?>
                <span class="gallery-total-pill">
                    <?= count($albums); ?> <?= count($albums) === 1 ? 'Album' : 'Albums'; ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($recentMedia)): ?>
            <section class="cc-gallery-media-panel" aria-labelledby="recentMediaTitle">
                <div class="cc-gallery-media-head">
                    <div>
                        <span class="gallery-kicker"><i class="bi bi-collection-play"></i> Uploaded media</span>
                        <h2 id="recentMediaTitle">Latest campus moments</h2>
                        <p>Only media uploaded into real gallery albums appears here. No decorative background videos are mixed into the gallery content.</p>
                    </div>
                    <a class="gallery-view-btn" href="#galleryAlbums">Browse albums <i class="bi bi-arrow-down"></i></a>
                </div>
                <div class="cc-gallery-media-grid">
                    <?php foreach ($recentMedia as $item): ?>
                        <?php
                            $type = strtolower((string)($item['media_type'] ?? 'image'));
                            $base = $type === 'video'
                                ? 'public/uploads/gallery/videos/'
                                : 'public/uploads/gallery/images/';
                            $mediaPath = cc_normalize_asset_path($item['file_path'] ?? '', $base);
                            $targetId = (int)($item['gallery_id'] ?? 0);
                        ?>
                        <a class="cc-gallery-media-card" href="index.php?page=gallery-album&id=<?= $targetId; ?>" title="Open <?= htmlspecialchars($item['gallery_title'] ?? 'gallery'); ?>">
                            <?php if (strtolower($item['media_type'] ?? '') === 'video'): ?>
                                <video muted playsinline preload="metadata" poster="public/uploads/gallery/covers/bvraju.jpg">
                                    <source src="<?= htmlspecialchars($mediaPath, ENT_QUOTES, 'UTF-8'); ?>" type="<?= htmlspecialchars($item['mime_type'] ?? 'video/mp4'); ?>">
                                </video>
                                <span class="cc-gallery-media-type"><i class="bi bi-camera-video"></i> Video</span>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($mediaPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($item['title'] ?? ($item['gallery_title'] ?? 'Gallery photo')); ?>" loading="lazy" data-fallback="public/images/gallery/default-cover.svg">
                                <span class="cc-gallery-media-type"><i class="bi bi-image"></i> Photo</span>
                            <?php endif; ?>
                            <span class="cc-gallery-media-caption"><?= htmlspecialchars($item['gallery_title'] ?? 'Gallery'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <div id="galleryAlbums">
        <?php if (!empty($albums)): ?>
            <div class="gallery-grid">
                <?php foreach ($albums as $album): ?>
                    <?php
                    $coverImage = cc_asset_url(
                        $album['cover_image'] ?? '',
                        'public/images/gallery/default-cover.svg',
                        'public/uploads/gallery/covers/'
                    );
                    $coverImage = htmlspecialchars($coverImage, ENT_QUOTES, "UTF-8");
                    $photos = 0;
                    $videos = 0;
                    foreach ($mediaController->getMediaByGallery((int)$album['id']) as $albumMediaItem) {
                        $albumMediaType = strtolower((string)($albumMediaItem['media_type'] ?? 'image'));
                        $albumMediaBase = $albumMediaType === 'video'
                            ? 'public/uploads/gallery/videos/'
                            : 'public/uploads/gallery/images/';
                        if (!cc_asset_exists($albumMediaItem['file_path'] ?? '', $albumMediaBase)) {
                            continue;
                        }
                        if ($albumMediaType === 'video') {
                            $videos++;
                        } else {
                            $photos++;
                        }
                    }
                    ?>

                    <article class="gallery-card">
                        <a class="gallery-cover-wrap" href="index.php?page=gallery-album&id=<?= (int)$album['id']; ?>" aria-label="Open <?= htmlspecialchars($album['title']); ?> album">
                            <img
                                src="<?= $coverImage; ?>"
                                alt="<?= htmlspecialchars($album['title']); ?>"
                                class="gallery-cover"
                                loading="lazy"
                                data-fallback="public/images/gallery/default-cover.svg">
                            <span class="gallery-cover-overlay"></span>
                            <span class="gallery-open"><i class="bi bi-arrow-up-right"></i></span>
                        </a>

                        <div class="gallery-content">
                            <div class="gallery-meta-row">
                                <span class="gallery-type"><?= htmlspecialchars($album['gallery_type'] ?? 'Gallery'); ?></span>
                                <?php if ($photos || $videos): ?>
                                    <span class="gallery-counts">
                                        <?php if ($photos): ?><i class="bi bi-image"></i> <?= $photos; ?><?php endif; ?>
                                        <?php if ($videos): ?><i class="bi bi-camera-video"></i> <?= $videos; ?><?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h2><?= htmlspecialchars($album['title']); ?></h2>

                            <?php
                                $description = trim(strip_tags((string)($album['description'] ?? '')));
                                if (function_exists('mb_strimwidth')) {
                                    $description = mb_strimwidth($description, 0, 150, '...');
                                } elseif (strlen($description) > 150) {
                                    $description = substr($description, 0, 147) . '...';
                                }
                            ?>
                            <p><?= htmlspecialchars($description); ?></p>

                            <a href="index.php?page=gallery-album&id=<?= (int)$album['id']; ?>" class="gallery-view-btn">
                                View album <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-gallery">
                <div class="empty-gallery-icon"><i class="bi bi-images"></i></div>
                <h2>No Gallery Albums Available</h2>
                <p>Albums will automatically appear here after an administrator publishes them.</p>
            </div>
        <?php endif; ?>
        </div>

    </div>
</section>
