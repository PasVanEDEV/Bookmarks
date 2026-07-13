<?php

const REMEMBER_COOKIE_NAME = 'remember_me';
const REMEMBER_LIFETIME = 90 * 24 * 60 * 60;

// Loads KEY=value pairs from a .env file into getenv()/$_ENV. Shared by
// every entry point (index.php, account.php, ...) so env-based password
// fallback works consistently no matter which script handles the request.
function loadEnvFile($file) {
    if (!is_readable($file)) return;

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === "" || $line[0] === "#" || strpos($line, "=") === false) continue;

        list($name, $value) = explode("=", $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === "") continue;
        $first = substr($value, 0, 1);
        $last = substr($value, -1);
        if (($first === chr(34) && $last === chr(34)) || ($first === chr(39) && $last === chr(39))) {
            $value = substr($value, 1, -1);
        }

        putenv($name . "=" . $value);
        $_ENV[$name] = $value;
    }
}

loadEnvFile(__DIR__ . "/.env");

// Resolves the current password hash: prefer one set in-app (via
// account.php's change-password action), then APP_PASSWORD_HASH from env.
// Returns null if neither is set (caller should fall back to plaintext
// APP_PASSWORD for backward compatibility).
function resolvePasswordHash() {
    $stored = getStoredPasswordHash();
    if ($stored !== null) {
        return $stored;
    }
    $envHash = getenv("APP_PASSWORD_HASH");
    return ($envHash === false || $envHash === "") ? null : $envHash;
}

function resolvePasswordPlain() {
    $envPlain = getenv("APP_PASSWORD");
    return ($envPlain === false || $envPlain === "") ? null : $envPlain;
}

function verifyAppPassword($input, $hash, $plain) {
    if (!is_string($input) || $input === "") {
        return false;
    }
    if ($hash !== null) {
        return password_verify($input, $hash);
    }
    if ($plain !== null) {
        return hash_equals($plain, $input);
    }
    return false;
}

// Where mutable data lives. Defaults to the app folder for local/self-hosted
// use; set DATA_DIR (e.g. a mounted Railway volume) to keep data across
// redeploys on hosts with an ephemeral filesystem.
function dataDir() {
    $dir = getenv('DATA_DIR');
    if ($dir === false || $dir === '') {
        return __DIR__;
    }
    $dir = rtrim($dir, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function dataFile() {
    return dataDir() . '/data.php';
}

function authTokenFile() {
    return dataDir() . '/auth_tokens.php';
}

function credentialFile() {
    return dataDir() . '/auth_config.php';
}

// Returns the bcrypt/argon hash set via the in-app "change password"
// feature, or null if the password has never been changed (in which case
// the caller should fall back to the APP_PASSWORD_HASH/APP_PASSWORD env
// vars from .env).
function getStoredPasswordHash() {
    $file = credentialFile();
    if (!is_readable($file)) {
        return null;
    }
    $content = file_get_contents($file);
    $json = preg_replace('/^<\?php exit; \?>\s*/i', '', $content);
    $data = json_decode($json, true);
    return (is_array($data) && !empty($data['hash']) && is_string($data['hash'])) ? $data['hash'] : null;
}

function setStoredPasswordHash($hash) {
    $file = credentialFile();
    $content = "<?php exit; ?>\n" . json_encode(['hash' => $hash]);
    return file_put_contents($file, $content, LOCK_EX) !== false;
}

function isSecureRequest() {
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
}

function rememberCookieOptions($expires = null) {
    $options = [
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    if ($expires !== null) {
        $options['expires'] = $expires;
    }

    return $options;
}

function startSecureSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Atomically read, mutate, and write the token file under one exclusive
// lock so concurrent requests can't clobber each other's changes.
// $mutator receives the current tokens array and returns the array to
// persist (return null to leave the file untouched).
function withTokenLock(callable $mutator, $file = null) {
    if ($file === null) {
        $file = authTokenFile();
    }
    $fp = @fopen($file, 'c+');
    if ($fp === false) {
        return;
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return;
        }

        $content = stream_get_contents($fp);
        $json = preg_replace('/^<\?php exit; \?>\s*/i', '', $content);
        $tokens = json_decode($json, true) ?: [];

        $newTokens = $mutator($tokens);

        if (is_array($newTokens)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, "<?php exit; ?>\n" . json_encode($newTokens));
            fflush($fp);
        }
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function clearRememberCookie() {
    setcookie(REMEMBER_COOKIE_NAME, '', rememberCookieOptions(time() - 3600));
}

function createRememberLogin() {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = time() + REMEMBER_LIFETIME;

    setcookie(REMEMBER_COOKIE_NAME, $token, rememberCookieOptions($expires));

    withTokenLock(function ($tokens) use ($hash, $expires) {
        $tokens[] = ['hash' => $hash, 'expires' => $expires];
        return $tokens;
    });
}

function restoreLoginFromRememberCookie() {
    if (isset($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === true) {
        return true;
    }

    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        return false;
    }

    $hash = hash('sha256', $_COOKIE[REMEMBER_COOKIE_NAME]);
    $now = time();
    $newExpires = $now + REMEMBER_LIFETIME;
    $valid = false;

    withTokenLock(function ($tokens) use ($hash, $now, $newExpires, &$valid) {
        $newTokens = [];
        foreach ($tokens as $token) {
            if (!isset($token['hash'], $token['expires']) || $token['expires'] <= $now) {
                continue;
            }

            if (hash_equals($token['hash'], $hash)) {
                $valid = true;
                $token['expires'] = $newExpires;
            }

            $newTokens[] = $token;
        }
        return $newTokens;
    });

    if (!$valid) {
        clearRememberCookie();
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['loggedIn'] = true;
    setcookie(REMEMBER_COOKIE_NAME, $_COOKIE[REMEMBER_COOKIE_NAME], rememberCookieOptions($newExpires));
    return true;
}

// Pure predicate: no side effects. Call restoreLoginFromRememberCookie()
// once per request (after startSecureSession) if you want cookie recovery.
function isLoggedIn() {
    return isset($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === true;
}
