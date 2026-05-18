<?php
require_once __DIR__ . '/auth.php';

startSecureSession();

if (!isLoggedIn()) {
    http_response_code(403);
    die(json_encode(["status" => "error", "message" => "Not logged in."]));
}

error_reporting(0);
header("Content-Type: application/json");

$csrfToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
if (empty($_SESSION["csrfToken"]) || !hash_equals($_SESSION["csrfToken"], $csrfToken)) {
    http_response_code(403);
    die(json_encode(["status" => "error", "message" => "Invalid CSRF token."]));
}

$data = file_get_contents("php://input");
$decoded = json_decode($data, true); // Decode to catch errors

// Ensure the payload is valid JSON and has the expected shape
if (
    json_last_error() === JSON_ERROR_NONE &&
    is_array($decoded) &&
    isset($decoded["bookmarks"], $decoded["categoryOrder"]) &&
    is_array($decoded["bookmarks"]) &&
    is_array($decoded["categoryOrder"])
) {
    $mainFile = 'data.php';
    $backupDir = 'backups';
    
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
        file_put_contents($backupDir . '/.htaccess', "Require all denied\n");
    }

    if (file_exists($mainFile)) {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . '/backup_' . $timestamp . '.php';
        copy($mainFile, $backupFile);

        $backups = glob($backupDir . '/backup_*.php');
        if (count($backups) > 10) {
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            while (count($backups) > 10) {
                $oldest = array_shift($backups);
                unlink($oldest);
            }
        }
    }

    $contentToSave = "<?php exit; ?>\n" . $data;
    $result = file_put_contents($mainFile, $contentToSave, LOCK_EX);
    
    if ($result !== false) {
        echo json_encode(["status" => "success"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Cannot write to file. Check file permissions."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid bookmark data."]);
}
?>
