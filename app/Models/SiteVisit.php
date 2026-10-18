<?php

require_once "config/database.php";

class SiteVisit
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
        // Schema is installed by tools/migrate.php, never by a web request.
    }

    /**
     * Counts one unique PHP browser session as one site visitor.
     * The session id is stored only as a one-way SHA-256 hash.
     */
    public function recordSession($sessionId)
    {
        if (empty($sessionId)) {
            return false;
        }

        $visitKey = hash('sha256', $sessionId);

        $sql = "INSERT IGNORE INTO site_visits (visit_key)
                VALUES (:visit_key)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':visit_key', $visitKey, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function getTotalVisitors()
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM site_visits");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['total'] ?? 0);
    }
}
