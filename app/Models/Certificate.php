<?php

require_once "config/database.php";

class Certificate
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }

    /* ==================================================
        GENERATE CERTIFICATE
    ================================================== */

    public function generateCertificate($userId, $eventId, $registrationId, $rank = null)
    {
        $ownsTransaction = !$this->conn->inTransaction();
        try {
            if ($ownsTransaction) $this->conn->beginTransaction();
            $q=$this->conn->prepare('SELECT id FROM events WHERE id=? FOR UPDATE'); $q->execute([$eventId]);
            $q=$this->conn->prepare('SELECT status,attendance_marked FROM registrations WHERE id=? AND user_id=? AND event_id=? FOR UPDATE');
            $q->execute([$registrationId,$userId,$eventId]); $registration=$q->fetch();
            if (!$registration || !in_array($registration['status'],['Attended','Completed'],true) || !(int)$registration['attendance_marked']) throw new DomainException('Confirmed attendance is required before issuing a certificate.');
            if ($rank !== null && !in_array($rank,[1,2,3],true)) throw new DomainException('Choose first, second or third prize.');
            $type=$rank===null?'Participation':'Winner';
            $q=$this->conn->prepare('SELECT * FROM certificates WHERE user_id=? AND event_id=? AND certificate_type=?'); $q->execute([$userId,$eventId,$type]);
            if ($existing=$q->fetch()) {
                if ($rank !== null && (int)$existing['prize_rank'] !== $rank) throw new DomainException('This student already has a different prize. Review the existing award before changing it.');
                if ($ownsTransaction) $this->conn->commit();
                return ['success'=>true,'existing'=>true,'message'=>'Certificate already issued.','certificate_id'=>$existing['id'],'certificate_code'=>$existing['certificate_code']];
            }
            if ($rank !== null) {
                $q=$this->conn->prepare('SELECT id FROM certificates WHERE event_id=? AND prize_rank=?');$q->execute([$eventId,$rank]);
                if ($q->fetchColumn()) throw new DomainException('This prize has already been awarded to another student.');
                // An award always keeps the student's participation certificate too.
                $participant=$this->generateCertificate($userId,$eventId,$registrationId);
                if (!$participant['success']) throw new RuntimeException('Unable to issue the participation certificate.');
            }
            require_once 'app/Models/CertificateSettings.php';
            $presentation=(new CertificateSettings())->presentation($eventId,$type);
            $q=$this->conn->prepare('SELECT u.fullname,e.title AS event_title,e.event_date,e.venue,e.faculty_name FROM users u JOIN events e ON e.id=? WHERE u.id=?');$q->execute([$eventId,$userId]);
            $presentation['issued_details']=$q->fetch(PDO::FETCH_ASSOC);
            $code='CC-'.strtoupper(bin2hex(random_bytes(16)));
            $q=$this->conn->prepare("INSERT INTO certificates(user_id,event_id,registration_id,certificate_code,status,certificate_type,prize_rank,presentation_json) VALUES(?,?,?,?,'Generated',?,?,?)");
            $q->execute([$userId,$eventId,$registrationId,$code,$type,$rank,json_encode($presentation,JSON_THROW_ON_ERROR)]);$id=$this->conn->lastInsertId();
            $this->createCertificateNotification($userId,$eventId,$id);
            if ($ownsTransaction) $this->conn->commit();
            return ['success'=>true,'existing'=>false,'message'=>$type.' certificate issued successfully.','certificate_id'=>$id,'certificate_code'=>$code];
        } catch (Throwable $e) {
            if (!$ownsTransaction) throw $e; // Let the attendance transaction roll back too.
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('Certificate issuance failed: '.$e->getMessage());
            return ['success'=>false,'message'=>$e instanceof DomainException?$e->getMessage():'Unable to issue the certificate right now.'];
        }
    }
    /* ==================================================
        GET CERTIFICATE BY ID
    ================================================== */

    public function getCertificateById($id)
    {
        $sql = "
            SELECT c.*, u.fullname, u.email,
                   e.title as event_title, e.event_date, e.faculty_name, e.venue
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN events e ON c.event_id = e.id
            WHERE c.id=?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $this->issuedDetails($stmt->fetch(PDO::FETCH_ASSOC));
    }

    /* ==================================================
        GET CERTIFICATE BY CODE
    ================================================== */

    public function getCertificateByCode($code)
    {
        $sql = "
            SELECT c.*, u.fullname, u.email, e.title as event_title
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN events e ON c.event_id = e.id
            WHERE c.certificate_code=?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$code]);

        return $this->issuedDetails($stmt->fetch(PDO::FETCH_ASSOC));
    }

    /* ==================================================
        GET USER CERTIFICATES
    ================================================== */

    public function getUserCertificates($userId)
    {
        $sql = "
            SELECT c.*, e.title as event_title, e.event_date
            FROM certificates c
            JOIN events e ON c.event_id = e.id
            WHERE c.user_id = ?
            ORDER BY c.generated_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        GET EVENT CERTIFICATES
    ================================================== */

    public function getEventCertificates($eventId)
    {
        $sql = "
            SELECT c.*, u.fullname, u.email
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            WHERE c.event_id = ?
            ORDER BY c.generated_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$eventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        UPDATE CERTIFICATE STATUS
    ================================================== */

    public function updateCertificateStatus($certificateId, $status)
    {
        $allowedStatuses = ['Generated', 'Emailed', 'Downloaded'];

        if(!in_array($status, $allowedStatuses)) {
            return false;
        }

        $updateColumn = match($status) {
            'Emailed' => 'emailed_at',
            'Downloaded' => 'downloaded_at',
            default => null
        };

        if($updateColumn) {
            $sql = "
                UPDATE certificates 
                SET status=?, {$updateColumn}=NOW()
                WHERE id=?
            ";
        } else {
            $sql = "
                UPDATE certificates 
                SET status=?
                WHERE id=?
            ";
        }

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$status, $certificateId]);
    }

    /* ==================================================
        MARK CERTIFICATE AS DOWNLOADED
    ================================================== */

    public function markAsDownloaded($certificateId)
    {
        $sql = "
            UPDATE certificates 
            SET status='Downloaded', downloaded_at=NOW()
            WHERE id=?
        ";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$certificateId]);
    }

    /* ==================================================
        MARK CERTIFICATE AS EMAILED
    ================================================== */

    public function markAsEmailed($certificateId)
    {
        $sql = "
            UPDATE certificates 
            SET status='Emailed', emailed_at=NOW()
            WHERE id=?
        ";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$certificateId]);
    }

    /* ==================================================
        DELETE CERTIFICATE
    ================================================== */

    public function deleteCertificate($certificateId)
    {
        $certificate = $this->getCertificateById($certificateId);

        if(!$certificate) {
            return false;
        }

        // Delete certificate file if exists
        if($certificate['certificate_file']) {
            $filePath = "public/uploads/certificates/" . $certificate['certificate_file'];
            if(file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $sql = "DELETE FROM certificates WHERE id=?";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$certificateId]);
    }

    /* ==================================================
        GET CERTIFICATE STATISTICS
    ================================================== */

    public function getCertificateStats($eventId = null)
    {
        if($eventId) {
            $sql = "
                SELECT 
                    COUNT(*) as total_certificates,
                    SUM(CASE WHEN status='Generated' THEN 1 ELSE 0 END) as generated,
                    SUM(CASE WHEN status='Emailed' THEN 1 ELSE 0 END) as emailed,
                    SUM(CASE WHEN status='Downloaded' THEN 1 ELSE 0 END) as downloaded
                FROM certificates
                WHERE event_id=?
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$eventId]);
        } else {
            $sql = "
                SELECT 
                    COUNT(*) as total_certificates,
                    SUM(CASE WHEN status='Generated' THEN 1 ELSE 0 END) as generated,
                    SUM(CASE WHEN status='Emailed' THEN 1 ELSE 0 END) as emailed,
                    SUM(CASE WHEN status='Downloaded' THEN 1 ELSE 0 END) as downloaded
                FROM certificates
            ";

            $stmt = $this->conn->query($sql);
        }

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        VERIFY CERTIFICATE
    ================================================== */

    public function verifyCertificate($code)
    {
        $sql = "
            SELECT c.*, u.fullname, u.email,
                   e.title as event_title, e.event_date, e.faculty_name, e.venue
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN events e ON c.event_id = e.id
            WHERE c.certificate_code=? AND c.status != 'Deleted'
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$code]);

        $result = $this->issuedDetails($stmt->fetch(PDO::FETCH_ASSOC));

        if($result) {
            return [
                'valid' => true,
                'certificate' => $result
            ];
        }

        return [
            'valid' => false,
            'message' => 'Certificate not found or invalid'
        ];
    }

    /* ==================================================
        BULK GENERATE CERTIFICATES
    ================================================== */

    public function bulkGenerateCertificates($eventId)
    {
        $ownsTransaction=!$this->conn->inTransaction();
        try {
            if ($ownsTransaction) $this->conn->beginTransaction();
            $q=$this->conn->prepare('SELECT id FROM events WHERE id=? FOR UPDATE');$q->execute([$eventId]);
            $q=$this->conn->prepare("SELECT r.* FROM registrations r LEFT JOIN certificates c ON r.event_id=c.event_id AND r.user_id=c.user_id AND c.certificate_type='Participation' WHERE r.event_id=? AND r.status IN ('Attended','Completed') AND r.attendance_marked=1 AND c.id IS NULL");$q->execute([$eventId]);
            $count=0;
            foreach ($q->fetchAll() as $r) { $this->generateCertificate($r['user_id'],$eventId,$r['id']); $count++; }
            if ($ownsTransaction) $this->conn->commit();
            return ['success'=>true,'count'=>$count,'message'=>"$count participation certificates issued."];
        } catch (Throwable $e) {
            if (!$ownsTransaction) throw $e;
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('Bulk certificate issuance failed: '.$e->getMessage());
            return ['success'=>false,'message'=>'Unable to issue certificates right now.'];
        }
    }
    /* ==================================================
        GENERATE UNIQUE CERTIFICATE CODE
    ================================================== */

    private function generateCertificateCode($userId, $eventId)
    {
        // Format: CC-EVENTID-USERID-TIMESTAMP-RANDOM
        $timestamp = time();
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return "CC-" . str_pad($eventId, 5, '0', STR_PAD_LEFT) . "-" . 
               str_pad($userId, 5, '0', STR_PAD_LEFT) . "-" . $timestamp . "-" . $random;
    }

    /* ==================================================
        HELPER: CREATE CERTIFICATE NOTIFICATION
    ================================================== */

    private function createCertificateNotification($userId, $eventId, $certificateId)
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
            'certificate',
            'Certificate Generated',
            'Your certificate for ' . $event['title'] . ' is ready to download',
            'index.php?page=certificates'
        ]);
    }

    /* ==================================================
        CREATE CERTIFICATE DIRECTORY
    ================================================== */

    public static function ensureCertificateDirectory()
    {
        $dir = "public/uploads/certificates/";

        if(!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private function issuedDetails($row)
    {
        if (!$row) return $row;
        $presentation=json_decode($row['presentation_json'] ?? '{}',true);
        foreach (['fullname','event_title','event_date','venue','faculty_name'] as $key) {
            if (isset($presentation['issued_details'][$key])) $row[$key]=$presentation['issued_details'][$key];
        }
        return $row;
    }
}
