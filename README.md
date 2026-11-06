# Campus Notes: security review project

This application is intentionally vulnerable educational material. Run it only on an isolated machine you control, with synthetic data and a disposable local database. Never expose it to the internet, deploy it, or connect it to shared or real accounts. The companion laboratory handout defines the assignment.

## Local setup

1. Install a supported PHP 8 release with `pdo_mysql` and a local MySQL server. Confirm `pdo_mysql` appears in `php -m`.
2. As a local MySQL administrator, import `schema.sql` into a fresh disposable server/database. It creates `notes_lab` and its tables; use a fresh database to avoid conflicts. Review the SQL before running it.
3. Create a dedicated local MySQL account with SELECT, INSERT, UPDATE, and DELETE privileges only on `notes_lab`; do not use a production account. Set `LAB_DB_USER` and `LAB_DB_PASSWORD` for your shell. In PowerShell use `$env:LAB_DB_USER = 'your_local_user'` and `$env:LAB_DB_PASSWORD = 'your_local_password'`. Do not commit credentials.
4. From this folder, run `php -S 127.0.0.1:8000`. Visit `http://127.0.0.1:8000`. The PHP built-in server is for local development only. Stop it with Ctrl+C after use.
5. The schema includes two synthetic accounts and notes:
   - `alex@example.test` / `LabOnly-Alex-123`
   - `sam@example.test` / `LabOnly-Sam-456`

   These public, throwaway credentials are for the isolated starter application only; never reuse them elsewhere. Use separate browser profiles for separate sessions. The starter stores these passwords using MD5, which students must replace. When switching to secure password hashing, recreate or reset the synthetic accounts and document the new test setup; existing MD5 hashes will not automatically work with `password_verify()`.

## Repaired version (Ralph Lacanlale)

Requires PHP 8.0+ with `pdo_mysql` and `mbstring`, and MySQL/MariaDB. Tested with PHP 8.3.33 and MariaDB 10.4.32 (XAMPP).

- `schema.sql` now stores the two synthetic accounts as bcrypt hashes (`password_hash()`), same lab passwords as above. Re-import it into a fresh database; old MD5 rows will not log in.
- The app no longer falls back to `root` with an empty password. `LAB_DB_USER` and `LAB_DB_PASSWORD` are required. Optional: `LAB_DB_HOST` (default `127.0.0.1`) and `LAB_DB_PORT` (default `3306`).
- Start the server with the router so private files (`.git/`, `schema.sql`, `lib/`, `templates/`) are not served: `php -S 127.0.0.1:8000 router.php`.
- If `pdo_mysql` is commented out in `php.ini`, you can enable it for one run: `php -d extension=pdo_mysql -S 127.0.0.1:8000 router.php`.
- The API now needs a logged-in session. `GET /api.php` lists your own notes (`?user_id=` must be your own ID). `POST /api.php` needs `Content-Type: application/json` and the page's CSRF token (from `<meta name="csrf-token">`) in an `X-CSRF-Token` header.

Files:

- `index.php` - handles web requests (login, register, logout, note create/update/delete)
- `api.php` - JSON API
- `lib/bootstrap.php` - error settings, database connection, session settings, CSRF and escaping helpers
- `lib/notes.php` - all SQL queries for users and notes
- `templates/home.php` - the HTML page
- `assets/style.css` - page styles (light and dark mode)
- `assets/app.js` - asks for confirmation before a note is deleted
- `router.php` - router for the PHP built-in server; only `index.php`, `api.php` and `assets/` are public

The local HTTP setup is for the exercise only. A production deployment requires HTTPS, secure cookies, protected secrets, proper error handling, and additional security controls. Students should document their own fixes and limitations.

The starter includes `api.php`: GET `/api.php?user_id=1` lists notes for an owner, and POST `/api.php` accepts a JSON object with `id`, `title`, and `body` to update a note. These endpoints intentionally lack important protections; use only synthetic data in the isolated lab. Review their behavior and repair them as part of the assignment.
