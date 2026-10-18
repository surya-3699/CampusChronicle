<?php
// Middle manager first: health probe, per-IP rate limit and admission control
// run before the session or the database are touched (see docs/SCALING.md).
require_once __DIR__ . '/config/gateway.php';
RequestGateway::handle();

require_once __DIR__ . '/config/security.php';
cc_bootstrap();

require_once "config/database.php";
require_once "config/helpers.php";
/*
|--------------------------------------------------------------------------
| Default Page
|--------------------------------------------------------------------------
*/

$page = $_GET['page'] ?? "welcome";

/*
|--------------------------------------------------------------------------
| Site visitor tracking
|--------------------------------------------------------------------------
| Keep the welcome experience standalone exactly as designed. Visitor
| tracking starts only after the user leaves the welcome screen.
*/
if ($page !== 'welcome' && !RequestGateway::isProbableBot()) {
    require_once "app/Models/SiteVisit.php";

    if (!isset($_SESSION['cc_site_visit_recorded'])) {
        try {
            (new SiteVisit())->recordSession(session_id());
            $_SESSION['cc_site_visit_recorded'] = true;
        } catch (Throwable $e) {
            error_log('Site visitor tracking failed: ' . $e->getMessage());
        }
    }
}

/*
|--------------------------------------------------------------------------
| Router
|--------------------------------------------------------------------------
*/

switch ($page) {

    /*=========================================================
      AUTHENTICATION
    =========================================================*/

    case "welcome":
        include "app/Views/user/welcome.php";
        break;

    case "login":
        include "app/Views/auth/login.php";
        break;

    case "signup":
        include "app/Views/auth/signup.php";
        break;

    case "forgot-password":
        include "app/Views/auth/forgot_password.php";
        break;

    case "google-login":
        require_once "app/Controllers/AuthController.php";
        (new AuthController())->googleLogin();
        break;

    case "linkedin-login":
        require_once "app/Controllers/AuthController.php";
        (new AuthController())->linkedinLogin();
        break;

    case "linkedin-callback":
        require_once "app/Controllers/AuthController.php";
        (new AuthController())->linkedinCallback();
        break;

    case "logout":

        require_once "app/Controllers/AuthController.php";

        $auth = new AuthController();

        $auth->logout();

        break;

    /*=========================================================
      USER PAGES
    =========================================================*/

    case "home":

        require_once "app/Controllers/EventController.php";
        require_once "app/Models/User.php";
        require_once "app/Models/Registration.php";
        require_once "app/Models/Gallery.php";

    $eventController = new EventController();
    $featuredEvents = array_slice($eventController->getUpcomingEvents(), 0, 3);

    if (count($featuredEvents) < 3) {
        $ongoingFill = $eventController->getOngoingEvents();
        $featuredEvents = array_merge($featuredEvents, array_slice($ongoingFill, 0, 3 - count($featuredEvents)));
    }

        $homeStats = [
            'events'        => (new Event())->getEventCount(),
            'students'      => (new User())->getStudentCount(),
            'participation' => (new Registration())->getTotalRegistrationsCount(),
            'galleries'     => (new Gallery())->getPublishedCount(),
        ];

        include "app/Views/layouts/header.php";
        include "app/Views/home.php";
        include "app/Views/layouts/footer.php";

        break;

    case "about":

        require_once "app/Models/Event.php";
        require_once "app/Models/User.php";
        require_once "app/Models/Gallery.php";
        require_once "app/Models/Registration.php";
        require_once "app/Models/Category.php";

        $aboutStats = [
            'events'        => (new Event())->getEventCount(),
            'students'      => (new User())->getStudentCount(),
            'participation' => (new Registration())->getTotalRegistrationsCount(),
            'galleries'     => (new Gallery())->getPublishedCount(),
        ];
        $aboutCategories = (new Category())->getActiveCategories();

        include "app/Views/layouts/header.php";
        include "app/Views/user/about.php";
        include "app/Views/layouts/footer.php";

        break;

    case "events":

        require_once "app/Controllers/EventController.php";
        require_once "app/Models/Category.php";
        $controller = new EventController();
        // Whitelist: this value is echoed into links by the events sidebar.
        $requestedView = $_GET['view'] ?? 'upcoming';
        $currentView = in_array($requestedView, ['upcoming', 'ongoing', 'completed'], true) ? $requestedView : 'upcoming';
        $currentCategory = !empty($_GET['category']) ? (int)$_GET['category'] : null;
        $categories = (new Category())->getActiveCategories();

        if ($currentView == 'ongoing') {
            $events = $controller->getOngoingEvents($currentCategory);
        } elseif ($currentView == 'completed') {
            $events = $controller->getCompletedEvents($currentCategory);
        } else {
            $events = $controller->getUpcomingEvents($currentCategory);
        }

        include "app/Views/layouts/header.php";
        include "app/Views/user/events.php";
        include "app/Views/layouts/footer.php";

        break;

    case "event-details":

        require_once "app/Controllers/EventController.php";
        require_once "app/Controllers/GalleryController.php";
        require_once "app/Controllers/GalleryMediaController.php";
        require_once "app/Models/Registration.php";

        $controller = new EventController();
        $galleryController = new GalleryController();
        $registrationModel = new Registration();

        $eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $event = $controller->getEvent($eventId);

        if (!$event || (($event['publish'] ?? 'No') !== 'Yes' && ($_SESSION['role'] ?? '') !== 'admin')) {
            http_response_code(404);
            include "app/Views/layouts/header.php";
            echo "<div style='padding:120px 20px;text-align:center;'><h2>Event not found</h2><p><a href='index.php?page=events'>Browse all events</a></p></div>";
            include "app/Views/layouts/footer.php";
            break;
        }

        $eventStatus = Event::computeStatus($event);
        $eventGallery = $galleryController->getGalleryByEvent($eventId);
        if ($eventGallery && ($eventGallery['status'] ?? '') !== 'Published' && ($_SESSION['role'] ?? '') !== 'admin') $eventGallery=null;
        $eventMedia = $eventGallery
            ? (new GalleryMediaController())->getMediaByGallery((int)$eventGallery['id'])
            : [];
        $registrationStatus = isLoggedIn()
            ? $registrationModel->getRegistrationStatus($eventId, $_SESSION['user_id'])
            : null;

        include "app/Views/layouts/header.php";
        include "app/Views/user/event-details.php";
        include "app/Views/layouts/footer.php";

        break;

    case "gallery":

        require_once "app/Controllers/GalleryController.php";
        // Scheduled maintenance publishes completed-event galleries.

        include "app/Views/layouts/header.php";
        include "app/Views/user/gallery.php";
        include "app/Views/layouts/footer.php";

        break;

    case "gallery-album":

        require_once "app/Controllers/GalleryController.php";
        // Scheduled maintenance publishes completed-event galleries.

        include "app/Views/layouts/header.php";
        include "app/Views/user/gallery-album.php";
        include "app/Views/layouts/footer.php";

        break;

    // These three used to be separate, duplicate listing pages.
    // They now redirect into the one real events page so there's a
    // single, consistently-styled place to browse events.
    case "present-events":
    case "ongoing-events":

        header("Location: index.php?page=events&view=ongoing");
        exit;

    case "upcoming-events":

        header("Location: index.php?page=events&view=upcoming");
        exit;

    case "team":

        include "app/Views/layouts/header.php";
        include "app/Views/user/team.php";
        include "app/Views/layouts/footer.php";

        break;

    case "contact":

        include "app/Views/layouts/header.php";
        include "app/Views/user/contact.php";
        include "app/Views/layouts/footer.php";

        break;

    case "dashboard":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkLogin();

        require_once "app/Models/Registration.php";
        require_once "app/Models/Certificate.php";
        require_once "app/Controllers/NotificationController.php";

        $userId = $_SESSION['user_id'];

        $myRegistrations = (new Registration())->getUserRegistrations($userId);
        $myCertificates = (new Certificate())->getUserCertificates($userId);
        $myNotifications = array_slice((new NotificationController())->getNotifications($userId), 0, 5);

        $myUpcoming = array_filter($myRegistrations, function ($r) {
            return in_array($r['status'], ['Pending', 'Approved']) && strtotime($r['event_date']) >= strtotime('today');
        });

        include "app/Views/layouts/header.php";
        include "app/Views/user/dashboard.php";
        include "app/Views/layouts/footer.php";

        break;

    case "profile":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkLogin();

        include "app/Views/layouts/header.php";
        include "app/Views/profile/profile.php";
        include "app/Views/layouts/footer.php";

        break;

    case "my-events":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkLogin();

        include "app/Views/layouts/header.php";
        include "app/Views/profile/my-events.php";
        include "app/Views/layouts/footer.php";

        break;

    case "certificates":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkLogin();

        include "app/Views/layouts/header.php";
        include "app/Views/profile/certificates.php";
        include "app/Views/layouts/footer.php";

        break;

    case "notifications":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkLogin();

        include "app/Views/layouts/header.php";
        include "app/Views/user/notifications.php";
        include "app/Views/layouts/footer.php";

        break;

    case "announcements":
        require_once "app/Models/Announcement.php";
        $announcements=(new Announcement())->getAll(50);
        include "app/Views/layouts/header.php"; include "app/Views/user/announcements.php"; include "app/Views/layouts/footer.php"; break;

    /*=========================================================
      ADMIN DASHBOARD
    =========================================================*/

    case "admin":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkAdmin();

        include "app/Views/layouts/header.php";
        include "app/Views/admin/dashboard.php";
        include "app/Views/layouts/footer.php";

        break;

    /*=========================================================
      EVENT MANAGEMENT
    =========================================================*/

    case "admin-events":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkAdmin();

        include "app/Views/layouts/header.php";
        include "app/Views/admin/events.php";
        include "app/Views/layouts/footer.php";

        break;

    case "add-event":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkAdmin();

        include "app/Views/layouts/header.php";
        include "app/Views/admin/add-event.php";
        include "app/Views/layouts/footer.php";

        break;

    case "save-event":

        require_once "app/Controllers/EventController.php";

        $controller = new EventController();

        $controller->saveEvent();

        break;
        case "edit-event":

    require_once "app/Controllers/AuthController.php";
    AuthController::checkAdmin();

    include "app/Views/layouts/header.php";
    include "app/Views/admin/edit-event.php";
    include "app/Views/layouts/footer.php";

    break;

case "update-event":

    require_once "app/Controllers/EventController.php";

    $controller = new EventController();

    $controller->updateEvent();

    break;
    case "delete-event":

    require_once "app/Controllers/EventController.php";

    $controller = new EventController();

    $controller->deleteEvent($_GET['id']);

    header("Location: index.php?page=admin-events");

    exit;

    // EVENT REGISTRATION ROUTES
case "event-register":
    require_once "app/Controllers/AuthController.php";
    AuthController::checkLogin();
    require_once "app/Controllers/EventController.php";
    $registerController = new EventController();
    $eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $event = $registerController->getEvent($eventId);

    if (!$event || (($event['publish'] ?? 'No') !== 'Yes' && ($_SESSION['role'] ?? '') !== 'admin')) {
            http_response_code(404);
        header('Location: index.php?page=events');
        exit;
    }

    $eventStatus = Event::computeStatus($event);
    if (strtolower($eventStatus) === 'completed') {
        $_SESSION['error'] = 'This event has already ended.';
        header('Location: index.php?page=event-details&id=' . $eventId);
        exit;
    }

    include "app/Views/layouts/header.php";
    include "app/Views/user/event-register.php";
    include "app/Views/layouts/footer.php";
    break;

case "register-event":
    require_once "app/Controllers/EventController.php";
    $controller = new EventController();
    $controller->registerForEvent();
    break;

case "cancel-registration":
    require_once "app/Controllers/EventController.php";
    $controller = new EventController();
    $controller->cancelRegistration();
    break;

// ADMIN REGISTRATION MANAGEMENT
case "admin-registrations":
    require_once "app/Controllers/AuthController.php";
    AuthController::checkAdmin();
    include "app/Views/layouts/header.php";
    include "app/Views/admin/registrations.php";
    include "app/Views/layouts/footer.php";
    break;

case "approve-registration":
    require_once "app/Controllers/RegistrationController.php";
    (new RegistrationController())->approve();
    break;

case "reject-registration":
    require_once "app/Controllers/RegistrationController.php";
    (new RegistrationController())->reject();
    break;

case "mark-attendance":
    require_once "app/Controllers/RegistrationController.php";
    (new RegistrationController())->markAttendance();
    break;

case "save-certificate-settings":
case "award-certificate":
case "backfill-certificates":
case "preview-certificate":
    require_once 'app/Controllers/CertificateController.php';
    $controller = new CertificateController();
    if ($page === 'save-certificate-settings') $controller->settings();
    elseif ($page === 'award-certificate') $controller->award();
    elseif ($page === 'backfill-certificates') $controller->backfill();
    else $controller->preview();
    break;

case "generate-certificate":
    require_once "app/Controllers/CertificateController.php";
    (new CertificateController())->generate();
    break;

// CERTIFICATE ROUTES
case "view-certificates":
    require_once "app/Controllers/AuthController.php";
    AuthController::checkLogin();
    include "app/Views/layouts/header.php";
    include "app/Views/profile/certificates.php";
    include "app/Views/layouts/footer.php";
    break;

case "download-certificate":
    require_once "app/Controllers/CertificateController.php";
    $controller = new CertificateController();
    $controller->download();
    break;

case "verify-certificate":
    require_once "app/Controllers/CertificateController.php";
    $controller = new CertificateController();
    $controller->verify();
    break;

// CONTACT ROUTES
case "submit-contact":
    require_once "app/Models/Contact.php";
    if (($_POST['website'] ?? '') !== '') {
        // Honeypot filled in: pretend success so the bot learns nothing.
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully. We will get back to you soon.']);
        exit;
    }
    $contact = new Contact();
    $result = $contact->saveContact(
        $_POST['name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['phone'] ?? '',
        $_POST['subject'] ?? '',
        $_POST['message'] ?? ''
    );
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);

    // Email the team AFTER the visitor has their answer, so a slow mail service can never delay or break the form.
    if (!empty($result['success'])) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        try {
            require_once "app/Services/Mailer.php";
            Mailer::notifyContact(
                trim((string)($_POST['name'] ?? '')), trim((string)($_POST['email'] ?? '')), trim((string)($_POST['phone'] ?? '')),
                trim((string)($_POST['subject'] ?? '')), trim((string)($_POST['message'] ?? ''))
            );
        } catch (Throwable $e) {
            error_log('Contact notification failed: ' . $e->getMessage());
        }
    }
    exit;
    break;

case "admin-contacts":
    require_once "app/Controllers/AuthController.php";
    AuthController::checkAdmin();
    require_once "app/Models/Contact.php";

    $contactModel = new Contact();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id']) && isset($_GET['mark_read'])) {
        $contactModel->updateStatus((int)$_GET['id'], 'Read');
    }

    $contactMessages = $contactModel->getAllContacts(100, 0);
    $openContact = isset($_GET['id']) ? $contactModel->getContactById((int)$_GET['id']) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $openContact && $openContact['status'] === 'New') {
        $contactModel->updateStatus($openContact['id'], 'Read');
        $openContact['status'] = 'Read';
    }

    include "app/Views/layouts/header.php";
    include "app/Views/admin/contacts.php";
    include "app/Views/layouts/footer.php";
    break;

case "admin-users":
    require_once "app/Controllers/AuthController.php";
    AuthController::checkAdmin();
    require_once "app/Models/User.php";

    $userModel = new User();
    $allUsers = $userModel->getAllUsers();

    include "app/Views/layouts/header.php";
    include "app/Views/admin/users.php";
    include "app/Views/layouts/footer.php";
    break;

case "promote-user":
    require_once "app/Controllers/AuthController.php";
    AuthController::checkAdmin();
    require_once "app/Models/User.php";
    (new User())->promoteToAdmin((int)($_GET['id'] ?? 0));
    $_SESSION['success'] = "User promoted to admin.";
    header("Location: index.php?page=admin-users");
    exit;

case "demote-user":
    require_once "app/Controllers/AuthController.php";
    AuthController::checkAdmin();
    require_once "app/Models/User.php";
    if ((int)($_GET['id'] ?? 0) === (int)$_SESSION['user_id']) {
        $_SESSION['error'] = "You can't demote your own account.";
    } else {
        if ((new User())->demoteToStudent((int)($_GET['id'] ?? 0))) $_SESSION['success'] = "User moved back to student.";
        else $_SESSION['error'] = "User could not be changed. Keep at least one administrator.";
    }
    header("Location: index.php?page=admin-users");
    exit;

case "delete-user":
    require_once "app/Controllers/AuthController.php";
    AuthController::checkAdmin();
    require_once "app/Models/User.php";
    if ((int)($_GET['id'] ?? 0) === (int)$_SESSION['user_id']) {
        $_SESSION['error'] = "You can't delete your own account.";
    } else {
        if ((new User())->deleteUser((int)($_GET['id'] ?? 0))) $_SESSION['success'] = "User deleted.";
        else $_SESSION['error'] = "User could not be deleted. Keep at least one administrator.";
    }
    header("Location: index.php?page=admin-users");
    exit;

            /*=========================================================
      GALLERY MANAGEMENT
    =========================================================*/

    case "admin-gallery":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkAdmin();

        include "app/Views/layouts/header.php";
        include "app/Views/admin/gallery.php";
        include "app/Views/layouts/footer.php";

        break;

    case "add-gallery":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkAdmin();

        include "app/Views/layouts/header.php";
        include "app/Views/admin/add-gallery.php";
        include "app/Views/layouts/footer.php";

        break;

    case "edit-gallery":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkAdmin();

        include "app/Views/layouts/header.php";
        include "app/Views/admin/edit-gallery.php";
        include "app/Views/layouts/footer.php";

        break;

    case "manage-media":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkAdmin();

        include "app/Views/layouts/header.php";
        include "app/Views/admin/manage-media.php";
        include "app/Views/layouts/footer.php";

        break;

    case "edit-media":

        require_once "app/Controllers/AuthController.php";
        AuthController::checkAdmin();

        include "app/Views/layouts/header.php";
        include "app/Views/admin/edit-media.php";
        include "app/Views/layouts/footer.php";

        break;

    /*=========================================================
      GALLERY CONTROLLER ACTIONS
    =========================================================*/

    case "save-gallery":

        require_once "app/Controllers/GalleryController.php";

        $controller = new GalleryController();

        $controller->saveGallery();

        break;

    case "update-gallery":

        require_once "app/Controllers/GalleryController.php";

        $controller = new GalleryController();

        $controller->updateGallery();

        break;

    case "delete-gallery":

        require_once "app/Controllers/GalleryController.php";

        $controller = new GalleryController();

        $controller->deleteGallery();

        break;

    /*=========================================================
      MEDIA CONTROLLER ACTIONS
    =========================================================*/

    case "upload-media":

        require_once "app/Controllers/GalleryMediaController.php";

        $controller = new GalleryMediaController();

        $controller->saveMedia();

        break;

    case "save-media":

        require_once "app/Controllers/GalleryMediaController.php";

        $controller = new GalleryMediaController();

        $controller->saveMedia();

        break;

    case "update-media":

        require_once "app/Controllers/GalleryMediaController.php";

        $controller = new GalleryMediaController();

        $controller->updateMedia();

        break;

    case "delete-media":

        require_once "app/Controllers/GalleryMediaController.php";

        $controller = new GalleryMediaController();

        $controller->deleteMedia();

        break;
            case "admin-announcements":
        require_once "app/Controllers/AuthController.php"; AuthController::checkAdmin();
        include "app/Views/layouts/header.php"; include "app/Views/admin/announcements.php"; include "app/Views/layouts/footer.php"; break;

    case "sitemap":
        // For Google Search Console: submit  https://YOUR-DOMAIN/index.php?page=sitemap
        header('Content-Type: application/xml; charset=utf-8');
        $sitemapBase = rtrim(cc_env('APP_URL'), '/');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        if ($sitemapBase !== '') {
            foreach (['welcome', 'home', 'about', 'events', 'gallery', 'team', 'contact'] as $sitemapPage) {
                echo '  <url><loc>' . htmlspecialchars($sitemapBase . '/index.php?page=' . $sitemapPage, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc></url>\n";
            }
        }
        echo '</urlset>';
        exit;
    case "admin-security":
        require_once "app/Controllers/AuthController.php"; AuthController::checkAdmin();
        require_once "app/Controllers/TwoFactorController.php"; $twofa = (new TwoFactorController())->settings();
        include "app/Views/layouts/header.php"; include "app/Views/admin/security.php"; include "app/Views/layouts/footer.php"; break;
    case "two-factor":
        require_once "app/Controllers/TwoFactorController.php"; (new TwoFactorController())->challenge(); break;
    case "two-factor-enable":
        require_once "app/Controllers/TwoFactorController.php"; (new TwoFactorController())->enable(); break;
    case "two-factor-disable":
        require_once "app/Controllers/TwoFactorController.php"; (new TwoFactorController())->disable(); break;
    case "save-announcement":
        require_once "app/Controllers/AnnouncementController.php"; (new AnnouncementController())->save(); break;

    case "admin-templates":
        require_once "app/Controllers/AuthController.php"; AuthController::checkAdmin();
        include "app/Views/layouts/header.php"; include "app/Views/admin/templates.php"; include "app/Views/layouts/footer.php"; break;

    case "save-template":
        require_once "app/Controllers/TemplateController.php"; (new TemplateController())->save(); break;

    case "delete-template":
        require_once "app/Controllers/TemplateController.php"; (new TemplateController())->delete(); break;

    /*=========================================================
      NOTIFICATIONS
    =========================================================*/

    case "mark-notification-read":

        require_once "app/Controllers/NotificationController.php";

        $notificationController = new NotificationController();

        $notificationController->markNotificationRead();

        break;

    /*=========================================================
      DEFAULT ROUTE
    =========================================================*/

    default:

        include "app/Views/user/welcome.php";

        break;
}

?>
