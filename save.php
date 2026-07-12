<?php
require_once __DIR__ . '/auth.php';

startSecureSession();
restoreLoginFromRememberCookie();

if (!isLoggedIn()) {
    http_response_code(403);
    die(json_encode(["status" => "error", "message" => "Not logged in."]));
}

// Log errors, never leak them into the JSON response.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
header("Content-Type: application/json");

$csrfToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
if (empty($_SESSION["csrfToken"]) || !hash_equals($_SESSION["csrfToken"], $csrfToken)) {
    http_response_code(403);
    die(json_encode(["status" => "error", "message" => "Invalid CSRF token."]));
}

const MAX_BOOKMARKS = 5000;
const LIMIT_TITLE = 120;
const LIMIT_URL = 2048;
const LIMIT_CATEGORY = 40;
const LIMIT_NOTES = 600;

function cleanStr($value, $collapseWhitespace = false) {
    if (!is_string($value)) return "";
    if ($collapseWhitespace) {
        $value = preg_replace('/\s+/', ' ', $value);
    }
    return trim($value);
}

function isValidHttpUrl($value) {
    if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return $scheme === "http" || $scheme === "https";
}

$data = file_get_contents("php://input");
$decoded = json_decode($data, true);

// Ensure the payload is valid JSON and has the expected shape
if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($decoded) ||
    !isset($decoded["bookmarks"], $decoded["categoryOrder"]) ||
    !is_array($decoded["bookmarks"]) ||
    !is_array($decoded["categoryOrder"])
) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Invalid bookmark data."]));
}

if (count($decoded["bookmarks"]) > MAX_BOOKMARKS) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Too many bookmarks."]));
}

// Rebuild a sanitized structure; never trust the raw client payload.
$now = time();
$cleanBookmarks = [];
foreach ($decoded["bookmarks"] as $b) {
    if (!is_array($b)) continue;

    $title = mb_substr(cleanStr($b["title"] ?? ""), 0, LIMIT_TITLE);
    $url = cleanStr($b["url"] ?? "");
    $category = mb_substr(cleanStr($b["category"] ?? "", true), 0, LIMIT_CATEGORY);
    $notes = mb_substr(cleanStr($b["notes"] ?? ""), 0, LIMIT_NOTES);

    if ($title === "" || $category === "" || !isValidHttpUrl($url) || mb_strlen($url) > LIMIT_URL) {
        continue;
    }

    $id = (isset($b["id"]) && is_string($b["id"]) && $b["id"] !== "")
        ? mb_substr($b["id"], 0, 64)
        : bin2hex(random_bytes(16));
    $createdAt = (isset($b["createdAt"]) && is_int($b["createdAt"])) ? $b["createdAt"] : $now;
    $updatedAt = (isset($b["updatedAt"]) && is_int($b["updatedAt"])) ? $b["updatedAt"] : $now;

    $cleanBookmarks[] = [
        "id" => $id,
        "title" => $title,
        "url" => $url,
        "category" => $category,
        "notes" => $notes,
        "createdAt" => $createdAt,
        "updatedAt" => $updatedAt,
    ];
}

$cleanOrder = [];
foreach ($decoded["categoryOrder"] as $c) {
    $c = mb_substr(cleanStr($c ?? "", true), 0, LIMIT_CATEGORY);
    if ($c === "" || in_array($c, $cleanOrder, true)) continue;
    $cleanOrder[] = $c;
}

$out = [
    "version" => 1,
    "exportedAt" => gmdate("Y-m-d\TH:i:s.000\Z"),
    "bookmarks" => $cleanBookmarks,
    "categoryOrder" => $cleanOrder,
];

$mainFile = dataFile();
$contentToSave = "<?php exit; ?>\n" . json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$result = file_put_contents($mainFile, $contentToSave, LOCK_EX);

if ($result !== false) {
    echo json_encode(["status" => "success"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Cannot write to file. Check file permissions."]);
}
?>
