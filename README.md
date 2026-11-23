# WA Trolley Report

Public web app for reporting abandoned shopping trolleys in Western Australia. Live site: [sandgroper.net/trolley/](https://sandgroper.net/trolley/).

Visitors submit GPS location, brand(s), condition(s), and optional comments from `index.html`. The server enriches entries (suburb/state/LGA via Nominatim) in `log_trolley.php` and appends to `trolley_logs.json`. The list (`view_logs.php`) and map pages read that JSON feed.

## File layout

| Path | Purpose |
|------|---------|
| `index.html` | Report form |
| `log_trolley.php` | POST API — validation, geocoding, append log |
| `includes/` | PHP helpers (blocked from direct HTTP access) |
| `trolley_logs.json` | Public log feed (must remain fetchable) |
| `view_logs.php` | Paginated list |
| `map_view.html` | All-brand map + links to brand maps |
| `map.html?brand=` | Consolidated brand map + table + **Go to my location** |
| `map_*.html` | Legacy URLs (redirect stubs + `.htaccess` rules → `map.html`) |
| `map_my_location.js` / `.css` | Shared Leaflet geolocation control |
| `suburb_lga_mapping.json`, `lga_contact_details.json` | LGA lookup (not public; blocked by `.htaccess`) |
| `data/rate_limit/` | Writable rate-limit buckets (hashed IPs; not public) |

## cPanel deployment

1. Upload/sync the project into the webroot (e.g. `public_html/trolley/`).
2. Ensure **writable** paths: webroot (for `trolley_logs.json` append) and `data/rate_limit/` (create if missing, mode `755`).
3. Keep `includes/` and `data/` blocked (`.htaccess` files included).
4. PHP 8+ with `json`, `curl`/`allow_url_fopen` for Nominatim.
5. Do not commit or deploy `error_log` — it is gitignored and denied by `.htaccess`.

## Configuration

Edit `includes/app_config.php`:

- **`TROLLEY_ALLOWED_ORIGINS`** — CORS for `log_trolley.php` (default `https://sandgroper.net`).
- **`TROLLEY_RATE_LIMIT_MAX_REQUESTS`** / **`TROLLEY_RATE_LIMIT_WINDOW_SECONDS`** — server POST rate limit.
- **`TROLLEY_NOMINATIM_USER_AGENT`** — **Required** by [Nominatim usage policy](https://operations.osmfoundation.org/policies/nominatim/): identify your app and contact email.

## Rate limiting

- **Server:** Per-IP limit using SHA-256 hashes in `data/rate_limit/*.json`, pruned after ~24h. See `includes/rate_limit.php`.
- **Client:** Cookie-based limits in `index.html` (`trolley_submissions_v2`).

## Testing locally

```bash
php -S 127.0.0.1:8080 -t .
# Open http://127.0.0.1:8080/index.html
php -l log_trolley.php
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -r php -l
```

Submission from localhost will fail CORS on production API unless you add a dev origin to `TROLLEY_ALLOWED_ORIGINS`.

Manual checks: XSS in list detail rows and submit summary modal; POST rate limit; CORS from disallowed origin; oversized JSON body; invalid brand / out-of-WA coordinates; `map_coles.html` → `map.html?brand=coles`; **Go to my location** on `map.html`; list/map still load existing `trolley_logs.json`.

## Mobile APK / zip artifacts

Previously, Android packages (`trolley.apk`, `trolleyfinder.apk`, `trolley.zip`) lived in this repo. **This branch removes them from git** and drops the footer **Download** link in `index.html` so nothing points at missing files. There is **no GitHub release** in this change set. To distribute builds again later, publish binaries via a GitHub Release (or another host) and link from the site explicitly.