<?php
require_once __DIR__ . '/auth.php';

startSecureSession();

if (empty($_SESSION["csrfToken"])) {
    $_SESSION["csrfToken"] = bin2hex(random_bytes(32));
}

// Password resolution (env loading + hash/plain fallback) lives in
// auth.php so it's shared with account.php.
$passwordHash = resolvePasswordHash();
$passwordPlain = resolvePasswordPlain();
$hasCredential = $passwordHash !== null || $passwordPlain !== null;

if (isset($_POST['logout'])) {
    $token = $_POST['csrfToken'] ?? "";
    if (!empty($_SESSION["csrfToken"]) && hash_equals($_SESSION["csrfToken"], $token)) {
        clearRememberCookie();
        session_destroy();
    }
    header("Location: index.php");
    exit;
}

restoreLoginFromRememberCookie();

if (isset($_POST['login'])) {
    if ($hasCredential && verifyAppPassword($_POST["password"] ?? "", $passwordHash, $passwordPlain)) {
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
  <link rel="stylesheet" href="style.css?v=6">
  <script>
    var storedTheme = localStorage.getItem("theme");
    var wantsDark = storedTheme ? storedTheme === "dark" : (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches);
    if (wantsDark) {
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
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION["csrfToken"], ENT_QUOTES, "UTF-8") ?>" />
  <title>My Bookmarks</title>
  <link rel="icon" type="image/png" href="icon.png">
  <link rel="apple-touch-icon" href="icon.png">
  <link rel="stylesheet" href="style.css?v=6">
  <script>
    var storedTheme = localStorage.getItem("theme");
    var wantsDark = storedTheme ? storedTheme === "dark" : (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches);
    if (wantsDark) {
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
          <button class="btn btnSubtle" type="button" id="btnTheme" title="Toggle theme" aria-label="Thema wisselen">🌙</button>
          <div class="userMenu" id="userMenu">
            <button class="btn btnSubtle" type="button" id="btnUser" title="Account" aria-label="Accountmenu" aria-haspopup="true" aria-expanded="false">👤</button>
            <div class="userMenuDropdown" id="userMenuDropdown" hidden>
              <div class="userMenuStats" id="userMenuStats">…</div>
              <button class="userMenuItem" type="button" id="btnChangePassword">🔑 Wachtwoord wijzigen</button>
              <button class="userMenuItem" type="button" id="btnDownloadData">⬇️ Data downloaden (JSON)</button>
              <button class="userMenuItem" type="button" id="btnImportHtml">📥 Importeer bookmarks.html</button>
              <input type="file" id="fieldImportHtml" accept=".html,text/html" hidden>
              <button class="userMenuItem" type="button" id="btnLogoutAll">📴 Uitloggen op alle apparaten</button>
              <form method="POST" style="margin:0;">
                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($_SESSION["csrfToken"], ENT_QUOTES, "UTF-8") ?>" />
                <button type="submit" name="logout" class="userMenuItem userMenuItemDanger">⏻ Uitloggen</button>
              </form>
            </div>
          </div>
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

  <dialog id="passwordDialog">
    <div class="modalHeader">
      <div>
        <h2>Wachtwoord wijzigen</h2>
        <p>Kies een nieuw wachtwoord van minimaal 8 tekens.</p>
      </div>
      <button class="btn btnSubtle" type="button" id="btnPasswordDialogClose">Close</button>
    </div>
    <div class="modalBody">
      <form id="passwordForm">
        <div class="gridForm">
          <div class="field span2">
            <label for="fieldCurrentPassword">Huidig wachtwoord</label>
            <input id="fieldCurrentPassword" name="currentPassword" type="password" required autocomplete="current-password" />
          </div>
          <div class="field">
            <label for="fieldNewPassword">Nieuw wachtwoord</label>
            <input id="fieldNewPassword" name="newPassword" type="password" required minlength="8" autocomplete="new-password" />
          </div>
          <div class="field">
            <label for="fieldConfirmPassword">Bevestig nieuw wachtwoord</label>
            <input id="fieldConfirmPassword" name="confirmPassword" type="password" required minlength="8" autocomplete="new-password" />
          </div>
        </div>
        <div class="error" id="passwordFormError"></div>
      </form>
    </div>
    <div class="modalFooter">
      <button class="btn btnSubtle" type="button" id="btnPasswordCancel">Cancel</button>
      <button class="btn btnPrimary" type="submit" form="passwordForm" id="btnPasswordSave">Opslaan</button>
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

  <script src="app.js?v=6" defer></script>
</body>
</html>
