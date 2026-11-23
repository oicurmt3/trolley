# Changelog -- WA Trolley Report

All notable changes to this project are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).


## [Unreleased] - 2026-11 (GitHub sync / hardening)

### Security
- `.htaccess`: deny SQLite WAL/SHM/journal sidecars (`trolley_data.db-wal`, `-shm`, etc.) and backup extensions (`*.bak`, `*.sql`, `*.old`, `*~`, `*.swp`); `RewriteRule` + `FilesMatch` for LiteSpeed

### Fixed / Ops
- PHP CI matrix 8.1 + 8.3; cron-safe CLI (`chdir(__DIR__)`); token export `--export` with 0600, no token output on stdout
- Deploy manifest: cPanel ea-php81 one-shot cron commands

### Fixed (QA round 1)
- Map legacy redirects: `RewriteBase /trolley/`, absolute `/trolley/map_brand.html` targets
- `log_trolley.php`: WA bounds 400, invalid conditions/comments 400 (no silent strip), 25/hour rate-limit message, 10 KB body cap
- Mailto recipients use commas (RFC 6068); deploy docs clarify no server email and public LGA JSON

### Added
- `migrate_20261123.php` (CLI, idempotent schema + `schema_migrations` ledger)
- `rotate_brand_tokens.php` CLI and `brand_tokens.local.example.json` for controlled token rotation
- Admin CSRF (`X-Admin-CSRF` / login form), login throttling (`data/admin_login_throttle.json`), **Regenerate all brand tokens** / **Apply brand_tokens.local.json**
- `UPLOAD_CHECKLIST.md` — cPanel upload guide for owners
- `includes/admin_security.php`, shared `favicon.svg`, skip links and landmark `<main>` on key pages
- Git sync from live site snapshot (2026-10-20): SQLite storage, admin panel, brand portals, `api_logs.php`, consolidated `map_brand.html`
- `config.local.example.php` for admin bcrypt hash (real hash only on server in `config.local.php`)
- `map_my_location.js` / `.css` — “Go to my location” on `map_view.html` and `map_brand.html`
- Legacy `map_*.html` redirect stubs and `.htaccess` rules → `map_brand.html`
- GitHub Actions PHP syntax lint workflow

### Changed
- `view_logs.php`: icon-only From/To/Clear date filter row; selected range and counts in en-AU hint text below the toolbar
- `view_logs.php`: date filter a11y (focus return to icon buttons, `aria-hidden` inputs, min/max sync); `html { scrollbar-gutter: stable }`
- Admin state-changing actions require **POST** + CSRF (no GET mutations); CORS origins from `config.local.php`
- HSTS `max-age` only (no `includeSubDomains` / preload); block `*.local.json` / `*.local.php` in `.htaccess`
- `trolley_logs.json` / `brand_contacts.json` untracked — use `*.example.json`; reporter APIs strip non-email fields from brand contacts
- `.htaccess`: proxy-safe HTTPS redirect, block `data/` and `includes/` via `RewriteRule` (fixes common 500 from `DirectoryMatch`)
- Accessibility/contrast QA: location validation feedback, brand filter labels, OSM `https` attribution, WCAG-minded IGA/disclaimer colours
- XSS fixes: `view_logs.php` detail rows and `index.html` submit summary modal use safe DOM/`textContent`
- Server rate limit: 25 submissions per hour per hashed IP (existing `rate_limits.json` mechanism)
- Generic 500 responses from `log_trolley.php` (no log payload on errors)
- Keyed suburb→LGA lookup via `getSuburbLgaKeyByName()` in `db.php`
- `privacy.html` aligned with SQLite + hashed-IP rate limiting
- `.htaccess`: HSTS, unset `X-Powered-By`, block `*.md`, block `api_brand_portals.php` and `config.local.php`
- Fixed `brand_contacts.json` IGA/Bunnings mailto lists; removed APK footer link
- Removed unused `lga_emails.json` and empty `test.html`

### Security
- **Disabled public `api_brand_portals.php`** — portal tokens no longer sent to browsers or reporter email bodies (share links via admin only)

### Removed
- APK/zip binaries from repository (owner keeps local copies)


## [1.3] - 2025-05

### Added
- **LGA email integration** -- two-step suburb-to-LGA lookup (via `suburb_lga_mapping.json` and `lga_contact_details.json`) enables emailing local councils after submission
- **LGA contact details** for 30 Perth metropolitan councils with official email addresses
- **Suburb-to-LGA mapping** covering ~359 suburbs across Perth metropolitan area
- **Email prompt modal** -- after successful submission, presents buttons to email brand owners and/or the relevant LGA
- **Manual copy fallback** -- if `mailto:` link fails to open an email client, textarea with copy-to-clipboard buttons is shown
- **Bunnings** added as a reportable brand with dedicated brand colours and map page
- **Spudshed** added as a reportable brand with dedicated brand colours and map page
- **Per-brand condition tracking** -- each selected brand gets its own condition selector (Good, Needs Cleaning, Rusty, Damaged Wheel, Bent Frame, Broken Handle, Child Seat Issue, Other)
- **Brand-specific map pages** -- `map_coles.html`, `map_woolworths.html`, `map_aldi.html`, `map_iga.html`, `map_kmart.html`, `map_bigw.html`, `map_bunnings.html`, `map_spudshed.html`, `map_other.html`
- **Marker clustering** on all map pages using Leaflet.markercluster plugin
- **Sortable columns** in log viewer (brand, timestamp) with ascending/descending toggle
- **Pagination** in log viewer (30 entries per page)
- **Detail row expansion** -- clicking a brand toggle in the log viewer reveals condition and comment details
- **Server-side UTC timestamp** (`serverTimestampUTC`) added to each log entry for consistent ordering
- **Nominatim User-Agent** updated to `TrolleyReportApp/1.3` with contact email per usage policy
- **FAQ page** (`faq.html`) with 9 common questions and answers
- **Privacy Policy** (`privacy.html`) covering data collection, storage, and contact information
- **Terms and Conditions** (`terms.html`) covering usage, disclaimers, and liability
- **APK download link** in footer for Android app variant

### Changed
- Rate limit increased from 10 to 20 submissions per 12-hour window
- Cookie names updated to `trolley_submissions_v2` and `trolley_first_submission_v2`
- Email body generation includes Google Maps link instead of raw coordinates
- Email body includes LGA name (when emailing brand) and all brand conditions (when emailing LGA)
- Log viewer uses abbreviated brand codes (CO, WO, AL, IG, KM, BI, BU, SP, OT) for compact display
- Map popup content includes suburb/state and conditions
- Error logging improved with more descriptive messages and context

### Fixed
- File locking (`LOCK_EX`) on JSON write to prevent race conditions with concurrent submissions
- Timestamp parsing handles both ISO 8601 and `DD/MM/YYYY, H:MM AM/PM` formats for backward compatibility
- Form validation highlights both the input group border and its label in red on error
- Condition sections properly cleared when a brand is deselected

### Security
- HTML output escaping in log viewer using `<`/`>` entity replacement
- `escapeHtml()` function used in map popups to prevent XSS via log data
- Comments textarea enforces 200-character limit (client-side)

### Known Issues
- Nominatim API intermittently returns 403 (access blocked) or 509 (bandwidth exceeded) -- submissions succeed but suburb/state/LGA fields are `null`
- CORS set to `*` (should be restricted to production domain)
- No server-side rate limiting
- No CSRF token on form submissions
- `lga_emails.json` (legacy file) is unused by `log_trolley.php` but remains in the project


## [1.2] - 2025-04

### Added
- Multi-brand selection (users can report multiple brands per sighting)
- Reverse geocoding via Nominatim API to determine suburb and state
- Server-enriched log data returned in API response
- Comments field with character counter (200 char limit)
- Error state styling (red borders and labels) for form validation

### Changed
- Submission flow refactored to use `fetch()` with JSON payload instead of `FormData`
- Log data structure updated to include nested `conditions` object


## [1.1] - 2025-04

### Added
- Interactive map view (`map_view.html`) using Leaflet.js with OpenStreetMap tiles
- Cookie-based rate limiting (10 submissions per 12 hours)
- Dark theme with CSS custom properties

### Changed
- Layout optimised for mobile devices with responsive breakpoints


## [1.0] - 2025-04

### Added
- Initial release
- Single-brand trolley reporting form with GPS location capture
- JSON flat-file storage (`trolley_logs.json`)
- Basic log viewer (`view_logs.php`) with tabular display
- Brands supported: Coles, Woolworths, Aldi, IGA, Kmart, Big W, Other
