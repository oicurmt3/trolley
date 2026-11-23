# Handover notes

See `README.md` for layout, deploy, and testing.

- **Single consolidated brand map:** `map.html?brand=<slug>` replaces nine near-duplicate HTML files; legacy `map_*.html` URLs redirect.
- **Security:** `includes/` holds validation, CORS, rate limit, and locked JSON append; root `.htaccess` sets headers and blocks sensitive JSON except `trolley_logs.json`.
- **Supersedes PR #1:** Same branch family adds hardening plus retains `map_my_location` on `map_view.html` and `map.html`.
- **APK/zip removed from repo** per owner request — no release attached; restore only with an explicit release workflow.