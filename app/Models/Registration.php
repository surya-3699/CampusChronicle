<?php

require_once "config/database.php";

class Registration
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }

    /* ==================================================
        TOTAL PARTICIPATION COUNT (for stats)
    ================================================== */

    public function getTotalRegistrationsCount()
    {
        return $this->conn
            ->query("SELECT COUNT(*) FROM registrations WHERE status IN ('Approved','Attended')")
            ->fetchColumn();
    }

    /* ==================================================
        CREATE REGISTRATION
    ================================================== */

    public function register($eventId, $userId, $notes = '')
    {
        // During a rush for a popular event many requests queue on the same event
        // row lock. InnoDB may pick one as a deadlock victim (1213) or time one out
        // (1205); both are safe to repeat, so retry briefly before giving up.
        for ($attempt = 1; ; $attempt++) {
            $retryable = false;
            $result = $this->attemptRegister($eventId, $userId, $notes, $retryable);
            if (!$retryable || $attempt >= 3) {
                return $result;
            }
            usleep(random_int(40000, 160000) * $attempt);
        }
    }

    private function attemptRegister($eventId, $userId, $notes, &$retryable)
    {
        try {
            $this->conn->beginTransaction();
            $q=$this->conn->prepare('SELECT * FROM events WHERE id=? FOR UPDATE');
            $q->execute([$eventId]); $event=$q->fetch();
            require_once 'app/Models/Event.php';
            if (!$event || $event['publish']!=='Yes' || Event::computeStatus($event)==='Completed') {
                $this->conn->rollBack(); return ['success'=>false,'message'=>'This event is unavailable for registration.'];
            }
            $q=$this->conn->prepare('SELECT id FROM registrations WHERE event_id=? AND user_id=?');
            $q->execute([$eventId,$userId]);
            if($q->fetchColumn()) { $this->conn->rollBack(); return ['success'=>false,'message'=>'Already registered for this event']; }
            $q=$this->conn->prepare("SELECT COUNT(*) FROM registrations WHERE event_id=? AND status IN ('Pending','Approved','Attended','Completed')");
            $q->execute([$eventId]); $count=(int)$q->fetchColumn();
            if((int)$event['max_registrations']>0 && $count >= (int)$event['max_registrations']) {
                $this->conn->rollBack(); return ['success'=>false,'message'=>'Event is full, no more registrations allowed'];
            }
            $q=$this->conn->prepare("INSERT INTO registrations(event_id,user_id,status,notes) VALUES(?,?,'Pending',?)");
            $q->execute([$eventId,$userId,$notes!==''?$notes:null]);
            $id=$this->conn->lastInsertId();
            $q=$this->conn->prepare('UPDATE events SET current_registrations=? WHERE id=?'); $q->execute([$count+1,$eventId]);
            $this->createRegistrationNotification($userId,$eventId,$id);
            $this->conn->commit();
            return ['success'=>true,'message'=>'Successfully registered for event','registration_id'=>$id];
        } catch(Throwable $e) {
            if($this->conn->inTransaction()) $this->conn->rollBack();
            $driverCode = ($e instanceof PDOException) ? (int)($e->errorInfo[1] ?? 0) : 0;
            $retryable = in_array($driverCode, [1205, 1213], true);
            error_log('Registration failed'.($retryable ? ' (will retry)' : '').': '.$e->getMessage());
            return ['success'=>false,'message'=>'Registration could not be completed. Please try again.'];
        }
    }

    /* ==================================================
        GET USER REGISTRATIONS
    ================================================== */

    public function getUserRegistrations($userId)
    {
        $sql = "
            SELECT r.*, e.title, e.event_date, e.event_time, e.end_date, e.end_time, e.banner, e.venue
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            WHERE r.user_id = ?
            ORDER BY e.event_date DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        GET EVENT REGISTRATIONS
    ================================================== */

    public function getEventRegistrations($eventId, $status = null)
    {
        if($status) {
            $sql = "
                SELECT r.*, u.fullname, u.email, u.profile_image
                FROM registrations r
                JOIN users u ON r.user_id = u.id
                WHERE r.event_id = ? AND r.status = ?
                ORDER BY r.registration_date DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$eventId, $status]);
        } else {
            $sql = "
                SELECT r.*, u.fullname, u.email, u.profile_image
                FROM registrations r
                JOIN users u ON r.user_id = u.id
                WHERE r.event_id = ?
                ORDER BY r.registration_date DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$eventId]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        GET REGISTRATION BY ID
    ================================================== */

    public function getRegistrationById($id)
    {
        $sql = "SELECT * FROM registrations WHERE id=?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        APPROVE REGISTRATION
    ================================================== */

    public function approveRegistration($registrationId)
    {
        return $this->transition($registrationId, 'Approved', array (
  0 => 'Pending',
), null);
    }

    /* ==================================================
        REJECT REGISTRATION
    ================================================== */

    public function rejectRegistration($registrationId, $reason = null)
    {
        return $this->transition($registrationId, 'Rejected', array (
  0 => 'Pending',
  1 => 'Approved',
), $reason);
    }

    /* ==================================================
        MARK ATTENDANCE
    ================================================== */

    public function markAttendance($registrationId)
    {
        return $this->transition($registrationId, 'Attended', array (
  0 => 'Approved',
), null);
    }

    /* ==================================================
        CANCEL REGISTRATION
    ================================================== */

    public function cancelRegistration($registrationId)
    {
        return $this->transition($registrationId, 'Cancelled', array (
  0 => 'Pending',
  1 => 'Approved',
), null);
    }

    /* ==================================================
        GET REGISTRATION STATUS
    ================================================== */

    public function getRegistrationStatus($eventId, $userId)
    {
        $sql = "
            SELECT status FROM registrations 
            WHERE event_id=? AND user_id=?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$eventId, $userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['status'] : null;
    }

    /* ==================================================
        IS USER REGISTERED
    ================================================== */

    public function isUserRegistered($eventId, $userId)
    {
        $sql = "
            SELECT id FROM registrations 
            WHERE event_id=? AND user_id=?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$eventId, $userId]);

        return $stmt->rowCount() > 0;
    }

    /* ==================================================
        GET REGISTRATION STATISTICS
    ================================================== */

    public function getRegistrationStats($eventId)
    {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status='Attended' THEN 1 ELSE 0 END) as attended
            FROM registrations
            WHERE event_id=?
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$eventId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        HELPER: CREATE REGISTRATION NOTIFICATION
    ================================================== */

    private function transition($id, $target, array $allowed, $reason = null)
    {
        try {
            $this->conn->beginTransaction();
            $existing=$this->getRegistrationById($id);
            if(!$existing) { $this->conn->rollBack(); return false; }
            // All registration writers lock the parent first, then the row.
            $q=$this->conn->prepare('SELECT id FROM events WHERE id=? FOR UPDATE'); $q->execute([$existing['event_id']]);
            $q=$this->conn->prepare('SELECT * FROM registrations WHERE id=? FOR UPDATE'); $q->execute([$id]); $row=$q->fetch();
            if(!$row || !in_array($row['status'],$allowed,true)) { $this->conn->rollBack(); return false; }
            $q=$this->conn->prepare('UPDATE registrations SET status=? WHERE id=?'); $q->execute([$target,$id]);
            if(in_array($target,['Approved','Rejected'],true)) {
                $q=$this->conn->prepare('UPDATE registrations SET approval_date=NOW() WHERE id=?'); $q->execute([$id]);
            }
            if($target==='Approved') $this->createApprovalNotification($row['user_id'],$row['event_id']);
            if($target==='Rejected') {
                $q=$this->conn->prepare('UPDATE registrations SET notes=? WHERE id=?'); $q->execute([$reason,$id]);
                $this->createRejectionNotification($row['user_id'],$row['event_id'],$reason);
            }
            if($target==='Attended') {
                $q=$this->conn->prepare('UPDATE registrations SET attendance_marked=1,attendance_date=NOW() WHERE id=?'); $q->execute([$id]);
                require_once 'app/Models/Certificate.php';
                (new Certificate())->generateCertificate($row['user_id'],$row['event_id'],$id);
            }
            $q=$this->conn->prepare("UPDATE events SET current_registrations=(SELECT COUNT(*) FROM registrations WHERE event_id=? AND status IN ('Pending','Approved','Attended','Completed')) WHERE id=?");
            $q->execute([$row['event_id'],$row['event_id']]);
            $this->conn->commit(); return true;
        } catch(Throwable $e) {
            if($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('Registration transition failed: '.$e->getMessage()); return false;
        }
    }
    private function createRegistrationNotification($userId, $eventId, $registrationId)
    {
        $eventStmt = $this->conn->prepare("SELECT title FROM events WHERE id=?");
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        $notifSql = "
            INSERT INTO user_notifications (user_id, type, title, message, redirect_url)
            VALUES (?, ?, ?, ?, ?)
        ";

        $notifStmt = $this->conn->prepare($notifSql);
        $notifStmt->execute([
            $userId,
            'registration',
            'Event Registration',
            'You have successfully registered for ' . $event['title'],
            'index.php?page=dashboard'
        ]);
    }

    /* ==================================================
        HELPER: CREATE APPROVAL NOTIFICATION
    ================================================== */

    private function createApprovalNotification($userId, $eventId)
    {
        $eventStmt = $this->conn->prepare("SELECT title FROM events WHERE id=?");
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        $notifSql = "
            INSERT INTO user_notifications (user_id, type, title, message, redirect_url)
            VALUES (?, ?, ?, ?, ?)
        ";

        $notifStmt = $this->conn->prepare($notifSql);
        $notifStmt->execute([
            $userId,
            'approval',
            'Registration Approved',
            'Your registration for ' . $event['title'] . ' has been approved',
            'index.php?page=dashboard'
        ]);
    }

    /* ==================================================
        HELPER: CREATE REJECTION NOTIFICATION
    ================================================== */

    private function createRejectionNotification($userId, $eventId, $reason)
    {
        $eventStmt = $this->conn->prepare("SELECT title FROM events WHERE id=?");
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        $notifSql = "
            INSERT INTO user_notifications (user_id, type, title, message, redirect_url)
            VALUES (?, ?, ?, ?, ?)
        ";

        $message = 'Your registration for ' . $event['title'] . ' has been rejected';
        if($reason) {
            $message .= ': ' . $reason;
        }

        $notifStmt = $this->conn->prepare($notifSql);
        $notifStmt->execute([
            $userId,
            'rejection',
            'Registration Rejected',
            $message,
            'index.php?page=dashboard'
        ]);
    }
}
