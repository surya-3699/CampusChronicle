<?php

require_once "config/database.php";

class Contact
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }

    /* ==================================================
        SAVE CONTACT MESSAGE
    ================================================== */

    public function saveContact($name, $email, $phone, $subject, $message)
    {
        // Sanitize input — stored raw (parameterized query already
        // protects against SQL injection); HTML-escaping happens once,
        // at display time, wherever this data is shown.
        $name = trim((string)$name);
        $email = trim((string)$email);
        $phone = trim((string)$phone);
        $subject = trim((string)$subject);
        $message = trim((string)$message);

        // Validate input
        if ($name === '' || $email === '' || $subject === '' || $message === '') {
            return ['success' => false, 'message' => 'Please fill all required fields'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }

        // Length limits match the contacts table, so a long value gets a clear
        // message instead of a database error.
        $len = function ($v) { return function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v); };
        if ($len($name) > 150 || $len($email) > 150 || $len($subject) > 255) {
            return ['success' => false, 'message' => 'Name, email or subject is too long.'];
        }
        if ($len($message) > 5000) {
            return ['success' => false, 'message' => 'Please keep your message under 5000 characters.'];
        }
        if ($phone !== '' && !preg_match('/^[0-9+()\- ]{5,20}$/', $phone)) {
            return ['success' => false, 'message' => 'Enter a valid phone number (5-20 digits, spaces, + ( ) or -).'];
        }

        // Get client IP
        $ip_address = $this->getClientIP();

        try {
            $sql = "
                INSERT INTO contacts 
                (name, email, phone, subject, message, ip_address, status)
                VALUES (?, ?, ?, ?, ?, ?, 'New')
            ";

            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$name, $email, $phone, $subject, $message, $ip_address]);

            if ($result) {
                $contactId = $this->conn->lastInsertId();

                // Create notification for admins
                $this->createAdminNotification($contactId, $name, $subject);

                return [
                    'success' => true,
                    'message' => 'Your message has been sent successfully. We will get back to you soon.',
                    'contact_id' => $contactId
                ];
            }

            return ['success' => false, 'message' => 'Failed to save contact message'];

        } catch (PDOException $e) {
            error_log('Contact save failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to send your message right now. Please try again.'];
        }
    }

    /* ==================================================
        GET ALL CONTACTS
    ================================================== */

    public function getAllContacts($limit = 50, $offset = 0)
    {
        $sql = "
            SELECT * FROM contacts
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([(int)$limit, (int)$offset]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        GET CONTACTS BY STATUS
    ================================================== */

    public function getContactsByStatus($status, $limit = 50, $offset = 0)
    {
        $sql = "
            SELECT * FROM contacts
            WHERE status = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$status, (int)$limit, (int)$offset]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        GET CONTACT BY ID
    ================================================== */

    public function getContactById($id)
    {
        $sql = "SELECT * FROM contacts WHERE id=?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        UPDATE CONTACT STATUS
    ================================================== */

    public function updateStatus($id, $status)
    {
        $allowedStatuses = ['New', 'Read', 'Replied'];

        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        $sql = "
            UPDATE contacts 
            SET status=?, updated_at=NOW()
            WHERE id=?
        ";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$status, $id]);
    }

    /* ==================================================
        DELETE CONTACT
    ================================================== */

    public function deleteContact($id)
    {
        $sql = "DELETE FROM contacts WHERE id=?";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }

    /* ==================================================
        SEARCH CONTACTS
    ================================================== */

    public function searchContacts($query, $limit = 50, $offset = 0)
    {
        $query = '%' . $query . '%';

        $sql = "
            SELECT * FROM contacts
            WHERE name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$query, $query, $query, $query, (int)$limit, (int)$offset]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        GET TOTAL CONTACTS COUNT
    ================================================== */

    public function getTotalCount()
    {
        return $this->conn->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
    }

    /* ==================================================
        GET NEW CONTACTS COUNT
    ================================================== */

    public function getNewContactsCount()
    {
        return $this->conn->query("SELECT COUNT(*) FROM contacts WHERE status='New'")->fetchColumn();
    }

    /* ==================================================
        GET CONTACTS BY EMAIL
    ================================================== */

    public function getContactsByEmail($email)
    {
        $sql = "
            SELECT * FROM contacts
            WHERE email=?
            ORDER BY created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$email]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        EXPORT CONTACTS (CSV)
    ================================================== */

    public function exportToCSV($filename = 'contacts.csv')
    {
        $contacts = $this->getAllContacts(10000);

        $csv = "ID,Name,Email,Phone,Subject,Status,Created At\n";

        foreach ($contacts as $contact) {
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $contact['id'],
                str_replace('"', '""', $contact['name']),
                str_replace('"', '""', $contact['email']),
                str_replace('"', '""', $contact['phone']),
                str_replace('"', '""', $contact['subject']),
                $contact['status'],
                $contact['created_at']
            );
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
        exit;
    }

    /* ==================================================
        HELPER: GET CLIENT IP ADDRESS
    ================================================== */

    private function getClientIP()
    {
        // Only REMOTE_ADDR is trusted. Behind the platform proxy Apache's
        // mod_remoteip already replaced it with the real client address;
        // client-supplied forwarding headers can be forged and are ignored.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'UNKNOWN';
    }

    /* ==================================================
        HELPER: CREATE ADMIN NOTIFICATION
    ================================================== */

    private function createAdminNotification($contactId, $name, $subject)
    {
        try {
            // Get all admins
            $adminStmt = $this->conn->query("SELECT id FROM users WHERE role='admin'");
            $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

            // Create notification for each admin
            $notifSql = "
                INSERT INTO user_notifications (user_id, type, title, message, redirect_url)
                VALUES (?, ?, ?, ?, ?)
            ";

            $notifStmt = $this->conn->prepare($notifSql);

            foreach ($admins as $admin) {
                $notifStmt->execute([
                    $admin['id'],
                    'contact',
                    'New Contact Message',
                    'New message from ' . $name . ': ' . substr($subject, 0, 50),
                    'index.php?page=admin-contacts&id=' . $contactId
                ]);
            }

        } catch (PDOException $e) {
            // Log error but don't fail the contact save
            error_log('Error creating admin notification: ' . $e->getMessage());
        }
    }
}
