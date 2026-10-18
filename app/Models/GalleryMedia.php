<?php
require_once 'app/helpers/FileUpload.php';

require_once "config/database.php";

class GalleryMedia
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }

    /* ==========================================================
       ADD MEDIA
    ========================================================== */

    public function addMedia($data)
    {
        $sql = "
            INSERT INTO gallery_media
            (
                gallery_id,
                media_type,
                title,
                description,
                file_name,
                file_path,
                thumbnail_path,
                file_size,
                mime_type,
                display_order
            )
            VALUES
            (
                :gallery_id,
                :media_type,
                :title,
                :description,
                :file_name,
                :file_path,
                :thumbnail_path,
                :file_size,
                :mime_type,
                :display_order
            )
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([

            'gallery_id'      => $data['gallery_id'],

            'media_type'      => $data['media_type'],

            'title'           => $data['title'] ?? null,

            'description'     => $data['description'] ?? null,

            'file_name'       => $data['file_name'],

            'file_path'       => $data['file_path'],

            'thumbnail_path'  => $data['thumbnail_path'] ?? null,

            'file_size'       => $data['file_size'],

            'mime_type'       => $data['mime_type'],

            'display_order'   => $data['display_order'] ?? 0

        ]);
    }

    /* ==========================================================
       GET MEDIA BY ID
    ========================================================== */

    public function getMediaById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM gallery_media
            WHERE id=?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       GET ALL MEDIA OF A GALLERY
    ========================================================== */

    public function getMediaByGallery($galleryId)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM gallery_media
            WHERE gallery_id=?
            ORDER BY display_order ASC,
                     uploaded_at DESC
        ");

        $stmt->execute([$galleryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       GET ONLY IMAGES
    ========================================================== */

    public function getImages($galleryId)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM gallery_media
            WHERE gallery_id=?
            AND media_type='image'
            ORDER BY display_order ASC
        ");

        $stmt->execute([$galleryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       GET ONLY VIDEOS
    ========================================================== */

    public function getVideos($galleryId)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM gallery_media
            WHERE gallery_id=?
            AND media_type='video'
            ORDER BY display_order ASC
        ");

        $stmt->execute([$galleryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       UPDATE MEDIA
    ========================================================== */

    public function updateMedia($data)
    {
        $stmt = $this->conn->prepare("
            UPDATE gallery_media

            SET

                title=:title,

                description=:description,

                display_order=:display_order

            WHERE id=:id
        ");

        return $stmt->execute([

            'id'             => $data['id'],

            'title'          => $data['title'],

            'description'    => $data['description'],

            'display_order'  => $data['display_order']

        ]);
    }

    /* ==========================================================
       DELETE MEDIA
    ========================================================== */

    public function deleteMedia($id)
    {
        $stmt = $this->conn->prepare("
            DELETE
            FROM gallery_media
            WHERE id=?
        ");

        return $stmt->execute([$id]);
    }
        /* ==========================================================
       TOTAL MEDIA COUNT
    ========================================================== */

    public function getMediaCount($galleryId)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM gallery_media
            WHERE gallery_id=?
        ");

        $stmt->execute([$galleryId]);

        return $stmt->fetchColumn();
    }

    /* ==========================================================
       IMAGE COUNT
    ========================================================== */

    public function getImageCount($galleryId)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM gallery_media
            WHERE gallery_id=?
            AND media_type='image'
        ");

        $stmt->execute([$galleryId]);

        return $stmt->fetchColumn();
    }

    /* ==========================================================
       VIDEO COUNT
    ========================================================== */

    public function getVideoCount($galleryId)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM gallery_media
            WHERE gallery_id=?
            AND media_type='video'
        ");

        $stmt->execute([$galleryId]);

        return $stmt->fetchColumn();
    }

    /* ==========================================================
       SEARCH MEDIA
    ========================================================== */

    public function searchMedia($galleryId, $keyword)
    {
        $search = "%{$keyword}%";

        $stmt = $this->conn->prepare("
            SELECT *
            FROM gallery_media
            WHERE gallery_id=?
            AND
            (
                title LIKE ?
                OR description LIKE ?
                OR file_name LIKE ?
            )
            ORDER BY display_order ASC
        ");

        $stmt->execute([
            $galleryId,
            $search,
            $search,
            $search
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       RECENT MEDIA
    ========================================================== */

    public function getRecentMedia($limit = 10)
    {
        $limit = (int)$limit;

        $stmt = $this->conn->query("
            SELECT *
            FROM gallery_media
            ORDER BY uploaded_at DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* ==========================================================
       RECENT MEDIA WITH ALBUM CONTEXT
    ========================================================== */

    public function getRecentMediaWithGallery($limit = 8)
    {
        $limit = max(1, min(24, (int)$limit));
        $stmt = $this->conn->query("
            SELECT gm.*, g.title AS gallery_title, g.event_id
            FROM gallery_media gm
            INNER JOIN gallery g ON g.id = gm.gallery_id
            WHERE g.status = 'Published'
            ORDER BY gm.uploaded_at DESC
            LIMIT {$limit}
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       UPDATE DISPLAY ORDER
    ========================================================== */

    public function updateDisplayOrder($id, $order)
    {
        $stmt = $this->conn->prepare("
            UPDATE gallery_media
            SET display_order=?
            WHERE id=?
        ");

        return $stmt->execute([
            $order,
            $id
        ]);
    }

    /* ==========================================================
       DELETE ALL MEDIA OF A GALLERY
    ========================================================== */

    public function deleteGalleryMedia($galleryId)
    {
        $media = $this->getMediaByGallery($galleryId);

        foreach ($media as $item) {

            if (!empty($item['file_path']) && file_exists($item['file_path'])) {
                unlink($item['file_path']);
            }

            if (!empty($item['thumbnail_path']) && file_exists($item['thumbnail_path'])) {
                unlink($item['thumbnail_path']);
            }
        }

        $stmt = $this->conn->prepare("
            DELETE
            FROM gallery_media
            WHERE gallery_id=?
        ");

        return $stmt->execute([$galleryId]);
    }

    /* ==========================================================
       DELETE SINGLE FILE
    ========================================================== */

    public function deleteMediaFile($id)
    {
        $media = $this->getMediaById($id);

        if (!$media) {
            return false;
        }

        if (!$this->deleteMedia($id)) return false;
        if (!empty($media['file_path']) && file_exists($media['file_path'])) {
            FileUpload::deleteFile($media['file_path']);
        }

        if (!empty($media['thumbnail_path']) && file_exists($media['thumbnail_path'])) {
            FileUpload::deleteFile($media['thumbnail_path']);
        }

        return true;
    }

    /* ==========================================================
       GALLERY MEDIA STATISTICS
    ========================================================== */

    public function getStatistics($galleryId)
    {
        return [

            'total' => $this->getMediaCount($galleryId),

            'images' => $this->getImageCount($galleryId),

            'videos' => $this->getVideoCount($galleryId)

        ];
    }

    /* ==========================================================
       PAGINATION
    ========================================================== */

    public function getMediaPage($galleryId, $start, $limit)
    {
        $start = (int)$start;
        $limit = (int)$limit;

        $stmt = $this->conn->prepare("
            SELECT *
            FROM gallery_media
            WHERE gallery_id=?
            ORDER BY display_order ASC
            LIMIT {$start}, {$limit}
        ");

        $stmt->execute([$galleryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}