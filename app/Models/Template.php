<?php

require_once "config/database.php";

class Template
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
        // Schema is installed by tools/migrate.php, never by a web request.
    }

    public function all(): array
    {
        return $this->conn
            ->query("SELECT * FROM content_templates ORDER BY template_type, created_at DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM content_templates WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $type, string $name, string $description, string $config, string $status, ?string $filePath = null, ?string $mimeType = null): bool
    {
        $stmt = $this->conn->prepare("
            INSERT INTO content_templates
                (template_type, name, description, config_json, file_path, mime_type, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $type,
            $name,
            $description,
            $config,
            $filePath,
            $mimeType,
            $status,
            $_SESSION['user_id'] ?? null
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM content_templates WHERE id=?");
        return $stmt->execute([$id]);
    }
}
