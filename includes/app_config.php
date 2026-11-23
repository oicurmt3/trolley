<?php
/**
 * Application configuration (safe to commit — no secrets).
 * Adjust ALLOWED_ORIGINS for staging or alternate hostnames.
 */

declare(strict_types=1);

/** Origins permitted for CORS on log_trolley.php (exact match, no trailing slash). */
const TROLLEY_ALLOWED_ORIGINS = [
    'https://sandgroper.net',
];

/** Max POST body size in bytes (JSON payload). */
const TROLLEY_MAX_BODY_BYTES = 65536;

/** Western Australia approximate bounding box (decimal degrees). */
const TROLLEY_WA_LAT_MIN = -35.2;
const TROLLEY_WA_LAT_MAX = -13.5;
const TROLLEY_WA_LON_MIN = 112.9;
const TROLLEY_WA_LON_MAX = 129.1;

const TROLLEY_MAX_COMMENT_LENGTH = 200;
const TROLLEY_MAX_TIMESTAMP_LENGTH = 64;
const TROLLEY_MAX_CLIENT_SUBMISSION_TS_LENGTH = 64;

const TROLLEY_ALLOWED_BRANDS = [
    'Coles',
    'Woolworths',
    'Aldi',
    'IGA',
    'Kmart',
    'BigW',
    'Bunnings',
    'Spudshed',
    'Other',
];

const TROLLEY_ALLOWED_CONDITIONS = [
    'Good Condition',
    'Needs Cleaning',
    'Rusty Frame',
    'Damaged Wheel(s)',
    'Bent Frame',
    'Broken Handle',
    'Child Seat Issue',
    'Other Damage',
];

/** Per-IP rate limit: max POSTs per window. */
const TROLLEY_RATE_LIMIT_MAX_REQUESTS = 25;

/** Rate-limit window length in seconds. */
const TROLLEY_RATE_LIMIT_WINDOW_SECONDS = 3600;

/** How long to keep rate-limit bucket files on disk (seconds). */
const TROLLEY_RATE_LIMIT_RETENTION_SECONDS = 86400;

const TROLLEY_NOMINATIM_BASE_URL = 'https://nominatim.openstreetmap.org/reverse';
const TROLLEY_NOMINATIM_USER_AGENT = 'TrolleyReportApp/1.4 (sandgroper.net; info@sandgroper.net)';

const TROLLEY_JSON_LOG_FILE = 'trolley_logs.json';
const TROLLEY_SUBURB_LGA_MAP_FILE = 'suburb_lga_mapping.json';
const TROLLEY_LGA_CONTACTS_FILE = 'lga_contact_details.json';