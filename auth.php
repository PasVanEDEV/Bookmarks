<?php

const AUTH_TOKEN_FILE = __DIR__ . '/auth_tokens.php';
const REMEMBER_COOKIE_NAME = 'remember_me';
const REMEMBER_LIFETIME = 90 * 24 * 60 * 60;

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

function readTokens($file = AUTH_TOKEN_FILE) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    $json = preg_replace('/^<\?php exit; \?>\s*/i', '', $content);
    return json_decode($json, true) ?: [];
}

function writeTokens($tokens, $file = AUTH_TOKEN_FILE) {
    file_put_contents($file, "<?php exit; ?>\n" . json_encode($tokens), LOCK_EX);
}

function clearRememberCookie() {
    setcookie(REMEMBER_COOKIE_NAME, '', rememberCookieOptions(time() - 3600));
}

function createRememberLogin() {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = time() + REMEMBER_LIFETIME;

    setcookie(REMEMBER_COOKIE_NAME, $token, rememberCookieOptions($expires));

    $tokens = readTokens();
    $tokens[] = ['hash' => $hash, 'expires' => $expires];
    writeTokens($tokens);
}

function restoreLoginFromRememberCookie() {
    if (isset($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === true) {
        return true;
    }

    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        return false;
    }

    $tokens = readTokens();
    $hash = hash('sha256', $_COOKIE[REMEMBER_COOKIE_NAME]);
    $now = time();
    $newExpires = $now + REMEMBER_LIFETIME;
    $valid = false;
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

    writeTokens($newTokens);

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
