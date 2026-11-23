# Deployment Guide -- WA Trolley Report

**Target Environment:** Shared hosting (Apache) with PHP 7.4+
**Current Production URL:** `https://sandgroper.net/trolley/`


## Prerequisites

- Web server with PHP 7.4+ (Apache recommended; nginx with PHP-FPM also works)
- `file_get_contents()` enabled for external URLs (for Nominatim API calls)
- `allow_url_fopen = On` in `php.ini`
- SSL/TLS certificate (HTTPS required for Geolocation API in modern browsers)
- Write permissions on the deployment directory for PHP process


## Step 1: Upload Files

Upload all project files to your target directory. The expected structure is:

```
/public_html/trolley/          (or equivalent webroot subdirectory)
|-- index.html
|-- log_trolley.php
|-- view_logs.php
|-- map_view.html
|-- map_coles.html
|-- map_woolworths.html
|-- map_aldi.html
|-- map_iga.html
|-- map_kmart.html
|-- map_bigw.html
|-- map_bunnings.html
|-- map_spudshed.html
|-- map_other.html
|-- trolley_logs.json
|-- suburb_lga_mapping.json
|-- lga_contact_details.json
|-- lga_emails.json
|-- privacy.html
|-- terms.html
|-- faq.html
```

All paths in the application are relative, so the app will work in any subdirectory.


## Step 2: Set File Permissions

The PHP process needs to read and write the JSON log file and read the mapping files.

```bash
# Make the trolley directory writable (for error_log)
chmod 755 /path/to/trolley/

# Data file -- read/write for PHP, readable by web server
chmod 664 /path/to/trolley/trolley_logs.json
chown www-data:www-data /path/to/trolley/trolley_logs.json

# Mapping files -- read-only
chmod 644 /path/to/trolley/suburb_lga_mapping.json
chmod 644 /path/to/trolley/lga_contact_details.json
chmod 644 /path/to/trolley/lga_emails.json

# PHP files -- readable/executable
chmod 644 /path/to/trolley/log_trolley.php
chmod 644 /path/to/trolley/view_logs.php
```

On shared hosting (cPanel), the user and PHP process typically run as the same user, so `644` for all files and `755` for the directory is usually sufficient.


## Step 3: Initialise the Data File

If deploying fresh, create an empty `trolley_logs.json`:

```bash
echo "[]" > /path/to/trolley/trolley_logs.json
```

If migrating, upload your existing `trolley_logs.json` with all existing entries.


## Step 4: Verify PHP Configuration

Check that the following PHP settings are active:

```php
// Required
allow_url_fopen = On          // For Nominatim API calls
date.timezone = "UTC"         // Recommended for consistent timestamps

// Recommended
display_errors = Off          // Do not expose errors to users
log_errors = On               // Log to error_log file
error_log = /path/to/trolley/error_log
```

If you cannot modify `php.ini`, you can set these at runtime in `log_trolley.php` (before any output):

```php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
```


## Step 5: Verify Nominatim Compliance

The app uses OpenStreetMap's Nominatim API for reverse geocoding. Nominatim has strict usage policies:

- **Maximum 1 request per second** (this app makes 1 request per form submission)
- **Must include a valid User-Agent** with contact details
- **No heavy usage from shared hosting IP ranges** without prior arrangement

The User-Agent is set in `log_trolley.php`:

```php
$nominatimUserAgent = 'TrolleyReportApp/1.3 (sandgroper.net; admin@sandgroper.net)';
```

Update the domain and email to match your deployment. If Nominatim blocks your IP (403/509 errors), consider:

1. Implementing a local geocoding cache
2. Using a self-hosted Nominatim instance
3. Switching to an alternative geocoding API (e.g., LocationIQ, Google Geocoding API)


## Step 6: HTTPS Requirement

Modern browsers require HTTPS for the Geolocation API. Ensure your server has a valid SSL certificate.

If using shared hosting, most providers offer free SSL via Let's Encrypt / AutoSSL.

If using Apache, ensure the SSL module is loaded and force HTTPS redirect:

```apache
# .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```


## Step 7: Test the Deployment

1. **Report form:** Navigate to `https://yourdomain.com/trolley/index.html`
   - Verify GPS capture works (requires HTTPS and location permission)
   - Submit a test report and confirm success message
   - Check that `trolley_logs.json` contains the new entry

2. **Log viewer:** Navigate to `https://yourdomain.com/trolley/view_logs.php`
   - Verify the table loads and displays entries
   - Test sorting and pagination

3. **Map view:** Navigate to `https://yourdomain.com/trolley/map_view.html`
   - Verify markers appear on the Leaflet map
   - Test popup content and marker clustering

4. **Email prompt:** Submit a report and verify the email modal appears
   - Test a `mailto:` link opens your email client
   - Verify manual copy fallback works

5. **Error log:** Check `error_log` for any PHP warnings (especially Nominatim 403s)


## Step 8: Optional -- Apache Configuration

### Restrict Direct Access to Log File

You may want to prevent direct browsing of certain files:

```apache
# .htaccess
<FilesMatch "^(error_log|lga_emails\.json)$">
    Order deny,allow
    Deny from all
</FilesMatch>
```

**Note:** `trolley_logs.json` must remain publicly accessible as it is fetched by the frontend.

### Enable Compression

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/json text/html text/css application/javascript
</IfModule>
```

### Cache Control

```apache
<FilesMatch "\.(html|css|js)$">
    Header set Cache-Control "public, max-age=3600"
</FilesMatch>
<FilesMatch "trolley_logs\.json$">
    Header set Cache-Control "no-cache, must-revalidate"
</FilesMatch>
```


## Troubleshooting

| Symptom                                   | Likely Cause                                          | Fix                                                    |
|-------------------------------------------|-------------------------------------------------------|--------------------------------------------------------|
| GPS says "Permission denied"              | HTTPS not configured or user denied permission         | Ensure HTTPS is active; user must grant location access |
| Submissions fail with 500 error            | `trolley_logs.json` not writable                       | Check file/directory permissions                        |
| Suburb/State/LGA are null in responses     | Nominatim API blocked (403/509)                        | Check error_log; comply with Nominatim usage policy     |
| Map shows no markers                       | `trolley_logs.json` is empty or inaccessible           | Verify file exists and is valid JSON array               |
| Email links do not open                    | No `mailto:` handler on device                         | Manual copy fallback is provided in the modal           |
| Brand maps show no data                    | No entries for that brand in `trolley_logs.json`       | Expected behaviour for brands with zero reports         |


## Production Hardening Checklist

- [ ] Restrict CORS origin in `log_trolley.php` from `*` to your domain
- [ ] Enable HTTPS redirect via `.htaccess` or server config
- [ ] Set `display_errors = Off` in PHP
- [ ] Implement server-side rate limiting (e.g., per-IP throttle)
- [ ] Add CSRF token to form submission
- [ ] Set up automated backup for `trolley_logs.json`
- [ ] Monitor `error_log` for Nominatim blocks
- [ ] Consider Content Security Policy (CSP) headers
- [ ] Restrict direct access to `error_log` and `lga_emails.json`
- [ ] Update Nominatim User-Agent to match your domain/contact
