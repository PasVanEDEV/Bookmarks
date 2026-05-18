<?php
require_once __DIR__ . '/auth.php';

startSecureSession();

// Check if the user is logged in
if (!isLoggedIn()) {
    http_response_code(403);
    die(json_encode(["status" => "error", "message" => "Not authorized."]));
}

header('Content-Type: application/json');

if (file_exists('data.php')) {
    $content = file_get_contents('data.php');
    $json = preg_replace('/^<\?php exit; \?>\s*/i', '', $content);
    echo $json;
} else {
    // Return an empty structure if the file does not exist
    echo json_encode(["bookmarks" => [], "categoryOrder" => []]);
}
?>