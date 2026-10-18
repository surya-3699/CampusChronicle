<?php

require_once "config/database.php";

class User
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }

    /* ==================================================
        REGISTER USER
    ================================================== */

    public function register($fullname, $email, $password)
    {
        // Check if email already exists
        $check = $this->conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->execute([$email]);

        if($check->rowCount() > 0){
            return false;
        }

        // Generate username from email
        $username = explode('@', $email)[0] . '_' . uniqid();

        $sql = "
        INSERT INTO users
        (
            username,
            fullname,
            email,
            password,
            profile_image
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?
        )
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $username,
            $fullname,
            $email,
            $password,
            "default.png"  // Set default profile image
        ]);
    }

    /* ==================================================
        LOGIN
    ================================================== */

    public function login($email)
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE email=? LIMIT 1"
        );

        $stmt->execute([$email]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        GOOGLE IDENTITY LOOKUP
    ================================================== */

    public function getUserByGoogleSub($googleSub)
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE google_sub=? LIMIT 1"
        );
        $stmt->execute([$googleSub]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function linkGoogleAccount($id, $googleSub)
    {
        $stmt = $this->conn->prepare(
            "UPDATE users
             SET google_sub=?,
                 auth_provider=CASE
                     WHEN auth_provider='password' THEN 'both'
                     WHEN auth_provider='linkedin' THEN 'multiple'
                     ELSE auth_provider
                 END,
                 updated_at=NOW()
             WHERE id=? AND (google_sub IS NULL OR google_sub='')"
        );

        return $stmt->execute([$googleSub, $id]);
    }

    public function createGoogleUser($googleSub, $fullname, $email, $profileImage = null)
    {
        if ($this->login($email) || $this->getUserByGoogleSub($googleSub)) {
            return false;
        }

        $username = $this->generateUniqueUsername($email);
        $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $profileImage = trim((string) $profileImage);
        if ($profileImage === '') {
            $profileImage = 'default.png';
        } else {
            $profileImage = substr($profileImage, 0, 255);
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO users
             (username, fullname, email, password, profile_image, google_sub, auth_provider)
             VALUES (?, ?, ?, ?, ?, ?, 'google')"
        );

        return $stmt->execute([
            $username,
            $fullname,
            $email,
            $randomPassword,
            $profileImage,
            $googleSub
        ]);
    }

    /* ==================================================
        LINKEDIN IDENTITY LOOKUP
    ================================================== */

    public function getUserByLinkedInSub($linkedinSub)
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE linkedin_sub=? LIMIT 1"
        );
        $stmt->execute([$linkedinSub]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function linkLinkedInAccount($id, $linkedinSub)
    {
        $stmt = $this->conn->prepare(
            "UPDATE users
             SET linkedin_sub=?,
                 auth_provider=CASE
                     WHEN auth_provider='linkedin' THEN 'linkedin'
                     ELSE 'multiple'
                 END,
                 updated_at=NOW()
             WHERE id=? AND (linkedin_sub IS NULL OR linkedin_sub='')"
        );

        return $stmt->execute([$linkedinSub, $id]);
    }

    public function createLinkedInUser($linkedinSub, $fullname, $email, $profileImage = null)
    {
        if ($this->login($email) || $this->getUserByLinkedInSub($linkedinSub)) {
            return false;
        }

        $username = $this->generateUniqueUsername($email);
        $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $profileImage = trim((string) $profileImage);
        if ($profileImage === '') {
            $profileImage = 'default.png';
        } else {
            $profileImage = substr($profileImage, 0, 255);
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO users
             (username, fullname, email, password, profile_image, linkedin_sub, auth_provider)
             VALUES (?, ?, ?, ?, ?, ?, 'linkedin')"
        );

        return $stmt->execute([
            $username,
            $fullname,
            $email,
            $randomPassword,
            $profileImage,
            $linkedinSub
        ]);
    }

    private function generateUniqueUsername($email)
    {
        $base = strtolower((string) strstr($email, '@', true));
        $base = preg_replace('/[^a-z0-9._-]+/', '_', $base);
        $base = trim((string) $base, '._-');

        if ($base === '') {
            $base = 'student';
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = substr($base, 0, 110) . '_' . bin2hex(random_bytes(4));
            $check = $this->conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
            $check->execute([$candidate]);

            if (!$check->fetchColumn()) {
                return $candidate;
            }
        }

        return substr($base, 0, 95) . '_' . bin2hex(random_bytes(12));
    }

    /* ==================================================
        GET USER BY ID
    ================================================== */

    public function getUserById($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE id=? LIMIT 1"
        );

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        GET USER BY USERNAME
    ================================================== */

    public function getUserByUsername($username)
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE username=? LIMIT 1"
        );

        $stmt->execute([$username]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        UPDATE PROFILE (FIXED)
        Fixed: Changed profile_photo to profile_image
    ================================================== */

    public function updateProfile($id, $fullname, $profile_image = null)
    {
        if ($profile_image === null) {
            // Update only fullname
            $stmt = $this->conn->prepare(
                "UPDATE users SET fullname=?, updated_at=NOW() WHERE id=?"
            );
            return $stmt->execute([$fullname, $id]);
        } else {
            // Update fullname and profile image
            $stmt = $this->conn->prepare(
                "UPDATE users SET fullname=?, profile_image=?, updated_at=NOW() WHERE id=?"
            );
            return $stmt->execute([$fullname, $profile_image, $id]);
        }
    }

    /* ==================================================
        UPDATE PROFILE WITH PHONE AND BIO
    ================================================== */

    public function updateProfileComplete($id, $fullname, $phone = null, $bio = null, $profile_image = null)
    {
        $updates = ["fullname = ?"];
        $params = [$fullname];

        if ($phone !== null) {
            $updates[] = "phone = ?";
            $params[] = $phone;
        }

        if ($bio !== null) {
            $updates[] = "bio = ?";
            $params[] = $bio;
        }

        if ($profile_image !== null) {
            $updates[] = "profile_image = ?";
            $params[] = $profile_image;
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $id;

        $stmt = $this->conn->prepare(
            "UPDATE users SET " . implode(", ", $updates) . " WHERE id=?"
        );

        return $stmt->execute($params);
    }

    public function updateProfileBanner($id, $filename)
    {
        $stmt = $this->conn->prepare("UPDATE users SET profile_banner=?, updated_at=NOW() WHERE id=?");
        return $stmt->execute([$filename, $id]);
    }

    public function updateAcademicProfile($id, $data)
    {
        $sql = "UPDATE users SET fullname=?, institution_name=?, phone=?, bio=?, course=?, department=?, current_year=?, semester=?, student_id=?, admission_year=?, expected_graduation=?, github_url=?, linkedin_url=?, portfolio_url=?, skills=?, academic_interests=?, profile_visibility=?, updated_at=NOW() WHERE id=?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['fullname'], $data['institution_name'], $data['phone'], $data['bio'], $data['course'], $data['department'],
            $data['current_year'], $data['semester'], $data['student_id'], $data['admission_year'],
            $data['expected_graduation'], $data['github_url'], $data['linkedin_url'], $data['portfolio_url'],
            $data['skills'], $data['academic_interests'], $data['profile_visibility'], $id
        ]);
    }

    /* ==================================================
        CHANGE PASSWORD
    ================================================== */

    public function changePassword($id, $password)
    {
        $password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare(
            "UPDATE users SET password=?, updated_at=NOW() WHERE id=?"
        );

        return $stmt->execute([$password, $id]);
    }

    /* ==================================================
        CHECK IF USER HAS PROFILE IMAGE (FIXED)
    ================================================== */

    public function hasProfileImage($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT profile_image FROM users WHERE id=?"
        );

        $stmt->execute([$id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($user['profile_image']) && $user['profile_image'] !== 'default.png';
    }

    /* ==================================================
        DASHBOARD - TOTAL USERS
    ================================================== */

    public function getUserCount()
    {
        return $this->conn
            ->query("SELECT COUNT(*) FROM users")
            ->fetchColumn();
    }

    /* ==================================================
        DASHBOARD - TOTAL STUDENTS
    ================================================== */

    public function getStudentCount()
    {
        return $this->conn
            ->query("SELECT COUNT(*) FROM users WHERE role = 'student'")
            ->fetchColumn();
    }

    /* ==================================================
        DASHBOARD - RECENT USERS
    ================================================== */

    public function getRecentUsers($limit = 5)
    {
        $limit = (int)$limit;

        $stmt = $this->conn->prepare("
            SELECT
                id,
                fullname,
                email,
                profile_image,
                role,
                created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        DASHBOARD - ALL USERS
    ================================================== */

    public function getAllUsers()
    {
        $stmt = $this->conn->query("
            SELECT *
            FROM users
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        GET ALL STUDENTS
    ================================================== */

    public function getPublicProfileFields($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllStudents()
    {
        $stmt = $this->conn->query("
            SELECT *
            FROM users
            WHERE role = 'student'
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================================================
        PROMOTE TO ADMIN
    ================================================== */

    public function promoteToAdmin($id)
    {
        $stmt = $this->conn->prepare(
            "UPDATE users SET role='admin' WHERE id=?"
        );

        return $stmt->execute([$id]);
    }

    /* ==================================================
        DEMOTE TO STUDENT
    ================================================== */

    public function demoteToStudent($id)
    {
        try {
            $this->conn->beginTransaction();
            $admins=$this->conn->query("SELECT id FROM users WHERE role='admin' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array((int)$id,array_map('intval',$admins),true) && count($admins)<=1) {
                $this->conn->rollBack(); return false;
            }
            $q=$this->conn->prepare("UPDATE users SET role='student' WHERE id=?"); $q->execute([$id]); $changed=$q->rowCount()>0;
            $this->conn->commit(); return $changed;
        } catch(Throwable $e) {
            if($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }
    /* ==================================================
        DELETE USER
    ================================================== */

    public function deleteUser($id)
    {
        try {
            $this->conn->beginTransaction();
            // Serialize this rare administrative operation with event registration.
            // Lock parents before deleting cascading child rows, then reconcile counts.
            $this->conn->query("SELECT id FROM events ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            $admins=$this->conn->query("SELECT id FROM users WHERE role='admin' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array((int)$id,array_map('intval',$admins),true) && count($admins)<=1) {
                $this->conn->rollBack(); return false;
            }
            $q=$this->conn->prepare("DELETE FROM users WHERE id=?"); $q->execute([$id]); $changed=$q->rowCount()>0;
            if ($changed) $this->conn->exec("UPDATE events e SET current_registrations=(SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status IN ('Pending','Approved','Attended','Completed'))");
            $this->conn->commit(); return $changed;
        } catch(Throwable $e) {
            if($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }
    /* ==================================================
        CHECK IF EMAIL EXISTS
    ================================================== */

    public function emailExists($email)
    {
        $stmt = $this->conn->prepare(
            "SELECT id FROM users WHERE email=?"
        );

        $stmt->execute([$email]);

        return $stmt->rowCount() > 0;
    }

    /* ==================================================
        SEARCH USERS
    ================================================== */

    public function searchUsers($query, $limit = 10)
    {
        $query = '%' . $query . '%';

        $stmt = $this->conn->prepare("
            SELECT *
            FROM users
            WHERE fullname LIKE ? OR email LIKE ? OR username LIKE ?
            LIMIT ?
        ");

        $stmt->execute([$query, $query, $query, (int)$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
