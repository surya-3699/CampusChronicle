<?php
require_once "app/Models/Announcement.php"; require_once "app/Models/Notification.php"; require_once "app/Controllers/AuthController.php";
class AnnouncementController {
 public function save(){ AuthController::checkAdmin(); if($_SERVER['REQUEST_METHOD']!=='POST'){return;} $title=trim($_POST['title']??'');$description=trim($_POST['description']??''); if($title===''){$_SESSION['error']='Announcement title is required.';header('Location:index.php?page=admin-announcements');exit;} $id=(new Announcement())->create($title,$description,$_SESSION['user_id']); (new Notification())->broadcast('announcement',$title,$description?:'A new academic announcement has been published.','index.php?page=announcements'); $_SESSION['success']='Academic announcement published and sent to all users.'; header('Location:index.php?page=admin-announcements');exit;}
}
