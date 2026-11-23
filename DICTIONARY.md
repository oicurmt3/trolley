# Dictionary -- WA Trolley Report

Glossary of terms, abbreviations, data values, and domain-specific vocabulary used throughout the codebase and documentation.


## Domain Terms

| Term                | Definition                                                                                              |
|---------------------|---------------------------------------------------------------------------------------------------------|
| Abandoned Trolley   | A shopping trolley found away from the premises of its owning retailer                                   |
| Brand               | The retailer who owns the trolley (e.g., Coles, Woolworths, Aldi)                                       |
| Condition           | The physical state of the trolley at the time of reporting                                                |
| LGA                 | Local Government Area -- the municipal council responsible for a geographic area in Australia             |
| LGAKey              | The identifier used to look up an LGA in `lga_contact_details.json` (e.g., "City of Canning")            |
| LGAFullName         | The display name of the LGA (typically the same as LGAKey)                                                |
| Report / Log        | A single trolley sighting submission, containing location, brand(s), condition(s), and comments           |
| Reverse Geocoding   | The process of converting GPS coordinates (lat/lon) into a human-readable address (suburb, state)         |
| Trolley Spotter     | An anonymous user who submits a trolley report                                                            |
| Nominatim           | OpenStreetMap's free geocoding service used for reverse geocoding                                          |
| MarkerCluster       | A Leaflet.js plugin that groups nearby map markers into clusters for performance and readability            |


## Brand Identifiers

Used in the `brands` array and as keys in the `conditions` object.

| Identifier   | Display Name | Brand Colour (Hex) | Abbreviation (List View) |
|--------------|--------------|---------------------|--------------------------|
| `Coles`      | Coles        | `#ed1c22` (Red)     | CO                       |
| `Woolworths` | Woolworths   | `#54b848` (Green)   | WO                       |
| `Aldi`       | Aldi         | `#00005F` (Navy)    | AL                       |
| `IGA`        | IGA          | `#D8C69D` (Tan)     | IG                       |
| `Kmart`      | Kmart        | `#E92A28` (Red)     | KM                       |
| `BigW`       | Big W        | `#0046C8` (Blue)    | BI                       |
| `Bunnings`   | Bunnings     | `#0d5257` (Teal)    | BU                       |
| `Spudshed`   | Spudshed     | `#a6cf4d` (Lime)    | SP                       |
| `Other`      | Other        | `#444` (Grey)       | OT                       |

**Note:** `BigW` is stored without a space but displayed as "Big W" in the UI.


## Condition Values

Used in the `conditions[BrandName]` arrays.

| Value               | Description                                           |
|---------------------|-------------------------------------------------------|
| `Good Condition`    | Trolley is structurally intact and usable              |
| `Needs Cleaning`    | Dirty but functionally sound                           |
| `Rusty Frame`       | Visible rust on the frame                              |
| `Damaged Wheel(s)`  | One or more wheels are broken, stuck, or missing        |
| `Bent Frame`        | Frame is deformed or structurally compromised           |
| `Broken Handle`     | Push handle is broken or detached                       |
| `Child Seat Issue`  | Child seat is damaged, missing, or stuck                |
| `Other Damage`      | Damage not covered by the above categories              |


## Australian State Abbreviations

Used in `view_logs.php` for compact location display.

| Full Name                      | Abbreviation |
|--------------------------------|--------------|
| Western Australia              | WA           |
| South Australia                | SA           |
| New South Wales                | NSW          |
| Queensland                     | QLD          |
| Victoria                       | VIC          |
| Tasmania                       | TAS          |
| Northern Territory             | NT           |
| Australian Capital Territory   | ACT          |
| Jervis Bay Territory           | ACT          |


## CSS Variables

Custom properties defined in `:root` across the application.

### Core Theme (index.html)

| Variable                          | Value        | Usage                        |
|-----------------------------------|--------------|------------------------------|
| `--bg-color`                      | `#1a1a1a`    | Page background               |
| `--text-color`                    | `#e0e0e0`    | Default text colour            |
| `--card-bg`                       | `#2c2c2c`    | Container/card background      |
| `--border-color`                  | `#444`       | Borders and dividers           |
| `--input-bg`                      | `#333`       | Form input background          |
| `--input-text`                    | `#e0e0e0`    | Form input text colour         |
| `--button-bg`                     | `#007bff`    | Primary button background      |
| `--button-text`                   | `#ffffff`    | Button text colour             |
| `--button-hover-bg`               | `#0056b3`    | Primary button hover           |
| `--button-secondary-bg`           | `#555`       | Secondary button background    |
| `--button-thirdly-bg`             | `#097969`    | Tertiary button background     |
| `--rating-active-bg`              | `#bf9000`    | Active/selected state (gold)   |
| `--error-color`                   | `#dc3545`    | Error text and borders         |
| `--error-bg`                      | `#401010`    | Error message background       |
| `--modal-bg`                      | `rgba(0,0,0,0.7)` | Modal overlay             |
| `--modal-button-action-bg`        | `#28a745`    | Modal action button (green)    |
| `--modal-button-lga-bg`           | `#17a2b8`    | Modal LGA button (info blue)   |


## Cookie Names

| Cookie Name                     | Type      | Expiry  | Purpose                                      |
|---------------------------------|-----------|---------|----------------------------------------------|
| `trolley_submissions_v2`        | Integer   | 12 hrs  | Count of submissions in current rate window    |
| `trolley_first_submission_v2`   | Timestamp | 12 hrs  | Start time of current rate window (ms)         |


## localStorage Keys

| Key                              | Type   | Lifecycle                    | Purpose                                    |
|----------------------------------|--------|------------------------------|--------------------------------------------|
| `pendingTrolleyEmailSubmission`  | JSON   | Set on submit, cleared on modal close | Stores enriched log data for email prompt |


## HTTP Status Codes (API)

| Code | Meaning                                              |
|------|------------------------------------------------------|
| 200  | Success -- report saved and enriched data returned    |
| 400  | Bad request -- missing/invalid input                  |
| 405  | Method not allowed -- only POST accepted              |
| 500  | Server error -- file write failure                    |


## File Formats

| Extension | Format                    | Usage in Project                                |
|-----------|---------------------------|-------------------------------------------------|
| `.html`   | HTML5 with inline CSS/JS  | Frontend pages (report form, maps, info pages)  |
| `.php`    | PHP 7.4+                  | Backend API and server-rendered pages            |
| `.json`   | JSON (UTF-8)              | Data storage and lookup tables                   |


## External Services

| Service              | URL                                              | Purpose                    | Rate Limit             |
|----------------------|--------------------------------------------------|----------------------------|------------------------|
| Nominatim            | `https://nominatim.openstreetmap.org/reverse`     | Reverse geocoding          | 1 req/sec              |
| OpenStreetMap Tiles  | `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png` | Map tile rendering      | Fair use               |
| Leaflet.js           | `https://unpkg.com/leaflet@1.9.4/`                | Map library (CDN)          | N/A                    |
| MarkerCluster        | `https://unpkg.com/leaflet.markercluster@1.4.1/`  | Marker clustering (CDN)    | N/A                    |


## Acronyms

| Acronym | Expansion                          |
|---------|------------------------------------|
| API     | Application Programming Interface  |
| ATOM    | Atom Syndication Format (date)     |
| CDN     | Content Delivery Network           |
| CORS    | Cross-Origin Resource Sharing      |
| CSRF    | Cross-Site Request Forgery         |
| GPS     | Global Positioning System          |
| ISO     | International Organization for Standardization |
| JSON    | JavaScript Object Notation         |
| LGA     | Local Government Area              |
| OSM     | OpenStreetMap                      |
| PHP     | PHP: Hypertext Preprocessor        |
| UTC     | Coordinated Universal Time         |
| WA      | Western Australia                  |
| WCAG    | Web Content Accessibility Guidelines |
| XSS     | Cross-Site Scripting               |
