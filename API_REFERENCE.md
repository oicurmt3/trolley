# API Reference -- WA Trolley Report

**Base URL:** `https://sandgroper.net/trolley/`
**Version:** 1.3


## Endpoints

### POST /log_trolley.php

Submit a new trolley report. The server validates input, performs reverse geocoding, looks up LGA details, and appends the enriched entry to `trolley_logs.json`.

#### Request

**Method:** `POST`
**Content-Type:** `application/json`

**Headers:**

| Header         | Value              | Required |
|----------------|--------------------|----------|
| Content-Type   | application/json   | Yes      |

**Body Parameters:**

| Field        | Type     | Required | Description                                              |
|--------------|----------|----------|----------------------------------------------------------|
| `latitude`   | string   | Yes      | GPS latitude (decimal degrees, e.g. `"-32.038275"`)       |
| `longitude`  | string   | Yes      | GPS longitude (decimal degrees, e.g. `"115.909398"`)      |
| `timestamp`  | string   | Yes      | ISO 8601 local timestamp (`YYYY-MM-DDTHH:MM:SS`)          |
| `brands`     | string[] | Yes      | Array of brand names (min 1). Valid values: `"Coles"`, `"Woolworths"`, `"Aldi"`, `"IGA"`, `"Kmart"`, `"BigW"`, `"Bunnings"`, `"Spudshed"`, `"Other"` |
| `conditions`  | object   | No       | Object keyed by brand name, each value is an array of condition strings |
| `comments`   | string   | No       | Free-text comments (max 200 characters, enforced client-side) |

**Example Request Body:**

```json
{
  "latitude": "-32.038275",
  "longitude": "115.909398",
  "timestamp": "2025-04-12T20:10:23",
  "brands": ["Woolworths", "Coles"],
  "conditions": {
    "Woolworths": ["Good Condition"],
    "Coles": ["Rusty Frame", "Damaged Wheel(s)"]
  },
  "comments": "Near the bus stop on Shelley Bridge"
}
```

#### Response

**Content-Type:** `application/json`

**Success (200):**

```json
{
  "status": "success",
  "message": "Log saved successfully.",
  "logData": {
    "latitude": "-32.038275",
    "longitude": "115.909398",
    "timestamp": "2025-04-12T20:10:23",
    "brands": ["Woolworths", "Coles"],
    "conditions": {
      "Woolworths": ["Good Condition"],
      "Coles": ["Rusty Frame", "Damaged Wheel(s)"]
    },
    "comments": "Near the bus stop on Shelley Bridge",
    "suburb": "Riverton",
    "state": "Western Australia",
    "lgaName": "City of Canning",
    "lgaEmail": "customer@canning.wa.gov.au",
    "serverTimestampUTC": "2025-04-12T12:10:30+00:00"
  }
}
```

**Error Responses:**

| HTTP Code | Status    | Condition                                      |
|-----------|-----------|------------------------------------------------|
| 400       | `error`   | No data received / Invalid JSON / Missing required fields |
| 405       | `error`   | Request method is not POST                      |
| 500       | `error`   | Server cannot write to log directory or file    |

**Error Response Example:**

```json
{
  "status": "error",
  "message": "Missing required location data (latitude, longitude, timestamp)."
}
```

#### Server-Side Enrichment

The following fields are added by the server before saving and are included in the `logData` response:

| Field                | Type        | Description                                                 |
|----------------------|-------------|-------------------------------------------------------------|
| `suburb`             | string|null | Suburb name from Nominatim reverse geocoding                 |
| `state`              | string|null | State name from Nominatim reverse geocoding                  |
| `lgaName`            | string|null | LGA full name from `lga_contact_details.json`                |
| `lgaEmail`           | string|null | LGA email address from `lga_contact_details.json`            |
| `serverTimestampUTC` | string      | Server-generated UTC timestamp in ISO 8601 / ATOM format     |

#### CORS

The endpoint returns the following CORS headers:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, OPTIONS
Access-Control-Allow-Headers: Content-Type
```

`OPTIONS` requests are handled for preflight and return `200`.

#### Nominatim Integration

The server calls the Nominatim reverse geocoding API:

```
GET https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat={lat}&lon={lon}&accept-language=en
```

- **User-Agent:** `TrolleyReportApp/1.3 (sandgroper.net; admin@sandgroper.net)`
- **Timeout:** 7 seconds
- Extracts `suburb` (or `village`/`town`/`city_district`/`hamlet` fallbacks) and `state` from the address response

**Note:** Nominatim enforces usage policies. Exceeding rate limits results in 403 or 509 responses. Submissions still succeed, but geocoded fields will be `null`.


---


### GET /trolley_logs.json

Retrieve all trolley report entries. This is a static JSON file served directly by the web server.

**Method:** `GET`
**Content-Type:** `application/json`

**Response:** JSON array of log entry objects (see data schema above).

**Cache Busting:** Client-side code appends `?_=<timestamp>` to prevent browser caching.

**Example:**

```
GET /trolley/trolley_logs.json?_=1712956230000
```

---


### GET /suburb_lga_mapping.json

Retrieve the suburb-to-LGA mapping table.

**Method:** `GET`
**Content-Type:** `application/json`

**Response:** JSON array of mapping objects.

**Schema:**

```json
[
  {
    "Suburb": "Armadale",
    "LGAKey": "City of Armadale"
  }
]
```

---


### GET /lga_contact_details.json

Retrieve LGA contact details (name and email).

**Method:** `GET`
**Content-Type:** `application/json`

**Response:** JSON object keyed by LGA identifier.

**Schema:**

```json
{
  "City of Armadale": {
    "LGAFullName": "City of Armadale",
    "Email": "info@armadale.wa.gov.au"
  }
}
```

---


## Data Schemas

### Log Entry Object

| Field                      | Type        | Nullable | Source   | Description                        |
|----------------------------|-------------|----------|----------|------------------------------------|
| `latitude`                 | string      | No       | Client   | Decimal degrees                    |
| `longitude`                | string      | No       | Client   | Decimal degrees                    |
| `timestamp`                | string      | No       | Client   | ISO 8601 local time                |
| `brands`                   | string[]    | No       | Client   | Array of brand identifiers          |
| `conditions`               | object      | Yes      | Client   | `{BrandName: [condition strings]}` |
| `comments`                 | string      | Yes      | Client   | Free-text (max 200 chars)           |
| `clientSubmissionTimestamp` | string      | Yes      | Client   | Formatted local time string (legacy)|
| `suburb`                   | string|null | Yes      | Server   | From Nominatim geocoding            |
| `state`                    | string|null | Yes      | Server   | From Nominatim geocoding            |
| `lgaName`                  | string|null | Yes      | Server   | From LGA contact details lookup     |
| `lgaEmail`                 | string|null | Yes      | Server   | From LGA contact details lookup     |
| `serverTimestampUTC`       | string      | No       | Server   | ISO 8601 / ATOM format (UTC)        |

### Condition Values

Valid condition strings used in the `conditions` object:

- `Good Condition`
- `Needs Cleaning`
- `Rusty Frame`
- `Damaged Wheel(s)`
- `Bent Frame`
- `Broken Handle`
- `Child Seat Issue`
- `Other Damage`

### Brand Identifiers

Valid brand strings for the `brands` array:

`Coles`, `Woolworths`, `Aldi`, `IGA`, `Kmart`, `BigW`, `Bunnings`, `Spudshed`, `Other`

**Note:** "Big W" is displayed to the user but stored as `BigW` (no space) in the data.


---


## Rate Limiting

Rate limiting is enforced **client-side only** via cookies:

| Cookie Name                     | Purpose                                    |
|---------------------------------|--------------------------------------------|
| `trolley_submissions_v2`        | Counter of submissions in current window    |
| `trolley_first_submission_v2`   | Timestamp (ms) of first submission in window|

- **Window:** 12 hours
- **Limit:** 20 submissions per window
- **Reset:** Cookies expire after 12 hours; counter resets when window expires

**Important:** This is bypassable by clearing cookies. There is no server-side rate limiting.


---


## Brand Email Addresses

Hardcoded in `index.html` JavaScript (`brandEmailAddresses` object):

| Brand       | Email Address(es)                                                    |
|-------------|----------------------------------------------------------------------|
| Coles       | `ccccontactus@coles.com.au`                                          |
| Woolworths  | `info@trolleytracker.com.au`                                         |
| Aldi        | `customer@service.aldi.com.au`                                       |
| IGA         | `hostmaster@metcash.com;privacy@metcash.com`                         |
| Kmart       | `info@wesfarmers.com.au`                                             |
| Bunnings    | `support@bunnings.com.au;info@bunnings.com.au;scamwatch@bunnings.com.au;info@wesfarmers.com.au;customersupport@bunnings.com.au;privacy@bunnings.com.au` |
| BigW        | `info@trolleytracker.com.au`                                         |
| Spudshed    | `CustomerService@spudshed.com.au`                                    |
| Other       | *(none)*                                                             |
