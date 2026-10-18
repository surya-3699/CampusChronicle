<?php

require_once "app/Models/GalleryMedia.php";
require_once "app/Models/Gallery.php";
require_once "app/helpers/FileUpload.php";
require_once "app/Controllers/AuthController.php";

class GalleryMediaController
{
    private GalleryMedia $mediaModel;
    private Gallery $galleryModel;

    public function __construct()
    {
        $this->mediaModel = new GalleryMedia();
        $this->galleryModel = new Gallery();
    }

    /* ==========================================================
       GET ALL MEDIA OF GALLERY
    ========================================================== */

    public function getMediaByGallery($galleryId)
    {
        return $this->mediaModel->getMediaByGallery($galleryId);
    }

    /* ==========================================================
       GET RECENT MEDIA WITH ALBUM CONTEXT
       Keep this query at controller level for views that consume
       the controller directly.
    ========================================================== */

    public function getRecentMediaWithGallery($limit = 8)
    {
        return $this->mediaModel->getRecentMediaWithGallery($limit);
    }

    /* ==========================================================
       GET IMAGES
    ========================================================== */

    public function getImages($galleryId)
    {
        return $this->mediaModel->getImages($galleryId);
    }

    /* ==========================================================
       GET VIDEOS
    ========================================================== */

    public function getVideos($galleryId)
    {
        return $this->mediaModel->getVideos($galleryId);
    }

    /* ==========================================================
       GET SINGLE MEDIA
    ========================================================== */

    public function getMediaById($id)
    {
        return $this->mediaModel->getMediaById($id);
    }

    /* ==========================================================
       SAVE MEDIA
    ========================================================== */

    public function saveMedia()
    {
        AuthController::checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $galleryId = (int)($_POST['gallery_id'] ?? 0);

        if (!$galleryId) {
            $_SESSION['error'] = "Missing gallery.";
            header("Location:index.php?page=admin-gallery");
            exit;
        }

        if (
            !isset($_FILES['media_files']) ||
            empty($_FILES['media_files']['name'][0])
        ) {

            $_SESSION['error'] = "Please select at least one photo or video.";

            header("Location:index.php?page=manage-media&id=".$galleryId);

            exit;
        }

        $uploadedCount = 0;
        $skippedCount = 0;

        foreach ($_FILES['media_files']['name'] as $index => $name) {

            if ($_FILES['media_files']['error'][$index] != 0) {
                $skippedCount++;
                continue;
            }

            $extension = strtolower(
                pathinfo($name, PATHINFO_EXTENSION)
            );

            $file = [

                'name' => $_FILES['media_files']['name'][$index],

                'type' => $_FILES['media_files']['type'][$index],

                'tmp_name' => $_FILES['media_files']['tmp_name'][$index],

                'error' => $_FILES['media_files']['error'][$index],

                'size' => $_FILES['media_files']['size'][$index]

            ];

            $detectedMime = FileUpload::getMimeType($file['tmp_name']);

            if (in_array($extension, ['jpg','jpeg','png','gif','webp'])) {

                $mediaType = "image";
                if (!str_starts_with((string)$detectedMime, 'image/')) {
                    $skippedCount++;
                    continue;
                }

                $upload = FileUpload::upload(
                    $file,
                    "public/uploads/gallery/images/",
                    ['jpg','jpeg','png','gif','webp'],
                    'gallery',
                    10 * 1024 * 1024
                );

            } elseif (in_array($extension, ['mp4','mov','avi','webm'])) {

                $mediaType = "video";
                if (!str_starts_with((string)$detectedMime, 'video/')) {
                    $skippedCount++;
                    continue;
                }

                $upload = FileUpload::upload(
                    $file,
                    "public/uploads/gallery/videos/",
                    ['mp4','mov','avi','webm'],
                    'gallery',
                    50 * 1024 * 1024
                );

            } else {
                $skippedCount++;
                continue;
            }

            if (!$upload['success']) {
                $skippedCount++;
                continue;
            }

            $this->mediaModel->addMedia([

                'gallery_id' => $galleryId,

                'media_type' => $mediaType,

                'title' => $_POST['title'][$index] ?? '',

                'description' => $_POST['description'][$index] ?? '',

                'file_name' => basename($upload['path']),

                'file_path' => $upload['path'],

                'thumbnail_path' => '',

                'file_size' => $file['size'],

                'mime_type' => $detectedMime,

                'display_order' => $index

            ]);
            $uploadedCount++;
        }

        $this->galleryModel->refreshGalleryCounts($galleryId);

        if ($uploadedCount > 0) {
            cc_commit_write(); $_SESSION['success'] = $uploadedCount . " media file" . ($uploadedCount === 1 ? "" : "s") . " uploaded successfully.";
            if ($skippedCount > 0) {
                $_SESSION['success'] .= " " . $skippedCount . " unsupported or invalid file" . ($skippedCount === 1 ? " was" : "s were") . " skipped.";
            }
        } else {
            $_SESSION['error'] = "No media was uploaded. Check the file type, file size, and PHP upload limits.";
        }

        header("Location:index.php?page=manage-media&id=".$galleryId);

        exit;
    }
        /* ==========================================================
       UPDATE MEDIA
    ========================================================== */

    public function updateMedia()
    {
        AuthController::checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $data = [

            'id' => $_POST['id'],

            'title' => trim($_POST['title']),

            'description' => trim($_POST['description']),

            'display_order' => (int)$_POST['display_order']

        ];

        $media = $this->mediaModel->getMediaById($_POST['id']);

        if (!$media) {

            $_SESSION['error'] = "Media not found.";

            header("Location:index.php?page=admin-gallery");

            exit;
        }

        $this->mediaModel->updateMedia($data);

        cc_commit_write(); $_SESSION['success'] = "Media updated successfully.";

        header("Location:index.php?page=manage-media&id=".$media['gallery_id']);

        exit;
    }

    /* ==========================================================
       DELETE MEDIA
    ========================================================== */

    public function deleteMedia()
    {
        AuthController::checkAdmin();

        if (!isset($_GET['id'])) {

            header("Location:index.php?page=admin-gallery");

            exit;
        }

        $id = (int)$_GET['id'];

        $media = $this->mediaModel->getMediaById($id);

        if (!$media) {

            $_SESSION['error'] = "Media not found.";

            header("Location:index.php?page=admin-gallery");

            exit;
        }

        $galleryId = $media['gallery_id'];

        $this->mediaModel->deleteMediaFile($id);

        $this->galleryModel->refreshGalleryCounts($galleryId);

        cc_commit_write(); $_SESSION['success'] = "Media deleted successfully.";

        header("Location:index.php?page=manage-media&id=".$galleryId);

        exit;
    }

    /* ==========================================================
       DELETE ALL MEDIA
    ========================================================== */

    public function deleteGalleryMedia($galleryId)
    {
        $this->mediaModel->deleteGalleryMedia($galleryId);

        $this->galleryModel->refreshGalleryCounts($galleryId);

        return true;
    }

    /* ==========================================================
       MEDIA STATISTICS
    ========================================================== */

    public function getStatistics($galleryId)
    {
        return $this->mediaModel->getStatistics($galleryId);
    }

    /* ==========================================================
       IMAGE COUNT
    ========================================================== */

    public function getImageCount($galleryId)
    {
        return $this->mediaModel->getImageCount($galleryId);
    }

    /* ==========================================================
       VIDEO COUNT
    ========================================================== */

    public function getVideoCount($galleryId)
    {
        return $this->mediaModel->getVideoCount($galleryId);
    }

    /* ==========================================================
       TOTAL MEDIA COUNT
    ========================================================== */

    public function getMediaCount($galleryId)
    {
        return $this->mediaModel->getMediaCount($galleryId);
    }

    /* ==========================================================
       RECENT MEDIA
    ========================================================== */

    public function getRecentMedia($limit = 10)
    {
        return $this->mediaModel->getRecentMedia($limit);
    }
        /* ==========================================================
       SEARCH MEDIA
    ========================================================== */

    public function searchMedia($galleryId, $keyword)
    {
        return $this->mediaModel->searchMedia($galleryId, $keyword);
    }

    /* ==========================================================
       PAGINATION
    ========================================================== */

    public function getMediaPage($galleryId, $page = 1, $limit = 20)
    {
        $page = max(1, (int)$page);

        $start = ($page - 1) * $limit;

        return $this->mediaModel->getMediaPage(
            $galleryId,
            $start,
            $limit
        );
    }

    /* ==========================================================
       UPDATE DISPLAY ORDER
    ========================================================== */

    public function updateDisplayOrder()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (empty($_POST['media'])) {
            return;
        }

        foreach ($_POST['media'] as $order => $id) {

            $this->mediaModel->updateDisplayOrder(
                (int)$id,
                $order + 1
            );
        }

        echo json_encode([
            "status" => true
        ]);

        exit;
    }

    /* ==========================================================
       SEARCH REQUEST
    ========================================================== */

    public function searchRequest($galleryId)
    {
        if (empty($_GET['search'])) {

            return $this->getMediaByGallery($galleryId);
        }

        return $this->mediaModel->searchMedia(
            $galleryId,
            trim($_GET['search'])
        );
    }

    /* ==========================================================
       IMAGE REQUEST
    ========================================================== */

    public function imageRequest($galleryId)
    {
        return $this->mediaModel->getImages($galleryId);
    }

    /* ==========================================================
       VIDEO REQUEST
    ========================================================== */

    public function videoRequest($galleryId)
    {
        return $this->mediaModel->getVideos($galleryId);
    }

}