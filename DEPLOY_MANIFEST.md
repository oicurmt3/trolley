# DEPLOY_MANIFEST.md — machine followable deploy (PR #3 branch)

**Target webroot:** `/home/perthrel/sandgroper.net/trolley/`  
**Source branch:** `cursor/live-sync-and-hardening-4807`  
**Secrets:** Never commit or upload `config.local.php`, `brand_tokens.local.json`, or password hashes.

**Email / portal (decision):** Reporter emails are **client `mailto:` only** — no server `mail()` in this release. Portal links are **not** sent to reporters; the owner copies each brand’s portal URL from **Admin → Brand accounts** and sends it privately. Automatic server-sent email may be a **future** feature.

**Public reference JSON:** `lga_contact_details.json` and `suburb_lga_mapping.json` remain **web-readable** (used for council lookup / admin). They are not in the `.htaccess` deny list.

**`.htaccess`:** `RewriteBase /trolley/` must match this install path (not the filesystem path) so legacy `map_*.html` redirects emit `https://<host>/trolley/map_brand.html?brand=…`.

This manifest is separate from `UPLOAD_CHECKLIST.md` (human-oriented). Follow steps in order.

---

## Phase 0 — Preconditions

- [ ] PR branch checked out locally; deployer has cPanel File Manager and/or SFTP + Terminal access.
- [ ] Live site URL base: `https://sandgroper.net/trolley/`

---

## Phase 1 — NEVER OVERWRITE (leave server files in place)

Do **not** upload, delete, or replace these paths on an existing live site:

```
trolley_data.db
trolley_logs.json
rate_limits.json
error_log
geocache/
backups/
brand_contacts.json
config.local.php
brand_tokens.local.json
```

Also do not upload: `*.apk`, `trolleyfinder.apk`, `data/admin_login_throttle.json`, `brand_tokens.generated.json`.

---

## Phase 2 — BACKUP (before any replace)

1. Zip the full live folder to e.g. `backups/trolley-pre-deploy-YYYYMMDD.zip` **outside** public_html if possible.
2. Download a copy of `trolley_data.db` to local storage.
3. Copy live `admin.php` or note the existing `$adminPasswordHash` / bcrypt line for step 4.

---

## Phase 3 — CREATE `config.local.php` (before replacing `admin.php`)

1. On server, if `config.local.php` does **not** exist:
   - Copy `config.local.example.php` → `config.local.php`
   - Paste the **existing live** bcrypt hash into `admin_password_hash` (from old `admin.php`).
2. If `config.local.php` already exists: **do not overwrite**; verify `admin_password_hash` is set.
3. Optionally set `cors_allowed_origins` (see example file); defaults match sandgroper.net.

---

## Phase 4 — UPLOAD (replace or new), ordered

Upload from repo clone into `/home/perthrel/sandgroper.net/trolley/`.  
Skip every path listed in Phase 1.

### 4.1 — Directory deny files (LiteSpeed; upload even if folders already exist on server)

```
geocache/.htaccess
backups/.htaccess
data/.htaccess
includes/.htaccess
```

### 4.2 — `includes/` PHP (upload before `admin.php` uses it)

```
includes/admin_api.php
includes/admin_security.php
includes/app_config.php
includes/brand_token_rotation.php
includes/data_files.php
includes/schema_migrate.php
```

### 4.3 — Core PHP APIs and data layer

```
db.php
log_trolley.php
api_logs.php
api_notifications.php
api_collections.php
brand_portal.php
admin.php
api_brand_portals.php
migrate_20261123.php
rotate_brand_tokens.php
```

### 4.4 — HTML / CSS / JS (public UI)

```
index.html
styles.css
favicon.svg
view_logs.php
map_view.html
map_brand.html
map_my_location.css
map_my_location.js
privacy.html
terms.html
faq.html
```

### 4.5 — Map redirect stubs

```
map_coles.html
map_woolworths.html
map_aldi.html
map_iga.html
map_kmart.html
map_bigw.html
map_bunnings.html
map_spudshed.html
map_other.html
```

### 4.6 — Reference JSON

```
lga_contact_details.json
suburb_lga_mapping.json
```

These stay **public** (frontend/admin fetch them). Upload only when intentionally replacing mapping data.

Do **not** upload `brand_contacts.json` or `trolley_logs.json` on an existing site.

### 4.7 — Example templates (safe, no secrets)

```
config.local.example.php
brand_contacts.example.json
brand_tokens.local.example.json
trolley_logs.example.json
```

### 4.8 — `.htaccess` **LAST** (among web-accessible deploy files)

Upload:

```
.htaccess
```

**Why last:** New rules deny `data/`, `includes/`, `*.local.*`, and CLI scripts. Uploading `.htaccess` before the rest of the tree is in place can cause **403** on half-deployed paths or block you mid-upload. Upload application files and `includes/` first, then switch rules atomically.

**Exception:** If HTTP→HTTPS returns **500** due to old broken rules, you may upload **only** `.htaccess` early to fix redirects, then continue other files — restore full file set before smoke tests.

---

## Phase 5 — DELETE from server (if present)

Remove obsolete paths (do not delete Phase 1 items):

```
test.html
lga_emails.json
view_log#2
view_log2
trolleyfinder.apk
geocache/setup.php*
geocache/migrate_to_sqlite.php*
```

Delete any **root-level** `*.php*` backup artifacts (e.g. `setup.php.bak`, `migrate_to_sqlite.php.old`) left from manual edits — not part of the current repo.

Delete **only if** they are old full map pages (not current redirect stubs):

- Pre-consolidation `map_*.html` copies that are **not** the repo’s thin redirect stubs

---

## Phase 6 — WRITABLE PATHS (permissions)

From `/home/perthrel/sandgroper.net/trolley/`:

| Path | Permission | Purpose |
|------|------------|---------|
| `data/` | `755` (dir), PHP writable | `data/admin_login_throttle.json` |
| `geocache/` | `755`+ writable | Nominatim cache (existing dir — do not wipe) |
| `backups/` | `755`+ writable | Migration DB copies + owner zips |
| Webroot | DB file writable by PHP user | `trolley_data.db` updates; migration backup `copy()` |

`rate_limits.json` is created in webroot when needed (do not upload; ensure webroot writable).

Create `data/` if missing: `mkdir data && chmod 755 data`

---

## Phase 7 — MIGRATION (idempotent; backs up DB first)

**No SSH on this host:** run via **one-shot cPanel Cron** using the **PHP 8.1 CLI** binary (web PHP may be 8.3).

**Probe CLI PHP (once):**

```bash
/opt/cpanel/ea-php81/root/usr/bin/php -v
```

If that path differs, use cPanel **MultiPHP Manager** / **Select PHP Version** → **PHP 8.1** → note the CLI path shown for “ea-php81”, or run `which php` from a Cron test job.

**One-shot Cron command** (copy exactly; absolute script path — scripts call `chdir(__DIR__)` internally):

```text
/opt/cpanel/ea-php81/root/usr/bin/php /home/perthrel/sandgroper.net/trolley/migrate_20261123.php
```

- Schedule: once, ~2 minutes from now (or “run once” if your cPanel supports it).
- Read Cron **email/output log**: expect lines on **stdout** only (counts/steps), exit **0** on success, non-zero on failure.
- **Remove or disable** the Cron job immediately after a successful run.

**Check only (no DB changes):**

```text
/opt/cpanel/ea-php81/root/usr/bin/php /home/perthrel/sandgroper.net/trolley/migrate_20261123.php --check
```

Exit `0` = already applied, `1` = pending.

**Behavior:**

- If migration `20261123` not yet applied: copies `trolley_data.db` → `backups/trolley_data_pre_20261123_<UTCstamp>.db`, then runs schema steps.
- If already applied: safe no-op message; re-run is idempotent.

HTTP access to `migrate_20261123.php` must remain **403**.

---

## Phase 8 — ROTATE BRAND TOKENS

Pick **one** (after migration):

**A — Admin UI**

1. Open `https://sandgroper.net/trolley/admin.php` and log in.
2. Brand accounts → **Regenerate all brand tokens** → confirm  
   **or** **Apply brand_tokens.local.json** (server file only).
3. Copy each portal URL from admin; email brands privately.

**B — Cron CLI (no SSH)**

Create export directory **outside webroot** (owner-only):

```text
mkdir -p /home/perthrel/trolley_private
chmod 700 /home/perthrel/trolley_private
```

**One-shot Cron command** (generate tokens, update DB, write portal-link map; stdout shows counts + export path only — **never tokens**):

```text
/opt/cpanel/ea-php81/root/usr/bin/php /home/perthrel/sandgroper.net/trolley/rotate_brand_tokens.php --generate --export /home/perthrel/trolley_private/brand_portal_links.json
```

- File mode **0600** (umask applied on write). Refuses to overwrite unless you add `--force`.
- JSON contains `brand_portal_links[]` with `brand_name` + `portal_url` per account (for owner distribution).
- Optional: set `site_public_base` in `config.local.php` if the public URL is not `https://sandgroper.net/trolley`.
- **Remove** the Cron job after success. Download the export via SFTP/File Manager; do not place under `public_html`.

**Predefined tokens from file:**

```text
/opt/cpanel/ea-php81/root/usr/bin/php /home/perthrel/sandgroper.net/trolley/rotate_brand_tokens.php --from-file /home/perthrel/sandgroper.net/trolley/brand_tokens.local.json --export /home/perthrel/trolley_private/brand_portal_links.json
```

Tokens are **not** printed to Cron stdout/stderr or `error_log` by these scripts.

---

## Phase 9 — SMOKE TESTS (read-only; no form submit, no mail)

Base: `https://sandgroper.net/trolley/`

| # | Request | Expected |
|---|---------|----------|
| 1 | `GET index.html` | **200**, HTML contains `WA Trolley Report` |
| 2 | `GET log_trolley.php` (same-origin or with allowed Origin) | **200**, JSON with `csrf_token` key (no portal tokens) |
| 3 | `GET api_logs.php` | **200**, JSON array (may be empty) |
| 4 | `GET api_brand_portals.php` | **403** Forbidden |
| 5 | `GET migrate_20261123.php` | **403** Forbidden (or CLI-only message) |
| 6 | `GET rotate_brand_tokens.php` | **403** Forbidden |
| 7 | `GET config.local.php` | **403** Forbidden |
| 8 | `GET brand_tokens.local.json` (if present) | **403** Forbidden |
| 9 | `GET view_logs.php` | **200**, list UI loads |
| 10 | `GET map_view.html` | **200**, map container present |
| 11 | `GET map_brand.html?brand=Coles` | **200** |
| 12 | `HEAD` or `GET` via **http://** same host → trolley | **301** to HTTPS (not **500**) |
| 13 | `GET admin.php` | **200**, login form (do not brute-force password) |
| 14 | `GET geocache/` or any `geocache/*.json` | **403** Forbidden |

**Do not:** POST to `log_trolley.php`, open `mailto:` from reporter flow, or send test emails.

---

## Phase 10 — ROLLBACK

1. Restore Phase 2 zip over `/home/perthrel/sandgroper.net/trolley/`.
2. Restore `trolley_data.db` from local download **or** from `backups/trolley_data_pre_20261123_*.db` if migration ran.
3. Restore prior `config.local.php` if it was changed.
4. Clear browser cache; re-run smoke test #1 only.

---

## Manifest checksum

- Document version: PR #3 `cursor/live-sync-and-hardening-4807`
- Update this file when deploy file set or commands change.