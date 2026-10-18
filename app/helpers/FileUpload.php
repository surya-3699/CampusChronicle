<?php

class FileUpload
{
    private static $maxFileSize = 5 * 1024 * 1024; // 5MB
    private static $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private static $allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
    private static $allowedFileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'ogg', 'pdf'];

    /* ==================================================
        VALIDATE UPLOADED FILE
        $allowedExtensions lets a caller narrow the check
        (e.g. images only) without touching the class-wide
        default list used elsewhere.
    ================================================== */

    public static function validateFile($file, $allowedExtensions = null, $maxFileSize = null)
    {
        if (!isset($file['error'])) {
            return ['valid' => false, 'error' => 'No file provided'];
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => self::getUploadErrorMessage($file['error'])
            ];
        }

        // Check file size. Callers such as gallery video/template uploads can
        // opt into a larger cap without weakening profile-image uploads.
        $sizeLimit = $maxFileSize ?? self::$maxFileSize;
        $fileSize = (int)($file['size'] ?? 0);
        if ($fileSize <= 0) {
            return [
                'valid' => false,
                'error' => 'The uploaded file is empty'
            ];
        }
        if ($fileSize > $sizeLimit) {
            return [
                'valid' => false,
                'error' => 'File size exceeds maximum limit of ' . self::formatBytes($sizeLimit)
            ];
        }

        $allowed = $allowedExtensions ?? self::$allowedFileExtensions;

        // Check file type
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowed, true)) {
            return [
                'valid' => false,
                'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed)
            ];
        }

        $mimeMap = ['jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'gif'=>['image/gif'],'webp'=>['image/webp'],'pdf'=>['application/pdf'],'mp4'=>['video/mp4'],'webm'=>['video/webm'],'mov'=>['video/quicktime'],'ogg'=>['video/ogg','application/ogg']];
        $path=$file['tmp_name'] ?? '';
        if(!is_string($path) || !is_file($path) || filesize($path)!==$fileSize || !in_array(self::getMimeType($path),$mimeMap[$fileExtension]??[],true)) {
            return ['valid'=>false,'error'=>'File content does not match its extension.'];
        }
        if(in_array($fileExtension,['jpg','jpeg','png','gif','webp'],true)) {
            $info=@getimagesize($path);
            if(!$info || $info[0]*$info[1]>40000000) return ['valid'=>false,'error'=>'Invalid or oversized image dimensions.'];
        }
        return ['valid' => true];
    }

    /* ==================================================
        GENERIC UPLOAD
        Saves any validated file into $uploadDir and
        returns a result shaped the same way as every
        other upload*() method below:
        ['success' => bool, 'error' => ?string, 'filename' => ?string, 'path' => ?string]
    ================================================== */

    public static function upload($file, $uploadDir, $allowedExtensions = null, $prefix = 'file', $maxFileSize = null)
    {
        $validation = self::validateFile($file, $allowedExtensions, $maxFileSize);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        $uploadDir = rtrim($uploadDir, '/') . '/';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
            return [
                'success' => false,
                'error' => 'Could not create the upload folder: ' . $uploadDir
            ];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        try {
            $random = bin2hex(random_bytes(6));
        } catch (Throwable $e) {
            $random = substr(uniqid('', true), -12);
        }
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix) . '_' . time() . '_' . $random . '.' . $extension;
        $uploadPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return [
                'success' => false,
                'error' => 'Failed to move uploaded file'
            ];
        }

        if(isset($GLOBALS['cc_new_files'])) $GLOBALS['cc_new_files'][]=$uploadPath;
        return [
            'success' => true,
            'error' => null,
            'filename' => $filename,
            'path' => $uploadPath
        ];
    }

    /* ==================================================
        UPLOAD PROFILE IMAGE
    ================================================== */

    public static function uploadProfileImage($file, $userId)
    {
        $mime = self::getMimeType($file['tmp_name'] ?? '');
        if (!self::isImageType($mime)) {
            return ['success' => false, 'error' => 'Invalid image content'];
        }

        return self::upload(
            $file,
            'public/uploads/profile/',
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'profile_' . (int)$userId,
            5 * 1024 * 1024
        );
    }

    /* ==================================================
        UPLOAD EVENT BANNER
    ================================================== */

    public static function uploadEventBanner($file, $eventId)
    {
        $mime = self::getMimeType($file['tmp_name'] ?? '');
        if (!self::isImageType($mime)) {
            return ['success' => false, 'error' => 'Event banners and coordinator photos must be real image files'];
        }

        return self::upload(
            $file,
            'public/uploads/event_banners/',
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'event_' . (int)$eventId . '_asset',
            10 * 1024 * 1024
        );
    }

    /* ==================================================
        UPLOAD GALLERY MEDIA
    ================================================== */

    public static function uploadGalleryMedia($file, $galleryId, $type = 'image')
    {
        $type = $type === 'video' ? 'video' : 'image';
        $mime = self::getMimeType($file['tmp_name'] ?? '');

        if ($type === 'image') {
            if (!self::isImageType($mime)) {
                return ['success' => false, 'error' => 'Invalid image content'];
            }
            $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $maxBytes = 10 * 1024 * 1024;
            $directory = 'public/uploads/gallery/images/';
        } else {
            if (!self::isVideoType($mime)) {
                return ['success' => false, 'error' => 'Invalid video content'];
            }
            $extensions = ['mp4', 'webm', 'mov', 'ogg'];
            $maxBytes = 50 * 1024 * 1024;
            $directory = 'public/uploads/gallery/videos/';
        }

        $result = self::upload(
            $file,
            $directory,
            $extensions,
            'gallery_' . (int)$galleryId,
            $maxBytes
        );
        if ($result['success']) {
            $result['type'] = $type;
            $result['mime_type'] = $mime;
        }
        return $result;
    }

    /* ==================================================
        DELETE FILE
    ================================================== */

    public static function deleteFile($filePath)
    {
        // Shutdown handlers can run after PHP restores the process directory.
        // Resolve relative application paths against the project, never cwd.
        $root=dirname(__DIR__,2);
        $absolute=preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~',$filePath) ? $filePath : $root.'/'.$filePath;
        $base=realpath($root.'/public/uploads'); $resolved=realpath($absolute);
        if(!$base || !$resolved || !str_starts_with(str_replace('\\','/',$resolved),str_replace('\\','/',$base).'/')) {
            return ['success'=>false,'error'=>'File is outside upload storage.'];
        }
        if (is_file($resolved)) {
            try {
                unlink($resolved);
                return ['success' => true];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => 'Failed to delete file: ' . $e->getMessage()
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'File does not exist'
        ];
    }

    /* ==================================================
        GET FILE EXTENSION
    ================================================== */

    public static function getFileExtension($filename)
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /* ==================================================
        GET MIME TYPE
    ================================================== */

    public static function getMimeType($filePath)
    {
        if (!$filePath || !is_file($filePath)) {
            return 'application/octet-stream';
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if (is_string($mimeType) && $mimeType !== '') {
                    return $mimeType;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mimeType = @mime_content_type($filePath);
            if (is_string($mimeType) && $mimeType !== '') {
                return $mimeType;
            }
        }

        return 'application/octet-stream';
    }

    /* ==================================================
        IS IMAGE TYPE
    ================================================== */

    public static function isImageType($mimeType)
    {
        return in_array($mimeType, self::$allowedImageTypes);
    }

    /* ==================================================
        IS VIDEO TYPE
    ================================================== */

    public static function isVideoType($mimeType)
    {
        return in_array($mimeType, self::$allowedVideoTypes);
    }

    /* ==================================================
        GET ALLOWED EXTENSIONS
    ================================================== */

    public static function getAllowedExtensions()
    {
        return self::$allowedFileExtensions;
    }

    /* ==================================================
        GET MAX FILE SIZE
    ================================================== */

    public static function getMaxFileSize()
    {
        return self::$maxFileSize;
    }

    /* ==================================================
        FORMAT BYTES (for human-readable error messages)
    ================================================== */

    public static function formatBytes($bytes)
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . 'MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . 'KB';
        }

        return $bytes . ' bytes';
    }

    /* ==================================================
        GET UPLOAD ERROR MESSAGE
    ================================================== */

    private static function getUploadErrorMessage($errorCode)
    {
        $errors = [
            UPLOAD_ERR_OK           => 'No error',
            UPLOAD_ERR_INI_SIZE     => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE    => 'File exceeds MAX_FILE_SIZE in HTML form',
            UPLOAD_ERR_PARTIAL      => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE      => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR   => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE   => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION    => 'File upload stopped by extension'
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }

    /* ==================================================
        GENERATE THUMBNAIL (IMAGE)
    ================================================== */

    public static function generateThumbnail($sourcePath, $destinationPath, $width = 200, $height = 200)
    {
        if (!extension_loaded('gd')) {
            return [
                'success' => false,
                'error' => 'GD extension not loaded'
            ];
        }

        $imageType = exif_imagetype($sourcePath);

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($sourcePath);
                break;
            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported image type'
                ];
        }

        if (!$image) {
            return [
                'success' => false,
                'error' => 'Failed to create image from source'
            ];
        }

        $srcWidth = imagesx($image);
        $srcHeight = imagesy($image);

        // Calculate aspect ratio
        $ratio = $srcWidth / $srcHeight;

        if ($ratio > 1) {
            $newWidth = $width;
            $newHeight = $width / $ratio;
        } else {
            $newHeight = $height;
            $newWidth = $height * $ratio;
        }

        // Center crop
        $x = ($width - $newWidth) / 2;
        $y = ($height - $newHeight) / 2;

        $thumbnail = imagecreatetruecolor($width, $height);
        imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, 255, 255, 255));

        imagecopyresampled($thumbnail, $image, $x, $y, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        try {
            imagejpeg($thumbnail, $destinationPath, 85);
            imagedestroy($image);
            imagedestroy($thumbnail);

            return [
                'success' => true,
                'path' => $destinationPath
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to save thumbnail: ' . $e->getMessage()
            ];
        }
    }

    /* ==================================================
        SET MAX FILE SIZE
    ================================================== */

    public static function setMaxFileSize($bytes)
    {
        self::$maxFileSize = (int)$bytes;
    }

    /* ==================================================
        ADD ALLOWED EXTENSION
    ================================================== */

    public static function addAllowedExtension($extension)
    {
        $extension = strtolower($extension);

        if (!in_array($extension, self::$allowedFileExtensions)) {
            self::$allowedFileExtensions[] = $extension;
        }
    }

    /* ==================================================
        REMOVE ALLOWED EXTENSION
    ================================================== */

    public static function removeAllowedExtension($extension)
    {
        $extension = strtolower($extension);

        $key = array_search($extension, self::$allowedFileExtensions);
        if ($key !== false) {
            unset(self::$allowedFileExtensions[$key]);
        }
    }
}
