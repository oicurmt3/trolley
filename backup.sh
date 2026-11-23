#!/bin/bash
# =============================================================================
# backup.sh -- WA Trolley Report Daily Backup
#
# Creates a timestamped copy of trolley_logs.json and prunes backups older
# than 30 days. Add to cPanel Cron Jobs to run daily.
#
# Cron entry (cPanel > Cron Jobs > Add New Cron Job):
#   Schedule: Once Per Day (0 3 * * *)
#   Command:  /home/perthrel/sandgroper.net/trolley/backup.sh
#
# Version: 1.4
# =============================================================================

# --- Configuration ---
TROLLEY_DIR="/home/perthrel/sandgroper.net/trolley"
BACKUP_DIR="${TROLLEY_DIR}/backups"
DB_FILE="${TROLLEY_DIR}/trolley_data.db"
JSON_FILE="${TROLLEY_DIR}/trolley_logs.json"
DAYS_TO_KEEP=30

# --- Create backup directory if it does not exist ---
mkdir -p "${BACKUP_DIR}"

# --- Create today's backup ---
DATE_STAMP=$(date +%Y%m%d_%H%M%S)

# Back up SQLite database (primary data store)
if [ -f "${DB_FILE}" ]; then
    cp "${DB_FILE}" "${BACKUP_DIR}/trolley_data_${DATE_STAMP}.db"
    echo "[$(date)] Backup created: trolley_data_${DATE_STAMP}.db"
else
    echo "[$(date)] WARNING: Database file not found: ${DB_FILE}"
fi

# Also back up legacy JSON if it still exists
if [ -f "${JSON_FILE}" ]; then
    cp "${JSON_FILE}" "${BACKUP_DIR}/trolley_logs_${DATE_STAMP}.json"
    echo "[$(date)] Legacy JSON backup: trolley_logs_${DATE_STAMP}.json"
fi

# --- Also backup rate limits and LGA data (small files, cheap insurance) ---
if [ -f "${TROLLEY_DIR}/rate_limits.json" ]; then
    cp "${TROLLEY_DIR}/rate_limits.json" "${BACKUP_DIR}/rate_limits_${DATE_STAMP}.json"
fi
if [ -f "${TROLLEY_DIR}/lga_contact_details.json" ]; then
    cp "${TROLLEY_DIR}/lga_contact_details.json" "${BACKUP_DIR}/lga_contact_details_${DATE_STAMP}.json"
fi
if [ -f "${TROLLEY_DIR}/suburb_lga_mapping.json" ]; then
    cp "${TROLLEY_DIR}/suburb_lga_mapping.json" "${BACKUP_DIR}/suburb_lga_mapping_${DATE_STAMP}.json"
fi
if [ -f "${TROLLEY_DIR}/brand_contacts.json" ]; then
    cp "${TROLLEY_DIR}/brand_contacts.json" "${BACKUP_DIR}/brand_contacts_${DATE_STAMP}.json"
fi

# --- Prune old backups ---
find "${BACKUP_DIR}" -name "*.json" -type f -mtime +${DAYS_TO_KEEP} -delete
echo "[$(date)] Pruned backups older than ${DAYS_TO_KEEP} days."

echo "[$(date)] Backup complete."
