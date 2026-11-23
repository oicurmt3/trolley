<?php
/**
 * db.php -- SQLite Database Helper for WA Trolley Report
 *
 * Provides a shared database connection and table initialisation.
 * All files that need database access include this file.
 *
 * Usage:
 *   require_once __DIR__ . '/db.php';
 *   $db = getDatabase();
 *   $stmt = $db->prepare('SELECT * FROM reports WHERE id = :id');
 *
 * Version: 1.6
 */

/**
 * Human-readable reason the last getDatabase() call returned null.
 * Intended for diagnostic output only -- never shown raw to end users.
 *
 * @return string|null
 */
function getDatabaseError() {
    return $GLOBALS['DB_LAST_ERROR'] ?? null;
}

/**
 * Get a PDO connection to the SQLite database.
 * Creates the database and tables if they do not exist.
 *
 * @return PDO|null -- null when PDO and/or the SQLite driver are unavailable
 */
function getDatabase() {
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    // ------------------------------------------------------------------
    // Extension guard.
    //
    // Switching PHP version in cPanel (MultiPHP Manager / Select PHP
    // Version) does not carry extensions over. Newer PHP builds often
    // ship with PDO but WITHOUT pdo_sqlite, which surfaces as
    // "could not find driver" -- or as a fatal "Class PDO not found"
    // if PDO itself is missing. Check both before connecting so callers
    // get a clean null instead of a fatal error / blank 500 page.
    // ------------------------------------------------------------------
    if (!class_exists('PDO')) {
        $GLOBALS['DB_LAST_ERROR'] = 'The PDO extension is not enabled in PHP ' . PHP_VERSION . '.';
        error_log(
            'PHP Database Error: PDO extension missing in PHP ' . PHP_VERSION .
            '. Enable the "pdo" extension in cPanel > Select PHP Version > Extensions.'
        );
        return null;
    }

    $availableDrivers = PDO::getAvailableDrivers();
    if (!in_array('sqlite', $availableDrivers, true)) {
        $GLOBALS['DB_LAST_ERROR'] = 'The pdo_sqlite extension is not enabled in PHP ' . PHP_VERSION . '.';
        error_log(
            'PHP Database Error: pdo_sqlite driver missing in PHP ' . PHP_VERSION .
            ' (available PDO drivers: ' . (empty($availableDrivers) ? 'none' : implode(', ', $availableDrivers)) . ')' .
            '. Enable "pdo_sqlite" and "sqlite3" in cPanel > Select PHP Version > Extensions.'
        );
        return null;
    }

    $dbPath = __DIR__ . '/trolley_data.db';

    try {
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Performance pragmas for SQLite
        $db->exec('PRAGMA journal_mode = WAL');       // Write-ahead logging (concurrent reads during writes)
        $db->exec('PRAGMA synchronous = NORMAL');     // Good balance of safety and speed
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 5000');      // Wait up to 5s if database is locked

        // Create tables if they do not exist
        initTables($db);

        return $db;
    } catch (PDOException $e) {
        $GLOBALS['DB_LAST_ERROR'] = 'SQLite connection failed: ' . $e->getMessage();
        error_log('PHP Database Error: Could not connect to SQLite: ' . $e->getMessage());
        return null;
    }
}


/**
 * Create the reports table and indexes if they do not exist.
 */
function initTables(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            latitude REAL NOT NULL,
            longitude REAL NOT NULL,
            timestamp TEXT NOT NULL,
            brands TEXT NOT NULL,
            conditions TEXT,
            comments TEXT,
            suburb TEXT,
            state TEXT,
            lga_name TEXT,
            lga_email TEXT,
            server_timestamp_utc TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        )
    ");

    // Notification tracking -- records when users confirm they sent an email
    $db->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id INTEGER NOT NULL,
            entity_type TEXT NOT NULL,
            entity_name TEXT NOT NULL,
            notified_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
        )
    ");

    // Brand accounts -- token-based login for brand portals
    // Migration: if table exists with UNIQUE on brand_name, recreate without it
    $tableInfo = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='brand_accounts'")->fetchColumn();
    if ($tableInfo && strpos($tableInfo, 'brand_name TEXT NOT NULL UNIQUE') !== false) {
        $db->exec("ALTER TABLE brand_accounts RENAME TO brand_accounts_old");
        $db->exec("
            CREATE TABLE brand_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                brand_name TEXT NOT NULL,
                token TEXT NOT NULL UNIQUE,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT (datetime('now')),
                last_login TEXT
            )
        ");
        $db->exec("INSERT INTO brand_accounts SELECT * FROM brand_accounts_old");
        $db->exec("DROP TABLE brand_accounts_old");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS brand_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                brand_name TEXT NOT NULL,
                token TEXT NOT NULL UNIQUE,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT (datetime('now')),
                last_login TEXT
            )
        ");
    }

    // Collection records -- audit trail of brand collection claims
    // Migration: verify FK integrity by checking the stored CREATE SQL.
    // If the table exists but was created without proper FK references (or with
    // references to tables that didn't exist yet), rebuild it.
    $collTableSql = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='collections'")->fetchColumn();
    if ($collTableSql && (
        strpos($collTableSql, 'FOREIGN KEY') === false ||
        strpos($collTableSql, 'REFERENCES reports') === false ||
        strpos($collTableSql, 'REFERENCES brand_accounts') === false
    )) {
        // Table exists with missing/broken FK -- rebuild preserving data
        $db->exec('PRAGMA foreign_keys = OFF');
        $db->exec("ALTER TABLE collections RENAME TO collections_old");
        $db->exec("
            CREATE TABLE collections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_id INTEGER NOT NULL,
                brand_account_id INTEGER NOT NULL,
                collected_at TEXT DEFAULT (datetime('now')),
                reversed_by_admin INTEGER DEFAULT 0,
                reversed_at TEXT,
                admin_notes TEXT,
                FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
                FOREIGN KEY (brand_account_id) REFERENCES brand_accounts(id)
            )
        ");
        $db->exec("INSERT INTO collections SELECT * FROM collections_old");
        $db->exec("DROP TABLE collections_old");
        $db->exec('PRAGMA foreign_keys = ON');
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS collections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_id INTEGER NOT NULL,
                brand_account_id INTEGER NOT NULL,
                collected_at TEXT DEFAULT (datetime('now')),
                reversed_by_admin INTEGER DEFAULT 0,
                reversed_at TEXT,
                admin_notes TEXT,
                FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
                FOREIGN KEY (brand_account_id) REFERENCES brand_accounts(id)
            )
        ");
    }

    // Indexes for common queries
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reports_timestamp ON reports(server_timestamp_utc)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reports_suburb ON reports(suburb)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reports_brands ON reports(brands)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reports_lat_lon ON reports(latitude, longitude)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_report ON notifications(report_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collections_report ON collections(report_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_brand_accounts_token ON brand_accounts(token)");
}


/**
 * Insert a new report into the database.
 *
 * @param PDO $db
 * @param array $entry -- the enriched log entry array
 * @return int|false -- the new row ID or false on failure
 */
function insertReport(PDO $db, array $entry) {
    try {
        $stmt = $db->prepare("
            INSERT INTO reports (latitude, longitude, timestamp, brands, conditions, comments,
                                 suburb, state, lga_name, lga_email, server_timestamp_utc)
            VALUES (:latitude, :longitude, :timestamp, :brands, :conditions, :comments,
                    :suburb, :state, :lga_name, :lga_email, :server_timestamp_utc)
        ");

        $stmt->execute([
            ':latitude'             => $entry['latitude'],
            ':longitude'            => $entry['longitude'],
            ':timestamp'            => $entry['timestamp'],
            ':brands'               => json_encode($entry['brands'] ?? []),
            ':conditions'           => json_encode($entry['conditions'] ?? null),
            ':comments'             => $entry['comments'] ?? null,
            ':suburb'               => $entry['suburb'] ?? null,
            ':state'                => $entry['state'] ?? null,
            ':lga_name'             => $entry['lgaName'] ?? null,
            ':lga_email'            => $entry['lgaEmail'] ?? null,
            ':server_timestamp_utc' => $entry['serverTimestampUTC']
        ]);

        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        error_log('PHP Database Error: Insert failed: ' . $e->getMessage());
        return false;
    }
}


/**
 * Get all reports as an array of associative arrays.
 * Brands and conditions are decoded from JSON strings back to arrays/objects.
 *
 * @param PDO $db
 * @param array $filters -- optional filters: brand, after, before, limit, offset
 * @return array
 */
function getReports(PDO $db, array $filters = []) {
    $where = [];
    $params = [];

    // Brand filter (search within JSON array string)
    if (!empty($filters['brand'])) {
        $where[] = "r.brands LIKE :brand";
        $params[':brand'] = '%"' . $filters['brand'] . '"%';
    }

    // Date range filters
    if (!empty($filters['after'])) {
        $where[] = "r.server_timestamp_utc >= :after";
        $params[':after'] = $filters['after'];
    }
    if (!empty($filters['before'])) {
        $where[] = "r.server_timestamp_utc <= :before";
        $params[':before'] = $filters['before'];
    }

    $whereSql = [];
    if (!empty($filters['brand'])) {
        $whereSql[] = "r.brands LIKE :brand";
    }
    if (!empty($filters['after'])) {
        $whereSql[] = "r.server_timestamp_utc >= :after";
    }
    if (!empty($filters['before'])) {
        $whereSql[] = "r.server_timestamp_utc <= :before";
    }

    $sql = "
        SELECT r.*,
            (SELECT GROUP_CONCAT(DISTINCT n.entity_name)
             FROM notifications n
             WHERE n.report_id = r.id AND n.entity_type = 'Brand') AS notified_brands,
            (SELECT COUNT(*)
             FROM notifications n
             WHERE n.report_id = r.id AND n.entity_type = 'LGA') AS notified_lga,
            ba.brand_name AS collected_by,
            c.collected_at AS collected_at,
            c.reversed_by_admin AS collection_reversed
        FROM reports r
        LEFT JOIN collections c ON c.report_id = r.id
            AND c.id = (SELECT MAX(c2.id) FROM collections c2 WHERE c2.report_id = r.id)
        LEFT JOIN brand_accounts ba ON c.brand_account_id = ba.id
    ";

    if (!empty($whereSql)) {
        $sql .= " WHERE " . implode(" AND ", $whereSql);
    }
    $sql .= " ORDER BY r.server_timestamp_utc DESC";

    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . (int)$filters['limit'];
        if (!empty($filters['offset'])) {
            $sql .= " OFFSET " . (int)$filters['offset'];
        }
    }

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(function ($row) {
            return reportRowToArray($row);
        }, $rows);
    } catch (PDOException $e) {
        error_log('PHP Database Error: Select failed: ' . $e->getMessage());
        return [];
    }
}


/**
 * Convert a database row to the client-facing array format.
 * Includes notification and collection status if joined data is present.
 */
function reportRowToArray(array $row) {
    $entry = [
        'id'                 => (int)$row['id'],
        'latitude'           => (string)$row['latitude'],
        'longitude'          => (string)$row['longitude'],
        'timestamp'          => $row['timestamp'],
        'brands'             => json_decode($row['brands'], true) ?: [],
        'conditions'         => json_decode($row['conditions'], true),
        'comments'           => $row['comments'],
        'suburb'             => $row['suburb'],
        'state'              => $row['state'],
        'lgaName'            => $row['lga_name'],
        'lgaEmail'           => $row['lga_email'],
        'serverTimestampUTC' => $row['server_timestamp_utc']
    ];

    // Add notification status if present (from enriched query)
    if (isset($row['notified_brands'])) {
        $entry['notifiedBrands'] = $row['notified_brands'] ? explode(',', $row['notified_brands']) : [];
    }
    if (isset($row['notified_lga'])) {
        $entry['notifiedLga'] = $row['notified_lga'] ? true : false;
    }

    // Add collection status if present (from enriched query)
    // Check collected_at first -- it comes directly from the collections table
    // and is reliable even if the brand account was deleted (collected_by would be NULL)
    if (array_key_exists('collected_at', $row) && !empty($row['collected_at'])) {
        $entry['collectedBy'] = $row['collected_by'] ?: 'Unknown';
        $entry['collectedAt'] = $row['collected_at'];
        $entry['collectionReversed'] = (int)($row['collection_reversed'] ?? 0) === 1;
    } elseif (array_key_exists('collected_by', $row) && !empty($row['collected_by'])) {
        $entry['collectedBy'] = $row['collected_by'];
        $entry['collectedAt'] = null;
        $entry['collectionReversed'] = (int)($row['collection_reversed'] ?? 0) === 1;
    } else {
        $entry['collectedBy'] = null;
        $entry['collectedAt'] = null;
        $entry['collectionReversed'] = false;
    }

    return $entry;
}


/**
 * Delete a report by ID.
 *
 * @param PDO $db
 * @param int $id
 * @return bool
 */
function deleteReport(PDO $db, int $id) {
    try {
        $stmt = $db->prepare("DELETE FROM reports WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('PHP Database Error: Delete failed: ' . $e->getMessage());
        return false;
    }
}


/**
 * Get report count.
 */
function getReportCount(PDO $db) {
    try {
        return (int)$db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}


/**
 * Find nearby duplicate reports using Haversine distance.
 * SQLite doesn't have trig functions, so we do a bounding box pre-filter
 * then calculate exact distance in PHP.
 *
 * @param PDO $db
 * @param float $lat
 * @param float $lon
 * @param float $radiusMetres
 * @param int $windowSeconds
 * @return array
 */
function findNearbyDuplicates(PDO $db, float $lat, float $lon, float $radiusMetres = 50, int $windowSeconds = 604800) {
    // Rough bounding box (1 degree latitude ~ 111km)
    $degreeOffset = ($radiusMetres / 111000) * 2; // Double for safety margin
    $cutoffTime = gmdate('Y-m-d\TH:i:sP', time() - $windowSeconds);

    try {
        $stmt = $db->prepare("
            SELECT id, latitude, longitude, brands, suburb, server_timestamp_utc
            FROM reports
            WHERE latitude BETWEEN :lat_min AND :lat_max
              AND longitude BETWEEN :lon_min AND :lon_max
              AND server_timestamp_utc >= :cutoff
        ");
        $stmt->execute([
            ':lat_min' => $lat - $degreeOffset,
            ':lat_max' => $lat + $degreeOffset,
            ':lon_min' => $lon - $degreeOffset,
            ':lon_max' => $lon + $degreeOffset,
            ':cutoff'  => $cutoffTime
        ]);
        $candidates = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('PHP Database Error: Duplicate check failed: ' . $e->getMessage());
        return [];
    }

    $duplicates = [];
    foreach ($candidates as $row) {
        $dLat = deg2rad($row['latitude'] - $lat);
        $dLon = deg2rad($row['longitude'] - $lon);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat)) * cos(deg2rad($row['latitude'])) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceMetres = 6371000 * $c;

        if ($distanceMetres <= $radiusMetres) {
            $duplicates[] = [
                'brands'         => json_decode($row['brands'], true) ?: [],
                'suburb'         => $row['suburb'],
                'timestamp'      => $row['server_timestamp_utc'],
                'distanceMetres' => round($distanceMetres, 1)
            ];
        }
    }

    return $duplicates;
}


// ==========================================================================
// NOTIFICATION FUNCTIONS
// ==========================================================================

/**
 * Record that a user confirmed sending an email notification.
 *
 * @param PDO $db
 * @param int $reportId
 * @param string $entityType -- 'Brand' or 'LGA'
 * @param string $entityName -- e.g., 'Coles' or 'City of Gosnells'
 * @return bool
 */
function recordNotification(PDO $db, int $reportId, string $entityType, string $entityName) {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (report_id, entity_type, entity_name)
            VALUES (:report_id, :entity_type, :entity_name)
        ");
        $stmt->execute([
            ':report_id'   => $reportId,
            ':entity_type' => $entityType,
            ':entity_name' => $entityName
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('PHP Database Error: Notification insert failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all notifications for a report.
 */
function getNotificationsForReport(PDO $db, int $reportId) {
    try {
        $stmt = $db->prepare("SELECT * FROM notifications WHERE report_id = :id ORDER BY notified_at");
        $stmt->execute([':id' => $reportId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}


// ==========================================================================
// BRAND ACCOUNT FUNCTIONS
// ==========================================================================

/**
 * Create a new brand account with a unique token.
 */
function createBrandAccount(PDO $db, string $brandName) {
    try {
        $token = bin2hex(random_bytes(32)); // 64-char hex token
        $stmt = $db->prepare("
            INSERT INTO brand_accounts (brand_name, token)
            VALUES (:brand_name, :token)
        ");
        $stmt->execute([
            ':brand_name' => $brandName,
            ':token'      => $token
        ]);
        return [
            'id'         => (int)$db->lastInsertId(),
            'brand_name' => $brandName,
            'token'      => $token
        ];
    } catch (PDOException $e) {
        error_log('PHP Database Error: Brand account creation failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get a brand account by token. Returns null if not found or inactive.
 */
function getBrandAccountByToken(PDO $db, string $token) {
    try {
        $stmt = $db->prepare("SELECT * FROM brand_accounts WHERE token = :token");
        $stmt->execute([':token' => $token]);
        $account = $stmt->fetch();
        return $account ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Update last login timestamp for a brand account.
 */
function updateBrandLastLogin(PDO $db, int $accountId) {
    try {
        $stmt = $db->prepare("UPDATE brand_accounts SET last_login = datetime('now') WHERE id = :id");
        $stmt->execute([':id' => $accountId]);
    } catch (PDOException $e) {
        // Non-critical, just log
        error_log('PHP Database Error: Last login update failed: ' . $e->getMessage());
    }
}

/**
 * Get all brand accounts.
 */
function getAllBrandAccounts(PDO $db) {
    try {
        return $db->query("SELECT * FROM brand_accounts ORDER BY brand_name")->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Toggle brand account active/inactive.
 */
function toggleBrandAccount(PDO $db, int $accountId, bool $isActive) {
    try {
        $stmt = $db->prepare("UPDATE brand_accounts SET is_active = :active WHERE id = :id");
        $stmt->execute([':active' => $isActive ? 1 : 0, ':id' => $accountId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Delete a brand account.
 */
function deleteBrandAccount(PDO $db, int $accountId) {
    try {
        $stmt = $db->prepare("DELETE FROM brand_accounts WHERE id = :id");
        $stmt->execute([':id' => $accountId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Regenerate token for a brand account.
 */
function regenerateBrandToken(PDO $db, int $accountId) {
    try {
        $newToken = bin2hex(random_bytes(32));
        $stmt = $db->prepare("UPDATE brand_accounts SET token = :token WHERE id = :id");
        $stmt->execute([':token' => $newToken, ':id' => $accountId]);
        return $newToken;
    } catch (PDOException $e) {
        return false;
    }
}


// ==========================================================================
// COLLECTION FUNCTIONS
// ==========================================================================

/**
 * Record a collection claim by a brand.
 * Allows re-collection if the previous collection was reversed.
 */
function recordCollection(PDO $db, int $reportId, int $brandAccountId) {
    try {
        // Check the most recent collection for this report
        $stmt = $db->prepare("
            SELECT id, reversed_by_admin FROM collections
            WHERE report_id = :report_id
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':report_id' => $reportId]);
        $latest = $stmt->fetch();

        // If the most recent collection is active (not reversed), reject
        if ($latest && (int)$latest['reversed_by_admin'] === 0) {
            return ['error' => 'duplicate', 'message' => 'Active collection already exists (collection #' . $latest['id'] . ')'];
        }

        // Temporarily disable FK checks for the INSERT -- the caller has already
        // verified both report_id and brand_account_id exist. This works around
        // stale FK references in SQLite tables created before schema was finalised.
        $db->exec('PRAGMA foreign_keys = OFF');

        $stmt = $db->prepare("
            INSERT INTO collections (report_id, brand_account_id)
            VALUES (:report_id, :brand_account_id)
        ");
        $stmt->execute([
            ':report_id'        => $reportId,
            ':brand_account_id' => $brandAccountId
        ]);
        $newId = (int)$db->lastInsertId();

        // Re-enable FK checks
        $db->exec('PRAGMA foreign_keys = ON');

        return $newId;
    } catch (PDOException $e) {
        // Ensure FK checks are re-enabled even on failure
        try { $db->exec('PRAGMA foreign_keys = ON'); } catch (PDOException $ignore) {}
        error_log('PHP Database Error: Collection record failed: ' . $e->getMessage());
        return ['error' => 'exception', 'message' => $e->getMessage()];
    }
}

/**
 * Reverse/undo a collection claim (admin action). Finds the most recent active
 * collection for the given report ID and marks it as reversed.
 */
function reverseCollection(PDO $db, int $reportId, string $notes = '') {
    try {
        $stmt = $db->prepare("
            SELECT id FROM collections
            WHERE report_id = :report_id AND reversed_by_admin = 0
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':report_id' => $reportId]);
        $collection = $stmt->fetch();

        if (!$collection) {
            return false;
        }

        $stmt = $db->prepare("
            UPDATE collections
            SET reversed_by_admin = 1, reversed_at = datetime('now'), admin_notes = :notes
            WHERE id = :id
        ");
        $stmt->execute([':id' => $collection['id'], ':notes' => $notes]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('PHP Database Error: Reverse collection failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Undo a reversal (admin restores a previously reversed collection).
 */
function undoReversal(PDO $db, int $reportId) {
    try {
        $stmt = $db->prepare("
            SELECT id FROM collections
            WHERE report_id = :report_id AND reversed_by_admin = 1
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':report_id' => $reportId]);
        $collection = $stmt->fetch();

        if (!$collection) return false;

        $stmt = $db->prepare("
            UPDATE collections
            SET reversed_by_admin = 0, reversed_at = NULL, admin_notes = NULL
            WHERE id = :id
        ");
        $stmt->execute([':id' => $collection['id']]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get all collections for a report.
 */
function getCollectionsForReport(PDO $db, int $reportId) {
    try {
        $stmt = $db->prepare("
            SELECT c.*, ba.brand_name
            FROM collections c
            JOIN brand_accounts ba ON c.brand_account_id = ba.id
            WHERE c.report_id = :id
            ORDER BY c.collected_at DESC
        ");
        $stmt->execute([':id' => $reportId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get collection statistics for a brand.
 */
function getCollectionStats(PDO $db, int $brandAccountId = 0) {
    try {
        $where = $brandAccountId > 0 ? "WHERE c.brand_account_id = :account_id" : "";
        $params = $brandAccountId > 0 ? [':account_id' => $brandAccountId] : [];

        // Total collections
        $stmt = $db->prepare("SELECT COUNT(*) FROM collections c $where");
        $stmt->execute($params);
        $totalCollections = (int)$stmt->fetchColumn();

        // Active (not reversed) collections
        $activeWhere = $brandAccountId > 0
            ? "WHERE c.brand_account_id = :account_id AND c.reversed_by_admin = 0"
            : "WHERE c.reversed_by_admin = 0";
        $stmt = $db->prepare("SELECT COUNT(*) FROM collections c $activeWhere");
        $stmt->execute($params);
        $activeCollections = (int)$stmt->fetchColumn();

        // Average time to collect (days) -- from report timestamp to collection timestamp
        $avgSql = "
            SELECT AVG(
                julianday(c.collected_at) - julianday(r.server_timestamp_utc)
            ) as avg_days
            FROM collections c
            JOIN reports r ON c.report_id = r.id
            " . ($brandAccountId > 0 ? "WHERE c.brand_account_id = :account_id AND" : "WHERE") . "
            c.reversed_by_admin = 0
        ";
        $stmt = $db->prepare($avgSql);
        $stmt->execute($params);
        $avgDays = $stmt->fetchColumn();
        $avgDays = $avgDays !== false ? round((float)$avgDays, 1) : null;

        // Reversed count
        $reversedWhere = $brandAccountId > 0
            ? "WHERE c.brand_account_id = :account_id AND c.reversed_by_admin = 1"
            : "WHERE c.reversed_by_admin = 1";
        $stmt = $db->prepare("SELECT COUNT(*) FROM collections c $reversedWhere");
        $stmt->execute($params);
        $reversedCount = (int)$stmt->fetchColumn();

        return [
            'totalCollections'  => $totalCollections,
            'activeCollections' => $activeCollections,
            'reversedCount'     => $reversedCount,
            'avgDaysToCollect'  => $avgDays
        ];
    } catch (PDOException $e) {
        error_log('PHP Database Error: Collection stats failed: ' . $e->getMessage());
        return ['totalCollections' => 0, 'activeCollections' => 0, 'reversedCount' => 0, 'avgDaysToCollect' => null];
    }
}

/**
 * Get a single report by ID with full enrichment.
 */
function getReportById(PDO $db, int $id) {
    try {
        $stmt = $db->prepare("
            SELECT r.*,
                (SELECT GROUP_CONCAT(DISTINCT n.entity_name)
                 FROM notifications n
                 WHERE n.report_id = r.id AND n.entity_type = 'Brand') AS notified_brands,
                (SELECT COUNT(*)
                 FROM notifications n
                 WHERE n.report_id = r.id AND n.entity_type = 'LGA') AS notified_lga,
                ba.brand_name AS collected_by,
                c.collected_at AS collected_at,
                c.reversed_by_admin AS collection_reversed
            FROM reports r
            LEFT JOIN collections c ON c.report_id = r.id
                AND c.id = (SELECT MAX(c2.id) FROM collections c2 WHERE c2.report_id = r.id)
            LEFT JOIN brand_accounts ba ON c.brand_account_id = ba.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? reportRowToArray($row) : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Cached suburb name (case-insensitive) => LGAKey lookup from suburb_lga_mapping.json.
 */
function getSuburbLgaKeyByName(string $suburbName): ?string
{
    static $index = null;
    if ($index === null) {
        $index = [];
        $path = __DIR__ . '/suburb_lga_mapping.json';
        if (is_readable($path)) {
            $json = file_get_contents($path);
            if ($json !== false) {
                $list = json_decode($json, true);
                if (is_array($list)) {
                    foreach ($list as $row) {
                        if (!isset($row['Suburb'], $row['LGAKey']) || !is_string($row['Suburb']) || !is_string($row['LGAKey'])) {
                            continue;
                        }
                        $key = strtolower($row['Suburb']);
                        if (!isset($index[$key])) {
                            $index[$key] = $row['LGAKey'];
                        }
                    }
                }
            }
        }
    }
    $lookup = strtolower($suburbName);
    return $index[$lookup] ?? null;
}
?>