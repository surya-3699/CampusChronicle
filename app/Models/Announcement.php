<?php
require_once "config/database.php";
class Announcement { private PDO $conn; public function __construct(){
    $this->conn=Database::connect();

}
public function create($title,$description,$createdBy){$s=$this->conn->prepare("INSERT INTO announcements(title,description,created_by) VALUES(?,?,?)");$s->execute([$title,$description,$createdBy]);return (int)$this->conn->lastInsertId();}
public function getAll($limit=20){$s=$this->conn->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT ?");$s->bindValue(1,(int)$limit,PDO::PARAM_INT);$s->execute();return $s->fetchAll(PDO::FETCH_ASSOC);}}
