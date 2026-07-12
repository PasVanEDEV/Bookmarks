# Bookmarks

Lightweight, secure self-hosted bookmark manager for storing and organizing your favorite links on your own PHP server.

Bookmarks stores its data locally in PHP-protected files. No database is required.

---

# Features

* **Password-protected dashboard**
  The application opens with a login screen and protects `index.php`, `load.php`, and `save.php` through shared authentication logic.

* **90-day persistent login**
  A secure `remember_me` cookie keeps you logged in for up to 90 days. When the PHP session expires, the app restores it automatically from the hashed remember-me token.

* **Session recovery for API calls**
  `load.php` and `save.php` use the same recovery logic as the dashboard, so data loading and saving keep working after a normal PHP session timeout.

* **Add, edit, and delete bookmarks**
  Manage bookmark title, URL, category, and optional notes from a modal editor.

* **Category organization**
  Bookmarks are grouped by category. Existing categories can be selected from a dropdown when adding or editing a bookmark.

* **Multi-category filtering**
  Select one or more category pills to filter the dashboard. The `Alle` pill clears category filtering.

* **Search**
  Search bookmarks by title or category. The result counter shows how many bookmarks are currently visible.

* **Drag-and-drop sorting**
  Reorder category pills, reorder category cards, and move bookmarks within or between categories.

* **Category colors**
  Built-in color accents for categories such as Muziek, Social, TV&Film, AI, Shopping, Vakantie, ICT, Weer, Cyber, Werk, Financieel, Nieuws, and Vrijetijd. Google gets a Google-colored accent bar.

* **Favicons, hostnames, and notes**
  Bookmark rows show the site favicon and hostname for quick scanning, plus the optional note when one is set.

* **Dark mode**
  Toggle light and dark mode. The selected theme is saved in the browser with `localStorage` and is also applied on the login screen.

* **Server-side validation**
  `save.php` does not trust the client: it rebuilds a clean data structure on every save, requiring a title and category, enforcing `http`/`https` URLs, capping field lengths and the total bookmark count, and stripping unknown fields. Dynamic output is also escaped in the browser to reduce XSS risk.

* **Mobile-friendly interface**
  Responsive layout with scrollable filters and compact controls for smaller screens.

* **Keyboard shortcuts**
  Press `/` to focus search and `ESC` to clear and blur the search field.

---

# Requirements

* PHP 7.3 or newer.
* A web server that can run PHP, such as Apache, Nginx with PHP-FPM, or a hosting control panel with PHP support.
* Write access for PHP to the app folder or at least to `data.php` and `auth_tokens.php`.
* A modern browser with JavaScript enabled.

---

# Files

| File | Purpose |
| --- | --- |
| `index.php` | Login screen, dashboard markup, `.env` password loading, CSRF-protected logout |
| `auth.php` | Shared session, cookie, and 90-day remember-me token handling (locked token file) |
| `load.php` | Loads bookmark data from `data.php` after auth validation |
| `save.php` | Validates, sanitizes, and saves bookmark data to `data.php` |
| `app.js` | Frontend logic for bookmarks, categories, search, filters, drag-and-drop, theme, and toasts |
| `style.css` | Responsive light/dark styling |
| `data.php` | Local bookmark data file, generated or maintained by the app |
| `auth_tokens.php` | Local storage for hashed remember-me tokens |
| `icon.png` | Optional favicon/apple-touch icon referenced by `index.php` |
| `.htaccess` | Apache rules blocking direct access to `.env`, `data.php`, and `auth_tokens.php` |

---

# Installation

## 1. Upload the application

Upload these files to your PHP-enabled web folder:

```text
index.php
auth.php
load.php
save.php
app.js
style.css
.htaccess
```

Optional but recommended:

```text
icon.png
```

Example target folder:

```text
/httpdocs/bookmarks/
```

## 2. Set your password

Create a `.env` file in the same folder as `index.php`.

Recommended — store a bcrypt hash instead of the plaintext password:

```text
APP_PASSWORD_HASH=$2y$10$....your-generated-hash....
```

Generate the hash on any machine with PHP:

```bash
php -r 'echo password_hash("YOUR_STRONG_PASSWORD", PASSWORD_DEFAULT), "\n";'
```

Backward-compatible — a plaintext password still works if no hash is set:

```text
APP_PASSWORD=YOUR_STRONG_PASSWORD
```

When both are present, `APP_PASSWORD_HASH` wins. The `.env` file is ignored by Git, so the secret stays outside the repository. Values may be unquoted or wrapped in single or double quotes.

For non-Apache hosting, make sure the web server blocks direct access to `.env`, `data.php`, and `auth_tokens.php`. The included `.htaccess` covers this for Apache-compatible hosting.

## 3. Create writable data files

Create these files in the same folder as `index.php`:

```text
auth_tokens.php
data.php
```

Recommended initial contents:

```php
<?php exit; ?>
[]
```

For `data.php`, you can also use this initial structure:

```php
<?php exit; ?>
{"bookmarks":[],"categoryOrder":[]}
```

The app can also create/update these files if the PHP process has write access to the application folder.

## 4. Set permissions

Recommended folder permissions:

```bash
chmod 755 /path/to/bookmarks
```

Recommended file permissions:

```bash
chmod 664 /path/to/bookmarks/auth_tokens.php
chmod 664 /path/to/bookmarks/data.php
```

If your hosting setup uses a different PHP user, make sure the PHP process can write to:

```text
auth_tokens.php
data.php
```

If `data.php` or `auth_tokens.php` do not exist, the app can create them when it has write access to the application folder.

## 5. Open the app

Visit the app URL in your browser, for example:

```text
https://example.com/bookmarks/
```

Log in with the password from `.env`.

---

# Security and Privacy

## Authentication

* Login verifies against `APP_PASSWORD_HASH` (bcrypt, via `password_verify`) when set, or a plaintext `APP_PASSWORD` as fallback.
* PHP sessions protect the dashboard and JSON endpoints.
* Remember-me tokens are random values generated with `random_bytes()`.
* Only hashed token values are stored in `auth_tokens.php`.
* Expired tokens are removed during token checks.
* Session IDs are regenerated after login and remember-me recovery.
* Remember-me tokens are read and written under an exclusive file lock so concurrent logins cannot clobber each other.
* Logout is a POST request and, like saving, requires a session CSRF token before it clears the remember-me cookie and destroys the PHP session.
* Save requests require a session CSRF token from the dashboard page, and the payload is fully re-validated server-side.

## Cookies

Session and remember-me cookies use:

* `HttpOnly`
* `Secure` when the request is detected as HTTPS
* `SameSite=Lax`

HTTPS detection also supports `HTTP_X_FORWARDED_PROTO=https` for reverse proxy setups.

## Data Storage

* Bookmarks are stored locally in `data.php`.
* Remember-me token hashes are stored locally in `auth_tokens.php`.
* `data.php` and `auth_tokens.php` start with `<?php exit; ?>` to prevent direct PHP execution from exposing raw data.
* `.htaccess` blocks direct web access to `.env`, `data.php`, and `auth_tokens.php` on Apache-compatible hosting.

## External Requests

Bookmark favicons are loaded in the browser through Google favicon URLs. Bookmark data itself is still stored locally on your own server.

## Recommended Hardening

* Serve the app over HTTPS so session and remember-me cookies can use the `Secure` flag.
* On non-Apache hosting, add equivalent deny rules for `.env`, `data.php`, and `auth_tokens.php`.
* Prefer `APP_PASSWORD_HASH` (bcrypt) over a plaintext `APP_PASSWORD`, and rotate the password if it was ever committed or shared.
* Consider server-level rate limiting or basic access restrictions if the app is publicly reachable.

---

# Data Format

`data.php` stores JSON after a PHP guard line:

```php
<?php exit; ?>
{
  "version": 1,
  "exportedAt": "2026-01-01T12:00:00.000Z",
  "bookmarks": [],
  "categoryOrder": []
}
```

Each bookmark can contain:

```json
{
  "id": "unique-id",
  "title": "Example",
  "url": "https://example.com",
  "category": "Example category",
  "notes": "Optional notes",
  "createdAt": 1234567890,
  "updatedAt": 1234567890
}
```

---

# Usage

1. Click `Add` to create a bookmark.
2. Enter a title, URL, category, and optional notes.
3. Use category pills to filter bookmarks.
4. Use search to find bookmarks by title or category.
5. Drag category pills or category cards to reorder categories.
6. Drag bookmarks to reorder them or move them to another category.
7. Use the edit and delete buttons on a bookmark row to manage existing bookmarks.
8. Use the theme button to switch between light and dark mode.
9. Use the logout button to end the session and clear the remember-me cookie.

---

# Keyboard Shortcuts

| Key | Action |
| --- | --- |
| `/` | Focus the search field |
| `ESC` | Clear search and remove focus from the search field |

---

# Troubleshooting

## I get logged out before 90 days

Make sure `auth.php`, `load.php`, `save.php`, and `index.php` are all uploaded together. The JSON endpoints need `auth.php` to restore the session from the remember-me cookie.

## Data is not saved

Check that PHP can write to `data.php`. Also confirm that the submitted data contains `bookmarks` and `categoryOrder` arrays, the CSRF token meta tag is present on the dashboard, and `save.php` is reachable.

## A bookmark disappeared after saving

`save.php` drops entries that fail validation: a missing title or category, a non-`http(s)` URL, or a URL longer than 2048 characters. Fix the field and re-add the bookmark.

## The favicon is missing

Add an `icon.png` file to the app folder, or remove the icon links from `index.php`.

---

# License

Built for personal use and self-hosting.

Free to customize and extend for your own needs.
