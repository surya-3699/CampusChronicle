<?php

require_once "app/Controllers/GalleryController.php";
require_once "app/Controllers/GalleryMediaController.php";

$galleryId = (int)($_GET['id'] ?? 0);

$galleryController = new GalleryController();
$mediaController = new GalleryMediaController();

$gallery = $galleryController->getGalleryById($galleryId);

if (!$gallery) {

    echo "<h2>Gallery not found.</h2>";

    return;
}

$media = $mediaController->getMediaByGallery($galleryId);

$stats = $mediaController->getStatistics($galleryId);

$search = $_GET['search'] ?? '';

$type = $_GET['type'] ?? '';

if (!empty($search)) {

    $media = $mediaController->searchMedia($galleryId, $search);

} elseif ($type == "image") {

    $media = $mediaController->getImages($galleryId);

} elseif ($type == "video") {

    $media = $mediaController->getVideos($galleryId);

}

?>

<div class="admin-container">

    <?php foreach (['success', 'error'] as $notice): ?>
        <?php if (!empty($_SESSION[$notice])): ?>
            <p role="status"><?= htmlspecialchars((string)$_SESSION[$notice], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php unset($_SESSION[$notice]); ?>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="page-header">

        <div>

            <h1>

                <?= htmlspecialchars($gallery['title']); ?>

            </h1>

            <p>

                Upload, organize and manage gallery media.

            </p>

        </div>

        <a
            href="index.php?page=admin-gallery"
            class="back-btn">

            ← Back

        </a>

    </div>

    <div class="dashboard-cards">

        <div class="stat-card">

            <h2><?= $stats['images'] ?? 0 ?></h2>

            <p>Images</p>

        </div>

        <div class="stat-card">

            <h2><?= $stats['videos'] ?? 0 ?></h2>

            <p>Videos</p>

        </div>

        <div class="stat-card">

            <h2><?= $stats['total'] ?? 0 ?></h2>

            <p>Total Media</p>

        </div>

    </div>

    <div class="filter-bar">

        <form method="GET">

            <input
                type="hidden"
                name="page"
                value="manage-media">

            <input
                type="hidden"
                name="id"
                value="<?= $galleryId ?>">

            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search media..."
                value="<?= htmlspecialchars($search) ?>">

            <select
                name="type"
                class="form-control">

                <option value="">All Media</option>

                <option value="image">Images</option>

                <option value="video">Videos</option>

            </select>

            <button
                class="save-btn">

                Search

            </button>

        </form>

    </div>

    <form
        action="index.php?page=upload-media"
        method="POST"
        enctype="multipart/form-data"
        class="upload-card"><?= cc_csrf_field(); ?>

        <input
            type="hidden"
            name="gallery_id"
            value="<?= $galleryId ?>">

        <div class="form-group">

            <label>Select Images / Videos</label>

            <input
                type="file"
                name="media_files[]"
                class="form-control"
                multiple
                accept="image/*,video/*">

        </div>

        <button
            type="submit"
            class="save-btn">

            Upload Media

        </button>

    </form>
        <div class="media-grid">

        <?php if(empty($media)): ?>

            <div class="empty-state">

                <h2>No Media Found</h2>

                <p>

                    Upload images or videos to this gallery.

                </p>

            </div>

        <?php else: ?>

            <?php foreach($media as $item): ?>
                <?php
                $isImage = strtolower($item['media_type']) === 'image';
                $mediaPath = cc_normalize_asset_path($item['file_path'] ?? '', $isImage ? 'public/uploads/gallery/images/' : 'public/uploads/gallery/videos/');
                $mediaExists = cc_asset_exists($mediaPath);
                ?>

                <div class="media-card">

                    <div class="media-preview">

                        <?php if (!$mediaExists): ?>
                            <div class="media-missing" role="status"><strong>Original file is missing</strong><p>Restore this file from your upload backup, or upload it again.</p></div>
                        <?php elseif($isImage): ?>

                            <img
                                src="<?= htmlspecialchars($mediaPath, ENT_QUOTES, 'UTF-8'); ?>"
                                class="gallery-image"
                                alt="Gallery Image">

                        <?php else: ?>

                            <video
                                controls
                                preload="metadata" playsinline
                                class="gallery-video">

                                <source
                                    src="<?= htmlspecialchars($mediaPath, ENT_QUOTES, 'UTF-8'); ?>">

                            </video>

                        <?php endif; ?>

                    </div>

                    <div class="media-body">
                        <?php if ($mediaExists && !$isImage): ?>
                            <a href="<?= htmlspecialchars($mediaPath, ENT_QUOTES, 'UTF-8'); ?>" download>Download video</a>
                        <?php endif; ?>

                        <h3>

                            <?= !empty($item['title']) ? htmlspecialchars($item['title']) : "Untitled"; ?>

                        </h3>

                        <p>

                            <?= !empty($item['description']) ? nl2br(htmlspecialchars($item['description'])) : "No description available."; ?>

                        </p>

                        <div class="media-info">

                            <span>

                                Type:
                                <?= ucfirst($item['media_type']); ?>

                            </span>

                            <span>

                                <?= number_format(($item['file_size'] ?? 0)/1024,2); ?> KB

                            </span>

                        </div>

                        <div class="media-actions">

                            <a
                                href="index.php?page=edit-media&id=<?= $item['id']; ?>"
                                class="edit-btn">

                                Edit

                            </a>

                            <a
                                href="index.php?page=delete-media&id=<?= $item['id']; ?>"
                                class="delete-btn"
                                data-confirm="Delete this media?">

                                Delete

                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>
    </div>

<style>

.media-missing{height:240px;display:flex;flex-direction:column;justify-content:center;text-align:center;padding:20px;box-sizing:border-box;}

.admin-container{
    max-width:1400px;
    margin:30px auto;
    padding:25px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    flex-wrap:wrap;
    margin-bottom:30px;
}

.page-header h1{
    margin:0;
    font-size:32px;
}

.page-header p{
    margin-top:8px;
    color:#666;
}

.back-btn{
    padding:10px 18px;
    background:#6b7280;
    color:#fff;
    text-decoration:none;
    border-radius:8px;
}

.dashboard-cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
    margin-bottom:30px;
}

.stat-card{
    background:#fff;
    padding:25px;
    border-radius:14px;
    text-align:center;
    box-shadow:0 4px 15px rgba(0,0,0,.08);
}

.stat-card h2{
    margin:0;
    color:#2563eb;
    font-size:34px;
}

.stat-card p{
    margin-top:10px;
    color:#666;
}

.filter-bar{
    margin-bottom:25px;
}

.filter-bar form{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

.form-control{
    padding:12px 14px;
    border:1px solid #d1d5db;
    border-radius:8px;
    min-width:220px;
}

.upload-card{
    background:#fff;
    padding:30px;
    border-radius:14px;
    box-shadow:0 5px 18px rgba(0,0,0,.08);
    margin-bottom:35px;
    border:2px dashed #d1d5db;
}

.upload-card:hover{
    border-color:#2563eb;
}

.form-group{
    margin-bottom:20px;
}

.form-group label{
    display:block;
    margin-bottom:10px;
    font-weight:600;
}

.media-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(320px,1fr));
    gap:24px;
}

.media-card{
    background:#fff;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 5px 18px rgba(0,0,0,.08);
    transition:.25s;
}

.media-card:hover{
    transform:translateY(-6px);
}

.media-preview img,
.media-preview video{
    width:100%;
    height:240px;
    object-fit:cover;
    display:block;
}

.media-body{
    padding:18px;
}

.media-body h3{
    margin:0 0 10px;
}

.media-body p{
    color:#666;
    line-height:1.6;
    min-height:55px;
}

.media-info{
    display:flex;
    justify-content:space-between;
    margin:18px 0;
    font-size:14px;
    color:#555;
}

.media-actions{
    display:flex;
    gap:10px;
}

.media-actions a{
    flex:1;
    text-align:center;
    text-decoration:none;
    color:#fff;
    padding:10px;
    border-radius:8px;
}

.save-btn{
    background:#2563eb;
    color:#fff;
    border:none;
    padding:12px 22px;
    border-radius:8px;
    cursor:pointer;
}

.edit-btn{
    background:#f59e0b;
}

.delete-btn{
    background:#dc2626;
}

.empty-state{
    grid-column:1/-1;
    background:#fff;
    padding:60px;
    text-align:center;
    border-radius:14px;
    box-shadow:0 4px 15px rgba(0,0,0,.08);
}

.empty-state h2{
    margin-bottom:12px;
}

.empty-state p{
    color:#666;
}

@media(max-width:768px){

    .page-header{
        flex-direction:column;
        align-items:flex-start;
    }

    .filter-bar form{
        flex-direction:column;
    }

    .form-control,
    .save-btn{
        width:100%;
    }

    .media-grid{
        grid-template-columns:1fr;
    }

    .media-actions{
        flex-direction:column;
    }

}

</style>
