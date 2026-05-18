<?php
require_once __DIR__ . '/auth.php';

startSecureSession();

// ==== PASSWORD CONFIGURATION ====
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
$password = getenv("APP_PASSWORD");
if ($password === false || $password === "") {
    $password = null;
}
// ================================

if (isset($_GET['logout'])) {
    clearRememberCookie();
    session_destroy();
    header("Location: index.php");
    exit;
}

restoreLoginFromRememberCookie();

if (isset($_POST['login'])) {
    if ($password !== null && hash_equals($password, $_POST["password"])) {
        session_regenerate_id(true);
        $_SESSION['loggedIn'] = true;
        createRememberLogin();
        
        header("Location: index.php");
        exit;
    } else {
        $error = "Incorrect password";
    }
}

if (!isLoggedIn()) {
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login - My Bookmarks</title>
  <link rel="icon" type="image/png" href="icon.png">
  <link rel="apple-touch-icon" href="icon.png">
  <link rel="stylesheet" href="style.css?v=2">
  <script>
    if (localStorage.getItem("theme") === "dark") {
      document.documentElement.setAttribute("data-theme", "dark");
    }
  </script>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="login-body">
  <div class="login-card">
    <svg viewBox="0 0 24 24" fill="none" style="width:48px;height:48px;background:linear-gradient(135deg,#2563eb,#3b82f6);border-radius:14px;margin-bottom:8px;padding:10px;box-shadow: 0 12px 25px rgba(37, 99, 235, 0.28);"><path d="M7 4.5h10a1.5 1.5 0 0 1 1.5 1.5v15l-6.5-3-6.5 3V6A1.5 1.5 0 0 1 7 4.5Z" stroke="white" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 9h6" stroke="white" stroke-width="1.8" stroke-linecap="round"/></svg>
    <form method="POST">
      <input type="password" name="password" placeholder="Password..." required autofocus>
      <button type="submit" name="login">Log in</button>
      <?php if(isset($error)) echo "<div class='login-error'>".htmlspecialchars($error)."</div>"; ?>
    </form>
  </div>
</body>
</html>
<?php
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="color-scheme" content="light dark" />
  <title>My Bookmarks</title>
  <link rel="icon" type="image/png" href="icon.png">
  <link rel="apple-touch-icon" href="icon.png">
  <link rel="stylesheet" href="style.css?v=2">
  <script>
    if (localStorage.getItem("theme") === "dark") {
      document.documentElement.setAttribute("data-theme", "dark");
    }
  </script>
</head>
<body>
  <div class="app" id="app">
    <div class="stickyTop">
      <section class="card">
        <div class="controls">
          <div class="searchWrap">
            <svg class="searchIcon" viewBox="0 0 24 24" fill="none"><path d="M10.5 18.5a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="1.8"/><path d="M16.5 16.5 21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <input id="searchInput" type="search" placeholder="Search by title or category (press /) ..." autocomplete="off" />
          </div>
          <button class="btn btnPrimary" type="button" id="btnAdd">＋ Add</button>
          <button class="btn btnSubtle" type="button" id="btnClearFilters">Clear filters</button>
          <button class="btn btnSubtle" type="button" id="btnTheme" title="Toggle theme">🌙</button>
          <a href="?logout=1" class="btn btnDanger" title="Logout">⏻</a>
        </div>
        <div class="filters">
          <div class="filterRow" id="categoryPills"></div>
          <div class="count" id="resultsCount"></div>
        </div>
      </section>
    </div>

    <main>
      <section class="grid" id="grid"></section>
      <section class="empty" id="emptyState" hidden>
        <h2>No bookmarks found</h2>
        <p>Try a different search query or category filter.</p>
      </section>
    </main>
  </div>

  <dialog id="editorDialog">
    <div class="modalHeader">
      <div>
        <h2 id="dialogTitle">Add a bookmark</h2>
        <p>Save a title, URL, category and optional notes.</p>
      </div>
      <button class="btn btnSubtle" type="button" id="btnDialogClose">Close</button>
    </div>
    <div class="modalBody">
      <form id="bookmarkForm">
        <div class="gridForm">
          <div class="field">
            <label for="fieldTitle">Titel *</label>
            <input id="fieldTitle" name="title" type="text" required maxlength="120" />
          </div>
          <div class="field">
            <label for="fieldCategory">Categorie *</label>
            <div style="display:flex; gap:8px;">
              <input id="fieldCategory" name="category" type="text" required maxlength="40" style="flex:1;" autocomplete="off" placeholder="New or choose from the right..." />
              <select id="categorySelect" tabindex="-1" style="width: auto; cursor:pointer;" title="Choose an existing category">
              </select>
            </div>
          </div>
          <div class="field span2">
            <label for="fieldUrl">URL *</label>
            <input id="fieldUrl" name="url" type="url" required maxlength="2048" />
          </div>
          <div class="field span2">
            <label for="fieldNotes">Notes (optional)</label>
            <textarea id="fieldNotes" name="notes" maxlength="600"></textarea>
          </div>
        </div>
        <div class="error" id="formError"></div>
      </form>
    </div>
    <div class="modalFooter">
      <button class="btn btnDanger" type="button" id="btnDeleteInDialog" hidden>Delete</button>
      <button class="btn btnSubtle" type="button" id="btnCancel">Cancel</button>
      <button class="btn btnPrimary" type="submit" form="bookmarkForm" id="btnSave">Save</button>
    </div>
  </dialog>

  <div class="toastRegion">
    <div class="toast" id="toast" data-open="false">
      <div>
        <strong id="toastTitle">Saved</strong>
        <small id="toastMessage">Your change has been saved.</small>
      </div>
      <button class="toastClose" type="button" id="toastClose">OK</button>
    </div>
  </div>

  <script src="app.js?v=2" defer></script>
</body>
</html>
