# Handover Document -- WA Trolley Report

**Project:** WA Trolley Report
**URL:** https://sandgroper.net/trolley/
**Date:** April 2026
**Contact:** info@sandgroper.net


## 1. Project Summary

WA Trolley Report is a community web app for reporting abandoned shopping trolleys across Western Australia. Users submit GPS-tagged reports via a mobile-friendly form, optionally emailing brand owners and local councils. All reports are publicly viewable on an interactive map and sortable list.

The app runs on shared hosting with no database, no frameworks, and no build step. All frontend pages are self-contained single-file HTML with inline CSS and JS. The backend is a single PHP file that handles validation, geocoding, and JSON file storage.


## 2. Current State

**Status:** Live and operational.

**Data volume:** ~100+ reports in `trolley_logs.json` (as of early 2026).

**Known operational issue:** Nominatim (OpenStreetMap) intermittently blocks requests with 403/509 errors. When this happens, submissions still succeed but suburb, state, and LGA fields are `null`. The error log shows this has occurred periodically since April 2025. This does not cause data loss -- it only affects the richness of the geocoded data.

**Outstanding technical debt:**

| Item                          | Severity | Notes                                                    |
|-------------------------------|----------|----------------------------------------------------------|
| CORS set to `*`               | Medium   | Should be restricted to `sandgroper.net`                  |
| No server-side rate limiting   | Medium   | Cookie-based only; trivially bypassed                     |
| No CSRF token                 | Medium   | Form POST has no anti-forgery protection                  |
| JSON flat-file scaling         | Low      | Entire file read/rewritten per submission; OK for now     |
| IP address logging commented out | Info   | Line exists but is commented out in `log_trolley.php`     |
| `lga_emails.json` unused       | Info     | Legacy file; not referenced by any active code            |
| Inline styles in some places   | Info     | A few `style=""` attributes remain in JS-generated HTML   |
| Brand emails hardcoded client-side | Low  | `brandEmailAddresses` in `index.html` JS; requires code edit to update |


## 3. Architecture at a Glance

```
Frontend (browser)              Backend (server)                Storage (filesystem)
------------------              ----------------                --------------------
index.html                      log_trolley.php                 trolley_logs.json
  - Form + GPS capture            - Input validation              - JSON array of reports
  - Brand/condition selection      - Nominatim reverse geocode
  - POST to backend                - LGA lookup (2-step)         suburb_lga_mapping.json
  - Email prompt modal             - File-locked JSON append       - Suburb -> LGA key
  - Rate limiting (cookies)        - Returns enriched logData
                                                                 lga_contact_details.json
view_logs.php                                                      - LGA key -> name + email
  - Fetches trolley_logs.json
  - Sortable paginated table

map_view.html / map_<brand>.html
  - Leaflet.js + MarkerCluster
  - Fetches trolley_logs.json
```


## 4. File Inventory

### Core Application Files

| File               | Type | Size  | Description                                    |
|--------------------|------|-------|------------------------------------------------|
| `index.html`       | HTML | 72 KB | Report form (single-file: HTML + CSS + JS)      |
| `log_trolley.php`  | PHP  | 11 KB | Backend API endpoint                            |
| `view_logs.php`    | PHP  | 28 KB | Log viewer page (server-rendered HTML + client JS) |
| `map_view.html`    | HTML | 18 KB | All-brands interactive map                      |
| `map_coles.html`   | HTML | 33 KB | Coles-only filtered map                         |
| `map_woolworths.html` | HTML | 33 KB | Woolworths-only filtered map                 |
| `map_aldi.html`    | HTML | 33 KB | Aldi-only filtered map                          |
| `map_iga.html`     | HTML | 33 KB | IGA-only filtered map                           |
| `map_kmart.html`   | HTML | 33 KB | Kmart-only filtered map                         |
| `map_bigw.html`    | HTML | 33 KB | Big W-only filtered map                         |
| `map_bunnings.html`| HTML | 33 KB | Bunnings-only filtered map                      |
| `map_spudshed.html`| HTML | 33 KB | Spudshed-only filtered map                      |
| `map_other.html`   | HTML | 33 KB | Other brands filtered map                       |

### Data Files

| File                       | Format | Description                            | Writable |
|----------------------------|--------|----------------------------------------|----------|
| `trolley_logs.json`        | JSON   | All report entries (main data store)    | Yes      |
| `suburb_lga_mapping.json`  | JSON   | Suburb name to LGA key lookup (~359 entries) | No  |
| `lga_contact_details.json` | JSON   | LGA key to full name + email (30 LGAs)  | No      |
| `lga_emails.json`          | JSON   | Legacy suburb/LGA/email mapping (unused) | No     |

### Content Pages

| File           | Description          |
|----------------|----------------------|
| `privacy.html` | Privacy policy        |
| `terms.html`   | Terms and conditions  |
| `faq.html`     | FAQ (9 questions)     |

### Other

| File         | Description                          |
|--------------|--------------------------------------|
| `error_log`  | PHP error log (Nominatim 403s, etc.) |
| `test.html`  | Empty test file (0 bytes)            |


## 5. External Dependencies

| Dependency           | Type      | Loaded From                          | Version | Purpose              |
|----------------------|-----------|--------------------------------------|---------|----------------------|
| Leaflet.js           | CDN (JS)  | unpkg.com/leaflet@1.9.4              | 1.9.4   | Map rendering         |
| Leaflet MarkerCluster| CDN (JS)  | unpkg.com/leaflet.markercluster@1.4.1| 1.4.1   | Marker grouping       |
| OpenStreetMap Tiles   | Tile API  | tile.openstreetmap.org               | N/A     | Map tiles             |
| Nominatim            | REST API  | nominatim.openstreetmap.org          | N/A     | Reverse geocoding     |

No server-side dependencies. No `composer.json`, no `package.json`, no build tooling.


## 6. How to Make Common Changes

### Add a New Brand

1. **index.html:** Add a button in `.brand-section .button-grid` with `data-value="BrandName"`. Add CSS for brand colour and active state. Add a new `.condition-section` with `data-brand="BrandName"`.
2. **index.html (JS):** Add the brand's email address to the `brandEmailAddresses` object.
3. **view_logs.php:** Add brand colour CSS variables. Add entry in `customBrandText` object for the 2-letter abbreviation.
4. **map_view.html:** No changes needed (it displays all brands automatically).
5. **Create `map_brandname.html`:** Duplicate an existing brand map file and update the target brand filter and page title.
6. **map_view.html:** Add a brand link button in `.brand-links`.

### Update a Brand Email Address

Edit the `brandEmailAddresses` object in `index.html` (around line 516-526).

### Add a New LGA

1. Add suburb entries to `suburb_lga_mapping.json` with the appropriate `LGAKey`
2. Add the LGA entry to `lga_contact_details.json` with `LGAFullName` and `Email`

### Update an LGA Email

Edit the relevant entry in `lga_contact_details.json`.

### Change the Submission Rate Limit

In `index.html`, find the `checkSubmissionLimit()` function (around line 549). Change the `count < 20` threshold and/or the `12 * 60 * 60 * 1000` window duration.

### Change Items Per Page in Log Viewer

In `view_logs.php`, change the `itemsPerPage` constant (currently `30`).


## 7. Hosting Details

- **Provider:** Shared hosting (cPanel-based)
- **Server path:** `/home/perthrel/sandgroper.net/trolley/` (visible in error log paths)
- **PHP version:** 7.4+ (check with hosting provider)
- **SSL:** Active (required for Geolocation API)
- **Domain:** sandgroper.net
- **Subdirectory:** /trolley/

All paths in the code are relative, so the app is portable to any subdirectory.


## 8. Backup and Recovery

There is no automated backup system. The critical file to back up is `trolley_logs.json` -- it contains all report data.

**Manual backup:**

```bash
cp trolley_logs.json trolley_logs_backup_$(date +%Y%m%d).json
```

**Recovery:** Replace `trolley_logs.json` with the backup file. Ensure it is valid JSON (array format).


## 9. Monitoring

Check `error_log` periodically for:

- Nominatim 403/509 blocks (indicates rate limiting or IP ban)
- JSON decode errors (indicates corrupted data file)
- File write errors (indicates permission issues)
- LGA mapping misses (suburbs outside the Perth metro coverage)


## 10. Future Considerations

These are documented observations, not commitments:

- **Database migration:** If report volume grows significantly, migrating from JSON flat-file to MySQL/SQLite would improve write performance and enable querying
- **Server-side rate limiting:** Per-IP throttling would prevent abuse that bypasses client-side cookies
- **CSRF protection:** Adding a token to the form would prevent cross-site submission attacks
- **Nominatim caching:** Caching geocode results for recently-seen coordinates would reduce API calls and avoid rate limiting
- **Admin interface:** A simple authenticated page for viewing/deleting reports and managing LGA data
- **Photo upload:** Allowing users to attach a photo of the trolley
- **Regional expansion:** Extending suburb/LGA mapping beyond Perth metro


## 11. Documentation Index

| Document                          | Purpose                                          |
|-----------------------------------|--------------------------------------------------|
| [README.md](README.md)           | Project overview and quick start                  |
| [DOCUMENT.md](DOCUMENT.md)       | Full technical documentation                      |
| [API_REFERENCE.md](API_REFERENCE.md) | Detailed API endpoint reference               |
| [API.md](API.md)                  | Concise API usage guide                           |
| [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) | Step-by-step deployment instructions     |
| [DEPLOYMENT.md](DEPLOYMENT.md)   | Quick deployment checklist                        |
| [DICTIONARY.md](DICTIONARY.md)   | Glossary of terms, values, and abbreviations      |
| [CHANGELOG.md](CHANGELOG.md)     | Version history                                   |
| [JS_FUNCTIONS.md](JS_FUNCTIONS.md) | JavaScript function reference                   |
| [USER_MANUAL.md](USER_MANUAL.md) | End-user guide                                    |
| [HANDOVER.md](HANDOVER.md)       | This document -- developer handover               |
