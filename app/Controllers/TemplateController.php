<?php
require_once 'app/helpers/FileUpload.php';

require_once "app/Models/Template.php";
require_once "app/Controllers/AuthController.php";
require_once "app/helpers/FileUpload.php";

class TemplateController
{
    private const MAX_TEMPLATE_BYTES = 10485760; // 10 MB

    public function save()
    {
        AuthController::checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location:index.php?page=admin-templates');
            exit;
        }

        $type = $_POST['template_type'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $status = in_array($_POST['status'] ?? 'Draft', ['Draft','Published'], true)
            ? $_POST['status']
            : 'Draft';

        $allowedTypes = ['event', 'gallery', 'certificate'];
        $allowedExt = ['jpg','jpeg','png','webp','pdf'];
        $allowedMime = ['image/jpeg','image/png','image/webp','application/pdf'];

        if (!in_array($type, $allowedTypes, true) || $name === '') {
            $_SESSION['error'] = 'Template type and name are required.';
            header('Location:index.php?page=admin-templates');
            exit;
        }

        if (strlen($name) > 180) {
            $_SESSION['error'] = 'Template name must be 180 characters or fewer.';
            header('Location:index.php?page=admin-templates');
            exit;
        }

        $filePath = null;
        $mimeType = null;

        if (!isset($_FILES['template_file']) || ($_FILES['template_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $_SESSION['error'] = 'Choose a template file to upload.';
            header('Location:index.php?page=admin-templates');
            exit;
        }

        if (isset($_FILES['template_file']) && ($_FILES['template_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['template_file'];
            $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExt, true)) {
                $_SESSION['error'] = 'Template assets must be JPG, PNG, WEBP or PDF.';
                header('Location:index.php?page=admin-templates');
                exit;
            }

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $validation = FileUpload::validateFile($file, $allowedExt, self::MAX_TEMPLATE_BYTES);
                $_SESSION['error'] = 'Template upload failed: ' . ($validation['error'] ?? 'Upload error.');
                header('Location:index.php?page=admin-templates');
                exit;
            }

            $mimeType = FileUpload::getMimeType($file['tmp_name']);
            if (!in_array($mimeType, $allowedMime, true)) {
                $_SESSION['error'] = 'The uploaded file content does not match a supported JPG, PNG, WEBP or PDF template.';
                header('Location:index.php?page=admin-templates');
                exit;
            }

            $upload = FileUpload::upload(
                $file,
                'public/uploads/templates/',
                $allowedExt,
                'template',
                self::MAX_TEMPLATE_BYTES
            );

            if (!$upload['success']) {
                $_SESSION['error'] = 'Template upload failed: ' . $upload['error'];
                header('Location:index.php?page=admin-templates');
                exit;
            }
            $filePath = $upload['path'];
        }

        try {
            $created = (new Template())->create($type, $name, $desc, '{}', $status, $filePath, $mimeType);
            if (!$created) {
                throw new RuntimeException('Template row was not created.');
            }
            cc_commit_write(); $_SESSION['success'] = $filePath
                ? 'Template uploaded successfully.'
                : 'Template entry created successfully.';
        } catch (Throwable $e) {
            if ($filePath && is_file($filePath)) {
                FileUpload::deleteFile($filePath);
            }
            error_log('Template save failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Unable to save the template. Please try again.';
        }

        header('Location:index.php?page=admin-templates');
        exit;
    }

    public function delete()
    {
        AuthController::checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = 'Invalid template delete request.';
            header('Location:index.php?page=admin-templates');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Invalid template.';
            header('Location:index.php?page=admin-templates');
            exit;
        }

        $model = new Template();
        $template = $model->getById($id);
        if (!$template) {
            $_SESSION['error'] = 'Template not found.';
            header('Location:index.php?page=admin-templates');
            exit;
        }

        $db=Database::connect();
        $q=$db->prepare('SELECT COUNT(*) FROM event_certificate_settings WHERE participation_template_id=? OR winner_template_id=?');$q->execute([$id,$id]);
        $inUse=(int)$q->fetchColumn()>0;
        $q=$db->prepare("SELECT COUNT(*) FROM certificates WHERE JSON_UNQUOTE(JSON_EXTRACT(presentation_json,'$.background'))=?");$q->execute([$template['file_path']]);
        if ($inUse || (int)$q->fetchColumn()>0) {
            $_SESSION['error']='This template is used by an event or issued certificate. Keep it to preserve certificate downloads.';
            header('Location:index.php?page=admin-templates');exit;
        }
        if ($model->delete($id)) {
            $path = trim((string)($template['file_path'] ?? ''));
            if ($template['template_type'] !== 'certificate' && $path !== '' && is_file($path)) {
                FileUpload::deleteFile($path);
            }
            cc_commit_write(); $_SESSION['success'] = 'Template deleted.';
        } else {
            $_SESSION['error'] = 'Unable to delete the template.';
        }

        header('Location:index.php?page=admin-templates');
        exit;
    }
}
