<?php
/**
 * brand_portal.php -- Brand Collection Portal
 *
 * Token-authenticated page for trolley brand owners to:
 * - View reports on a Leaflet map with coloured markers
 * - Expand detail rows showing conditions, comments, status
 * - Mark reports as collected / undo collections
 * - View collection statistics
 *
 * Access via: brand_portal.php?token=<64-char-hex-token>
 *
 * Version: 2.0
 */

require_once __DIR__ . '/db.php';

$db = getDatabase();
$account = null;
$error = '';

// Authenticate via token
$token = $_GET['token'] ?? '';
if (!empty($token) && strlen($token) === 64 && ctype_xdigit($token)) {
    if ($db !== null) {
        $account = getBrandAccountByToken($db, $token);
        if ($account && (int)$account['is_active']) {
            updateBrandLastLogin($db, (int)$account['id']);
        } elseif ($account && !(int)$account['is_active']) {
            $account = null;
            $error = 'This account has been suspended. Please contact admin@sandgroper.net.';
        } else {
            $error = 'Invalid or expired access link.';
        }
    } else {
        $error = 'Service temporarily unavailable.';
    }
} elseif (!empty($token)) {
    $error = 'Invalid access link format.';
}

// Get stats and base brand name if authenticated
$stats = null;
$baseBrand = '';
if ($account) {
    $stats = getCollectionStats($db, (int)$account['id']);
    // Extract base brand from account name (e.g. "Coles 1" -> "Coles")
    $baseBrand = preg_replace('/\s+\d+$/', '', $account['brand_name']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo $account ? htmlspecialchars($account['brand_name']) . ' Portal' : 'Brand Portal'; ?> - Trolley Report</title>

<?php if ($account): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
 integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
 crossorigin=""/>

<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css"
 integrity="sha256-+bdWuWOXMFkX0v9Cvr3OWClPiYefDQz9GGZP/7xZxdc="
 crossorigin="anonymous" />

<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css"
 integrity="sha256-LWhzWaQGZRsWFrrJxg+6Zn8TT84k0/trtiHBc6qcGpY="
 crossorigin="anonymous" />

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
 integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
 crossorigin=""></script>

<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"
 integrity="sha256-WL6HHfYfbFEkZOFdsJQeY7lJG/E5airjvqbznghUzRw="
 crossorigin="anonymous"></script>
<?php endif; ?>

    <link rel="stylesheet" href="styles.css">
    <style>
        /* --- brand_portal.php page-specific styles --- */
        body { padding: 5px; }
        .container {
            width: 100%; max-width: 1200px; margin: 10px auto; padding: 15px;
            border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            background-color: var(--card-bg); border: 1px solid var(--border-color);
            display: flex; flex-direction: column; flex-grow: 1;
        }
        h1 { color: var(--text-color); margin-bottom: 15px; font-size: 1.8em; text-align: center; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        h1 span { display: block; font-size: 0.5em; font-weight: normal; }

        /* Stats cards */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 20px; }
        .stat-card { background-color: var(--input-bg); padding: 12px; border-radius: 5px; text-align: center; border: 1px solid var(--border-color); }
        .stat-card .stat-value { font-size: 1.8em; font-weight: bold; color: var(--highlight); }
        .stat-card .stat-label { font-size: 0.8em; color: #aaa; }

        /* Map */
        #map { width: 100%; height: 400px; border-radius: 5px; border: 1px solid var(--border-color); margin-bottom: 20px; flex-shrink: 0; }

        /* Table */
        .table-section { flex-grow: 1; display: flex; flex-direction: column; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; font-size: 1em; }
        th { background-color: #444; position: sticky; top: 0; color: var(--highlight); z-index: 1; }
        th.sortable { cursor: pointer; }
        th.sortable:hover { background-color: #555; }
        td { background-color: var(--card-bg); vertical-align: middle; }
        tr.detail-row td { background-color: var(--detail-row-bg); white-space: normal; }
        .sortable::after { content: var(--sort-icon, " \2195"); font-size: 1em; opacity: 0.8; margin-left: 4px; }
        .map-btn { padding: 5px 8px; background-color: var(--button-bg); color: var(--button-text); border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1em; text-align: center; line-height: 1.2; min-width: 70px; }
        .map-btn:hover { background-color: var(--button-hover-bg); }
        .brand-button-group { display: flex; flex-wrap: wrap; gap: 4px; }
        .time-part { display: inline; margin-left: 5px; font-size: 0.9em; opacity: 0.8; }

        /* Action buttons */
        .collect-btn {
            padding: 4px 10px; font-size: 0.8em; border: none; border-radius: 4px;
            cursor: pointer; background-color: #28a745; color: white; transition: background-color 0.2s;
        }
        .collect-btn:hover { background-color: #218838; }
        .collect-btn:disabled { background-color: #555; cursor: not-allowed; }
        .undo-btn {
            padding: 4px 10px; font-size: 0.8em; border: none; border-radius: 4px;
            cursor: pointer; background-color: #ff9800; color: white; transition: background-color 0.2s;
        }
        .undo-btn:hover { background-color: #e68a00; }
        .undo-btn:disabled { background-color: #555; cursor: not-allowed; }

        /* Pagination */
        /* Pagination -- sticky to bottom of viewport */
        .pagination {
            position: sticky; bottom: 0; z-index: 10;
            margin: 0; padding: 10px 0;
            text-align: center; flex-shrink: 0;
            background-color: var(--card-bg);
            border-top: 1px solid var(--border-color);
        }
        .pagination button, .pagination span { vertical-align: middle; margin: 0 4px; }
        .pagination button { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; transition: background-color 0.2s ease; display: inline-block; background-color: var(--button-bg); color: var(--button-text); }
        .pagination button:disabled { background-color: #666; cursor: not-allowed; opacity: 0.7; }
        .pagination button:hover:not(:disabled) { background-color: var(--button-hover-bg); }
        .pagination span { margin: 0 8px; display: inline-block; }

        .error-loading { text-align: center; padding: 20px; font-size: 1.1em; }
        .error-msg { text-align: center; padding: 40px 20px; }
        .error-msg p { font-size: 1.1em; color: var(--error-color); }

        /* Toast notification */
        .portal-toast {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background-color: #28a745; color: white; padding: 8px 20px;
            border-radius: 5px; font-size: 0.85em; z-index: 9999;
            opacity: 0; transition: opacity 0.3s ease; pointer-events: none;
        }
        .portal-toast.show { opacity: 1; }

        .back-link { display: inline-block; padding: 10px 15px; background-color: var(--button-bg); color: var(--button-text); border-radius: 5px; text-decoration: none; transition: background-color 0.2s ease; margin: 5px; }
        .back-link:hover { background-color: var(--button-secondary-hover-bg); text-decoration: none; color: var(--button-text); }

        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 5px; }
            h1 { font-size: 1.5em; }
            #map { height: 300px; }
            table { font-size: 0.85em; }
            th, td { padding: 6px 8px; white-space: normal; }
            th { white-space: nowrap; font-size: 1em; }
            .map-btn { padding: 4px 6px; font-size: 1em; min-width: 60px; }
            .pagination button { padding: 5px 10px; font-size: 1em; }
            .detail-content { padding: 5px; }
            .detail-content h4 { font-size: 0.95em; }
            .detail-content span { font-size: 0.85em; }
            .time-part { display: none; }
            .back-link { padding: 8px 12px; font-size: 0.9em; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
        }

        /* MarkerCluster overrides */
        .marker-cluster-small { background-color: rgba(181, 226, 140, 0.6); }
        .marker-cluster-small div { background-color: rgba(110, 204, 57, 0.6); }
        .marker-cluster-medium { background-color: rgba(241, 211, 87, 0.6); }
        .marker-cluster-medium div { background-color: rgba(240, 194, 12, 0.6); }
        .marker-cluster-large { background-color: rgba(253, 156, 115, 0.6); }
        .marker-cluster-large div { background-color: rgba(241, 128, 23, 0.6); }
        .marker-cluster div { width: 30px; height: 30px; margin-left: 5px; margin-top: 5px; text-align: center; border-radius: 15px; font: 12px "Helvetica Neue", Arial, Helvetica, sans-serif; color: #fff; line-height: 30px; text-shadow: 1px 1px 1px #000; }
        .marker-cluster { border-radius: 50%; box-shadow: 0 0 5px rgba(0,0,0,0.5); }
    </style>
</head>
<body>
    <div class="container">
    <div id="portal-toast" class="portal-toast"></div>

<?php if (!$account): ?>
    <!-- NOT AUTHENTICATED -->
    <h1>Brand Portal</h1>
    <div class="error-msg">
        <p><?php echo htmlspecialchars($error ?: 'Please use the access link provided to you by the Trolley Report administrator.'); ?></p>
        <a href="index.html" class="back-link" style="margin-top:20px;">Back to Report Form</a>
    </div>

<?php else: ?>
    <!-- AUTHENTICATED BRAND VIEW -->
    <h1><?php echo htmlspecialchars($account['brand_name']); ?> Portal &#x1F6D2;
        <span>Trolley Collection Management</span>
    </h1>

    <!-- Stats -->
    <div class="stat-grid" id="stat-cards"></div>

    <!-- Map -->
    <div id="map"></div>

    <!-- Table -->
    <div class="table-section">
        <div id="loading" class="loading-message">Loading data...</div>
        <div id="table-container" style="display: none;">
            <table id="logs-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="timestamp">Date</th>
                        <th>Location</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="table-body"></tbody>
            </table>
        </div>
        <div id="error-loading" class="loading-message error-loading" style="display: none;"></div>
        <p class="bottom-message">*Location accuracy depends on device GPS.</p>
        <div class="pagination">
            <button id="prev-page" disabled>Previous</button>
            <span id="page-info">Page 1 of 1</span>
            <button id="next-page" disabled>Next</button><br /><p>
            <!-- <a href="map_brand.html?brand=<?php echo urlencode($baseBrand); ?>" class="back-link">Brand Map</a>
            <a href="index.html" class="back-link">Report Form</a> --></p>
        </div>
    </div>

<script>
'use strict';
document.addEventListener('DOMContentLoaded', () => {

    // --- Auth config (injected by PHP) ---
    const token = '<?php echo htmlspecialchars($token, ENT_QUOTES); ?>';
    const brandName = '<?php echo htmlspecialchars($account['brand_name'], ENT_QUOTES); ?>';
    const baseBrand = '<?php echo htmlspecialchars($baseBrand, ENT_QUOTES); ?>';

    // --- Fix Leaflet default icon paths ---
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png'
    });


    // =====================================================================
    // SHARED HELPERS
    // =====================================================================

    const stateAbbreviations = {
        "Western Australia": "WA", "South Australia": "SA", "New South Wales": "NSW",
        "Queensland": "QLD", "Victoria": "VIC", "Tasmania": "TAS",
        "Northern Territory": "NT", "Australian Capital Territory": "ACT",
        "Jervis Bay Territory": "ACT"
    };

    /** Convert full state name to abbreviation */
    function getStateAbbreviation(stateName) {
        return stateName ? (stateAbbreviations[stateName] || stateName) : null;
    }

    /** Escape HTML special characters */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /** Format timestamp for display */
    function formatDisplayDateTime(isoTimestamp, includeTime) {
        if (!isoTimestamp) return 'N/A';
        try {
            const options = { timeZone: 'Australia/Perth', dateStyle: 'short' };
            if (includeTime) { options.timeStyle = 'short'; options.hour12 = true; }
            const date = new Date(isoTimestamp);
            if (isNaN(date.getTime())) {
                const altDate = new Date(isoTimestamp.replace('T', ' ').replace(/-/g, '/'));
                return !isNaN(altDate.getTime()) ? altDate.toLocaleString('en-AU', options) : isoTimestamp;
            }
            return date.toLocaleString('en-AU', options);
        } catch (e) { return isoTimestamp; }
    }

    function formatDisplayDate(ts) { return formatDisplayDateTime(ts, false); }

    function formatDisplayTime(isoTimestamp) {
        if (!isoTimestamp) return '';
        try {
            const date = new Date(isoTimestamp);
            if (isNaN(date.getTime())) return '';
            return date.toLocaleString('en-AU', { timeZone: 'Australia/Perth', timeStyle: 'short', hour12: true });
        } catch (e) { return ''; }
    }

    /** Generate a stable row ID */
    function generateStableRowId(log, index) {
        const tsPart = log.serverTimestampUTC || log.timestamp || 'no-ts-' + index;
        const ipPart = log.ipAddress || 'no-ip';
        return 'log-portal-' + tsPart + '-' + ipPart + '-' + index;
    }

    /** Get conditions text for the target brand */
    function getConditionsText(conditions) {
        if (typeof conditions !== 'object' || conditions === null) return '';
        const brandConds = conditions[baseBrand] || [];
        if (!Array.isArray(brandConds)) return '';
        return [...new Set(brandConds)].join(', ');
    }

    /** Show toast notification */
    function showToast(msg, duration) {
        duration = duration || 1500;
        const toast = document.getElementById('portal-toast');
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => { toast.classList.remove('show'); }, duration);
    }


    // =====================================================================
    // MAP INITIALISATION
    // =====================================================================

    const initialCenter = [-31.9523, 115.8613];
    const initialZoom = 10;
    const map = L.map('map').setView(initialCenter, initialZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const markerClusterGroup = L.markerClusterGroup();
    map.addLayer(markerClusterGroup);
    setTimeout(() => { map.invalidateSize(); }, 200);


    // =====================================================================
    // TABLE STATE
    // =====================================================================

    let tableLogs = [];
    const itemsPerPage = 15;
    let currentPage = 1;
    let sortColumn = 'timestamp';
    let sortDirection = -1;
    const markerMap = {}; // Maps _rowId to Leaflet marker for map interaction

    const loadingDiv = document.getElementById('loading');
    const errorDiv = document.getElementById('error-loading');
    const tableContainer = document.getElementById('table-container');
    const tableBody = document.getElementById('table-body');
    const pageInfo = document.getElementById('page-info');
    const prevButton = document.getElementById('prev-page');
    const nextButton = document.getElementById('next-page');
    const tableHead = document.querySelector('#logs-table thead');


    // =====================================================================
    // DATA LOADING
    // =====================================================================

    /** Fetch log data, filter by brand, populate map and table */
    async function loadAndProcessData() {
        loadingDiv.style.display = 'block';
        tableContainer.style.display = 'none';
        errorDiv.style.display = 'none';
        markerClusterGroup.clearLayers();

        try {
            const response = await fetch('api_logs.php?brand=' + encodeURIComponent(baseBrand));
            if (!response.ok) throw new Error('HTTP error! Status: ' + response.status);
            let allLogs = await response.json();
            if (!Array.isArray(allLogs)) throw new Error('Invalid data format received.');

            const filteredLogs = allLogs.map((log, index) => {
                log._rowId = generateStableRowId(log, index);
                return log;
            });

            // Update stats cards
            updateStats(filteredLogs);

            // Populate map
            populateMap(filteredLogs);

            // Populate table
            tableLogs = filteredLogs;
            sortLogs();
            currentPage = 1;
            updateTable();

            loadingDiv.style.display = 'none';
            tableContainer.style.display = 'block';

        } catch (error) {
            console.error('Error loading data:', error);
            loadingDiv.style.display = 'none';
            errorDiv.textContent = 'Error loading data: ' + error.message + '. Please try refreshing.';
            errorDiv.style.display = 'block';
        }
    }

    /** Update stat cards from loaded data */
    function updateStats(logs) {
        let pending = 0;
        let collected = 0;
        logs.forEach(log => {
            const isCollected = ((log.collectedBy && log.collectedBy !== null) || (log.collectedAt && log.collectedAt !== null)) && !log.collectionReversed;
            if (isCollected) { collected++; } else { pending++; }
        });
        const container = document.getElementById('stat-cards');
        container.innerHTML = ''
            + '<div class="stat-card"><div class="stat-value">' + logs.length + '</div><div class="stat-label">Total Reports</div></div>'
            + '<div class="stat-card"><div class="stat-value">' + collected + '</div><div class="stat-label">Collected</div></div>'
            + '<div class="stat-card"><div class="stat-value">' + pending + '</div><div class="stat-label">Pending</div></div>';
    }

    /** Create Leaflet markers */
    function populateMap(logsToMap) {
        const markerIcons = {
            blue: new L.Icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
            }),
            orange: new L.Icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-orange.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
            }),
            green: new L.Icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
            }),
            red: new L.Icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
            })
        };

        function getMarkerIcon(log) {
            if (log.collectedBy && !log.collectionReversed) return markerIcons.green;
            if ((log.notifiedBrands && log.notifiedBrands.length > 0) || log.notifiedLga) return markerIcons.orange;
            return markerIcons.blue;
        }

        let markersFound = 0;
        logsToMap.forEach(log => {
            const lat = parseFloat(log.latitude);
            const lon = parseFloat(log.longitude);
            if (!isNaN(lat) && !isNaN(lon) && Math.abs(lat) <= 90 && Math.abs(lon) <= 180) {
                const marker = L.marker([lat, lon], { icon: getMarkerIcon(log) });

                // Build popup
                let popupContent = '<b>Brand:</b> ' + escapeHtml(baseBrand) + '<br>';
                popupContent += '<b>Listed:</b> ' + formatDisplayDateTime(log.serverTimestampUTC || log.timestamp, true) + '<br>';

                // Status -- only show if notified or collected
                const hasNotifiedBrands = log.notifiedBrands && log.notifiedBrands.length > 0;
                const hasNotifiedLga = log.notifiedLga;
                const isCollected = log.collectedBy && !log.collectionReversed;
                let statusParts = [];

                if (hasNotifiedBrands) {
                    const d = formatDisplayDate(log.serverTimestampUTC || log.timestamp);
                    statusParts.push('Reported to ' + log.notifiedBrands.map(escapeHtml).join(', ') + (d && d !== 'N/A' ? ' ' + d : ''));
                }
                if (hasNotifiedLga && log.lgaName) {
                    const d = formatDisplayDate(log.serverTimestampUTC || log.timestamp);
                    statusParts.push('Reported to ' + escapeHtml(log.lgaName) + (d && d !== 'N/A' ? ' ' + d : ''));
                }
                if (isCollected) {
                    const d = log.collectedAt ? formatDisplayDate(log.collectedAt) : '';
                    statusParts.push('Collected by ' + escapeHtml(log.collectedBy) + (d && d !== 'N/A' ? ' ' + d : ''));
                }
                if (statusParts.length > 0) {
                    popupContent += '<b>Status:</b> ' + statusParts.join('; ') + '<br>';
                }

                if (log.suburb && log.state) {
                    popupContent += '<b>Location:</b> ' + escapeHtml(log.suburb) + ', ' + escapeHtml(log.state) + '<br>';
                }
                const conditionsText = getConditionsText(log.conditions);
                if (conditionsText) {
                    popupContent += '<b>Condition(s):</b> ' + escapeHtml(conditionsText) + '<br>';
                }
                if (log.comments) {
                    popupContent += '<b>Comments:</b> ' + escapeHtml(log.comments);
                }
                marker.bindPopup(popupContent);
                markerClusterGroup.addLayer(marker);
                // Store marker reference for table-to-map interaction
                if (log._rowId) { markerMap[log._rowId] = marker; }
                markersFound++;
            }
        });

        if (markersFound === 0) {
            map.setView(initialCenter, initialZoom);
        } else {
            const bounds = markerClusterGroup.getBounds();
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
            } else {
                map.setView(initialCenter, initialZoom);
            }
        }
        map.invalidateSize();
    }


    // =====================================================================
    // TABLE RENDERING
    // =====================================================================

    /** Sort table data by timestamp */
    function sortLogs() {
        if (sortColumn !== 'timestamp') return;
        tableLogs.sort((a, b) => {
            const timeA = new Date(a.serverTimestampUTC || a.timestamp || 0).getTime();
            const timeB = new Date(b.serverTimestampUTC || b.timestamp || 0).getTime();
            return ((isNaN(timeA) ? 0 : timeA) - (isNaN(timeB) ? 0 : timeB)) * sortDirection;
        });
    }

    /** Toggle sort direction */
    function updateSortDirection(column) {
        if (column !== 'timestamp') return;
        if (sortColumn === column) { sortDirection *= -1; }
        else { sortColumn = column; sortDirection = -1; }
        sortLogs();
        currentPage = 1;
        updateTable();
    }

    /** Update sort indicator arrows */
    function updateSortIndicators() {
        document.querySelectorAll('#logs-table th.sortable').forEach(th => {
            th.style.setProperty('--sort-icon', '""');
            if (th.dataset.sort === 'timestamp' && sortColumn === 'timestamp') {
                const indicator = sortDirection === 1 ? ' \u25B2' : ' \u25BC';
                th.style.setProperty('--sort-icon', '"' + indicator + '"');
            }
        });
    }

    /** Render table rows for current page */
    function renderTable() {
        tableBody.innerHTML = '';
        document.querySelectorAll('.detail-row').forEach(row => row.remove());

        const totalPages = Math.max(1, Math.ceil(tableLogs.length / itemsPerPage));
        currentPage = Math.max(1, Math.min(currentPage, totalPages));
        const start = (currentPage - 1) * itemsPerPage;
        const pageData = tableLogs.slice(start, start + itemsPerPage);

        if (pageData.length === 0 && tableLogs.length > 0) {
            currentPage = Math.max(1, currentPage - 1);
            renderTable();
            return;
        } else if (pageData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No reports found.</td></tr>';
        }

        pageData.forEach(log => {
            if (typeof log !== 'object' || log === null || !log._rowId) return;
            const rowId = log._rowId;
            const reportId = log.id || 0;
            const tr = document.createElement('tr');
            tr.dataset.rowId = rowId;

            // Date cell (clickable to toggle details)
            const tdDate = document.createElement('td');
            const dateBtn = document.createElement('button');
            dateBtn.className = 'brand-toggle';
            dateBtn.dataset.brand = baseBrand;
            dateBtn.dataset.targetRowId = rowId;
            dateBtn.type = 'button';
            const timestamp = log.serverTimestampUTC || log.timestamp;
            dateBtn.textContent = formatDisplayDate(timestamp);
            tdDate.appendChild(dateBtn);

            // Location cell
            const tdMap = document.createElement('td');
            if (log.latitude && log.longitude) {
                const lat = log.latitude;
                const lon = log.longitude;
                if (Math.abs(parseFloat(lat)) <= 90 && Math.abs(parseFloat(lon)) <= 180) {
                    const mapLink = document.createElement('a');
                    mapLink.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lon);
                    mapLink.target = '_blank';
                    mapLink.rel = 'noopener noreferrer';
                    mapLink.className = 'map-btn';
                    const suburb = log.suburb || null;
                    const stateAbbr = getStateAbbreviation(log.state || null);
                    let buttonText = 'Map';
                    if (suburb && stateAbbr) { buttonText = escapeHtml(suburb) + ', ' + escapeHtml(stateAbbr); }
                    else if (suburb) { buttonText = escapeHtml(suburb); }
                    else if (stateAbbr) { buttonText = escapeHtml(stateAbbr); }
                    mapLink.textContent = buttonText;
                    tdMap.appendChild(mapLink);
                } else { tdMap.textContent = 'Invalid Coords'; }
            } else { tdMap.textContent = ''; }

            // Action cell
            const isCollected = ((log.collectedBy && log.collectedBy !== null) || (log.collectedAt && log.collectedAt !== null)) && !log.collectionReversed;
            const tdAction = document.createElement('td');
            if (isCollected) {
                tdAction.innerHTML = '<button type="button" class="undo-btn" data-report-id="' + reportId + '">Undo</button>';
            } else {
                tdAction.innerHTML = '<button type="button" class="collect-btn" data-report-id="' + reportId + '">Mark Collected</button>';
            }

            tr.appendChild(tdDate);
            tr.appendChild(tdMap);
            tr.appendChild(tdAction);
            tableBody.appendChild(tr);
        });

        updatePagination(totalPages);
        updateSortIndicators();
    }

    /** Toggle detail row showing conditions and comments */
    function toggleDetailRow(button) {
        const brand = button.dataset.brand;
        const targetRowId = button.dataset.targetRowId;
        const mainRow = document.querySelector('tr[data-row-id="' + targetRowId + '"]');
        if (!mainRow) return;

        const detailRowId = 'detail-' + targetRowId + '-' + brand;
        const existing = document.getElementById(detailRowId);

        // Close all other detail rows
        document.querySelectorAll('.detail-row').forEach(row => {
            if (row.id !== detailRowId) row.remove();
        });

        // Toggle off if already open
        if (existing) { existing.remove(); return; }

        const logData = tableLogs.find(log => log._rowId === targetRowId);
        if (!logData) return;

        // Pan map to this marker and open its popup
        const marker = markerMap[targetRowId];
        if (marker) {
            // Spiderfy the cluster if needed, then open popup
            markerClusterGroup.zoomToShowLayer(marker, () => {
                marker.openPopup();
            });
        }

        try {
            const detailRow = document.createElement('tr');
            detailRow.id = detailRowId;
            detailRow.classList.add('detail-row');
            const detailCell = document.createElement('td');
            detailCell.colSpan = 3;

            const conditions = (typeof logData.conditions === 'object' && logData.conditions !== null) ? logData.conditions : {};
            const comments = logData.comments || '';
            const brandConditionsText = getConditionsText(conditions);

            let html = '<div class="detail-content">';
            html += '<h4>Details for ' + escapeHtml(baseBrand) + ' Trolley</h4>';
            if (brandConditionsText) {
                html += '<span class="conditions_yes"><strong>Conditions Reported:</strong> ' + escapeHtml(brandConditionsText) + '</span>';
            } else {
                html += '<span class="no-details">No specific conditions reported.</span>';
            }
            if (comments) {
                html += '<span class="add_comments"><strong>General Comments:</strong> ' + escapeHtml(comments).replace(/\n/g, '<br>') + '</span>';
            }

            // Status -- only if notified or collected
            const hasNotifiedBrands = logData.notifiedBrands && logData.notifiedBrands.length > 0;
            const hasNotifiedLga = logData.notifiedLga;
            const isCollected = logData.collectedBy && !logData.collectionReversed;
            let statusParts = [];
            if (hasNotifiedBrands) {
                const d = logData.serverTimestampUTC ? new Date(logData.serverTimestampUTC).toLocaleDateString('en-AU', { timeZone: 'Australia/Perth', dateStyle: 'short' }) : '';
                statusParts.push('Reported to ' + logData.notifiedBrands.map(escapeHtml).join(', ') + (d ? ' at ' + d : ''));
            }
            if (hasNotifiedLga && logData.lgaName) {
                const d = logData.serverTimestampUTC ? new Date(logData.serverTimestampUTC).toLocaleDateString('en-AU', { timeZone: 'Australia/Perth', dateStyle: 'short' }) : '';
                statusParts.push('Reported to ' + escapeHtml(logData.lgaName) + (d ? ' at ' + d : ''));
            }
            if (isCollected) {
                const d = logData.collectedAt ? new Date(logData.collectedAt + 'Z').toLocaleDateString('en-AU', { timeZone: 'Australia/Perth', dateStyle: 'short' }) : '';
                statusParts.push('Reported as "collected" by ' + escapeHtml(logData.collectedBy) + (d ? ' at ' + d : ''));
            }
            if (statusParts.length > 0) {
                html += '<span class="report_status"><strong>Status:</strong> ' + statusParts.join('; ') + '</span>';
            }

            html += '</div>';
            detailCell.innerHTML = html;
            detailRow.appendChild(detailCell);
            mainRow.parentNode.insertBefore(detailRow, mainRow.nextSibling);
        } catch (error) {
            console.error('Error creating detail row:', error);
        }
    }

    /** Update pagination controls */
    function updatePagination(totalPages) {
        pageInfo.textContent = 'Page ' + currentPage + ' of ' + totalPages;
        prevButton.disabled = currentPage === 1;
        nextButton.disabled = currentPage === totalPages || totalPages === 0;
    }

    /** Navigate pages */
    function changePage(direction) {
        const oldPage = currentPage;
        currentPage += direction;
        const totalPages = Math.max(1, Math.ceil(tableLogs.length / itemsPerPage));
        currentPage = Math.max(1, Math.min(currentPage, totalPages));
        if (currentPage !== oldPage) {
            document.querySelectorAll('.detail-row').forEach(row => row.remove());
            renderTable();
        }
    }

    function updateTable() { renderTable(); }


    // =====================================================================
    // EVENT LISTENERS (attached once)
    // =====================================================================

    // Sort header clicks
    if (tableHead) {
        tableHead.addEventListener('click', (event) => {
            const th = event.target.closest('th.sortable');
            if (th) { updateSortDirection(th.dataset.sort); }
        });
    }

    // Pagination
    prevButton.addEventListener('click', () => changePage(-1));
    nextButton.addEventListener('click', () => changePage(1));

    // Detail row toggle (brand-toggle buttons)
    tableBody.addEventListener('click', (event) => {
        const button = event.target.closest('.brand-toggle');
        if (button) { toggleDetailRow(button); }
    });

    // Collect / Undo action buttons (attached once on tableBody)
    tableBody.addEventListener('click', async (event) => {
        // --- Collect ---
        const collectBtn = event.target.closest('.collect-btn');
        if (collectBtn) {
            const reportId = collectBtn.dataset.reportId;
            collectBtn.disabled = true;
            collectBtn.textContent = 'Saving...';
            try {
                const resp = await fetch('api_collections.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'collect', reportId: parseInt(reportId), token: token })
                });
                const result = await resp.json();
                if (result.status === 'success') {
                    showToast('Marked as collected');
                    loadAndProcessData();
                } else {
                    alert('Error: ' + result.message);
                    collectBtn.disabled = false;
                    collectBtn.textContent = 'Mark Collected';
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                collectBtn.disabled = false;
                collectBtn.textContent = 'Mark Collected';
            }
            return;
        }

        // --- Undo collection ---
        const undoBtn = event.target.closest('.undo-btn');
        if (undoBtn) {
            const reportId = undoBtn.dataset.reportId;
            undoBtn.disabled = true;
            undoBtn.textContent = 'Undoing...';
            try {
                const resp = await fetch('api_collections.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'uncollect', reportId: parseInt(reportId), token: token })
                });
                const result = await resp.json();
                if (result.status === 'success') {
                    showToast('Collection undone');
                    loadAndProcessData();
                } else {
                    alert('Error: ' + result.message);
                    undoBtn.disabled = false;
                    undoBtn.textContent = 'Undo';
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                undoBtn.disabled = false;
                undoBtn.textContent = 'Undo';
            }
        }
    });


    // =====================================================================
    // INITIAL LOAD
    // =====================================================================

    loadAndProcessData();
});
</script>

<?php endif; ?>

    </div>
</body>
</html>