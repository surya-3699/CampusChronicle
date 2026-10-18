<?php

require_once "config/database.php";
require_once "app/Models/Notification.php";

class Gallery
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }

    /* ==========================================================
       GENERATE SLUG
    ========================================================== */

    private function generateSlug($title)
    {
        $slug = strtolower(trim($title));

        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        $slug = trim($slug, '-');

        $baseSlug = $slug;

        $count = 1;

        while ($this->slugExists($slug)) {

            $slug = $baseSlug . "-" . $count;

            $count++;
        }

        return $slug;
    }

    private function slugExists($slug)
    {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM gallery
            WHERE slug = ?
            LIMIT 1
        ");

        $stmt->execute([$slug]);

        return $stmt->rowCount() > 0;
    }

    /* ==========================================================
       CREATE GALLERY
    ========================================================== */

    public function createGallery($data)
    {
        // "authorized_by" is only written once the column exists (tools/migrate.php).
        $withAuth = array_key_exists('authorized_by', $data) && Database::hasColumn('gallery', 'authorized_by');

        $sql = "
            INSERT INTO gallery
            (
                event_id,
                title,
                slug,
                description,
                cover_image,
                total_photos,
                total_videos,
                gallery_type,
                status,
                " . ($withAuth ? "authorized_by,\n                " : "") . "created_by
            )
            VALUES
            (
                :event_id,
                :title,
                :slug,
                :description,
                :cover_image,
                0,
                0,
                :gallery_type,
                :status,
                " . ($withAuth ? ":authorized_by,\n                " : "") . ":created_by
            )
        ";

        $params = [

            'event_id'      => $data['event_id'] ?? null,

            'title'         => $data['title'],

            'slug'          => $this->generateSlug($data['title']),

            'description'   => $data['description'],

            'cover_image'   => $data['cover_image'],

            'gallery_type'  => $data['gallery_type'],

            'status'        => $data['status'],

            'created_by'    => $data['created_by'] ?? null

        ];
        if ($withAuth) {
            $params['authorized_by'] = $data['authorized_by'];
        }

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute($params);
    }

    public function getLastInsertId()
    {
        return (int)$this->conn->lastInsertId();
    }

    /* ==========================================================
       UPDATE GALLERY
    ========================================================== */

    public function updateGallery($data)
    {
        // The event-sync path calls this without "authorized_by"; leave the
        // stored value untouched in that case.
        $withAuth = array_key_exists('authorized_by', $data) && Database::hasColumn('gallery', 'authorized_by');

        $sql = "
            UPDATE gallery
            SET

                event_id      = :event_id,

                title         = :title,

                description   = :description,

                cover_image   = :cover_image,

                gallery_type  = :gallery_type,

                status        = :status,

                " . ($withAuth ? "authorized_by = :authorized_by,\n\n                " : "") . "updated_at    = CURRENT_TIMESTAMP

            WHERE id = :id
        ";

        $params = [

            'id'            => $data['id'],

            'event_id'      => $data['event_id'],

            'title'         => $data['title'],

            'description'   => $data['description'],

            'cover_image'   => $data['cover_image'],

            'gallery_type'  => $data['gallery_type'],

            'status'        => $data['status']

        ];
        if ($withAuth) {
            $params['authorized_by'] = $data['authorized_by'];
        }

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute($params);
    }

    /* ==========================================================
       DELETE GALLERY
    ========================================================== */

    public function deleteGallery($id)
    {
        $stmt = $this->conn->prepare("
            DELETE
            FROM gallery
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    /* ==========================================================
       GET GALLERY BY ID
    ========================================================== */

    public function getGalleryById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT
                g.*,
                e.title AS event_title,
                e.event_date,
                e.end_date,
                e.event_time,
                e.end_time
            FROM gallery g
            LEFT JOIN events e
                ON g.event_id = e.id
            WHERE g.id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       GET ALL GALLERIES
    ========================================================== */

    public function getAllGallery()
    {
        $stmt = $this->conn->prepare("
            SELECT

                g.*,

                e.title AS event_title

            FROM gallery g

            LEFT JOIN events e

                ON g.event_id = e.id

            ORDER BY

                g.created_at DESC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       GET PUBLISHED GALLERIES
    ========================================================== */

    public function getPublishedGallery()
    {
        $stmt = $this->conn->prepare("
            SELECT

                g.*,

                e.title AS event_title

            FROM gallery g

            LEFT JOIN events e

                ON g.event_id = e.id

            WHERE

                g.status = 'Published'
                AND (g.gallery_type <> 'Event'
                     OR e.id IS NULL
                     OR NOW() > TIMESTAMP(COALESCE(e.end_date,e.event_date), COALESCE(e.end_time,'23:59:59')))

            ORDER BY

                g.created_at DESC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       GET GALLERY BY SLUG
    ========================================================== */

    public function getGalleryBySlug($slug)
    {
        $stmt = $this->conn->prepare("
            SELECT
                *
            FROM gallery
            WHERE slug = ?
            LIMIT 1
        ");

        $stmt->execute([$slug]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }    /* ==========================================================
       DASHBOARD STATISTICS
    ========================================================== */

    public function getDashboardStats()
    {
        $stmt = $this->conn->query("
            SELECT
                COUNT(*) AS total_galleries,

                SUM(CASE
                    WHEN status='Published'
                    THEN 1
                    ELSE 0
                END) AS published,

                SUM(CASE
                    WHEN status='Draft'
                    THEN 1
                    ELSE 0
                END) AS draft,

                SUM(total_photos) AS total_photos,

                SUM(total_videos) AS total_videos

            FROM gallery
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       SEARCH GALLERY
    ========================================================== */

    public function searchGallery($keyword)
    {
        $stmt = $this->conn->prepare("
            SELECT

                g.*,

                e.title AS event_title

            FROM gallery g

            LEFT JOIN events e

                ON g.event_id=e.id

            WHERE

                g.title LIKE ?

                OR

                g.description LIKE ?

                OR

                e.title LIKE ?

            ORDER BY

                g.created_at DESC
        ");

        $search = "%" . $keyword . "%";

        $stmt->execute([
            $search,
            $search,
            $search
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       FILTER BY STATUS
    ========================================================== */

    public function getGalleryByStatus($status)
    {
        $stmt = $this->conn->prepare("
            SELECT

                g.*,

                e.title AS event_title

            FROM gallery g

            LEFT JOIN events e

                ON g.event_id=e.id

            WHERE

                g.status=?

            ORDER BY

                g.created_at DESC
        ");

        $stmt->execute([$status]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       FILTER BY TYPE
    ========================================================== */

    public function getGalleryByType($type)
    {
        $stmt = $this->conn->prepare("
            SELECT

                g.*,

                e.title AS event_title

            FROM gallery g

            LEFT JOIN events e

                ON g.event_id=e.id

            WHERE

                g.gallery_type=?

            ORDER BY

                g.created_at DESC
        ");

        $stmt->execute([$type]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       RECENT GALLERIES
    ========================================================== */

    public function getRecentGallery($limit = 5)
    {
        $limit = (int)$limit;

        $stmt = $this->conn->query("
            SELECT

                g.*,

                e.title AS event_title

            FROM gallery g

            LEFT JOIN events e

                ON g.event_id=e.id

            ORDER BY

                g.created_at DESC

            LIMIT {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       TOTAL GALLERY COUNT
    ========================================================== */

    public function getGalleryCount()
    {
        return $this->conn
            ->query("SELECT COUNT(*) FROM gallery")
            ->fetchColumn();
    }

    /* ==========================================================
       PUBLISHED COUNT
    ========================================================== */

    public function getPublishedCount()
    {
        return $this->conn
            ->query("
                SELECT COUNT(*)
                FROM gallery
                WHERE status='Published'
            ")
            ->fetchColumn();
    }

    /* ==========================================================
       DRAFT COUNT
    ========================================================== */

    public function getDraftCount()
    {
        return $this->conn
            ->query("
                SELECT COUNT(*)
                FROM gallery
                WHERE status='Draft'
            ")
            ->fetchColumn();
    }

    /* ==========================================================
       EVENT GALLERY COUNT
    ========================================================== */

    public function getEventGalleryCount()
    {
        return $this->conn
            ->query("
                SELECT COUNT(*)
                FROM gallery
                WHERE gallery_type='Event'
            ")
            ->fetchColumn();
    }

    /* ==========================================================
       MANUAL GALLERY COUNT
    ========================================================== */

    public function getManualGalleryCount()
    {
        return $this->conn
            ->query("
                SELECT COUNT(*)
                FROM gallery
                WHERE gallery_type='Manual'
            ")
            ->fetchColumn();
    }

    /* ==========================================================
       PAGINATION
    ========================================================== */

    public function getGalleryPage($start, $limit)
    {
        $start = (int)$start;
        $limit = (int)$limit;

        $stmt = $this->conn->query("
            SELECT

                g.*,

                e.title AS event_title

            FROM gallery g

            LEFT JOIN events e

                ON g.event_id=e.id

            ORDER BY

                g.created_at DESC

            LIMIT {$start}, {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }    public function ensureCompletedEventGalleries()
    {
        $created = 0;
        // First publish existing event galleries when their event has completed.
        $sql = "SELECT g.id, g.event_id, e.title
                FROM gallery g JOIN events e ON e.id=g.event_id
                WHERE g.gallery_type='Event' AND g.status='Draft' AND e.publish='Yes'
                  AND NOW() > TIMESTAMP(COALESCE(e.end_date,e.event_date), COALESCE(e.end_time,'23:59:59'))";
        $rows = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row){
            $u=$this->conn->prepare("UPDATE gallery SET status='Published' WHERE id=?");$u->execute([(int)$row['id']]);
            $e=$this->conn->prepare("UPDATE events SET gallery_status='Published' WHERE id=?");$e->execute([(int)$row['event_id']]);
            (new Notification())->broadcast('gallery','Event gallery is live: '.$row['title'],'The completed event is now available in the campus gallery.','index.php?page=gallery-album&id='.(int)$row['id']);
        }
        // Then create missing galleries for completed events.
        // Auto-created albums inherit the event's "Authorized by" when it is recorded.
        $authColumn = Database::hasColumn('events', 'authorized_by') ? ', e.authorized_by' : '';
        $sql = "SELECT e.id, e.title, e.description, e.banner" . $authColumn . "
                FROM events e
                WHERE e.publish='Yes'
                  AND NOW() > TIMESTAMP(COALESCE(e.end_date,e.event_date), COALESCE(e.end_time,'23:59:59'))
                  AND NOT EXISTS (SELECT 1 FROM gallery g WHERE g.event_id=e.id)";
        $events=$this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach($events as $event){
            $cover=!empty($event['banner'])?'public/uploads/event_banners/'.$event['banner']:'public/images/gallery/default-cover.svg';
            $id=$this->createEventGallery(['event_id'=>(int)$event['id'],'title'=>$event['title'],'description'=>$event['description'],'cover_image'=>$cover,'status'=>'Published','authorized_by'=>$event['authorized_by'] ?? null,'created_by'=>null]);
            if($id){$created++;$u=$this->conn->prepare("UPDATE events SET gallery_status='Published' WHERE id=?");$u->execute([(int)$event['id']]);(new Notification())->broadcast('gallery','Event gallery is live: '.$event['title'],'The completed event is now available in the campus gallery.','index.php?page=gallery-album&id='.(int)$id);}
        }
        return $created+count($rows);
    }

    /* ==========================================================
       AUTO CREATE EVENT GALLERY
    ========================================================== */

    public function createEventGallery($event)
    {
        $withAuth = !empty($event['authorized_by']) && Database::hasColumn('gallery', 'authorized_by');

        $sql = "
            INSERT INTO gallery
            (
                event_id,
                title,
                slug,
                description,
                cover_image,
                total_photos,
                total_videos,
                gallery_type,
                status,
                " . ($withAuth ? "authorized_by,\n                " : "") . "created_by
            )
            VALUES
            (
                :event_id,
                :title,
                :slug,
                :description,
                :cover_image,
                0,
                0,
                'Event',
                :status,
                " . ($withAuth ? ":authorized_by,\n                " : "") . ":created_by
            )
        ";

        $params = [

            'event_id'     => $event['event_id'],

            'title'        => $event['title'],

            'slug'         => $this->generateSlug($event['title']),

            'description'  => $event['description'],

            'cover_image'  => $event['cover_image'],

            'status'       => $event['status'] ?? 'Draft',

            'created_by'   => $event['created_by'] ?? null

        ];
        if ($withAuth) {
            $params['authorized_by'] = $event['authorized_by'];
        }

        $stmt = $this->conn->prepare($sql);

        $stmt->execute($params);

        return $this->conn->lastInsertId();
    }

    /* ==========================================================
       UPDATE PHOTO COUNT
    ========================================================== */

    public function updatePhotoCount($galleryId)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM gallery_media
            WHERE gallery_id = ?
            AND media_type='image'
        ");

        $stmt->execute([$galleryId]);

        $count = $stmt->fetchColumn();

        $update = $this->conn->prepare("
            UPDATE gallery
            SET total_photos=?
            WHERE id=?
        ");

        return $update->execute([
            $count,
            $galleryId
        ]);
    }

    /* ==========================================================
       UPDATE VIDEO COUNT
    ========================================================== */

    public function updateVideoCount($galleryId)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM gallery_media
            WHERE gallery_id = ?
            AND media_type='video'
        ");

        $stmt->execute([$galleryId]);

        $count = $stmt->fetchColumn();

        $update = $this->conn->prepare("
            UPDATE gallery
            SET total_videos=?
            WHERE id=?
        ");

        return $update->execute([
            $count,
            $galleryId
        ]);
    }

    /* ==========================================================
       REFRESH MEDIA COUNTS
    ========================================================== */

    public function refreshGalleryCounts($galleryId)
    {
        $this->updatePhotoCount($galleryId);

        $this->updateVideoCount($galleryId);
    }

    /* ==========================================================
       DELETE ALL MEDIA
    ========================================================== */

    public function deleteGalleryMedia($galleryId)
    {
        $stmt = $this->conn->prepare("
            DELETE
            FROM gallery_media
            WHERE gallery_id=?
        ");

        return $stmt->execute([$galleryId]);
    }

    /* ==========================================================
       GET GALLERY COVER
    ========================================================== */

    public function getCoverImage($galleryId)
    {
        $stmt = $this->conn->prepare("
            SELECT cover_image
            FROM gallery
            WHERE id=?
        ");

        $stmt->execute([$galleryId]);

        return $stmt->fetchColumn();
    }

    /* ==========================================================
       UPDATE COVER IMAGE
    ========================================================== */

    public function updateCoverImage($galleryId, $coverImage)
    {
        $stmt = $this->conn->prepare("
            UPDATE gallery
            SET cover_image=?
            WHERE id=?
        ");

        return $stmt->execute([
            $coverImage,
            $galleryId
        ]);
    }

    /* ==========================================================
       DELETE COVER IMAGE
    ========================================================== */

    public function removeCoverImage($galleryId)
    {
        $cover = $this->getCoverImage($galleryId);

        if (!empty($cover) && file_exists($cover)) {
            unlink($cover);
        }

        return true;
    }

    /* ==========================================================
       DELETE COMPLETE GALLERY
    ========================================================== */

    public function deleteCompleteGallery($galleryId)
    {
        try {

            $this->conn->beginTransaction();

            $this->removeCoverImage($galleryId);

            $this->deleteGalleryMedia($galleryId);

            $this->deleteGallery($galleryId);

            $this->conn->commit();

            return true;

        } catch (Exception $e) {

            $this->conn->rollBack();

            return false;
        }
    }

    /* ==========================================================
       CHECK EVENT GALLERY EXISTS
    ========================================================== */

    public function eventGalleryExists($eventId)
    {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM gallery
            WHERE event_id=?
            LIMIT 1
        ");

        $stmt->execute([$eventId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==========================================================
       GET GALLERY BY EVENT
    ========================================================== */

    public function getGalleryByEvent($eventId)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM gallery
            WHERE event_id=?
            LIMIT 1
        ");

        $stmt->execute([$eventId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}