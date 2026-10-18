<?php

require_once "app/Models/Event.php";
require_once "app/Models/Registration.php";
require_once "app/helpers/FileUpload.php";
require_once "app/Controllers/GalleryController.php";
require_once "app/Controllers/GalleryMediaController.php";
require_once "app/Controllers/AuthController.php";
require_once "app/Models/Notification.php";

class EventController
{
    private $eventModel;
    private $registrationModel;
    private $galleryController;
    private $mediaController;

    public function __construct()
    {
        $this->eventModel = new Event();
        $this->registrationModel = new Registration();
        $this->galleryController = new GalleryController();
        $this->mediaController = new GalleryMediaController();
    }

    /* ==================================================
        GET ALL EVENTS
    ================================================== */

    public function getAllEvents()
    {
        return $this->eventModel->getAllEvents();
    }

    /* ==================================================
        GET PUBLISHED EVENTS
    ================================================== */

    public function getPublishedEvents()
    {
        return $this->eventModel->getPublishedEvents();
    }

    /* ==================================================
        GET ONE EVENT
    ================================================== */

    public function getEvent($id)
    {
        return $this->eventModel->getEventById($id);
    }

    /* ==================================================
        GET UPCOMING EVENTS
    ================================================== */

    public function getUpcomingEvents($categoryId = null)
    {
        return $this->eventModel->getUpcomingEvents($categoryId);
    }

    /* ==================================================
        GET ONGOING EVENTS
    ================================================== */

    public function getOngoingEvents($categoryId = null)
    {
        return $this->eventModel->getOngoingEvents($categoryId);
    }

    /* ==================================================
        GET PRESENT EVENTS
    ================================================== */

    public function getPresentEvents($categoryId = null)
    {
        return $this->eventModel->getPresentEvents($categoryId);
    }

    /* ==================================================
        GET COMPLETED EVENTS
    ================================================== */

    public function getCompletedEvents($categoryId = null)
    {
        return $this->eventModel->getCompletedEvents($categoryId);
    }

    /* ==================================================
        REGISTER FOR EVENT (NEW)
    ================================================== */

    public function registerForEvent()
    {
        AuthController::checkLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=events');
            exit;
        }

        $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
        if (!$eventId) {
            $_SESSION['error'] = 'Invalid event.';
            header('Location: index.php?page=events');
            exit;
        }

        $notes = trim($_POST['notes'] ?? '');
        $result = $this->registrationModel->register($eventId, $_SESSION['user_id'], $notes);

        $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        header('Location: index.php?page=event-details&id=' . $eventId);
        exit;
    }

    /* ==================================================
        CANCEL EVENT REGISTRATION (NEW)
    ================================================== */

    public function cancelRegistration()
    {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Please login']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }

        $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;

        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Invalid event']);
            exit;
        }

        // Get registration
        $userRegistrations = $this->registrationModel->getUserRegistrations($_SESSION['user_id']);

        $registration = null;
        foreach ($userRegistrations as $reg) {
            if ($reg['event_id'] == $eventId) {
                $registration = $reg;
                break;
            }
        }

        if (!$registration) {
            echo json_encode(['success' => false, 'message' => 'Registration not found']);
            exit;
        }

        $result = $this->registrationModel->cancelRegistration($registration['id']);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Registration cancelled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to cancel registration']);
        }

        exit;
    }

    /* ==================================================
        SAVE EVENT
    ================================================== */

    private function validateEventInput(): void
    {
        $error='';
        $title=trim($_POST['title']??'');
        $date=$_POST['event_date']??'';
        $time=$_POST['event_time']??'';
        $endDate=$_POST['end_date']??'';
        $endTime=$_POST['end_time']??'';
        $validDate=function($value){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $d && $d->format('Y-m-d')===$value;};
        if($title==='' || strlen($title)>255) $error='Use an event title of 1–255 characters.';
        elseif(!$validDate($date) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D',$time)) $error='Enter a valid start date and time.';
        elseif($endDate!=='' && !$validDate($endDate)) $error='Enter a valid end date.';
        elseif($endTime!=='' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D',$endTime)) $error='Enter a valid end time.';
        elseif(strtotime(($endDate?:$date).' '.($endTime?:'23:59:59'))<strtotime($date.' '.$time)) $error='The event must end after it starts.';
        elseif(!ctype_digit((string)($_POST['max_registrations']??'0')) || (int)($_POST['max_registrations']??0)>2147483647) $error='Capacity must be a non-negative whole number.';
        elseif(!in_array($_POST['publish']??'Yes',['Yes','No'],true)) $error='Choose a valid publication status.';
        elseif(($authorizerError=cc_authorizer_error($_POST['authorized_by']??''))!==null) $error=$authorizerError;
        foreach(['google_map','registration_link'] as $field) {
            $url=trim($_POST[$field]??'');
            if($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['http','https'],true))) $error='Use a valid HTTP or HTTPS link.';
        }
        if($error==='' && !Database::hasColumn('events','authorized_by')) {
            $error='Database upgrade pending: run "php tools/migrate.php" once to add the "Authorized by" column, then save again.';
        }
        if($error==='') {
            $id=(int)($_POST['id']??0);$limit=(int)($_POST['max_registrations']??0);
            if($id>0) {
                $db=Database::connect();$q=$db->prepare('SELECT id FROM events WHERE id=? FOR UPDATE');$q->execute([$id]);
                $q=$db->prepare("SELECT COUNT(*) FROM registrations WHERE event_id=? AND status IN ('Pending','Approved','Attended','Completed')");$q->execute([$id]);
                if($limit>0 && $limit<(int)$q->fetchColumn()) $error='Capacity cannot be lower than existing registrations.';
            }
        }
        if($error!=='') {
            $_SESSION['error']=$error;
            $target=empty($_POST['id'])?'add-event':'edit-event&id='.(int)$_POST['id'];
            header('Location:index.php?page='.$target);exit;
        }
    }
    public function saveEvent()
    {
        AuthController::checkAdmin();
        $this->validateEventInput();

        // Validate input
        if (empty($_POST['title']) || empty($_POST['event_date']) || empty($_POST['event_time'])) {
            $_SESSION['error'] = 'Please fill all required fields';
            header("Location: index.php?page=add-event");
            exit;
        }

        $data = [
            // Stored raw — every view escapes with htmlspecialchars() at
            // output time, so escaping here too would double-encode
            // characters like & and " (e.g. "AI & Robotics" would render
            // literally as "AI &amp; Robotics" on the page).
            'title' => trim($_POST['title']),
            'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            'event_date' => $_POST['event_date'],
            'event_time' => $_POST['event_time'],
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'end_time' => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
            'gallery_status' => 'Pending',
            'venue' => trim($_POST['venue'] ?? ''),
            'google_map' => $_POST['google_map'] ?? '',
            'description' => trim($_POST['description'] ?? ''),
            'rules' => trim($_POST['rules'] ?? ''),
            'prizes' => trim($_POST['prizes'] ?? ''),
            'schedule' => trim($_POST['schedule'] ?? ''),
            'registration_link' => $_POST['registration_link'] ?? '',
            'max_registrations' => (int)($_POST['max_registrations'] ?? 0),
            'publish' => $_POST['publish'] ?? 'Yes',
            'authorized_by' => cc_normalize_authorizer($_POST['authorized_by'] ?? ''),
            'banner' => '',
            'faculty_name' => trim($_POST['faculty_name'] ?? ''),
            'faculty_photo' => '',
            'student_name' => trim($_POST['student_name'] ?? ''),
            'student_photo' => ''
        ];

        // Banner upload with validation
        if (!empty($_FILES['banner']['name'])) {
            $upload = FileUpload::uploadEventBanner($_FILES['banner'], 0);

            if ($upload['success']) {
                $data['banner'] = $upload['filename'];
            } else {
                $_SESSION['error'] = 'Banner upload failed: ' . $upload['error'];
                header("Location: index.php?page=add-event");
                exit;
            }
        }

        // Faculty Photo upload with validation
        if (!empty($_FILES['faculty_photo']['name'])) {
            $upload = FileUpload::uploadEventBanner($_FILES['faculty_photo'], 0);

            if ($upload['success']) {
                $data['faculty_photo'] = $upload['filename'];
            } else {
                $_SESSION['error'] = 'Faculty photo upload failed: ' . $upload['error'];
                header("Location: index.php?page=add-event");
                exit;
            }
        }

        // Student Photo upload with validation
        if (!empty($_FILES['student_photo']['name'])) {
            $upload = FileUpload::uploadEventBanner($_FILES['student_photo'], 0);

            if ($upload['success']) {
                $data['student_photo'] = $upload['filename'];
            } else {
                $_SESSION['error'] = 'Student photo upload failed: ' . $upload['error'];
                header("Location: index.php?page=add-event");
                exit;
            }
        }

        $newId = $this->eventModel->addEvent($data)
            ? $this->eventModel->lastInsertId()
            : false;

        if ($newId) {

            if (($data['publish'] ?? 'Yes') === 'Yes') {
                (new Notification())->broadcast(
                    'event',
                    'New event: ' . $data['title'],
                    'A new campus event has been published. Tap to view the details.',
                    'index.php?page=event-details&id=' . (int)$newId
                );
            }

            // Auto-create the event's gallery and drop in any photos/videos
            // uploaded on the same form, so "Add Event" and "Add Gallery"
            // don't have to be two disconnected steps.
            $this->attachEventGallery($newId, $data['title'], $data['description'], $data['banner']);

            cc_commit_write(); $_SESSION['success'] = 'Event created successfully';
            header("Location: index.php?page=admin-events");
            exit;
        } else {
            $_SESSION['error'] = 'Failed to create event';
            header("Location: index.php?page=add-event");
            exit;
        }
    }

    /* ==================================================
        ATTACH GALLERY IMAGES/VIDEOS SUBMITTED ON THE
        ADD-EVENT FORM TO A NEWLY CREATED EVENT GALLERY
    ================================================== */

    private function attachEventGallery($eventId, $title, $description, $banner, $allowEmptyCreate = false)
    {
        $hasImages = !empty($_FILES['gallery_images']['name'][0]);
        $hasVideos = !empty($_FILES['gallery_videos']['name'][0]);

        if (!$hasImages && !$hasVideos) {
            return;
        }

        $galleryModel = new Gallery();
        $mediaModel = new GalleryMedia();

        $existingGallery = $galleryModel->getGalleryByEvent($eventId);
        if ($existingGallery) {
            $galleryId = (int)$existingGallery['id'];
            // Keep the album title/description aligned with the event.
            $galleryModel->updateGallery([
                'id' => $galleryId,
                'event_id' => $eventId,
                'title' => $title,
                'description' => $description,
                'cover_image' => $existingGallery['cover_image'] ?? '',
                'gallery_type' => 'Event',
                'status' => $existingGallery['status'] ?? 'Draft'
            ]);
        } else {
            $defaultCover = 'public/uploads/gallery/covers/bvraju.jpg';
            $bannerPath = $banner ? 'public/uploads/event_banners/' . basename($banner) : '';
            $coverImage = ($bannerPath && is_file($bannerPath) && filesize($bannerPath) > 0)
                ? $bannerPath
                : $defaultCover;
            $galleryId = $galleryModel->createEventGallery([
                'event_id' => $eventId,
                'title' => $title,
                'description' => $description,
                'cover_image' => $coverImage,
                'status' => 'Draft',
                'authorized_by' => cc_normalize_authorizer($_POST['authorized_by'] ?? ''),
                'created_by' => $_SESSION['user_id'] ?? null
            ]);
        }

        if (!$galleryId) {
            return;
        }

        $needsCover = empty($banner);

        foreach (['gallery_images' => 'image', 'gallery_videos' => 'video'] as $field => $type) {

            if (empty($_FILES[$field]['name'][0])) {
                continue;
            }

            $extensions = $type === 'image'
                ? ['jpg', 'jpeg', 'png', 'gif', 'webp']
                : ['mp4', 'mov', 'avi', 'webm'];

            $targetDir = $type === 'image'
                ? 'public/uploads/gallery/images/'
                : 'public/uploads/gallery/videos/';

            foreach ($_FILES[$field]['name'] as $index => $name) {

                if ($_FILES[$field]['error'][$index] !== 0) {
                    continue;
                }

                $file = [
                    'name' => $_FILES[$field]['name'][$index],
                    'type' => $_FILES[$field]['type'][$index],
                    'tmp_name' => $_FILES[$field]['tmp_name'][$index],
                    'error' => $_FILES[$field]['error'][$index],
                    'size' => $_FILES[$field]['size'][$index]
                ];

                $detectedMime = FileUpload::getMimeType($file['tmp_name']);
                $mimeMatchesType = $type === 'image'
                    ? str_starts_with($detectedMime, 'image/')
                    : str_starts_with($detectedMime, 'video/');

                if (!$mimeMatchesType) {
                    continue;
                }

                $maxBytes = $type === 'image' ? 10 * 1024 * 1024 : 50 * 1024 * 1024;
                $upload = FileUpload::upload($file, $targetDir, $extensions, 'gallery', $maxBytes);

                if (!$upload['success']) {
                    continue;
                }

                $mediaModel->addMedia([
                    'gallery_id' => $galleryId,
                    'media_type' => $type,
                    'title' => '',
                    'description' => '',
                    'file_name' => basename($upload['path']),
                    'file_path' => $upload['path'],
                    'thumbnail_path' => '',
                    'file_size' => $file['size'],
                    'mime_type' => $detectedMime,
                    'display_order' => $index
                ]);

                if ($needsCover && $type === 'image') {
                    $galleryModel->updateCoverImage($galleryId, $upload['path']);
                    $needsCover = false;
                }
            }
        }

        $galleryModel->refreshGalleryCounts($galleryId);
    }

    /* ==================================================
        UPDATE EVENT (IMPROVED)
    ================================================== */

    public function updateEvent()
    {
        AuthController::checkAdmin();
        $this->validateEventInput();

        if (empty($_POST['id'])) {
            $_SESSION['error'] = 'Invalid event';
            header("Location: index.php?page=admin-events");
            exit;
        }

        $id = (int)$_POST['id'];
        $event = $this->eventModel->getEventById($id);

        if (!$event) {
            $_SESSION['error'] = 'Event not found';
            header("Location: index.php?page=admin-events");
            exit;
        }

        $data = [
            'id' => $id,
            'title' => trim($_POST['title']),
            'category_id' => (int)$_POST['category_id'],
            'event_date' => $_POST['event_date'],
            'event_time' => $_POST['event_time'],
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'end_time' => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
            'venue' => trim($_POST['venue'] ?? ''),
            'google_map' => $_POST['google_map'] ?? '',
            'description' => trim($_POST['description'] ?? ''),
            'rules' => trim($_POST['rules'] ?? ''),
            'prizes' => trim($_POST['prizes'] ?? ''),
            'schedule' => trim($_POST['schedule'] ?? ''),
            'registration_link' => $_POST['registration_link'] ?? '',
            'max_registrations' => (int)($_POST['max_registrations'] ?? 0),
            'banner' => $event['banner'],
            'faculty_name' => trim($_POST['faculty_name'] ?? ''),
            'faculty_photo' => $event['faculty_photo'],
            'student_name' => trim($_POST['student_name'] ?? ''),
            'student_photo' => $event['student_photo'],
            'gallery_status' => $event['gallery_status'],
            'publish' => $_POST['publish'] ?? 'Yes',
            'authorized_by' => cc_normalize_authorizer($_POST['authorized_by'] ?? '')
        ];

        // Banner update
        if (!empty($_FILES['banner']['name'])) {
            $upload = FileUpload::uploadEventBanner($_FILES['banner'], $id);

            if ($upload['success']) {
                // Delete old banner
                if ($event['banner']) {
                    /* Retain replaced asset for rollback. */
                }
                $data['banner'] = $upload['filename'];
            } else {
                $_SESSION['error'] = 'Banner upload failed: ' . $upload['error'];
                header("Location: index.php?page=edit-event&id=$id");
                exit;
            }
        }

        // Faculty Photo update
        if (!empty($_FILES['faculty_photo']['name'])) {
            $upload = FileUpload::uploadEventBanner($_FILES['faculty_photo'], $id);

            if ($upload['success']) {
                if ($event['faculty_photo']) {
                    /* Retain replaced asset for rollback. */
                }
                $data['faculty_photo'] = $upload['filename'];
            } else {
                $_SESSION['error'] = 'Faculty photo upload failed: ' . $upload['error'];
                header("Location: index.php?page=edit-event&id=$id");
                exit;
            }
        }

        // Student Photo update
        if (!empty($_FILES['student_photo']['name'])) {
            $upload = FileUpload::uploadEventBanner($_FILES['student_photo'], $id);

            if ($upload['success']) {
                if ($event['student_photo']) {
                    /* Retain replaced asset for rollback. */
                }
                $data['student_photo'] = $upload['filename'];
            } else {
                $_SESSION['error'] = 'Student photo upload failed: ' . $upload['error'];
                header("Location: index.php?page=edit-event&id=$id");
                exit;
            }
        }

        if ($this->eventModel->updateEvent($id, $data)) {
            // Stage any newly selected event media into the event's real gallery.
            $this->attachEventGallery($id, $data['title'], $data['description'], $data['banner'], true);
            if (($event['publish'] ?? 'No') !== 'Yes' && ($data['publish'] ?? 'No') === 'Yes') {
                (new Notification())->broadcast(
                    'event',
                    'New event: ' . $data['title'],
                    'A new campus event has been published. Tap to view the details.',
                    'index.php?page=event-details&id=' . (int)$id
                );
            }
            cc_commit_write(); $_SESSION['success'] = 'Event updated successfully';
            header("Location: index.php?page=admin-events");
            exit;
        } else {
            $_SESSION['error'] = 'Failed to update event';
            header("Location: index.php?page=edit-event&id=$id");
            exit;
        }
    }

    /* ==================================================
        DELETE EVENT
    ================================================== */

    public function deleteEvent($id)
    {
        AuthController::checkAdmin();

        $id = (int)$id;
        $event = $this->eventModel->getEventById($id);

        if (!$event) {
            $_SESSION['error'] = 'Event not found';
            header("Location: index.php?page=admin-events");
            exit;
        }

        // Event::deleteEvent() now handles dependent registrations, certificates,
        // galleries and media before deleting the parent event.
        if ($this->eventModel->deleteEvent($id)) {
            foreach (['banner', 'faculty_photo', 'student_photo'] as $field) {
                if (!empty($event[$field])) {
                    FileUpload::deleteFile("public/uploads/event_banners/" . basename($event[$field]));
                }
            }
            cc_commit_write(); $_SESSION['success'] = 'Event and its dependent records deleted successfully';
        } else {
            $_SESSION['error'] = 'Unable to delete this event. No records were removed if the database transaction failed.';
        }

        header("Location: index.php?page=admin-events");
        exit;
    }

}
