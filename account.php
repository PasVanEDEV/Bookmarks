<?php
require_once __DIR__ . '/auth.php';

startSecureSession();
restoreLoginFromRememberCookie();

if (!isLoggedIn()) {
    http_response_code(403);
    die(json_encode(["status" => "error", "message" => "Not authorized."]));
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Downloading is a plain GET (so it can be a normal browser download) but
// still requires an active session, checked above.
if ($action === 'download') {
    $dataFile = dataFile();
    $json = file_exists($dataFile)
        ? preg_replace('/^<\?php exit; \?>\s*/i', '', file_get_contents($dataFile))
        : json_encode(["bookmarks" => [], "categoryOrder" => []]);
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="bookmarks-export-' . date('Y-m-d') . '.json"');
    echo $json;
    exit;
}

header('Content-Type: application/json');

// Every other action mutates state or reveals account info, so require the
// same CSRF check as save.php.
$csrfToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? ($_POST['csrfToken'] ?? '');
if (empty($_SESSION["csrfToken"]) || !hash_equals($_SESSION["csrfToken"], $csrfToken)) {
    http_response_code(403);
    die(json_encode(["status" => "error", "message" => "Invalid CSRF token."]));
}

if ($action === 'stats') {
    $dataFile = dataFile();
    $json = file_exists($dataFile)
        ? preg_replace('/^<\?php exit; \?>\s*/i', '', file_get_contents($dataFile))
        : '{"bookmarks":[],"categoryOrder":[]}';
    $data = json_decode($json, true);
    $bookmarks = (is_array($data) && isset($data['bookmarks']) && is_array($data['bookmarks'])) ? $data['bookmarks'] : [];
    $categories = (is_array($data) && isset($data['categoryOrder']) && is_array($data['categoryOrder'])) ? $data['categoryOrder'] : [];
    echo json_encode([
        "status" => "success",
        "bookmarkCount" => count($bookmarks),
        "categoryCount" => count($categories),
    ]);
    exit;
}

if ($action === 'change-password') {
    $input = json_decode(file_get_contents("php://input"), true);
    $current = is_array($input) ? ($input['currentPassword'] ?? '') : '';
    $new = is_array($input) ? ($input['newPassword'] ?? '') : '';

    $valid = is_string($current) && $current !== ""
        && verifyAppPassword($current, resolvePasswordHash(), resolvePasswordPlain());

    if (!$valid) {
        http_response_code(403);
        die(json_encode(["status" => "error", "message" => "Current password is incorrect."]));
    }

    if (!is_string($new) || mb_strlen($new) < 8) {
        http_response_code(400);
        die(json_encode(["status" => "error", "message" => "New password must be at least 8 characters."]));
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    if (!setStoredPasswordHash($newHash)) {
        http_response_code(500);
        die(json_encode(["status" => "error", "message" => "Could not save new password."]));
    }

    // Changing the password invalidates every "remember me" session on
    // every device; re-issue one for the device making this request.
    withTokenLock(function ($tokens) { return []; });
    clearRememberCookie();
    createRememberLogin();

    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'logout-all') {
    withTokenLock(function ($tokens) { return []; });
    clearRememberCookie();
    createRememberLogin();
    echo json_encode(["status" => "success"]);
    exit;
}

http_response_code(400);
echo json_encode(["status" => "error", "message" => "Unknown action."]);
