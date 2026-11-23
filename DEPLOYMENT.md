# Deployment -- WA Trolley Report

Quick deployment reference. For full detail see [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md).


## Requirements

- Apache or nginx with PHP 7.4+
- `allow_url_fopen = On` in `php.ini`
- Valid SSL/TLS certificate (HTTPS required for Geolocation API)
- Write permissions on deployment directory for PHP process


## Deploy

```bash
# 1. Upload files to server (excluding config.local.php — create on server only)
scp -r trolley/ user@server:/public_html/trolley/

# 2. BEFORE replacing admin.php on an existing site: copy the bcrypt line from the
#    current server admin.php into a new config.local.php (see config.local.example.php).
ssh user@server "cp /public_html/trolley/config.local.example.php /public_html/trolley/config.local.php"
# Edit config.local.php on the server and paste your real admin_password_hash.

# 3. Writable paths (SQLite is the write target; db is created on first submission)
ssh user@server "chmod 755 /public_html/trolley/ && touch /public_html/trolley/trolley_data.db && chmod 664 /public_html/trolley/trolley_data.db"

# 4. Test read API
curl -s https://yourdomain.com/trolley/api_logs.php | head -c 80
```


## File Checklist

| File                       | Writable | Public | Notes                           |
|----------------------------|----------|--------|---------------------------------|
| `index.html`               | No       | Yes    | Report form                     |
| `log_trolley.php`          | No       | Yes    | API endpoint                    |
| `view_logs.php`            | No       | Yes    | Log viewer                      |
| `map_view.html`            | No       | Yes    | All-brands map                  |
| `map_brand.html`           | No       | Yes    | Consolidated per-brand map      |
| `map_<brand>.html` (x9)   | No       | Yes    | Legacy redirects to map_brand   |
| `trolley_data.db`          | Yes      | No     | SQLite store (blocked by .htaccess) |
| `trolley_logs.json`        | No*      | Yes*   | Legacy snapshot; maps use api_logs.php |
| `config.local.php`         | No       | No     | Admin bcrypt hash — server only |
| `suburb_lga_mapping.json`  | No       | Yes    | Suburb-to-LGA lookup            |
| `lga_contact_details.json` | No       | Yes    | LGA name/email lookup           |
| `privacy.html`             | No       | Yes    | Privacy policy                  |
| `terms.html`               | No       | Yes    | Terms & conditions              |
| `faq.html`                 | No       | Yes    | FAQ                             |
| `error_log`                | Yes      | No*    | PHP errors -- restrict access   |

*Recommended: block direct HTTP access via `.htaccess`.


## Admin secrets (`config.local.php`)

Never commit the real admin bcrypt hash. On the server:

1. Copy `config.local.example.php` → `config.local.php`
2. Paste the hash from your **existing** live `admin.php` before uploading the new `admin.php`
3. Confirm `config.local.php` is gitignored and denied by `.htaccess`

**Brand portal tokens:** After deploying, rotate any brand portal tokens that may have been exposed by the old public `api_brand_portals.php` endpoint. In **Admin → Brand accounts**, use **Regenerate all brand tokens** (confirm dialog) or **New Token** per account, then re-send portal links to each brand.

**Admin CSRF / login throttle:** Deploy `includes/admin_security.php` and ensure the server can write `data/admin_login_throttle.json` (directory `data/` is blocked from HTTP by `.htaccess`). After login, keep the admin tab open or reload if you see CSRF errors on mutations.

**Database migration:** After uploading files, run `php migrate_20261123.php` once from cPanel Terminal (see `UPLOAD_CHECKLIST.md`). No manual editing of `trolley_data.db` is required.

**Token rotation (CLI):** `php rotate_brand_tokens.php --generate` or use `--from-file brand_tokens.local.json`. Web access to migration/rotation scripts and `*.local.json` token files is denied by `.htaccess`.

## Environment Variables / Config

Most configuration is inline in source files. Key values to update for a new deployment:

| Setting                      | File              | Current Value                                                |
|------------------------------|-------------------|--------------------------------------------------------------|
| Nominatim User-Agent         | `log_trolley.php` | `TrolleyReportApp/1.3 (sandgroper.net; admin@sandgroper.net)` |
| CORS origin                  | `log_trolley.php` | `*` (should be restricted)                                    |
| Map default centre           | `map_view.html`   | `[-31.9523, 115.8613]` (Perth)                                |
| Brand email addresses        | `index.html`      | Hardcoded `brandEmailAddresses` object                        |
| Submission rate limit         | `index.html`      | 20 per 12 hours (cookie-based)                                |
| Comment character limit       | `index.html`      | 200 characters                                                |


## Verify

After deployment, test each page:

1. `index.html` -- GPS capture, form submission, email modal
2. `view_logs.php` -- table loads, sorting, pagination, detail expand
3. `map_view.html` -- markers render, clusters work, popups show
4. `map_<brand>.html` -- brand filtering works
5. `log_trolley.php` -- POST returns 200 with `logData`
6. `privacy.html`, `terms.html`, `faq.html` -- pages render


## Rollback

To roll back, replace the deployed files with previous versions. Data continuity depends on `trolley_logs.json` -- back it up before any deployment.

```bash
# Backup current data
scp user@server:/public_html/trolley/trolley_logs.json ./trolley_logs_backup_$(date +%Y%m%d).json

# Deploy previous version
scp -r trolley_v1.2/ user@server:/public_html/trolley/

# Restore data if needed
scp ./trolley_logs_backup_20260405.json user@server:/public_html/trolley/trolley_logs.json
```
