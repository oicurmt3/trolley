# WA Trolley Report -- Technical Documentation

**Version:** 1.3
**Last Updated:** April 2026
**Author:** Sandgroper.net


## 1. Introduction

WA Trolley Report is a community-driven web application for reporting abandoned shopping trolleys across Western Australia. The application follows a single-file architecture pattern with vanilla HTML/CSS/JS on the frontend and PHP on the backend, with JSON flat-file storage.

This document provides a comprehensive technical overview of the application's architecture, data flow, components, and design decisions.


## 2. Architecture Overview

### 2.1 Design Principles

- **Single-file architecture** -- each page is a self-contained HTML file with inline CSS and JS (no build step, no bundler, no framework)
- **Mobile-first** -- dark theme, responsive layout, large touch targets (44px+)
- **Privacy-conscious** -- no user accounts, no tracking scripts, no third-party analytics
- **Progressive enhancement** -- core reporting works on any modern browser with GPS
- **Zero dependencies (server)** -- PHP with no Composer packages; only stdlib functions used

### 2.2 System Diagram

```
[User's Browser]
      |
      |  1. GPS capture (Geolocation API)
      |  2. Brand/condition selection
      |  3. POST JSON to log_trolley.php
      |
      v
[log_trolley.php]
      |
      |-- Validates input (lat, lon, timestamp, brands)
      |-- Reverse geocode via Nominatim API --> suburb, state
      |-- Lookup suburb --> LGA key (suburb_lga_mapping.json)
      |-- Lookup LGA key --> LGA name + email (lga_contact_details.json)
      |-- Append to trolley_logs.json (file-locked write)
      |-- Return enriched log entry as JSON response
      |
      v
[Browser receives response]
      |
      |-- Stores enriched data in localStorage
      |-- Page reloads
      |-- On reload: reads localStorage, displays email prompt modal
      |-- User can email brand owners and/or LGA via mailto: links
      |-- localStorage cleared on modal close
```

### 2.3 Component Map

| Component                  | File                       | Role                                              |
|----------------------------|----------------------------|----------------------------------------------------|
| Report Form                | `index.html`               | Main UI -- GPS capture, brand/condition selection, submission |
| Backend API                | `log_trolley.php`          | Validation, geocoding, LGA lookup, data persistence |
| Log Viewer                 | `view_logs.php`            | Sortable, paginated table of all reports            |
| All-brands Map             | `map_view.html`            | Leaflet map with clustered markers (all brands)     |
| Brand Maps (x9)            | `map_<brand>.html`         | Leaflet map filtered to a single brand              |
| Data Store                 | `trolley_logs.json`        | JSON array of all report entries                    |
| Suburb-to-LGA Mapping      | `suburb_lga_mapping.json`  | Array of `{Suburb, LGAKey}` objects                 |
| LGA Contact Details         | `lga_contact_details.json` | Object keyed by LGA name with `{LGAFullName, Email}` |
| Legacy LGA Emails           | `lga_emails.json`          | Alternate suburb-to-LGA-email mapping (not actively used by log_trolley.php) |
| Privacy Policy             | `privacy.html`             | Static content page                                 |
| Terms & Conditions         | `terms.html`               | Static content page                                 |
| FAQ                        | `faq.html`                 | Static content page                                 |


## 3. Data Flow -- Report Submission

### 3.1 Client Side (index.html)

1. User taps "Get Trolley Location" -- triggers `navigator.geolocation.getCurrentPosition()` with `enableHighAccuracy: true`, `timeout: 10000ms`, `maximumAge: 0`
2. On success, latitude and longitude are stored in hidden inputs; timestamp is formatted as ISO 8601 (`YYYY-MM-DDTHH:MM:SS`)
3. User selects brand(s) via multi-select button grid; each selection toggles `.active` class and reveals the brand's condition section
4. User optionally selects conditions per brand and adds comments (max 200 chars)
5. On form submit:
   - Rate limit check via cookies (`trolley_submissions_v2`, `trolley_first_submission_v2`) -- max 20 submissions per 12-hour window
   - Client-side validation: location captured, at least one brand selected
   - `FormData` is parsed into a structured JSON object with nested `conditions` object
   - POST request to `log_trolley.php` with `Content-Type: application/json`

### 3.2 Server Side (log_trolley.php)

1. CORS headers set (permissive `*` origin)
2. Request method validated (POST only; OPTIONS handled for preflight)
3. JSON payload decoded and validated (latitude, longitude, timestamp, brands required)
4. Latitude/longitude validated with `FILTER_VALIDATE_FLOAT`
5. **Reverse Geocoding** -- Nominatim API called with coordinates; extracts `suburb`, `state` from address response
6. **LGA Lookup** (two-step):
   - Step 1: Search `suburb_lga_mapping.json` for matching suburb (case-insensitive) to get `LGAKey`
   - Step 2: Look up `LGAKey` in `lga_contact_details.json` to get `LGAFullName` and `Email`
7. Final log entry constructed by merging client data with server-enriched fields (`suburb`, `state`, `lgaName`, `lgaEmail`, `serverTimestampUTC`)
8. Entry appended to `trolley_logs.json` using file locking (`LOCK_EX`) with truncate-and-rewrite strategy
9. JSON response returned with `{status, message, logData}` -- `logData` contains the full enriched entry

### 3.3 Email Prompt Flow

1. On successful submission response, `logData` is stored in `localStorage` as `pendingTrolleyEmailSubmission`
2. Page reloads after 1.5s delay
3. On `DOMContentLoaded`, the app checks for `pendingTrolleyEmailSubmission` in localStorage
4. If found, the email prompt modal (`displayTrolleyEmailPrompt()`) renders:
   - Summary of report details
   - Buttons for each brand with a known email address
   - Button for the LGA if suburb/LGA data was resolved
5. Clicking a brand/LGA email button:
   - Generates email subject and body via `generateTrolleyEmailBody()`
   - Opens `mailto:` link with pre-filled subject/body
   - Shows manual copy UI fallback (textarea + copy buttons)
6. On modal close/dismiss, `pendingTrolleyEmailSubmission` is removed from localStorage


## 4. Data Storage

### 4.1 trolley_logs.json

A JSON array where each element is a report object:

```json
{
  "latitude": "-32.038275",
  "longitude": "115.909398",
  "timestamp": "2025-04-12T20:10:23",
  "brands": ["Woolworths"],
  "conditions": {
    "Woolworths": ["Good Condition"]
  },
  "comments": "Near the bus stop",
  "suburb": "Riverton",
  "state": "Western Australia",
  "lgaName": "City of Canning",
  "lgaEmail": "customer@canning.wa.gov.au",
  "serverTimestampUTC": "2025-04-12T12:10:30+00:00"
}
```

### 4.2 suburb_lga_mapping.json

Array of objects mapping suburb names to LGA keys:

```json
[
  { "Suburb": "Armadale", "LGAKey": "City of Armadale" },
  { "Suburb": "Bassendean", "LGAKey": "Town of Bassendean" }
]
```

Currently covers ~359 suburbs across Perth metropolitan LGAs.

### 4.3 lga_contact_details.json

Object keyed by LGA name, containing full name and email:

```json
{
  "City of Armadale": {
    "LGAFullName": "City of Armadale",
    "Email": "info@armadale.wa.gov.au"
  }
}
```

Currently covers 30 Perth metropolitan LGAs.

### 4.4 lga_emails.json

Legacy mapping file -- array of `{Suburb, LGA, Email}` objects. Not actively used by `log_trolley.php` (which uses the two-step lookup via `suburb_lga_mapping.json` + `lga_contact_details.json`). Retained for reference/migration.


## 5. Map System

### 5.1 All-Brands Map (map_view.html)

- Leaflet.js 1.9.4 with OpenStreetMap tiles
- MarkerClusterGroup for performance with large datasets
- Fetches `trolley_logs.json` and creates markers for all valid entries
- Popup content includes brand(s), date, location, conditions, comments
- Auto-fits bounds to marker cluster extent
- Links to per-brand map pages via brand-coloured buttons

### 5.2 Brand-Specific Maps (map_<brand>.html)

- Same Leaflet/MarkerCluster stack
- Filters `trolley_logs.json` to entries containing the target brand
- Uses brand-specific marker colours
- Includes clickable popup with detail expansion
- Each map page includes navigation links to the report form, list view, and other brand maps


## 6. Security Considerations

- **Input validation** -- server-side validation of latitude (float), longitude (float), timestamp (required), brands (array, non-empty)
- **XSS protection** -- HTML entities escaped in `view_logs.php` and map popups via manual `<`/`>` replacement and `escapeHtml()` function
- **CORS** -- currently set to `*` (permissive); should be restricted to `sandgroper.net` in production
- **File locking** -- `LOCK_EX` used during JSON file writes to prevent race conditions
- **Rate limiting** -- client-side cookie-based (20 submissions per 12 hours); bypassable but provides basic abuse prevention
- **No authentication** -- by design; anonymous reporting
- **IP logging** -- commented out in current codebase (`$finalLogEntry['ipAddress']` line)
- **Nominatim User-Agent** -- custom User-Agent with contact email as required by Nominatim usage policy


## 7. Known Issues and Limitations

1. **Nominatim rate limiting** -- error log shows repeated 403/509 responses when Nominatim blocks requests for usage policy violations; submissions succeed but suburb/state/LGA fields are null
2. **JSON flat-file scaling** -- `trolley_logs.json` grows unbounded; entire file is read/rewritten on each submission; will degrade at thousands of entries
3. **No server-side rate limiting** -- cookie-based rate limiting is client-side only and easily bypassed
4. **No CSRF protection** -- form submissions have no token verification
5. **Permissive CORS** -- `Access-Control-Allow-Origin: *` allows any origin to POST
6. **No data backup** -- single JSON file with no automated backup or rotation
7. **LGA coverage** -- suburb-to-LGA mapping only covers Perth metropolitan area; interstate submissions (e.g., Wallsend, NSW) produce "No LGAKey found" warnings
8. **Brand email addresses hardcoded** -- stored in client-side JavaScript; requires code changes to update


## 8. Accessibility

- Semantic HTML5 form elements with `<label>` tags
- Keyboard-navigable button grid
- Minimum 44x44px touch targets on mobile
- Dark theme with sufficient contrast ratios (light text on dark backgrounds)
- Error states indicated by both colour and border changes
- `lang="en"` attribute on all HTML pages
- `alt` text not applicable (no images in current UI)


## 9. Browser APIs Used

- **Geolocation API** (`navigator.geolocation.getCurrentPosition`) -- GPS capture
- **Fetch API** -- AJAX requests to backend and JSON data files
- **Clipboard API** (`navigator.clipboard.writeText`) -- copy email details to clipboard
- **localStorage** -- temporary storage for email prompt data between page reload
- **Cookies** (`document.cookie`) -- submission rate limiting
