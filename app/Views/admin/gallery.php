<?php

require_once "app/Controllers/GalleryController.php";
// Publication is performed by scheduled maintenance.

$galleryController = new GalleryController();

$search = $_GET['search'] ?? '';

$status = $_GET['status'] ?? '';

$type = $_GET['type'] ?? '';

if (!empty($search)) {

    $galleries = $galleryController->searchGallery($search);

} elseif (!empty($status)) {

    $galleries = $galleryController->filterStatus($status);

} elseif (!empty($type)) {

    $galleries = $galleryController->filterType($type);

} else {

    $galleries = $galleryController->getAllGallery();

}

$stats = $galleryController->getDashboardStats();

?>

<div class="admin-container">

    <div class="page-header">

        <div>

            <h1>Gallery Management</h1>

            <p>Manage gallery albums, images and videos.</p>

        </div>

        <a href="index.php?page=add-gallery" class="save-btn">

            + Create Gallery

        </a>

    </div>

    <div class="dashboard-cards">

        <div class="stat-card">

            <h2><?= $stats['total_galleries'] ?? 0 ?></h2>

            <p>Total Galleries</p>

        </div>

        <div class="stat-card">

            <h2><?= $stats['published'] ?? 0 ?></h2>

            <p>Published</p>

        </div>

        <div class="stat-card">

            <h2><?= $stats['draft'] ?? 0 ?></h2>

            <p>Draft</p>

        </div>

        <div class="stat-card">

            <h2><?= $stats['total_photos'] ?? 0 ?></h2>

            <p>Photos</p>

        </div>

        <div class="stat-card">

            <h2><?= $stats['total_videos'] ?? 0 ?></h2>

            <p>Videos</p>

        </div>

    </div>

    <div class="filter-bar">

        <form method="GET">

            <input type="hidden" name="page" value="admin-gallery">

            <input
                type="text"
                name="search"
                placeholder="Search gallery..."
                value="<?= htmlspecialchars($search) ?>">

            <select name="status">

                <option value="">All Status</option>

                <option value="Published">Published</option>

                <option value="Draft">Draft</option>

            </select>

            <select name="type">

                <option value="">All Types</option>

                <option value="Manual">Manual</option>

                <option value="Event">Event</option>

            </select>

            <button class="save-btn">

                Search

            </button>

        </form>

    </div>
        <?php if(empty($galleries)): ?>

        <div class="upload-card">

            <h2>No Gallery Albums Found</h2>

            <p>
                Create your first gallery album to start uploading
                photos and videos.
            </p>

        </div>

    <?php else: ?>

        <div class="gallery-grid">

            <?php foreach($galleries as $gallery): ?>

                <div class="gallery-card">

                    <div class="gallery-image">

                        <?php if(!empty($gallery['cover_image'])): ?>

                            <img
                                src="<?= htmlspecialchars($gallery['cover_image']); ?>"
                                alt="<?= htmlspecialchars($gallery['title']); ?>">

                        <?php else: ?>

                            <img
                                src="public/images/gallery/default-cover.svg"
                                alt="Gallery">

                        <?php endif; ?>

                        <span class="gallery-type">

                            <?= htmlspecialchars($gallery['gallery_type']); ?>

                        </span>

                        <span class="gallery-status <?= strtolower($gallery['status']); ?>">

                            <?= htmlspecialchars($gallery['status']); ?>

                        </span>

                    </div>

                    <div class="gallery-body">

                        <h3>

                            <?= htmlspecialchars($gallery['title']); ?>

                        </h3>

                        <p>

                            <?= nl2br(htmlspecialchars($gallery['description'])); ?>

                        </p>

                        <div class="gallery-stats">

                            <span>

                                📷 <?= (int)$gallery['total_photos']; ?> Photos

                            </span>

                            <span>

                                🎥 <?= (int)$gallery['total_videos']; ?> Videos

                            </span>

                        </div>

                        <div class="gallery-date">

                            Created:

                            <?= date("d M Y", strtotime($gallery['created_at'])); ?>

                        </div>

                        <div class="gallery-authorized">

                            <i class="bi bi-patch-check-fill"></i>

                            Authorized by:

                            <?php $galleryAuthorizer = trim((string)($gallery['authorized_by'] ?? '')); ?>

                            <?php if($galleryAuthorizer !== ''): ?>
                                <strong><?= htmlspecialchars($galleryAuthorizer); ?></strong>
                            <?php else: ?>
                                <em>Not recorded</em>
                            <?php endif; ?>

                        </div>

                        <div class="gallery-actions">

                            <a
                                href="index.php?page=manage-media&id=<?= $gallery['id']; ?>"
                                class="manage-btn">

                                Manage Media

                            </a>

                            <a
                                href="index.php?page=edit-gallery&id=<?= $gallery['id']; ?>"
                                class="edit-btn">

                                Edit

                            </a>

                            <a
                                href="index.php?page=delete-gallery&id=<?= $gallery['id']; ?>"
                                class="delete-btn"
                                data-confirm="Delete this gallery permanently?">

                                Delete

                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>
            <?php endif; ?>

</div>

<style>

.dashboard-cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:20px;
    margin:25px 0;
}

.stat-card{
    background:#fff;
    padding:20px;
    border-radius:12px;
    text-align:center;
    box-shadow:0 3px 10px rgba(0,0,0,.08);
}

.stat-card h2{
    margin:0;
    font-size:30px;
    color:#2563eb;
}

.stat-card p{
    margin-top:8px;
    color:#666;
}

.filter-bar{
    margin:25px 0;
}

.filter-bar form{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

.filter-bar input,
.filter-bar select{
    padding:10px 14px;
    border:1px solid #ddd;
    border-radius:8px;
    min-width:180px;
}

.gallery-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(340px,1fr));
    gap:25px;
}

.gallery-card{
    background:#fff;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 5px 16px rgba(0,0,0,.08);
    transition:.25s;
}

.gallery-card:hover{
    transform:translateY(-5px);
}

.gallery-image{
    position:relative;
}

.gallery-image img{
    width:100%;
    height:220px;
    object-fit:cover;
}

.gallery-type,
.gallery-status{
    position:absolute;
    top:15px;
    padding:5px 12px;
    border-radius:20px;
    color:#fff;
    font-size:12px;
}

.gallery-type{
    left:15px;
    background:#2563eb;
}

.gallery-status{
    right:15px;
    background:#16a34a;
}

.gallery-status.draft{
    background:#f59e0b;
}

.gallery-body{
    padding:20px;
}

.gallery-body h3{
    margin:0 0 10px;
}

.gallery-body p{
    color:#666;
    line-height:1.6;
}

.gallery-stats{
    display:flex;
    justify-content:space-between;
    margin:18px 0;
    font-size:14px;
    font-weight:600;
}

.gallery-date{
    color:#777;
    font-size:13px;
    margin-bottom:18px;
}

.gallery-authorized{
    color:#475569;
    font-size:13px;
    margin:-8px 0 18px;
    overflow-wrap:anywhere;
}

.gallery-authorized i{
    color:#16a34a;
}

.gallery-authorized em{
    opacity:.65;
}

.gallery-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.gallery-actions a{
    text-decoration:none;
    padding:9px 16px;
    border-radius:8px;
    color:#fff;
    font-size:14px;
}

.manage-btn{
    background:#2563eb;
}

.edit-btn{
    background:#f59e0b;
}

.delete-btn{
    background:#dc2626;
}

.upload-card{
    background:#fff;
    padding:50px;
    text-align:center;
    border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,.08);
}

@media(max-width:768px){

    .filter-bar form{
        flex-direction:column;
    }

    .filter-bar input,
    .filter-bar select,
    .save-btn{
        width:100%;
    }

    .gallery-grid{
        grid-template-columns:1fr;
    }

}

</style>
