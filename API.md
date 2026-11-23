# API -- WA Trolley Report

**Base URL:** `https://sandgroper.net/trolley/`

This document provides a concise usage guide for integrating with the Trolley Report API.


## Submit a Trolley Report

```
POST /log_trolley.php
Content-Type: application/json
```

### Minimal Request

```json
{
  "latitude": "-32.038275",
  "longitude": "115.909398",
  "timestamp": "2025-04-12T20:10:23",
  "brands": ["Woolworths"]
}
```

### Full Request

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
  "comments": "Near the bus stop"
}
```

### Response (200 OK)

```json
{
  "status": "success",
  "message": "Log saved successfully.",
  "logData": {
    "latitude": "-32.038275",
    "longitude": "115.909398",
    "timestamp": "2025-04-12T20:10:23",
    "brands": ["Woolworths", "Coles"],
    "conditions": { ... },
    "comments": "Near the bus stop",
    "suburb": "Riverton",
    "state": "Western Australia",
    "lgaName": "City of Canning",
    "lgaEmail": "customer@canning.wa.gov.au",
    "serverTimestampUTC": "2025-04-12T12:10:30+00:00"
  }
}
```

### Error Responses

| Code | Cause                                        |
|------|----------------------------------------------|
| 400  | Missing/invalid JSON, missing required fields |
| 405  | Method not POST                               |
| 500  | Server write error                            |

All error responses follow the format:

```json
{
  "status": "error",
  "message": "Description of the error."
}
```


## Read All Reports

```
GET /trolley_logs.json
```

Returns a JSON array of all report objects. Append `?_=<timestamp>` to bust cache.

### Example (cURL)

```bash
curl -s "https://sandgroper.net/trolley/trolley_logs.json?_=$(date +%s)" | python3 -m json.tool
```


## Read LGA Data

### Suburb-to-LGA Mapping

```
GET /suburb_lga_mapping.json
```

Returns: `[{"Suburb": "Armadale", "LGAKey": "City of Armadale"}, ...]`

### LGA Contact Details

```
GET /lga_contact_details.json
```

Returns: `{"City of Armadale": {"LGAFullName": "City of Armadale", "Email": "info@armadale.wa.gov.au"}, ...}`


## Quick Integration Example (JavaScript)

```javascript
'use strict';

async function submitTrolleyReport(lat, lon, brands) {
  const payload = {
    latitude: String(lat),
    longitude: String(lon),
    timestamp: new Date().toISOString().slice(0, 19),
    brands: brands
  };

  try {
    const response = await fetch('https://sandgroper.net/trolley/log_trolley.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await response.json();

    if (response.ok && result.status === 'success') {
      console.log('Report saved:', result.logData);
      return result.logData;
    } else {
      console.error('Submission failed:', result.message);
      return null;
    }
  } catch (error) {
    console.error('Network error:', error);
    return null;
  }
}
```


## Accepted Values

### Brands

`Coles`, `Woolworths`, `Aldi`, `IGA`, `Kmart`, `BigW`, `Bunnings`, `Spudshed`, `Other`

### Conditions

`Good Condition`, `Needs Cleaning`, `Rusty Frame`, `Damaged Wheel(s)`, `Bent Frame`, `Broken Handle`, `Child Seat Issue`, `Other Damage`


## Notes

- **CORS:** The endpoint allows `*` origin (any domain can POST)
- **Rate limiting:** Client-side only (cookie-based, 20/12hrs) -- no server-side enforcement
- **Geocoding:** Server enriches with suburb/state/LGA via Nominatim; may return `null` if rate-limited by Nominatim
- **File storage:** Reports stored as flat JSON array; no database
- **No authentication:** The API is open and requires no API key or token
