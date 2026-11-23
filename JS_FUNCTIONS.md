# JavaScript Functions -- WA Trolley Report

Complete reference of all JavaScript functions across the application, organised by file.


## index.html

All functions are defined within a single `DOMContentLoaded` event listener.

### Rate Limiting

#### `getCookie(name)`
Retrieves a cookie value by name.

| Param  | Type   | Description      |
|--------|--------|------------------|
| `name` | string | Cookie name      |

**Returns:** `string|null` -- cookie value or `null` if not found.

**Implementation:** Splits `document.cookie` string and searches for matching name prefix.

---

#### `setCookie(name, value, hours)`
Sets a cookie with an expiration time.

| Param   | Type   | Description                    |
|---------|--------|--------------------------------|
| `name`  | string | Cookie name                    |
| `value` | string | Cookie value                   |
| `hours` | number | Hours until expiration          |

**Returns:** `void`

**Attributes set:** `expires`, `path=/`, `SameSite=Lax`.

---

#### `checkSubmissionLimit()`
Checks whether the user has exceeded the submission rate limit (20 per 12 hours). If within limits, increments the counter.

**Returns:** `boolean` -- `true` if submission is allowed, `false` if limit exceeded.

**Cookies used:** `trolley_submissions_v2`, `trolley_first_submission_v2`.

**Logic:**
- If no cookies exist or window has expired (>12 hours), resets counter to 1 and returns `true`
- If counter < 20, increments and returns `true`
- Otherwise returns `false`

---

### Timestamp Formatting

#### `formatTimestampForInput(date)`
Formats a `Date` object into ISO 8601 format for the hidden `timestamp` input field.

| Param  | Type | Description    |
|--------|------|----------------|
| `date` | Date | Date object    |

**Returns:** `string` -- formatted as `YYYY-MM-DDTHH:MM:SS` (local time, no timezone offset).

---

#### `formatTimestampForDisplay(date)`
Formats a `Date` object for display in the location section using Australian locale.

| Param  | Type | Description    |
|--------|------|----------------|
| `date` | Date | Date object    |

**Returns:** `string` -- formatted as `DD/MM/YY, H:MM am/pm` in `Australia/Perth` timezone.

---

#### `getDayWithSuffix(day)`
Appends the ordinal suffix (st, nd, rd, th) to a day number.

| Param | Type   | Description       |
|-------|--------|-------------------|
| `day` | number | Day of month (1-31) |

**Returns:** `string` -- e.g., `"1st"`, `"2nd"`, `"3rd"`, `"14th"`.

---

#### `parseTimestampToDateObj(timestampString)`
Parses a timestamp string into a `Date` object. Handles ISO 8601 format and falls back to `DD/MM/YYYY, H:MM AM/PM`.

| Param             | Type   | Description          |
|-------------------|--------|----------------------|
| `timestampString` | string | Timestamp to parse   |

**Returns:** `Date|null` -- parsed Date or `null` if parsing fails.

---

#### `formatDateForEmail(timestampToParse, forSubject)`
Formats a timestamp for use in email subject lines or body text.

| Param              | Type    | Description                                |
|--------------------|---------|--------------------------------------------|
| `timestampToParse` | string  | Timestamp string to format                  |
| `forSubject`       | boolean | If `true`, returns short format for subject |

**Returns:** `string`
- Subject format: `"Sunday 12th April 2025"`
- Body format: `"Sunday 12th April 2025 at 8:10 PM (Local Time)"`

Appends `"(UTC)"` if the timestamp contains `"Z"` or `"+00:00"`, otherwise `"(Local Time)"`.

---

### Form Handling

#### `handleMultiSelectGroup(groupElement)`
Attaches click listeners to all buttons in a button-grid group, enabling multi-select toggle behaviour.

| Param          | Type    | Description                        |
|----------------|---------|------------------------------------|
| `groupElement` | Element | The `.form-group` container element |

**Behaviour:**
- Toggles `.active` class on clicked button
- Calls `updateGroupValue()` to sync hidden input
- If the group is the brand section, calls `toggleConditionSectionVisibility()`
- Removes error styling on interaction

---

#### `updateGroupValue(groupElement)`
Serialises the currently selected (`.active`) button values into the group's hidden input as a JSON array string.

| Param          | Type    | Description              |
|----------------|---------|--------------------------|
| `groupElement` | Element | The group container       |

**Returns:** `void` -- updates the `.group-value` hidden input.

---

#### `toggleConditionSectionVisibility(brandValue, isActive)`
Shows or hides the condition section for a specific brand when it is selected/deselected.

| Param        | Type    | Description                          |
|--------------|---------|--------------------------------------|
| `brandValue` | string  | Brand identifier (e.g., `"Coles"`)   |
| `isActive`   | boolean | Whether the brand is being selected   |

**Behaviour:** Adds/removes `.visible` class. Clears condition selections when hiding.

---

#### `resetPage()`
Resets the entire form to a clean state by clearing localStorage and reloading the page.

**Returns:** `void`

**Note:** Uses `window.location.reload()` as a simple full-reset mechanism. Commented-out code shows an alternative manual reset approach.

---

### Email Generation

#### `generateTrolleyEmailBody(entityName, submissionData, entityType)`
Generates the full email body text for a trolley report email.

| Param            | Type   | Description                                      |
|------------------|--------|--------------------------------------------------|
| `entityName`     | string | Name of the recipient (brand or LGA name)         |
| `submissionData` | object | The enriched log data object from the server       |
| `entityType`     | string | `"Brand"` or `"LGA"` -- determines email wording |

**Returns:** `string` -- multi-line email body including greeting, details (brand, date, map link, suburb, conditions, comments), and sign-off.

**Key behaviours:**
- For `"Brand"` type: addresses "Dear [Brand] Team", lists only that brand's conditions
- For `"LGA"` type: addresses "Dear [LGA Name]", lists all brands and all conditions
- Includes Google Maps link from coordinates
- Appends app attribution footer

---

#### `displayTrolleyEmailPrompt(submissionData)`
Renders the email prompt modal with a summary of the submission and email buttons for each brand and the LGA.

| Param            | Type   | Description                              |
|------------------|--------|------------------------------------------|
| `submissionData` | object | Enriched log data from localStorage       |

**Returns:** `void`

**DOM modifications:**
- Populates `#trolley-submission-summary-modal` with report summary HTML
- Creates "Email [Brand]" buttons for each brand with a known email address
- Creates "Email [LGA]" button if LGA name and email are resolved
- Each button generates a `mailto:` link and triggers `showManualCopyUI()`

---

#### `showManualCopyUI(entityName, emailAddress, subject, body, entityType)`
Displays the manual email copy area with subject and body textareas and copy buttons.

| Param          | Type   | Description                         |
|----------------|--------|-------------------------------------|
| `entityName`   | string | Recipient name                      |
| `emailAddress` | string | Recipient email                     |
| `subject`      | string | Email subject line                  |
| `body`         | string | Email body text                     |
| `entityType`   | string | `"Brand"` or `"LGA"` for labelling  |

**Returns:** `void` -- renders HTML into `#manual-email-copy-area-modal` and attaches copy button handlers.

---

#### `copyToClipboard(textToCopy, feedbackElementId)`
Copies text to the clipboard and shows temporary feedback.

| Param               | Type   | Description                                  |
|----------------------|--------|----------------------------------------------|
| `textToCopy`        | string | Text to copy                                 |
| `feedbackElementId` | string | ID of the span element for "Copied!" feedback |

**Returns:** `void` -- uses `navigator.clipboard.writeText()`.

---

#### `handleModalMainAction()`
Closes the email prompt modal, clears localStorage, and resets modal content for next use.

**Returns:** `void`

**Triggered by:** Close button, "No, Thanks / All Done" button, or clicking outside the modal.

---

### Form Submission Handler

The `trolleyForm.addEventListener('submit', ...)` async handler orchestrates:

1. Rate limit check via `checkSubmissionLimit()`
2. Client-side validation (location captured, brands selected, required fields)
3. Parsing `FormData` into structured JSON with nested conditions
4. POST to `log_trolley.php` via `fetch()`
5. On success: stores `logData` in localStorage, reloads page after 1.5s
6. On error: displays error message, clears localStorage

---

### Event Listeners (index.html)

| Target                              | Event        | Handler                                |
|-------------------------------------|--------------|----------------------------------------|
| `#get-location-btn`                 | `click`      | GPS capture via Geolocation API         |
| `.brand-section` buttons            | `click`      | `handleMultiSelectGroup()` (delegated)  |
| `.condition-section` buttons        | `click`      | `handleMultiSelectGroup()` (delegated)  |
| `#comments` textarea                | `input`      | Character counter update                |
| `#reset-form-btn`                   | `click`      | Confirm dialog then `resetPage()`       |
| `#trolley-form`                     | `submit`     | Async submission handler                |
| `#close-trolley-email-modal`        | `click`      | `handleModalMainAction()`               |
| `#email-prompt-action-btn`          | `click`      | `handleModalMainAction()`               |
| `window`                            | `click`      | Close modal if clicking overlay          |


---


## view_logs.php

Functions are defined in the global scope, initialised on `DOMContentLoaded`.

### Data Loading

#### `fetchLogs()`
Async function that fetches `trolley_logs.json`, assigns stable row IDs, sorts the data, and renders the table.

**Cache busting:** Appends `?_=<timestamp>` to the fetch URL.

**Error handling:** Displays error message in `#error-loading` div on failure.

---

#### `generateStableRowId(log, index)`
Creates a unique, stable identifier for each log entry for DOM referencing.

| Param   | Type   | Description          |
|---------|--------|----------------------|
| `log`   | object | Log entry object      |
| `index` | number | Array index position  |

**Returns:** `string` -- format: `"log-{timestamp}-{ipAddress}-{index}"`.

---

### Display Formatting

#### `getStateAbbreviation(stateName)`
Converts a full Australian state name to its abbreviation.

| Param       | Type        | Description       |
|-------------|-------------|-------------------|
| `stateName` | string|null | Full state name   |

**Returns:** `string|null` -- abbreviation (e.g., `"WA"`) or the original string if not found.

---

#### `formatDisplayDate(isoTimestamp)`
Formats a timestamp for the date column in `en-AU` locale, `Australia/Perth` timezone, `dateStyle: 'short'`.

**Returns:** `string` -- e.g., `"12/4/25"`.

---

#### `formatDisplayTime(isoTimestamp)`
Formats a timestamp for the time display in `en-AU` locale, `Australia/Perth` timezone, `timeStyle: 'short'`, `hour12: true`.

**Returns:** `string` -- e.g., `"8:10 pm"`. Hidden on mobile via CSS.

---

### Sorting

#### `sortLogs()`
Sorts the `allLogs` array in-place based on `sortColumn` and `sortDirection`.

**Sort logic:**
- `brands`: Compares first brand name alphabetically (case-insensitive)
- `timestamp`: Compares `serverTimestampUTC` (or `timestamp`) as Date values numerically

---

#### `updateSortDirection(column)`
Toggles sort direction for the given column, or sets default direction for a new column.

| Param    | Type   | Description                  |
|----------|--------|------------------------------|
| `column` | string | Column identifier to sort by |

**Default directions:** `timestamp` defaults to descending (-1); all others default to ascending (1).

---

#### `updateSortIndicators()`
Updates the `--sort-icon` CSS custom property on sortable `<th>` elements to show up/down triangles or the default double-arrow.

---

### Table Rendering

#### `renderTable()`
Clears and rebuilds the table body with paginated log entries. Creates brand toggle buttons, date/time cells, and location links for each row.

**Pagination:** Slices `allLogs` from `(currentPage - 1) * itemsPerPage` to `currentPage * itemsPerPage`.

---

#### `toggleDetailRow(button)`
Expands or collapses a detail row below a log entry, showing brand-specific conditions and comments.

| Param    | Type    | Description                      |
|----------|---------|----------------------------------|
| `button` | Element | The brand toggle button clicked  |

**Behaviour:** Closes all other open detail rows before opening the new one. Creates the detail row dynamically with escaped HTML.

---

### Pagination

#### `updatePagination(totalPages)`
Updates the page info text and enables/disables prev/next buttons.

---

#### `changePage(direction)`
Navigates to the previous or next page.

| Param       | Type   | Description          |
|-------------|--------|----------------------|
| `direction` | number | `-1` or `1`          |

---

### Event Setup

#### `setupEventListeners()`
Attaches event listeners for sorting (thead click delegation), pagination buttons, and brand toggle detail expansion (tbody click delegation).

---

#### `updateTable()`
Helper that calls `renderTable()`.

---


## map_view.html

All functions defined within a single `DOMContentLoaded` listener.

### Map Initialisation

- Creates Leaflet map centred on Perth (`[-31.9523, 115.8613]`, zoom 9)
- Adds OpenStreetMap tile layer
- Creates and adds `L.markerClusterGroup()`

---

#### `loadMarkers()`
Async function that fetches `trolley_logs.json` and creates Leaflet markers for all entries with valid coordinates.

**Marker popups include:** Brand(s), reported date/time, suburb/state, conditions, comments.

**Post-load:** Fits map bounds to marker cluster extent with padding.

---

### Helper Functions

#### `formatDisplayDateTime(isoTimestamp)`
Formats timestamp for popup display in `en-AU` locale, `Australia/Perth` timezone.

**Returns:** `string` -- e.g., `"12/4/25, 8:10 pm"`.

---

#### `getConditionsText(conditions)`
Extracts and deduplicates all condition values from a conditions object across all brands.

| Param        | Type        | Description               |
|--------------|-------------|---------------------------|
| `conditions` | object|null | Conditions object from log |

**Returns:** `string` -- comma-separated unique conditions.

---

#### `escapeHtml(text)`
Escapes HTML special characters to prevent XSS in map popups.

| Param  | Type   | Description    |
|--------|--------|----------------|
| `text` | string | Raw text       |

**Returns:** `string` -- with `&`, `<`, `>`, `"`, `'` replaced by HTML entities.

---


## map_<brand>.html (Brand Maps)

Each brand map file follows the same structure as `map_view.html` with one key difference: markers are filtered to only include log entries where the `brands` array contains the target brand.

### Additional Functions (per-brand maps)

#### `loadMarkers()` (brand-filtered variant)
Same as `map_view.html` but includes a filter step:

```javascript
if (!Array.isArray(log.brands) || !log.brands.includes(targetBrand)) return;
```

#### `toggleDetailRow(brand, markerElement)`
Brand maps include expandable popup details with condition and comment information, similar to `view_logs.php` detail rows.

---


## Global Constants

### index.html

| Name                  | Type   | Value                                          |
|-----------------------|--------|-------------------------------------------------|
| `maxChars`            | number | `200`                                            |
| `brandEmailAddresses` | object | Maps brand names to email addresses              |

### view_logs.php

| Name                | Type   | Value                                               |
|---------------------|--------|------------------------------------------------------|
| `stateAbbreviations` | object | Maps full state names to abbreviations               |
| `customBrandText`   | object | Maps brand identifiers to 2-letter abbreviations      |
| `itemsPerPage`      | number | `30`                                                  |
| `sortColumn`        | string | `"timestamp"` (initial)                               |
| `sortDirection`     | number | `-1` (descending, initial)                            |
