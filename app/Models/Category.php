<?php

require_once "config/database.php";

class Category
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }

    /*========================================
        GET ACTIVE CATEGORIES (for public /
        admin dropdowns)
    ========================================*/
    public function getActiveCategories()
    {
        $sql = "SELECT * FROM categories WHERE status = 'Active' ORDER BY category_name ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*========================================
        GET ALL CATEGORIES (admin management,
        includes inactive ones)
    ========================================*/
    public function getAllCategories()
    {
        $sql = "SELECT
                    categories.*,
                    COUNT(events.id) AS event_count
                FROM categories
                LEFT JOIN events ON events.category_id = categories.id
                GROUP BY categories.id
                ORDER BY categories.category_name ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*========================================
        GET SINGLE CATEGORY
    ========================================*/
    public function getCategoryById($id)
    {
        $sql = "SELECT * FROM categories WHERE id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /*========================================
        ADD CATEGORY
    ========================================*/
    public function addCategory($name, $description = '')
    {
        $sql = "INSERT INTO categories (category_name, description, status) VALUES (?, ?, 'Active')";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$name, $description]);
    }

    /*========================================
        UPDATE CATEGORY
    ========================================*/
    public function updateCategory($id, $name, $description, $status)
    {
        $sql = "UPDATE categories SET category_name = ?, description = ?, status = ? WHERE id = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$name, $description, $status, $id]);
    }

    /*========================================
        DELETE CATEGORY
    ========================================*/
    public function deleteCategory($id)
    {
        $sql = "DELETE FROM categories WHERE id = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$id]);
    }
}
