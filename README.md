# WA Trolley Report

**Find & Report Abandoned Shopping Trolleys in Western Australia**

A community-driven web application that allows members of the public to report abandoned shopping trolleys, view reports on an interactive map, and optionally email brand owners and local councils (LGAs) with pre-filled report details.

Live at: [https://sandgroper.net/trolley/](https://sandgroper.net/trolley/)


## Overview

Abandoned shopping trolleys are a persistent urban problem across Perth and regional WA. This app provides a simple "3-tap" reporting workflow:

1. **Tap** "Get Trolley Location" (captures GPS coordinates and timestamp)
2. **Tap** the trolley brand(s) found (Coles, Woolworths, Aldi, IGA, Kmart, Big W, Bunnings, Spudshed, Other)
3. **Tap** "Submit Now"

After submission, users can optionally email the report to the trolley's brand owner and/or the relevant local council.


## Features

- **GPS-based reporting** -- captures device location and timestamp at the trolley's position
- **Multi-brand selection** -- report multiple brands in a single submission
- **Condition tracking** -- per-brand condition assessment (Good, Needs Cleaning, Rusty, Damaged Wheel, Bent Frame, Broken Handle, Child Seat Issue, Other)
- **Reverse geocoding** -- server-side suburb/state/LGA lookup via Nominatim (OpenStreetMap)
- **Email integration** -- generates pre-filled `mailto:` links for brand owners and LGA councils
- **Public log viewer** -- sortable, paginated table of all reports (`view_logs.php`)
- **Interactive maps** -- Leaflet.js maps with marker clustering for all reports and per-brand filtered views
- **Rate limiting** -- cookie-based client cap plus server hashed-IP limit (`rate_limits.json`)
- **SQLite storage** -- writes via `log_trolley.php`; public reads via `api_logs.php`
- **Mobile-first design** -- dark theme, responsive layout, 44px+ touch targets
- **No login required** -- anonymous reporting with no user accounts


## Tech Stack

| Layer       | Technology                                                    |
|-------------|---------------------------------------------------------------|
| Frontend    | Vanilla HTML5, CSS3, JavaScript (ES6+)                        |
| Backend     | PHP 7.4+                                                      |
| Data Store  | SQLite (`trolley_data.db`); public reads via `api_logs.php`   |
| Geocoding   | Nominatim (OpenStreetMap) reverse geocoding API               |
| Maps        | Leaflet.js 1.9.4 + Leaflet.markercluster 1.4.1               |
| Hosting     | Shared hosting (Apache) at `sandgroper.net/trolley/`          |
| Build       | None -- no frameworks, no build step, no package manager      |


## Project Structure

```
trolley/
|-- index.html                  # Main report form (HTML/CSS/JS single-file)
|-- log_trolley.php             # Backend API -- receives, validates, geocodes, saves reports
|-- view_logs.php               # Public log viewer (sortable table with pagination)
|-- map_view.html               # Interactive map -- all brands (Leaflet + MarkerCluster)
|-- map_coles.html              # Brand-filtered map -- Coles only
|-- map_woolworths.html         # Brand-filtered map -- Woolworths only
|-- map_aldi.html               # Brand-filtered map -- Aldi only
|-- map_iga.html                # Brand-filtered map -- IGA only
|-- map_kmart.html              # Brand-filtered map -- Kmart only
|-- map_bigw.html               # Brand-filtered map -- Big W only
|-- map_bunnings.html           # Brand-filtered map -- Bunnings only
|-- map_spudshed.html           # Brand-filtered map -- Spudshed only
|-- map_other.html              # Brand-filtered map -- Other brands
|-- trolley_logs.json           # Data store -- array of all log entries
|-- suburb_lga_mapping.json     # Lookup: suburb name -> LGA key
|-- lga_contact_details.json    # Lookup: LGA key -> full name + email
|-- lga_emails.json             # Legacy/alternate suburb-to-LGA-email mapping
|-- privacy.html                # Privacy policy page
|-- terms.html                  # Terms and conditions page
|-- faq.html                    # Frequently asked questions page
|-- error_log                   # PHP error log (server-side)
|-- config.local.example.php    # Template for server-only admin hash
```


## Quick Start

1. Upload all files to a PHP-enabled web server (Apache recommended)
2. Create `config.local.php` on the server from the example (see `DEPLOYMENT.md`)
3. Ensure the web directory is writable so SQLite can create/update `trolley_data.db`
4. Navigate to `https://yourdomain.com/trolley/index.html`
5. Grant location permissions when prompted
6. Report your first trolley


## Configuration

Key settings in `log_trolley.php`:

- `$jsonLogFileName` -- output log filename (default: `trolley_logs.json`)
- `$nominatimUserAgent` -- User-Agent string for Nominatim API requests (must comply with their usage policy)
- `$nominatimBaseUrl` -- Nominatim API endpoint

Key settings in `index.html` (JavaScript):

- `brandEmailAddresses` object -- maps brand names to their email addresses
- `maxChars` -- comment character limit (default: 200)
- Cookie names `trolley_submissions_v2` / `trolley_first_submission_v2` -- rate limiting


## External Dependencies

- **Nominatim API** (OpenStreetMap) -- reverse geocoding; subject to usage policy and rate limits
- **Leaflet.js** (CDN: unpkg.com) -- interactive map rendering
- **Leaflet.markercluster** (CDN: unpkg.com) -- marker clustering on maps
- **OpenStreetMap tiles** -- map tile layer


## Browser Support

Tested on modern browsers with Geolocation API support:

- Chrome/Edge (Android, Desktop)
- Safari (iOS, macOS)
- Firefox (Desktop, Android)


## Licence

This project is independently operated. Not affiliated with or endorsed by any retailer or local government authority.

Contact: info@sandgroper.net
