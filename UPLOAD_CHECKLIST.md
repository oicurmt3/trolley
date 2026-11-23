# cPanel upload checklist — WA Trolley Report (PR #3 branch)

Use this when deploying from the GitHub branch `cursor/live-sync-and-hardening-4807` to the live `trolley/` folder on sandgroper.net. **Do not upload secrets, the SQLite database, or APK files.**

For a **machine-followable** ordered deploy (exact file lists, smoke tests, rollback), use **`DEPLOY_MANIFEST.md`** (target path: `/home/perthrel/sandgroper.net/trolley/`).

---

## 1. Backup first

1. In cPanel **File Manager**, zip the entire live `public_html/trolley/` folder (or your webroot path) to e.g. `backups/trolley-pre-pr3-YYYY-MM-DD.zip` (keep outside the webroot if possible).
2. Download a copy of **`trolley_data.db`** from the server to your PC (separate from git).
3. Note the current admin password hash line from live `admin.php` or existing `config.local.php` if you already use one.

---

## 2. Never upload (keep server copies only)

| Item | Why |
|------|-----|
| `trolley_data.db` | Live data; deploy keeps existing file on server |
| `error_log` | Server-generated |
| `*.apk` / `trolleyfinder.apk` | Binaries not in repo |
| `config.local.php` | Create **on server** only |
| `brand_tokens.local.json` | Create **on server** from example (optional) |
| `brand_tokens.generated.json` | CLI export output |
| **`trolley_logs.json`** | **Live legacy/data file — do not overwrite** (SQLite is primary via `trolley_data.db`) |
| **`brand_contacts.json`** | **Live data — do not overwrite** (edited via admin; copy from example only on fresh install) |
| `data/admin_login_throttle.json` | Created at runtime (`data/` must be writable, chmod 755+) |
| `rate_limits.json` | Created at runtime in webroot (submission rate limits) |
| `geocache/` | Runtime cache |

---

## 3. Upload or replace (from repo)

Upload these from your local clone (match PR branch) into the live `trolley/` directory:

**Core**

- `.htaccess`
- `index.html`, `styles.css`, `favicon.svg`
- `view_logs.php`, `map_view.html`, `map_brand.html`
- `map_my_location.js`, `map_my_location.css`
- `log_trolley.php`, `api_logs.php`, `api_notifications.php`, `api_collections.php`
- `admin.php`, `brand_portal.php`, `db.php`
- `privacy.html`, `terms.html`, `faq.html`

**Legacy map stubs** (keep — they 302 to `map_brand.html`)

- `map_coles.html`, `map_woolworths.html`, `map_aldi.html`, `map_iga.html`, `map_kmart.html`, `map_bigw.html`, `map_bunnings.html`, `map_spudshed.html`, `map_other.html`

**Data / config (public JSON)**

- `brand_contacts.example.json` (template only — **not** `brand_contacts.json` on existing sites)
- `trolley_logs.example.json` (template only — **not** `trolley_logs.json`)
- `lga_contact_details.json`, `suburb_lga_mapping.json` (upload only if you intend to replace council mapping data)

**Maintenance scripts (blocked from web by `.htaccess`)**

- `migrate_20261123.php`
- `rotate_brand_tokens.php`
- `includes/admin_security.php`, `includes/brand_token_rotation.php`, `includes/schema_migrate.php`, `includes/.htaccess`

**Examples (safe to upload)**

- `config.local.example.php`
- `brand_tokens.local.example.json`

**Optional**

- `backup.sh`, `generate_sri.php` (not required for runtime)

**Do not upload** `api_brand_portals.php` unless you intentionally keep the file for reference — it should return **403** and is disabled; prefer uploading the repo version that denies access.

---

## 4. Delete from server (if still present)

Remove obsolete files so old links do not bypass hardening:

| Delete | Reason |
|--------|--------|
| `test.html` | Removed from repo |
| `view_log#2` / `view_log2` / duplicate log viewers | Obsolete duplicates |
| `lga_emails.json` | Removed from repo |
| Old full `map_*.html` **if** you still have pre-consolidation copies that are not the current redirect stubs | Replaced by stubs + `map_brand.html` |
| `trolleyfinder.apk` from webroot | APK not distributed via site |
| Any committed copy of **admin bcrypt** inside `admin.php` | Hash belongs in `config.local.php` only |

**Do not delete** `trolley_data.db`, `error_log`, or working `config.local.php`.

---

## 5. Server-only setup

### 5.1 `config.local.php`

1. Copy `config.local.example.php` → `config.local.php` on the server.
2. Paste your **existing** bcrypt `admin_password_hash` from the old live `admin.php`.
3. Confirm `.htaccess` denies web access to `config.local.php`.

### 5.2 Writable `data/` (admin login throttle)

1. Create `trolley/data/` if missing.
2. Permissions **755** on directory (or whatever your host requires for PHP writes).
3. After first failed admin login, `data/admin_login_throttle.json` may appear — must **not** be web-accessible (`.htaccess` blocks `data/`).

### 5.2b Brand contacts on fresh install only

If the server has **no** `brand_contacts.json` yet, copy `brand_contacts.example.json` → `brand_contacts.json` once.  
On an existing live site, **keep** the current `brand_contacts.json` — do not upload the repo copy.

### 5.2c Brand portal links (reporters)

Reporter `mailto:` emails **never** include portal tokens. After token rotation, email each brand its new portal URL from **Admin → Brand accounts** (Copy link). There is no server-sent brand email in this app.

### 5.3 Database migration (no manual DB editing)

From cPanel **Terminal** (or SSH), in the `trolley/` directory:

```bash
php migrate_20261123.php
```

Expected: messages about tables/indexes and `schema_migrations` version `20261123`. Safe to run twice (idempotent).

Check only:

```bash
php migrate_20261123.php --check
```

Exit `0` = already applied.

---

## 6. Rotate brand portal tokens (8 leaked links)

Choose **one** method:

### A) Admin UI (random tokens)

1. Log in to `admin.php`.
2. **Brand accounts** → **Regenerate all brand tokens** → confirm.
3. Copy each new portal URL and email brands (tokens are not written to `error_log`).

### B) Admin UI (your own token values)

1. On server: copy `brand_tokens.local.example.json` → `brand_tokens.local.json`.
2. Fill 64-character hex tokens per account (or omit tokens to auto-generate where `generate_missing` is true).
3. Admin → **Apply brand_tokens.local.json** → confirm.
4. Reload brand list and copy portal URLs.

### C) CLI

```bash
php rotate_brand_tokens.php --generate
# or
php rotate_brand_tokens.php --from-file brand_tokens.local.json
# optional export for offline copy (gitignored file):
php rotate_brand_tokens.php --generate --export brand_tokens.generated.json
```

Never commit `brand_tokens.local.json` or `brand_tokens.generated.json`.

---

## 7. Verification

| Step | Expected |
|------|----------|
| `https://…/trolley/index.html` | Loads; GPS flow works; submit succeeds |
| `view_logs.php` | Table loads, filters work |
| `map_view.html` / `map_brand.html?brand=Coles` | Map tiles + markers (if DB has data) |
| `api_brand_portals.php` | **403** Forbidden |
| `migrate_20261123.php` in browser | **403** (CLI only) |
| `admin.php` | Login with password; CSRF-protected actions work |
| HTTP → HTTPS | Redirect **301**, not **500** |

---

## 8. Rollback

1. Restore the zip from section 1 over `trolley/`.
2. Restore the backed-up **`trolley_data.db`** if the new code touched schema unexpectedly (migration is additive; rollback DB only if needed).
3. Restore previous `config.local.php` if you changed it.

---

## 9. Owner decisions (not blocking upload)

- Whether `lgaEmail` / `collectedBy` stay visible on public list/map (see handover notes).
- When to retire public `trolley_logs.json` entirely (SQLite is primary).

---

*Repository docs: `DEPLOYMENT.md`, `CHANGELOG.md`, `HANDOVER.md`.*