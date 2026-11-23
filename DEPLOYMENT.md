# Deployment -- WA Trolley Report

Quick deployment reference. For full detail see [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md).


## Requirements

- Apache or nginx with PHP 7.4+
- `allow_url_fopen = On` in `php.ini`
- Valid SSL/TLS certificate (HTTPS required for Geolocation API)
- Write permissions on deployment directory for PHP process


## Deploy

```bash
# 1. Upload files to server
scp -r trolley/ user@server:/public_html/trolley/

# 2. Initialise empty data file (fresh deploy only)
ssh user@server "echo '[]' > /public_html/trolley/trolley_logs.json"

# 3. Set permissions
ssh user@server "chmod 755 /public_html/trolley/ && chmod 664 /public_html/trolley/trolley_logs.json"

# 4. Test
curl -s https://yourdomain.com/trolley/trolley_logs.json
# Should return: []
```


## File Checklist

| File                       | Writable | Public | Notes                           |
|----------------------------|----------|--------|---------------------------------|
| `index.html`               | No       | Yes    | Report form                     |
| `log_trolley.php`          | No       | Yes    | API endpoint                    |
| `view_logs.php`            | No       | Yes    | Log viewer                      |
| `map_view.html`            | No       | Yes    | All-brands map                  |
| `map_<brand>.html` (x9)   | No       | Yes    | Per-brand maps                  |
| `trolley_logs.json`        | Yes      | Yes    | Data store -- must be writable  |
| `suburb_lga_mapping.json`  | No       | Yes    | Suburb-to-LGA lookup            |
| `lga_contact_details.json` | No       | Yes    | LGA name/email lookup           |
| `lga_emails.json`          | No       | No*    | Legacy -- consider restricting  |
| `privacy.html`             | No       | Yes    | Privacy policy                  |
| `terms.html`               | No       | Yes    | Terms & conditions              |
| `faq.html`                 | No       | Yes    | FAQ                             |
| `error_log`                | Yes      | No*    | PHP errors -- restrict access   |

*Recommended: block direct HTTP access via `.htaccess`.


## Environment Variables / Config

All configuration is inline in source files. Key values to update for a new deployment:

| Setting                      | File              | Current Value                                                |
|------------------------------|-------------------|--------------------------------------------------------------|
| Nominatim User-Agent         | `log_trolley.php` | `TrolleyReportApp/1.3 (sandgroper.net; admin@sandgroper.net)` |
| CORS origin                  | `log_trolley.php` | `*` (should be restricted)                                    |
| Map default centre           | `map_view.html`   | `[-31.9523, 115.8613]` (Perth)                                |
| Brand email addresses        | `index.html`      | Hardcoded `brandEmailAddresses` object                        |
| Submission rate limit         | `index.html`      | 20 per 12 hours (cookie-based)                                |
| Comment character limit       | `index.html`      | 200 characters                                                |


## Verify

After deployment, test each page:

1. `index.html` -- GPS capture, form submission, email modal
2. `view_logs.php` -- table loads, sorting, pagination, detail expand
3. `map_view.html` -- markers render, clusters work, popups show
4. `map_<brand>.html` -- brand filtering works
5. `log_trolley.php` -- POST returns 200 with `logData`
6. `privacy.html`, `terms.html`, `faq.html` -- pages render


## Rollback

To roll back, replace the deployed files with previous versions. Data continuity depends on `trolley_logs.json` -- back it up before any deployment.

```bash
# Backup current data
scp user@server:/public_html/trolley/trolley_logs.json ./trolley_logs_backup_$(date +%Y%m%d).json

# Deploy previous version
scp -r trolley_v1.2/ user@server:/public_html/trolley/

# Restore data if needed
scp ./trolley_logs_backup_20260405.json user@server:/public_html/trolley/trolley_logs.json
```
