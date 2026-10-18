<?php
require_once 'app/helpers/FileUpload.php';
require_once 'app/Models/User.php';
class ProfileController
{
    private $user;
    public function __construct() { $this->user = new User(); }
    public function profile()
    {
        if (!isset($_SESSION['user_id'])) { header('Location: index.php?page=login'); exit; }
        return ['user' => $this->user->getUserById($_SESSION['user_id'])];
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) return;
        $id = (int)$_SESSION['user_id'];
        $lock=Database::connect()->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$lock->execute([$id]);
        $current = $this->user->getUserById($id);
        $fullname = trim($_POST['fullname'] ?? '');
        if ($fullname === '' || mb_strlen($fullname) > 150) $this->fail('Enter a name of 1–150 characters.');
        $institutionName = trim($_POST['institution_name'] ?? '');
        if (mb_strlen($institutionName) > 150) $this->fail('Institution name must be 150 characters or fewer.');
        foreach (['github_url','linkedin_url','portfolio_url'] as $field) {
            $url = trim($_POST[$field] ?? '');
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http','https'], true))) $this->fail('Profile links must use http:// or https://.');
        }
        // The request transaction rolls back writes and removes new files on failure.
        foreach (['profile_image'=>2097152, 'profile_banner'=>5242880] as $field=>$limit) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
                $validation = FileUpload::validateFile($_FILES[$field], ['jpg','jpeg','png','webp'], $limit);
                if (!$validation['valid']) $this->fail($validation['error']);
            }
        }
        $images = ['profile_image'=>$current['profile_image'] ?? 'default.png', 'profile_banner'=>$current['profile_banner'] ?? null];
        foreach (['profile_image'=>2097152, 'profile_banner'=>5242880] as $field=>$limit) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $result = FileUpload::upload($_FILES[$field], 'public/uploads/profile/', ['jpg','jpeg','png','webp'], $field.'_'.$id, $limit);
                if (!$result['success']) $this->fail('Unable to save your image. Please try again.');
                $images[$field] = $result['filename'];
            }
        }
        if (isset($_POST['course'], $_POST['department'])) {
            $fields = ['fullname'=>$fullname];
            foreach (['institution_name','phone','bio','course','department','current_year','semester','student_id','admission_year','expected_graduation','github_url','linkedin_url','portfolio_url','skills','academic_interests'] as $field) $fields[$field] = trim($_POST[$field] ?? '');
            $fields['profile_visibility'] = in_array($_POST['profile_visibility'] ?? '', ['Public','Private'], true) ? $_POST['profile_visibility'] : 'Public';
            if (!$this->user->updateAcademicProfile($id, $fields)) $this->fail('Unable to save your profile.');
        }
        if (!$this->user->updateProfileBanner($id, $images['profile_banner']) || !$this->user->updateProfile($id, $fullname, $images['profile_image'])) $this->fail('Unable to save your profile.');
        cc_commit_write();
        $_SESSION['fullname'] = $fullname;
        $_SESSION['profile_image'] = $images['profile_image'];
        $_SESSION['success'] = 'Profile saved, including your photo and banner.';
        header('Location: index.php?page=profile'); exit;
    }
    private function fail($message)
    {
        $_SESSION['error'] = $message;
        header('Location: index.php?page=profile'); exit;
    }
}
