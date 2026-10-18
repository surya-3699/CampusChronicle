<?php

/*
|--------------------------------------------------------------------------
| Redirect Helper
|--------------------------------------------------------------------------
*/
function redirect($url)
{
    header("Location: " . $url);
    exit();
}

/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/*
|--------------------------------------------------------------------------
| Get Logged-in User Name
|--------------------------------------------------------------------------
*/
function currentUser()
{
    return $_SESSION['fullname'] ?? null;
}

/*
|--------------------------------------------------------------------------
| Get Logged-in User Email
|--------------------------------------------------------------------------
*/
function currentEmail()
{
    return $_SESSION['email'] ?? null;
}

/*
|--------------------------------------------------------------------------
| Get Profile Image
|--------------------------------------------------------------------------
*/
function currentImage()
{
    if (
        isset($_SESSION['profile_image']) &&
        !empty($_SESSION['profile_image'])
    ) {
        return $_SESSION['profile_image'];
    }

    return "default.png";
}

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/
function logout()
{
    session_unset();
    session_destroy();

    redirect("index.php?page=home");
}

/*
|--------------------------------------------------------------------------
| Campus Chronicle Asset URL
|--------------------------------------------------------------------------
| Normalizes legacy database paths and avoids broken images when a stale
| filename/path exists in the database but not on disk.
|--------------------------------------------------------------------------
*/
function cc_local_asset_is_usable($path)
{
    $path = trim((string)$path);
    if ($path === '' || !is_file($path)) {
        return false;
    }

    // A number of legacy project zips contained zero-byte JPG/PNG files.
    // Browsers treat them as broken images, so never return them as assets.
    $size = @filesize($path);
    return $size !== false && $size > 0;
}

function cc_normalize_asset_path($value, $basePath = '')
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $raw)) {
        return $raw;
    }

    $path = ltrim(str_replace('\\', '/', $raw), '/');

    if (strpos($path, 'public/') === 0) {
        return $path;
    }

    if (strpos($path, 'uploads/') === 0) {
        return 'public/' . $path;
    }

    if ($basePath !== '') {
        return rtrim($basePath, '/') . '/' . basename($path);
    }

    return $path;
}

function cc_asset_exists($value, $basePath = '')
{
    $path = cc_normalize_asset_path($value, $basePath);
    if ($path === '') {
        return false;
    }

    // Remote assets cannot be verified without making a network request.
    // Treat a valid http(s) URL as intentionally configured.
    if (preg_match('#^(https?:)?//#i', $path)) {
        return true;
    }

    return cc_local_asset_is_usable($path);
}

function cc_asset_url($value, $fallback, $basePath = '')
{
    $fallback = (string)$fallback;
    $path = cc_normalize_asset_path($value, $basePath);

    if ($path === '') {
        return $fallback;
    }

    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }

    return cc_local_asset_is_usable($path) ? $path : $fallback;
}

function cc_safe_url($value) {
    $value=trim((string)$value);
    return filter_var($value,FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value,PHP_URL_SCHEME)??''),['http','https'],true) ? $value : '#';
}

/*
|--------------------------------------------------------------------------
| "Authorized by" (events and gallery albums)
|--------------------------------------------------------------------------
| The name of the person who approved the event/album (e.g. "Dr. K. Srinivas,
| HOD CSE"). Stored raw, escaped on output like every other text field.
*/
function cc_normalize_authorizer($value)
{
    $value = trim(preg_replace('/\s+/u', ' ', (string)$value));
    return $value;
}

/** Returns an error message, or null when the name is acceptable. */
function cc_authorizer_error($value)
{
    $value = cc_normalize_authorizer($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

    if ($value === '') {
        return 'Enter who authorized this (the "Authorized by" name).';
    }
    if ($length < 2 || $length > 150) {
        return 'Use an "Authorized by" name of 2-150 characters.';
    }
    if (!preg_match('/^[\p{L}\p{M}\p{N} .,\'’()&\/-]+$/u', $value) || !preg_match('/\p{L}.*\p{L}/u', $value)) {
        return 'The "Authorized by" name may only contain letters, numbers, spaces and . , \' ( ) & / -';
    }
    return null;
}

/** Display text for admin lists. */
function cc_authorizer_label($value)
{
    $value = cc_normalize_authorizer($value);
    return $value !== '' ? $value : 'Not recorded';
}

/** Previously used authorizer names, offered as type-ahead suggestions. */
function cc_authorizer_suggestions()
{
    require_once __DIR__ . '/database.php';
    $names = [];
    try {
        $db = Database::connect();
        foreach (['events', 'gallery'] as $table) {
            if (!Database::hasColumn($table, 'authorized_by')) continue;
            $q = $db->query("SELECT DISTINCT authorized_by FROM `$table` WHERE authorized_by IS NOT NULL AND authorized_by <> '' ORDER BY authorized_by LIMIT 50");
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $name) $names[$name] = true;
        }
    } catch (Throwable $e) {
        error_log('Authorizer suggestions unavailable: ' . $e->getMessage());
    }
    return array_slice(array_keys($names), 0, 50);
}

/** Markup for the "Authorized by" field shared by the four editor forms. */
function cc_authorizer_field($current = '', $id = 'authorizedBy')
{
    $listId = $id . 'List';
    $html  = '<label class="cc-field-wide">Authorized by<input type="text" name="authorized_by" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' value="' . htmlspecialchars((string)$current, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' required minlength="2" maxlength="150" autocomplete="off" list="' . htmlspecialchars($listId, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' placeholder="Name and role, e.g. Dr. K. Srinivas, HOD">';
    $html .= '<small class="cc-help">The person who approved this. Shown to admins in the list view.</small></label>';
    $html .= '<datalist id="' . htmlspecialchars($listId, ENT_QUOTES, 'UTF-8') . '">';
    foreach (cc_authorizer_suggestions() as $name) {
        $html .= '<option value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">';
    }
    $html .= '</datalist>';
    return $html;
}

/*
|--------------------------------------------------------------------------
| Optional hosted 3D scene (CC_3D_SCENE_URL)
|--------------------------------------------------------------------------
| Returns a validated https URL, or '' when none is configured (the built-in
| WebGL scene is then used).
*/
function cc_scene_embed_url()
{
    $url = trim((string)getenv('CC_3D_SCENE_URL'));
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $parts = parse_url($url);
    return (strtolower($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])) ? $url : '';
}

/*
|--------------------------------------------------------------------------
| Self-hosted third-party assets with CDN fallback
|--------------------------------------------------------------------------
| The Docker build downloads Bootstrap into public/vendor/ (tools/fetch-vendor.sh).
| When that local copy exists it is used; otherwise (e.g. XAMPP) the CDN URL is used.
*/
function cc_vendor_asset($localPath, $cdnUrl)
{
    return is_file($localPath) ? $localPath : $cdnUrl;
}
