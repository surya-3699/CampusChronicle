<?php

require_once "config/database.php";

class Event
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }
    /*========================================
    LIVE STATUS (computed from real dates, not
    the legacy `status` column, so it can never
    go stale)
========================================*/
public static function computeStatus($event)
{
    $now = time();
    $start = strtotime(($event['event_date'] ?? '') . ' ' . ($event['event_time'] ?? '00:00'));
    $endDate = !empty($event['end_date']) ? $event['end_date'] : ($event['event_date'] ?? '');
    $endTime = !empty($event['end_time']) ? $event['end_time'] : '23:59:59';
    $end = strtotime($endDate . ' ' . $endTime);

    if ($start === false) {
        return 'Upcoming';
    }

    if ($end !== false && $now > $end) {
        return 'Completed';
    }

    if ($now < $start) {
        return 'Upcoming';
    }

    return 'Ongoing';
}

    /*========================================
    BASE EVENT QUERY
========================================*/
private function baseEventQuery()
{
    return "
        SELECT
            events.*,
            categories.category_name
        FROM events
        LEFT JOIN categories
            ON events.category_id = categories.id
    ";
}

 /*========================================
    GET ALL EVENTS
========================================*/
public function getAllEvents()
{
    $sql = $this->baseEventQuery() . "
        ORDER BY
            events.event_date ASC,
            events.event_time ASC
    ";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
/*========================================
    GET PUBLISHED EVENTS
========================================*/
public function getPublishedEvents()
{
    $sql = $this->baseEventQuery() . "
        WHERE events.publish = 'Yes'
        ORDER BY
            events.event_date ASC,
            events.event_time ASC
    ";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*========================================
    GET PUBLISHED EVENT COUNT (for stats)
========================================*/
public function getEventCount()
{
    return $this->conn
        ->query("SELECT COUNT(*) FROM events WHERE publish = 'Yes'")
        ->fetchColumn();
}

    // Get single event
    public function getEventById($id)
    {
        $sql = $this->baseEventQuery() . " WHERE events.id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

   public function getUpcomingEvents($categoryId = null)
{
    $sql = $this->baseEventQuery() . "
        WHERE
            events.publish = 'Yes'
            AND NOW() < TIMESTAMP(events.event_date, events.event_time)
            " . ($categoryId ? "AND events.category_id = :category_id" : "") . "
        ORDER BY
            events.event_date ASC,
            events.event_time ASC
    ";

    $stmt = $this->conn->prepare($sql);
    if ($categoryId) {
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
/*========================================
    GET ONGOING EVENTS
========================================*/
public function getOngoingEvents($categoryId = null)
{
    $sql = $this->baseEventQuery() . "
        WHERE
            events.publish = 'Yes'
            AND NOW() BETWEEN
                TIMESTAMP(events.event_date, events.event_time)
            AND
                TIMESTAMP(
                    COALESCE(events.end_date, events.event_date),
                    COALESCE(events.end_time, '23:59:59')
                )
            " . ($categoryId ? "AND events.category_id = :category_id" : "") . "
        ORDER BY
            events.event_date ASC,
            events.event_time ASC
    ";

    $stmt = $this->conn->prepare($sql);
    if ($categoryId) {
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    // Present events
  /*========================================
    PRESENT EVENTS (Compatibility)
========================================*/
public function getPresentEvents($categoryId = null)
{
    return $this->getOngoingEvents($categoryId);
}
 /*========================================
    GET COMPLETED EVENTS
========================================*/
public function getCompletedEvents($categoryId = null)
{
    $sql = $this->baseEventQuery() . "
        WHERE
            events.publish = 'Yes'
            AND NOW() > TIMESTAMP(
                COALESCE(events.end_date, events.event_date),
                COALESCE(events.end_time, '23:59:59')
            )
            " . ($categoryId ? "AND events.category_id = :category_id" : "") . "
        ORDER BY
            COALESCE(events.end_date, events.event_date) DESC,
            COALESCE(events.end_time, '23:59:59') DESC
    ";

    $stmt = $this->conn->prepare($sql);
    if ($categoryId) {
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Add Event
public function addEvent($data)
{
    // "authorized_by" is only written once the column exists (tools/migrate.php).
    $withAuth = array_key_exists('authorized_by', $data) && Database::hasColumn('events', 'authorized_by');
    if (!$withAuth) {
        unset($data['authorized_by']);
    }

    $sql = "INSERT INTO events
    (
        title,
        category_id,
        event_date,
        event_time,
        end_date,
        end_time,
        venue,
        google_map,
        description,
        rules,
        prizes,
        schedule,
        registration_link,
        max_registrations,
        banner,
        faculty_name,
        faculty_photo,
        student_name,
        student_photo,
        gallery_status,
        publish,
        " . ($withAuth ? "authorized_by,\n        " : "") . "created_at
    )
    VALUES
    (
        :title,
        :category_id,
        :event_date,
        :event_time,
        :end_date,
        :end_time,
        :venue,
        :google_map,
        :description,
        :rules,
        :prizes,
        :schedule,
        :registration_link,
        :max_registrations,
        :banner,
        :faculty_name,
        :faculty_photo,
        :student_name,
        :student_photo,
        :gallery_status,
        :publish,
        " . ($withAuth ? ":authorized_by,\n        " : "") . "NOW()
    )";

    $stmt = $this->conn->prepare($sql);

    return $stmt->execute($data);
}

// Last Insert Id
public function lastInsertId()
{
    return $this->conn->lastInsertId();
}

// Update Event
public function updateEvent($id, $data)
{
    $withAuth = array_key_exists('authorized_by', $data) && Database::hasColumn('events', 'authorized_by');
    if (!$withAuth) {
        unset($data['authorized_by']);
    }

    $sql = "UPDATE events SET

        title=:title,
        category_id=:category_id,
        event_date=:event_date,
        event_time=:event_time,
        end_date=:end_date,
        end_time=:end_time,
        venue=:venue,
        google_map=:google_map,
        description=:description,
        rules=:rules,
        prizes=:prizes,
        schedule=:schedule,
        registration_link=:registration_link,
        max_registrations=:max_registrations,
        banner=:banner,
        faculty_name=:faculty_name,
        faculty_photo=:faculty_photo,
        student_name=:student_name,
        student_photo=:student_photo,
        gallery_status=:gallery_status,
        " . ($withAuth ? "authorized_by=:authorized_by,\n        " : "") . "publish=:publish

        WHERE id=:id";

    $stmt = $this->conn->prepare($sql);

    $data['id'] = $id;

    return $stmt->execute($data);
}// Delete Event — explicitly remove dependent rows first so the action works
// even when an older local database has RESTRICT-style foreign keys.
public function deleteEvent($id)
{
    $id = (int)$id;
    if ($id <= 0) {
        return false;
    }

    try {
        $this->conn->beginTransaction();

        // Capture gallery media files before their rows are removed.
        $mediaStmt = $this->conn->prepare("
            SELECT gm.file_path, gm.thumbnail_path
            FROM gallery_media gm
            INNER JOIN gallery g ON g.id = gm.gallery_id
            WHERE g.event_id = ?
        ");
        $mediaStmt->execute([$id]);
        $mediaFiles = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

        // Delete certificate rows first because certificates may reference both
        // the event and a registration.
        $this->conn->prepare("DELETE FROM certificates WHERE event_id = ?")->execute([$id]);

        // Delete gallery media, then event galleries.
        $this->conn->prepare("
            DELETE gm FROM gallery_media gm
            INNER JOIN gallery g ON g.id = gm.gallery_id
            WHERE g.event_id = ?
        ")->execute([$id]);
        $this->conn->prepare("DELETE FROM gallery WHERE event_id = ?")->execute([$id]);

        // Registration rows are the FK that caused the reported crash.
        $this->conn->prepare("DELETE FROM registrations WHERE event_id = ?")->execute([$id]);

        $stmt = $this->conn->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$id]);

        $deleted = $stmt->rowCount() > 0;
        if (!$deleted) {
            $this->conn->rollBack();
            return false;
        }

        $this->conn->commit();

        // Clean up physical media files after the DB transaction succeeds.
        foreach ($mediaFiles as $media) {
            foreach (['file_path', 'thumbnail_path'] as $key) {
                $path = trim((string)($media[$key] ?? ''));
                if ($path !== '' && is_file($path)) {
                    require_once 'app/helpers/FileUpload.php'; FileUpload::deleteFile($path);
                }
            }
        }

        return true;
    } catch (Throwable $e) {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
        error_log('Event delete failed: ' . $e->getMessage());
        return false;
    }
}
}