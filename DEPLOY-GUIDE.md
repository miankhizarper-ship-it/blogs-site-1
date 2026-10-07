# Production Setup Guide — blog-site.ct.ws (alwaysdata)

## A. Database connection (the part you asked about)

Your code reads credentials from **`config/.env`** (loaded by `config/config.php`,
used by the PDO singleton in `config/database.php`). Nothing else needs changing.

1. In File Manager, open (or create) `blog-site.ct.ws/htdocs/config/.env` and put:

```ini
APP_NAME="My Blog"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://blog-site.ct.ws
APP_TIMEZONE=UTC

DB_HOST=mysql-khizar.alwaysdata.net
DB_PORT=3306
DB_NAME=khizar_blogs-site
DB_USER=khizar_
DB_PASS=YOUR_ALWAYS_DATA_DB_PASSWORD
DB_CHARSET=utf8mb4

SESSION_NAME=blogsid
SESSION_LIFETIME=7200
ADMIN_EMAIL=admin@site.com
MAIL_FROM=noreply@blog-site.ct.ws
```

2. **Where is the DB password?** alwaysdata panel → *Databases* → click your
   database `khizar_blogs-site`. The MySQL password shown there is what goes in
   `DB_PASS` (it is NOT your alwaysdata account password). If lost, click
   *Modify* to set a new one and update `.env` with it.

3. Important rules:
   - No spaces around `=` in `.env`; do not wrap the password in quotes if it
     contains none.
   - `DB_USER` is exactly `khizar_` (with trailing underscore).
   - Connections to alwaysdata MySQL come from the web host only — this works
     automatically when the site runs on your hosting. External tools (phpMyAdmin
     on your PC) need HTTPS relay or IP whitelisting in the panel.

## B. Import the schema + demo data

Option 1 — phpMyAdmin (recommended):
1. alwaysdata panel → *Databases* → *Administration* (phpMyAdmin), log in as `khizar_`.
2. Select database `khizar_blogs-site` → tab **Import**:
   - first `database/schema.sql` → Go
   - then `database/seed.sql` → Go
3. Open `https://blog-site.ct.ws/install` ONCE (it re-hashes the demo passwords
   with real bcrypt so admin login works), then delete/rename
   `database/install.php` on the server.

Option 2 — SSH:
```bash
cd ~/www/blog-site.ct.ws/htdocs   # adjust to your layout
php database/install.php          # imports schema+seed AND hashes passwords
```

## C. Fix the 127.0.0.1:8080 redirect (already patched)

The old bug: `APP_URL=http://127.0.0.1:8080` in `.env` was hard-coded into every
redirect (`/login`, etc.). Two layers of fix are now in the code:
- `app/helpers/functions.php` → `base_url()` ignores localhost/127.0.0.1 APP_URL
  values and auto-detects `https://blog-site.ct.ws` from the live request.
- `.env` should carry `APP_URL=https://blog-site.ct.ws` (as above).

**Action:** re-upload `app/helpers/functions.php` + `config/.env` and clear your
browser cache/cookies for the domain. Test: visit `https://blog-site.ct.ws/admin`
— you must land on `https://blog-site.ct.ws/login`, never `127.0.0.1`.

## D. Web root & .htaccess

- alwaysdata panel → *Websites* → your site → document root =
  `blog-site.ct.ws/htdocs/public` (best), OR keep root at `htdocs` — the provided
  root `.htaccess` forwards everything into `/public` automatically.
- Make sure these folders are writable (File Manager → permissions):
  - `public/uploads` → 755 (or 775)
  - `storage/logs` → 755 (or 775)

## E. First login & security checklist

1. Go to `https://blog-site.ct.ws/login`
   - Email: `admin@site.com`
   - Password: `Admin@12345`
2. You will be forced to change the password on first login — do it.
3. Delete `database/install.php` (and any test files) from the server.
4. Verify `APP_DEBUG=false` — no stack traces should ever show.
5. Check headers: `curl -I https://blog-site.ct.ws/` must show
   `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`.

## F. Cron job (scheduled posts publishing)

alwaysdata panel → *Tasks* → *Cron*:
```
*/10 * * * * php ~/www/blog-site.ct.ws/htdocs/cron/publish.php
```
(adjust path; run every 10 minutes publishes scheduled blogs on time)

## G. Troubleshooting quick table

| Symptom | Cause / Fix |
|---|---|
| "Service temporarily unavailable." | Wrong DB creds in `.env`, or connecting from outside hosting. Re-check DB_PASS; import via phpMyAdmin on the panel. |
| Access denied for user 'khizar_'@'%' | Password mismatch, or you're trying from an IP not allowed. Reset DB password in panel. |
| Redirect to 127.0.0.1 again | Old `functions.php` still on server — re-upload it; remove bad APP_URL from `.env`. |
| 404 on /blogs but / works | mod_rewrite off or missing root .htaccess — enable in panel / re-upload .htaccess. |
| Blank page, no error | Set `APP_DEBUG=true` temporarily, read the error, then set back to false. Also check `storage/logs/php-error.log`. |
| Login says too many attempts | Wait 15 min or clear `login_attempts` table in phpMyAdmin. |
| Images won't upload | `public/uploads` not writable — chmod 775. |

---

## 🔧 Troubleshooting: 502 Bad Gateway on free hosts (ct.ws / InfinityFree)

A 502 from OpenResty means PHP crashed before sending output. Common causes and fixes already patched in this project:

1. **Duplicate function declaration** — fixed: every helper in `app/helpers/functions.php` is wrapped in `if (!function_exists('…'))`, and all includes use `require_once`. If you see `Cannot redeclare e()` again, you uploaded an OLD version of that file — re-upload it.
2. **Missing mbstring extension** — fixed: `app/helpers/mb_compat.php` provides UTF-8-safe fallbacks for `mb_strlen`, `mb_substr`, `mb_strtolower`, `mb_strtoupper`, `mb_trim`, `mb_internal_encoding`, `mb_convert_encoding`. It is loaded first by `config/config.php`. Re-upload BOTH files: `app/helpers/mb_compat.php` (new!) and `config/config.php`.
3. **Still 502?** Set `APP_DEBUG=true` in `config/.env` and reload `/login` — the raw PHP error will now be printed on screen instead of a blank gateway error. Fix what it says, then set `APP_DEBUG=false` again.
4. Check the host's **error log** (control panel → PHP configuration → error logs, or `storage/logs/`).
5. Free hosts sometimes kill requests >30 s; make sure DB host `mysql-khizar.alwaysdata.net` is reachable from the web host (alwaysdata allows external connections; verify the MySQL user's host permission is `%` not just local).

### Files changed in this hotfix (upload these 4):
```
app/helpers/functions.php     (all functions wrapped in function_exists guards)
app/helpers/mb_compat.php     (NEW – mbstring polyfills)
config/config.php             (loads mb_compat.php first)
public/index.php              (require_once for config + autoload)
```
