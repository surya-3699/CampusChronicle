<?php
require_once 'app/helpers/FileUpload.php';

require_once "app/Models/Gallery.php";
require_once "app/Models/GalleryMedia.php";
require_once "app/helpers/FileUpload.php";
require_once "app/Controllers/AuthController.php";
require_once "app/Models/Notification.php";

class GalleryController
{
    private Gallery $galleryModel;

    public function __construct()
    {
        $this->galleryModel = new Gallery();
    }

    /* ==========================================================
       DASHBOARD
    ========================================================== */

    public function index()
    {
        return $this->galleryModel->getDashboardStats();
    }

    public function getDashboardStats()
    {
        return $this->galleryModel->getDashboardStats();
    }

    /* ==========================================================
       GET ALL GALLERIES
    ========================================================== */

    public function getAllGallery()
    {
        return $this->galleryModel->getAllGallery();
    }

    /* ==========================================================
       GET PUBLISHED GALLERIES
    ========================================================== */

    public function getPublishedGallery()
    {
        return $this->galleryModel->getPublishedGallery();
    }

    /* ==========================================================
       GET SINGLE GALLERY
    ========================================================== */

    public function getGalleryById($id)
    {
        return $this->galleryModel->getGalleryById($id);
    }

    /* ==========================================================
       GET GALLERY BY SLUG
    ========================================================== */

    public function getGalleryBySlug($slug)
    {
        return $this->galleryModel->getGalleryBySlug($slug);
    }

    /* ==========================================================
       SEARCH GALLERY
    ========================================================== */

    public function searchGallery($keyword)
    {
        return $this->galleryModel->searchGallery($keyword);
    }

    /* ==========================================================
       FILTER BY STATUS
    ========================================================== */

    public function filterStatus($status)
    {
        return $this->galleryModel->getGalleryByStatus($status);
    }

    /* ==========================================================
       FILTER BY TYPE
    ========================================================== */

    public function filterType($type)
    {
        return $this->galleryModel->getGalleryByType($type);
    }
        /* ==========================================================
       SAVE GALLERY
    ========================================================== */

    /** Validates the "Authorized by" field and that the column exists. */
    private function authorizerProblem()
    {
        $error = cc_authorizer_error($_POST['authorized_by'] ?? '');
        if ($error === null && !Database::hasColumn('gallery', 'authorized_by')) {
            $error = 'Database upgrade pending: run "php tools/migrate.php" once to add the "Authorized by" column, then save again.';
        }
        return $error;
    }

    public function saveGallery()
    {
        AuthController::checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $coverImage = "";

        if (
            isset($_FILES['cover_image']) &&
            $_FILES['cover_image']['error'] == 0
        ) {

            $upload = FileUpload::upload(
                $_FILES['cover_image'],
                "public/uploads/gallery/covers/",
                ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'cover',
                10 * 1024 * 1024
            );

            if (!$upload['success']) {

                $_SESSION['error'] = 'Cover image upload failed: ' . $upload['error'];

                header("Location:index.php?page=add-gallery");

                exit;
            }

            $coverImage = $upload['path'];
        }

        if (empty(trim($_POST['title'] ?? ''))) {
            $_SESSION['error'] = 'Please give the gallery a title.';
            header("Location:index.php?page=add-gallery");
            exit;
        }

        $authorizerError = $this->authorizerProblem();
        if ($authorizerError !== null) {
            $_SESSION['error'] = $authorizerError;
            header("Location:index.php?page=add-gallery");
            exit;
        }

        $data = [

            'event_id' => !empty($_POST['event_id'])
                ? $_POST['event_id']
                : null,

            'title' => trim($_POST['title']),

            'description' => trim($_POST['description'] ?? ''),

            'cover_image' => $coverImage,

            'gallery_type' => $_POST['gallery_type'] ?? 'Manual',

            'status' => $_POST['status'] ?? 'Draft',

            'authorized_by' => cc_normalize_authorizer($_POST['authorized_by'] ?? ''),

            'created_by' => $_SESSION['user_id'] ?? null

        ];

        if ($this->galleryModel->createGallery($data)) {

            $newId = (int)$this->galleryModel->getLastInsertId();
            $this->processUploadedMedia($newId);

            if (($data['status'] ?? 'Draft') === 'Published') {
                (new Notification())->broadcast(
                    'gallery',
                    'New gallery: ' . $data['title'],
                    'New campus media is now available in the gallery.',
                    'index.php?page=gallery-album&id=' . (int)$newId
                );
            }

            cc_commit_write(); $_SESSION['success'] = "Gallery created successfully.";

        } else {

            $_SESSION['error'] = "Unable to create gallery.";

        }

        header("Location:index.php?page=admin-gallery");

        exit;
    }
        /* ==========================================================
       UPDATE GALLERY
    ========================================================== */

    public function updateGallery()
    {
        AuthController::checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $id = $_POST['id'] ?? 0;

        $gallery = $this->galleryModel->getGalleryById($id);

        if (!$gallery) {

            $_SESSION['error'] = "Gallery not found.";

            header("Location:index.php?page=admin-gallery");

            exit;
        }

        $authorizerError = $this->authorizerProblem();
        if ($authorizerError !== null) {
            $_SESSION['error'] = $authorizerError;
            header("Location:index.php?page=edit-gallery&id=" . (int)$id);
            exit;
        }

        $coverImage = $gallery['cover_image'];

        if (
            isset($_FILES['cover_image']) &&
            $_FILES['cover_image']['error'] == 0
        ) {

            $upload = FileUpload::upload(
                $_FILES['cover_image'],
                "public/uploads/gallery/covers/",
                ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'cover',
                10 * 1024 * 1024
            );

            if (!$upload['success']) {

                $_SESSION['error'] = 'Cover image upload failed: ' . $upload['error'];

                header("Location:index.php?page=edit-gallery&id=" . $id);

                exit;
            }

            if (
                !empty($coverImage) &&
                file_exists($coverImage)
            ) {
                /* Retain replaced cover for rollback. */
            }

            $coverImage = $upload['path'];
        }

        $data = [

            'id' => $id,

            'event_id' => !empty($_POST['event_id'])
                ? $_POST['event_id']
                : null,

            'title' => trim($_POST['title'] ?? $gallery['title']),

            'description' => trim($_POST['description'] ?? ''),

            'cover_image' => $coverImage,

            'gallery_type' => $_POST['gallery_type'] ?? $gallery['gallery_type'],

            'status' => $_POST['status'] ?? $gallery['status'],

            'authorized_by' => cc_normalize_authorizer($_POST['authorized_by'] ?? '')

        ];

        if ($this->galleryModel->updateGallery($data)) {

            $this->processUploadedMedia((int)$id);

            if (($gallery['status'] ?? 'Draft') !== 'Published' && ($data['status'] ?? 'Draft') === 'Published') {
                (new Notification())->broadcast(
                    'gallery',
                    'New gallery: ' . $data['title'],
                    'New campus media is now available in the gallery.',
                    'index.php?page=gallery-album&id=' . (int)$id
                );
            }

            cc_commit_write(); $_SESSION['success'] = "Gallery updated successfully.";

        } else {

            $_SESSION['error'] = "Failed to update gallery.";

        }

        header("Location:index.php?page=admin-gallery");

        exit;
    }

    /* ==========================================================
       PROCESS MULTIPLE PHOTO/VIDEO UPLOADS
    ========================================================== */

    private function processUploadedMedia($galleryId)
    {
        $galleryId = (int)$galleryId;
        if ($galleryId <= 0 || empty($_FILES['media_files']['name'][0])) {
            return;
        }

        $mediaModel = new GalleryMedia();
        $gallery = $this->galleryModel->getGalleryById($galleryId);
        $hasCover = !empty($gallery['cover_image']);
        $displayOrder = count($mediaModel->getMediaByGallery($galleryId));

        foreach ($_FILES['media_files']['name'] as $index => $name) {
            if (($_FILES['media_files']['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $isImage = in_array($extension, ['jpg','jpeg','png','gif','webp'], true);
            $isVideo = in_array($extension, ['mp4','mov','avi','webm'], true);
            if (!$isImage && !$isVideo) {
                continue;
            }

            $type = $isImage ? 'image' : 'video';
            $targetDir = $isImage ? 'public/uploads/gallery/images/' : 'public/uploads/gallery/videos/';
            $allowed = $isImage ? ['jpg','jpeg','png','gif','webp'] : ['mp4','mov','avi','webm'];
            $file = [
                'name' => $_FILES['media_files']['name'][$index],
                'type' => $_FILES['media_files']['type'][$index],
                'tmp_name' => $_FILES['media_files']['tmp_name'][$index],
                'error' => $_FILES['media_files']['error'][$index],
                'size' => $_FILES['media_files']['size'][$index]
            ];
            $detectedMime = FileUpload::getMimeType($file['tmp_name']);
            if (($isImage && !str_starts_with((string)$detectedMime, 'image/')) ||
                ($isVideo && !str_starts_with((string)$detectedMime, 'video/'))) {
                continue;
            }

            $maxBytes = $isVideo ? 50 * 1024 * 1024 : 10 * 1024 * 1024;
            $upload = FileUpload::upload($file, $targetDir, $allowed, 'media', $maxBytes);
            if (!$upload['success']) {
                continue;
            }

            $mediaModel->addMedia([
                'gallery_id' => $galleryId,
                'media_type' => $type,
                'title' => pathinfo($name, PATHINFO_FILENAME),
                'description' => '',
                'file_name' => basename($upload['path']),
                'file_path' => $upload['path'],
                'thumbnail_path' => '',
                'file_size' => $file['size'],
                'mime_type' => $detectedMime,
                'display_order' => $displayOrder++
            ]);

            if (!$hasCover && $isImage) {
                $this->galleryModel->updateCoverImage($galleryId, $upload['path']);
                $hasCover = true;
            }
        }

        $this->galleryModel->refreshGalleryCounts($galleryId);
    }

    /* ==========================================================
       DELETE GALLERY
    ========================================================== */

    public function deleteGallery()
    {
        AuthController::checkAdmin();
        $id = (int)($_GET['id'] ?? 0);
        $gallery = $this->galleryModel->getGalleryById($id);
        if (!$gallery) {
            $_SESSION['error'] = "Gallery not found.";
            header("Location:index.php?page=admin-gallery");
            exit;
        }

        // Collect media paths first so the DB delete never leaves obvious orphaned files.
        require_once "app/Models/GalleryMedia.php";
        $mediaModel = new GalleryMedia();
        $media = $mediaModel->getMediaByGallery($id);
        if ($this->galleryModel->deleteGallery($id)) {
            foreach ($media as $item) {
                foreach (['file_path','thumbnail_path'] as $field) {
                    $path = trim((string)($item[$field] ?? ''));
                    if ($path !== '' && is_file($path)) { FileUpload::deleteFile($path); }
                }
            }
            $cover = trim((string)($gallery['cover_image'] ?? ''));
            if ($cover !== '' && is_file($cover)) { FileUpload::deleteFile($cover); }
            cc_commit_write(); $_SESSION['success'] = "Gallery and its media deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete gallery.";
        }
        header("Location:index.php?page=admin-gallery");
        exit;
    }

    /* ==========================================================
       GALLERY COUNTS
    ========================================================== */

    public function getGalleryCount()
    {
        return $this->galleryModel->getGalleryCount();
    }

    public function getPublishedCount()
    {
        return $this->galleryModel->getPublishedCount();
    }

    public function getDraftCount()
    {
        return $this->galleryModel->getDraftCount();
    }

    public function getEventGalleryCount()
    {
        return $this->galleryModel->getEventGalleryCount();
    }

    public function getManualGalleryCount()
    {
        return $this->galleryModel->getManualGalleryCount();
    }

    /* ==========================================================
       RECENT GALLERIES
    ========================================================== */

    public function getRecentGallery($limit = 5)
    {
        return $this->galleryModel->getRecentGallery($limit);
    }

    /* ==========================================================
       PAGINATION
    ========================================================== */

    public function getGalleryPage($page = 1, $limit = 10)
    {
        $page = max(1, (int)$page);

        $start = ($page - 1) * $limit;

        return $this->galleryModel->getGalleryPage($start, $limit);
    }

    public function ensureCompletedEventGalleries()
    {
        return $this->galleryModel->ensureCompletedEventGalleries();
    }

    /* ==========================================================
       CREATE EVENT GALLERY
    ========================================================== */

    public function createEventGallery($eventData)
    {
        if (empty($eventData['event_id'])) {
            return false;
        }

        if ($this->galleryModel->eventGalleryExists($eventData['event_id'])) {
            return true;
        }

        return $this->galleryModel->createEventGallery($eventData);
    }

    /* ==========================================================
       REFRESH PHOTO / VIDEO COUNTS
    ========================================================== */

    public function refreshGalleryCounts($galleryId)
    {
        return $this->galleryModel->refreshGalleryCounts($galleryId);
    }

    /* ==========================================================
       GET GALLERY BY EVENT
    ========================================================== */

    public function getGalleryByEvent($eventId)
    {
        return $this->galleryModel->getGalleryByEvent($eventId);
    }

    /* ==========================================================
       SEARCH REQUEST
    ========================================================== */

    public function searchRequest()
    {
        if (empty($_GET['search'])) {
            return $this->getAllGallery();
        }

        return $this->galleryModel->searchGallery(
            trim($_GET['search'])
        );
    }

    /* ==========================================================
       STATUS REQUEST
    ========================================================== */

    public function statusRequest()
    {
        if (empty($_GET['status'])) {
            return $this->getAllGallery();
        }

        return $this->galleryModel->getGalleryByStatus(
            $_GET['status']
        );
    }

    /* ==========================================================
       TYPE REQUEST
    ========================================================== */

    public function typeRequest()
    {
        if (empty($_GET['type'])) {
            return $this->getAllGallery();
        }

        return $this->galleryModel->getGalleryByType(
            $_GET['type']
        );
    }

}
