<?php

require_once "config/database.php";

class Notification
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }

    /*
    |--------------------------------------------------------------------------
    | Get All Notifications
    |--------------------------------------------------------------------------
    */

    public function getNotifications($userId)
    {
        $sql = "SELECT *
                FROM user_notifications
                WHERE user_id = :user_id
                AND is_read = 0
                ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Unread Notifications
    |--------------------------------------------------------------------------
    */

    public function getUnreadNotifications($userId)
    {
        $sql = "SELECT *
                FROM user_notifications
                WHERE user_id = :user_id
                AND is_read = 0
                ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Unread Count
    |--------------------------------------------------------------------------
    */

    public function getUnreadCount($userId)
    {
        $sql = "SELECT COUNT(*) AS total
                FROM user_notifications
                WHERE user_id = :user_id
                AND is_read = 0";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);

        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)$result['total'];
    }

    /*
    |--------------------------------------------------------------------------
    | Mark Notification As Read
    |--------------------------------------------------------------------------
    */

    public function markAsRead($notificationId)
    {
        $sql = "UPDATE user_notifications
                SET is_read = 1
                WHERE id = :id
                AND user_id = :user_id";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ":id" => $notificationId,
            ":user_id" => $_SESSION['user_id'] ?? 0
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Notification
    |--------------------------------------------------------------------------
    */

    public function createNotification(
        $userId,
        $type,
        $title,
        $message,
        $redirectUrl
    )
    {
        $sql = "INSERT INTO user_notifications
                (
                    user_id,
                    type,
                    title,
                    message,
                    redirect_url,
                    is_read,
                    created_at
                )
                VALUES
                (
                    :user_id,
                    :type,
                    :title,
                    :message,
                    :redirect_url,
                    0,
                    NOW()
                )";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ":user_id"      => $userId,
            ":type"         => $type,
            ":title"        => $title,
            ":message"      => $message,
            ":redirect_url" => $redirectUrl
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Broadcast Notification
    |--------------------------------------------------------------------------
    */
    public function broadcast($type, $title, $message, $redirectUrl)
    {
        // One set-based statement instead of one INSERT per user: a campus-wide
        // broadcast is a single round trip and a short lock, not thousands of
        // queries held inside one web request.
        $stmt = $this->conn->prepare("INSERT INTO user_notifications
            (user_id, type, title, message, redirect_url, is_read, created_at)
            SELECT id, ?, ?, ?, ?, 0, NOW() FROM users");
        $stmt->execute([$type, $title, $message, $redirectUrl]);
        return $stmt->rowCount();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Notification
    |--------------------------------------------------------------------------
    */

    public function deleteNotification($id)
    {
        $sql = "DELETE FROM user_notifications
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ":id" => $id
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete All Notifications Of User
    |--------------------------------------------------------------------------
    */

    public function clearNotifications($userId)
    {
        $sql = "DELETE FROM user_notifications
                WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ":user_id" => $userId
        ]);
    }
}