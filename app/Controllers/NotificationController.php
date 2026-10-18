<?php

require_once "app/Models/Notification.php";

class NotificationController
{
    private $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new Notification();
    }

    /* ===============================
       Get all notifications
    ================================ */

    public function getNotifications($userId)
    {
        return $this->notificationModel->getNotifications($userId);
    }

    /* ===============================
       Get unread notifications
    ================================ */

    public function getUnreadNotifications($userId)
    {
        return $this->notificationModel->getUnreadNotifications($userId);
    }

    /* ===============================
       Get unread count
    ================================ */

    public function getUnreadCount($userId)
    {
        return $this->notificationModel->getUnreadCount($userId);
    }


    public function broadcast($type, $title, $message, $redirectUrl)
    {
        return $this->notificationModel->broadcast($type, $title, $message, $redirectUrl);
    }

    /* ===============================
       Mark notification as read
    ================================ */

    public function markAsRead($notificationId)
    {
        return $this->notificationModel->markAsRead($notificationId);
    }

    /* ===============================
       Create notification
    ================================ */

    public function createNotification(
    $userId,
    $type,
    $title,
    $message,
    $redirectUrl
)
{
    return $this->notificationModel->createNotification(
        $userId,
        $type,
        $title,
        $message,
        $redirectUrl
    );
}
public function markNotificationRead()
{
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Please sign in."]);
        exit;
    }

    if (!isset($_POST['id'])) {
        echo json_encode([
            "success" => false
        ]);
        exit;
    }

    $id = (int) $_POST['id'];

    $success = $this->notificationModel->markAsRead($id);

    echo json_encode([
        "success" => $success
    ]);

    exit;
}
}
