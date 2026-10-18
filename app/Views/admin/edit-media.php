<?php

require_once "app/Controllers/GalleryMediaController.php";

$controller = new GalleryMediaController();

$id = $_GET['id'] ?? 0;

$media = $controller->getMediaById($id);

if(!$media){
    echo "<h2>Media not found.</h2>";
    return;
}

?>

<div class="admin-container">

    <div class="page-header">

        <div>

            <h1>Edit Media</h1>

            <p>
                Update media details.
            </p>

        </div>

        <a
            href="index.php?page=manage-media&id=<?= $media['gallery_id']; ?>"
            class="back-btn">

            ← Back

        </a>

    </div>

    <form
        action="index.php?page=update-media"
        method="POST"
        enctype="multipart/form-data"
        class="media-form"><?= cc_csrf_field(); ?>

        <input
            type="hidden"
            name="id"
            value="<?= $media['id']; ?>">

        <div class="form-grid"></div>
                    <div class="form-group">

                <label>Current Media</label>

                <?php if(strtolower($media['media_type']) == "image"): ?>

                    <img
                        src="<?= htmlspecialchars($media['file_path']); ?>"
                        class="media-preview"
                        alt="Gallery Image">

                <?php else: ?>

                    <video
                        controls
                        class="media-preview">

                        <source
                            src="<?= htmlspecialchars($media['file_path']); ?>">

                    </video>

                <?php endif; ?>

            </div>

            <div class="form-group">

                <label>Media Type</label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= ucfirst($media['media_type']); ?>"
                    readonly>

            </div>

            <div class="form-group full-width">

                <label>Title</label>

                <input
                    type="text"
                    name="title"
                    class="form-control"
                    value="<?= htmlspecialchars($media['title'] ?? ''); ?>"
                    placeholder="Enter media title">

            </div>

            <div class="form-group full-width">

                <label>Description</label>

                <textarea
                    name="description"
                    rows="5"
                    class="form-control"
                    placeholder="Enter media description"><?= htmlspecialchars($media['description'] ?? ''); ?></textarea>

            </div>

            <div class="form-group">

                <label>Display Order</label>

                <input
                    type="number"
                    name="display_order"
                    class="form-control"
                    min="1"
                    value="<?= (int)($media['display_order'] ?? 1); ?>">

            </div>

            <div class="form-group">

                <label>Replace File (Optional)</label>

                <input
                    type="file"
                    name="media_file"
                    class="form-control"
                    accept="image/*,video/*">

                <small class="form-text">
                    Leave this empty to keep the existing file.
                </small>

            </div>
                    </div>

        <div class="form-actions">

            <a
                href="index.php?page=manage-media&id=<?= $media['gallery_id']; ?>"
                class="cancel-btn">

                Cancel

            </a>

            <button
                type="reset"
                class="reset-btn">

                Reset

            </button>

            <button
                type="submit"
                class="save-btn">

                Update Media

            </button>

        </div>

    </form>

</div>

<style>

.admin-container{
    max-width:1100px;
    margin:30px auto;
    padding:25px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:20px;
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
    background:#6b7280;
    color:#fff;
    text-decoration:none;
    padding:10px 18px;
    border-radius:8px;
    transition:.3s;
}

.back-btn:hover{
    background:#4b5563;
}

.media-form{
    background:#fff;
    padding:30px;
    border-radius:14px;
    box-shadow:0 5px 18px rgba(0,0,0,.08);
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:24px;
}

.full-width{
    grid-column:1 / -1;
}

.form-group{
    display:flex;
    flex-direction:column;
}

.form-group label{
    font-weight:600;
    margin-bottom:8px;
}

.form-control{
    width:100%;
    padding:12px 14px;
    border:1px solid #d1d5db;
    border-radius:8px;
    font-size:15px;
    transition:.25s;
}

.form-control:focus{
    outline:none;
    border-color:#2563eb;
    box-shadow:0 0 0 3px rgba(37,99,235,.15);
}

textarea.form-control{
    resize:vertical;
    min-height:150px;
}

.media-preview{
    width:100%;
    max-width:400px;
    max-height:280px;
    border-radius:10px;
    border:1px solid #ddd;
    object-fit:cover;
    background:#000;
}

.form-text{
    margin-top:8px;
    color:#666;
    font-size:13px;
}

.form-actions{
    margin-top:30px;
    display:flex;
    justify-content:flex-end;
    gap:12px;
    flex-wrap:wrap;
}

.cancel-btn,
.reset-btn,
.save-btn{
    padding:12px 22px;
    border:none;
    border-radius:8px;
    color:#fff;
    text-decoration:none;
    cursor:pointer;
    transition:.3s;
    font-size:15px;
}

.cancel-btn{
    background:#6b7280;
}

.reset-btn{
    background:#f59e0b;
}

.save-btn{
    background:#2563eb;
}

.cancel-btn:hover,
.reset-btn:hover,
.save-btn:hover{
    opacity:.9;
    transform:translateY(-2px);
}

@media(max-width:768px){

    .form-grid{
        grid-template-columns:1fr;
    }

    .page-header{
        flex-direction:column;
        align-items:flex-start;
    }

    .media-preview{
        max-width:100%;
    }

    .form-actions{
        flex-direction:column;
    }

    .cancel-btn,
    .reset-btn,
    .save-btn{
        width:100%;
        text-align:center;
    }

}

</style>