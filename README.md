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

* **Favicons and hostnames**
  Bookmark rows show the site favicon and hostname for quick scanning.

* **Dark mode**
  Toggle light and dark mode. The selected theme is saved in the browser with `localStorage` and is also applied on the login screen.

* **Automatic backups**
  Every save creates a timestamped backup in `backups/`. The app keeps the latest 10 backups and creates a protective `.htaccess` file for that folder.

* **Input and output protection**
  URLs are validated, JSON payloads are checked before saving, and dynamic output is escaped to reduce XSS risk.

* **Mobile-friendly interface**
  Responsive layout with scrollable filters and compact controls for smaller screens.

* **Keyboard shortcuts**
  Press `/` to focus search and `ESC` to clear and blur the search field.

---

# Requirements

* PHP 7.3 or newer.
* A web server that can run PHP, such as Apache, Nginx with PHP-FPM, or a hosting control panel with PHP support.
* Write access for PHP to the app folder or at least to `data.php`, `auth_tokens.php`, and `backups/`.
* A modern browser with JavaScript enabled.

---

# Files

| File | Purpose |
| --- | --- |
| `index.php` | Login screen, dashboard markup, `.env` password loading, logout link |
| `auth.php` | Shared session, cookie, and 90-day remember-me token handling |
| `load.php` | Loads bookmark data from `data.php` after auth validation |
| `save.php` | Validates and saves bookmark data, creates backups, rotates old backups |
| `app.js` | Frontend logic for bookmarks, categories, search, filters, drag-and-drop, theme, and toasts |
| `style.css` | Responsive light/dark styling |
| `data.php` | Local bookmark data file, generated or maintained by the app |
| `auth_tokens.php` | Local storage for hashed remember-me tokens |
| `backups/` | Auto-created folder for the latest 10 backups |
| `icon.png` | Optional favicon/apple-touch icon referenced by `index.php` |

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
README.md
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

Create a `.env` file in the same folder as `index.php` and store the password there:

```text
APP_PASSWORD=YOUR_STRONG_PASSWORD
```

The `.env` file is ignored by Git, so the real password stays outside the repository.

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
chmod 755 /path/to/bookmarks/backups
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
backups/
```

If `backups/` does not exist, `save.php` will try to create it on the first successful save.

## 5. Open the app

Visit the app URL in your browser, for example:

```text
https://example.com/bookmarks/
```

Log in with the password from `.env`.

---

# Security and Privacy

## Authentication

* Login uses `APP_PASSWORD` from `.env`.
* PHP sessions protect the dashboard and JSON endpoints.
* Remember-me tokens are random values generated with `random_bytes()`.
* Only hashed token values are stored in `auth_tokens.php`.
* Expired tokens are removed during token checks.
* Session IDs are regenerated after login and remember-me recovery.
* Logout clears the remember-me cookie and destroys the PHP session.

## Cookies

Session and remember-me cookies use:

* `HttpOnly`
* `Secure` when the request is detected as HTTPS
* `SameSite=Lax`

HTTPS detection also supports `HTTP_X_FORWARDED_PROTO=https` for reverse proxy setups.

## Data Storage

* Bookmarks are stored locally in `data.php`.
* Remember-me token hashes are stored locally in `auth_tokens.php`.
* Backups are stored locally in `backups/`.
* `data.php`, `auth_tokens.php`, and backup files start with `<?php exit; ?>` to prevent direct PHP execution from exposing raw data.

## Backup Protection

When `backups/` is created automatically, the app also writes:

```apache
Require all denied
```

to `backups/.htaccess`.

This helps prevent direct web access to backup files on Apache-compatible hosting.

## External Requests

Bookmark favicons are loaded in the browser through Google favicon URLs. Bookmark data itself is still stored locally on your own server.

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

Check that PHP can write to `data.php` and `backups/`. Also confirm that the submitted data is valid JSON and that `save.php` is reachable.

## Backups are not created

Make sure the PHP process can create or write to the `backups/` directory.

## The favicon is missing

Add an `icon.png` file to the app folder, or remove the icon links from `index.php`.

---

# License

Built for personal use and self-hosting.

Free to customize and extend for your own needs.
