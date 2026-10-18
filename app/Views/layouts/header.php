<?php
require_once "config/helpers.php";
require_once "app/Controllers/NotificationController.php";
require_once "app/Controllers/AuthController.php";
$notifications = [];
$unreadCount = 0;

/*
 |--------------------------------------------------------------------------
 | Background routing
 | Every user-facing page (public + signed-in student pages) renders the 3D
 | animated scene (public/js/scene3d.js).
 | Admin pages keep the ambient video.
 | The welcome page is standalone and intentionally has no background layer.
 |--------------------------------------------------------------------------
 */
$ccPublicVideoPages = [
    "home",
    "about",
    "events",
    "event-details",
    "gallery",
    "gallery-album",
    "team",
    "contact",
    "announcements"
];

$ccCurrentPage = $_GET["page"] ?? "";
$ccIsPublicVideoPage = in_array($ccCurrentPage, $ccPublicVideoPages, true);
$ccIsAdminPage = ($ccCurrentPage === "admin" || strpos($ccCurrentPage, "admin-") === 0);
$ccVideoClass = ($ccIsPublicVideoPage ? "cc-video-public" : "cc-video-private") . ($ccIsAdminPage ? " cc-admin-page" : "");

if (isLoggedIn()) {

    $notificationController = new NotificationController();

    $userId = $_SESSION['user_id'];

    $notifications = $notificationController->getUnreadNotifications($userId);

    $unreadCount = $notificationController->getUnreadCount($userId);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Campus Chronicle</title>
<?php include "app/Views/layouts/meta.php"; ?>
<script src="public/js/csp-helpers.js"></script>

<link href="<?= htmlspecialchars(cc_vendor_asset('public/vendor/bootstrap/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
<link rel="stylesheet" href="public/css/style.css">
<link rel="stylesheet" href="public/css/liquid-glass.css">
<link rel="stylesheet" href="public/css/video-glass-modern.css">
<link rel="stylesheet" href="public/css/gallery.css">
<link rel="stylesheet" href="public/css/activity.css">
<link rel="stylesheet" href="public/css/admin-gallery.css">
<link rel="stylesheet" href="public/css/events.css">
<link rel="stylesheet" href="public/css/dashboard.css">
<link rel="stylesheet" href="public/css/profile-professional.css">
<link rel="stylesheet" href="public/css/admin-dashboard.css">
<link rel="stylesheet" href="<?= htmlspecialchars(cc_vendor_asset('public/vendor/bootstrap-icons/bootstrap-icons.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<meta name="cc-csrf" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><script src="public/js/security.js"></script><link rel="stylesheet" href="public/css/performance.css">
<?php if ($ccIsAdminPage): ?><script src="public/js/background-media.js" defer></script><?php else: ?><script src="public/js/scene3d.js" defer></script><?php endif; ?>
</head>

<body class="liquid-glass-app cc-video-page <?= htmlspecialchars($ccVideoClass, ENT_QUOTES, "UTF-8"); ?>">

<?php include "app/Views/layouts/background.php"; ?>

<div class="cc-video-overlay" aria-hidden="true"></div>

<header>

<nav>

<div class="logo">

<a href="index.php?page=home" class="logo-link">
Campus Chronicle
</a>

</div>

<button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
    <span></span><span></span><span></span>
</button>

<div class="nav-overlay" id="navOverlay"></div>

<ul id="navMenu">

<li><a href="index.php?page=home">Home</a></li>

<li><a href="index.php?page=about">About</a></li>

<li><a href="index.php?page=events">Events</a></li>

<li><a href="index.php?page=gallery">Gallery</a></li>

<li><a href="index.php?page=team">Team</a></li>

<li><a href="index.php?page=contact">Contact</a></li>

<!-- ===========================
     Activity Center Button
=========================== -->
<li class="activity-wrapper">

    <button
        id="activityBtn"
        class="activity-btn"
        type="button"
        aria-label="Activity and notifications"
        aria-expanded="false"
        aria-controls="activityPanel"
    >
        <span class="activity-icon" aria-hidden="true">
            <i class="bi bi-bell-fill"></i>
        </span>

        <?php if (isLoggedIn() && $unreadCount > 0): ?>
            <span
                class="activity-count"
                id="activityCount"
                aria-label="<?= (int)$unreadCount; ?> unread notifications"
            ><?= (int)$unreadCount; ?></span>
        <?php endif; ?>
    </button>

    <?php require_once "app/Views/layouts/activity_panel.php"; ?>

</li>

<?php if(isLoggedIn()) : ?>

<li class="cc-dropdown" id="profileDropdown">

    <div class="cc-dropdown-toggle">

        <img
            src="<?= htmlspecialchars(cc_asset_url($_SESSION['profile_image'] ?? '', 'public/uploads/profile/default.png', 'public/uploads/profile/'), ENT_QUOTES, 'UTF-8'); ?>"
            class="nav-avatar"
            alt="Profile"
            data-fallback="public/uploads/profile/default.png">

        <span>
            <?= htmlspecialchars($_SESSION['fullname']); ?>
        </span>


    </div>

    <div class="cc-dropdown-menu">

        <div class="cc-dropdown-header">

            <h4><?= htmlspecialchars($_SESSION['fullname']); ?></h4>

            <p><?= htmlspecialchars($_SESSION['email']); ?></p>

        </div>

        <a href="index.php?page=profile" class="cc-dropdown-item">

             My Profile

        </a>

        <a href="index.php?page=my-events" class="cc-dropdown-item">

             My Events

        </a>

        <a href="index.php?page=certificates" class="cc-dropdown-item">

             Certificates

        </a>

        <?php if (AuthController::isAdmin()): ?>

        <div class="cc-divider"></div>

        <a href="index.php?page=admin" class="cc-dropdown-item">

             Admin Dashboard

        </a>

        <?php endif; ?>

        <div class="cc-divider"></div>

       <a href="#"
            id="logoutBtn"
            class="cc-dropdown-item">
             Logout
       </a>

    </div>

</li>

<?php else: ?>

<li>

<a class="login-btn"

href="index.php?page=login">

Login

</a>

</li>

<?php endif; ?>

</ul>

</nav>

</header>

<!-- Logout Modal -->

<div class="logout-modal" id="logoutModal">

    <div class="logout-box">

        <h2>Logout?</h2>

        <p>
            Are you sure you want to logout from your account?
        </p>

        <div class="logout-buttons">

            <button
                class="cancel-btn"
                id="cancelLogout">

                Cancel

            </button>

            <button
                class="logout-btn"

                data-post-action="index.php?page=logout">

                Logout

            </button>

        </div>

    </div>

</div>
<script nonce="<?= cc_nonce(); ?>">

const logoutBtn=document.getElementById("logoutBtn");

const logoutModal=document.getElementById("logoutModal");

const cancelLogout=document.getElementById("cancelLogout");

if (logoutBtn) {

    logoutBtn.addEventListener("click",function(e){

        e.preventDefault();

        logoutModal.style.display="flex";

    });

}

if (cancelLogout) {

    cancelLogout.addEventListener("click",function(){

        logoutModal.style.display="none";

    });

}

window.addEventListener("click",function(e){

    if(e.target===logoutModal){

        logoutModal.style.display="none";

    }

});

// Mobile nav toggle
const navToggle = document.getElementById("navToggle");
const navMenu = document.getElementById("navMenu");
const navOverlay = document.getElementById("navOverlay");

function closeNav(){
    navToggle.setAttribute("aria-expanded", "false");
    navMenu.classList.remove("nav-open");
    navOverlay.classList.remove("show");
    document.body.classList.remove("nav-locked");
}

if (navToggle && navMenu && navOverlay) {

    navToggle.addEventListener("click", function () {
        const isOpen = navMenu.classList.toggle("nav-open");
        navToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        navOverlay.classList.toggle("show", isOpen);
        document.body.classList.toggle("nav-locked", isOpen);
    });

    navOverlay.addEventListener("click", closeNav);

    navMenu.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", closeNav);
    });
}

</script>
<script src="public/js/activity.js"></script>
