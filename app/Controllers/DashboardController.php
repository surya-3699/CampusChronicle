<?php

require_once "app/Models/Event.php";
require_once "app/Models/Gallery.php";
require_once "app/Models/GalleryMedia.php";
require_once "app/Models/Notification.php";
require_once "app/Models/User.php";
require_once "app/Models/SiteVisit.php";

class DashboardController
{
    private $event;
    private $gallery;
    private $media;
    private $notification;
    private $user;
    private $siteVisit;

    public function __construct()
    {
        $this->event = new Event();
        $this->gallery = new Gallery();
        $this->media = new GalleryMedia();
        $this->notification = new Notification();
        $this->user = new User();
        $this->siteVisit = new SiteVisit();
    }

    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?page=login");
            exit();
        }

        $dashboard = [];

        /* ==========================
           EVENT STATISTICS
        ========================== */

        $dashboard['totalEvents'] = count($this->event->getAllEvents());
        $dashboard['upcomingEvents'] = count($this->event->getUpcomingEvents());
        $dashboard['ongoingEvents'] = count($this->event->getOngoingEvents());
        $dashboard['completedEvents'] = count($this->event->getCompletedEvents());

        /* ==========================
           GALLERY
        ========================== */

        $galleryStats = $this->gallery->getDashboardStats();

        $dashboard['totalGalleries'] = $galleryStats['total_galleries'] ?? 0;
        $dashboard['totalPhotos'] = $galleryStats['total_photos'] ?? 0;
        $dashboard['totalVideos'] = $galleryStats['total_videos'] ?? 0;

        /* ==========================
           USERS
        ========================== */

        $dashboard['totalUsers'] = $this->user->getUserCount();
        $dashboard['totalVisitors'] = $this->siteVisit->getTotalVisitors();

        /* ==========================
           NOTIFICATIONS
        ========================== */

        $dashboard['unreadNotifications'] =
            $this->notification->getUnreadCount($_SESSION['user_id']);

        /* ==========================
           RECENT DATA
        ========================== */

        $dashboard['recentEvents'] =
            array_slice($this->event->getAllEvents(), 0, 5);

        $dashboard['recentGallery'] =
            $this->gallery->getRecentGallery();

        $dashboard['recentMedia'] =
            $this->media->getRecentMedia();

        $dashboard['recentUsers'] =
            $this->user->getRecentUsers();

        return $dashboard;
    }
}