<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View logged abandoned shopping trolleys">
    <title>Trolley List - Reported Abandoned Trolleys in WA</title>
    <link rel="stylesheet" href="styles.css">
<style>
/* --- view_logs.php page-specific styles --- */
body { padding: 5px; }
.container { width: 100%; max-width: 1200px; margin: 10px auto; padding: 5px; border-radius: 8px; background-color: var(--card-bg); border: 1px solid var(--border-color); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2); }
header { text-align: center; margin-bottom: 20px; margin-top: 10px; }
h1 { font-size: 1.8em; color: var(--highlight); }
h1 span { display: block; font-weight: normal; font-size: 0.47em; color: var(--text-color); margin-top: 5px; }
h1 icon { font-weight: normal; font-size: 0.9em; }

/* Table */
table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
th { background-color: #444; color: var(--highlight); z-index: 1; font-size: 0.95em; }
th.sortable { cursor: pointer; }
th:hover { background-color: #555; }
td { background-color: var(--card-bg); vertical-align: middle; }
tr.detail-row td { background-color: var(--detail-row-bg); white-space: normal; }
.sortable::after { content: var(--sort-icon, " \2195"); font-size: 0.8em; opacity: 0.8; margin-left: 4px; }
.map-btn {
    padding: 6px 10px; background-color: var(--button-bg); color: var(--button-text);
    border: none; border-radius: 5px; cursor: pointer; text-decoration: none;
    display: inline-block; font-size: 1em; text-align: center; white-space: normal;
    line-height: 1.2; min-width: 80px;
}
.map-btn:hover { background-color: var(--button-hover-bg); }
.brand-button-group { display: flex; flex-wrap: wrap; gap: 5px; }

/* Pagination -- sticky to bottom of viewport */
.pagination {
    position: sticky; bottom: 0; z-index: 10;
    margin: 0; padding: 10px 0;
    text-align: center;
    background-color: var(--card-bg);
    border-top: 1px solid var(--border-color);
}
.pagination a.back-to-form {
    background-color: var(--button-bg); color: var(--button-text);
    text-decoration: none; padding: 8px 16px; font-size: 1em;
    border-radius: 5px; transition: background-color 0.2s ease;
    display: inline-block; vertical-align: middle; margin: 0 5px;
}
.pagination a.back-to-form:hover { background-color: var(--button-hover-bg); color: var(--button-text); }
.pagination button, .pagination span { vertical-align: middle; margin: 0 5px; }
.pagination button { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; transition: background-color 0.2s ease; display: inline-block; background-color: var(--button-bg); color: var(--button-text); }
.pagination button:disabled { background-color: #666; cursor: not-allowed; opacity: 0.7; }
.pagination button:hover:not(:disabled) { background-color: var(--button-hover-bg); }
.pagination span { margin: 0 10px; display: inline-block; }
.pagination p { margin-top: 5px; }

/* Time display */
.time-part { display: inline; margin-left: 5px; font-size: 0.9em; opacity: 0.8; }

/* Condition detail rows - no negative margins needed with block display */

@media (max-width: 768px) {
    body { padding: 10px; }
    .container { padding: 5px; }
    table { font-size: 0.9em; }
    th, td { padding: 8px 6px; white-space: normal; }
    th { white-space: nowrap; font-size: 0.9em; }
    td:nth-child(1) { width: 20%; }
    td:nth-child(2) { width: 22%; }
    td:nth-child(3) { width: 58%; white-space: normal; }
    .sortable::after { font-size: 1em; }
    .map-btn { padding: 5px 8px; font-size: 0.9em; min-width: 70px; }
    .brand-toggle { font-size: 0.9em; padding: 5px 6px; }
    .pagination button, .pagination a.back-to-form { padding: 6px 12px; font-size: 0.9em; margin-bottom: 8px; margin-left: 3px; margin-right: 3px; }
    h1 { font-size: 1.6em; }
    h1 span { font-size: 0.45em; margin-top: 3px; }
    .time-part { display: none; }
}
</style>
</head>
<body>
    <div class="container">
        <header>
            <h1>WA Trolley List <icon>🛒️</icon><span>Reported Abandoned Trolleys in WA</span></h1>
        </header>

        <div id="loading" class="loading-message">Loading...</div>
        <div id="table-container" style="display: none;">

            <!-- Filter Controls -->
            <div id="filter-bar" style="margin-bottom:15px; padding:10px; background-color:#333; border-radius:5px; border:1px solid var(--border-color);">
                <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                    <label style="font-size:0.85em; font-weight:bold; margin-right:5px;">Filter:</label>
                    <button type="button" class="brand-filter-btn active" data-brand="all" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:var(--button-bg); color:var(--button-text); font-weight:bold;">All</button>
                    <button type="button" class="brand-filter-btn" data-brand="Coles" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:var(--coles-bg); color:white;">CO</button>
                    <button type="button" class="brand-filter-btn" data-brand="Woolworths" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:var(--woolworths-bg); color:white;">WO</button>
                    <button type="button" class="brand-filter-btn" data-brand="Aldi" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:var(--aldi-bg); color:white;">AL</button>
                    <button type="button" class="brand-filter-btn" data-brand="IGA" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:var(--iga-bg); color:#333;">IG</button>
                    <button type="button" class="brand-filter-btn" data-brand="Kmart" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:var(--kmart-bg); color:white;">KM</button>
                    <button type="button" class="brand-filter-btn" data-brand="BigW" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:var(--bigw-bg); color:white;">BI</button>
                    <button type="button" class="brand-filter-btn" data-brand="Bunnings" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:var(--bunnings-bg); color:white;">BU</button>
                    <button type="button" class="brand-filter-btn" data-brand="Spudshed" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:var(--spudshed-bg); color:#8b380a;">SP</button>
                    <button type="button" class="brand-filter-btn" data-brand="Other" style="padding:5px 10px; font-size:0.85em; border-radius:4px; border:none; cursor:pointer; background-color:#555; color:#ccc;">OT</button>
                </div>
                <!-- Date filter row (hidden for now) -->
                <div style="display:none;">
                    <input type="date" id="date-from" title="Filter from date">
                    <input type="date" id="date-to" title="Filter to date">
                    <button type="button" id="clear-dates-btn">x</button>
                    <span id="filter-count"></span>
                </div>
            </div>

            <table id="logs-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="brands">Brand</th>
                        <th class="sortable" data-sort="timestamp">Date</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    </tbody>
            </table>
             <div class="pagination">
                <button id="prev-page" disabled>Previous</button>
                <span id="page-info">Page 1 of 1</span>
                <button id="next-page" disabled>Next</button><br /><p>
                <a href="map_view.html" class="back-to-form">Map</a>
                <a href="index.html" class="back-to-form">Report</a></p>
            </div>
            
            
        <!-- <div class="brand-links-container">
             <h2>Brand Maps</h2>
             <div class="brand-links">
                 <a href="map_coles.html" class="brand-link-button brand-coles">Coles</a>
                 <a href="map_woolworths.html" class="brand-link-button brand-woolworths">Woolworths</a>
                 <a href="map_aldi.html" class="brand-link-button brand-aldi">Aldi</a>
                 <a href="map_iga.html" class="brand-link-button brand-iga">IGA</a>
                 <a href="map_kmart.html" class="brand-link-button brand-kmart">Kmart</a>
                 <a href="map_bigw.html" class="brand-link-button brand-bigw">Big W</a>
                 <a href="map_bunnings.html" class="brand-link-button brand-bunnings">Bunnings</a>
                 <a href="map_other.html" class="brand-link-button brand-other">Other</a>
             </div>
        </div>  -->          
            
            
            <p class="bottom-message">*Location accuracy depends on device GPS.</p>
        </div>
         <div id="error-loading" class="loading-message" style="display: none; color: var(--error-color);"></div>
    </div>

<script>
    // --- State Abbreviation Mapping ---
    const stateAbbreviations = { /* ... */
        "Western Australia": "WA", "South Australia": "SA", "New South Wales": "NSW",
        "Queensland": "QLD", "Victoria": "VIC", "Tasmania": "TAS",
        "Northern Territory": "NT", "Australian Capital Territory": "ACT",
        "Jervis Bay Territory": "ACT"
     };
    function getStateAbbreviation(stateName) { /* ... */
         return stateName ? (stateAbbreviations[stateName] || stateName) : null;
     }

    // --- Custom Brand Text Mapping ---
    const customBrandText = { /* ... */
         "Coles": "CO", "Woolworths": "WO", "Aldi": "AL", "IGA": "IG",
         "Kmart": "KM", "BigW": "BI", "Bunnings": "BU", "Spudshed": "SP", "Other": "OT"
     };

    // --- Data Loading and Initialization ---
    let allLogs = [];
    let filteredLogs = []; // Subset of allLogs after brand/date filtering
    const itemsPerPage = 30;
    let currentPage = 1;
    let sortColumn = "timestamp";
    let sortDirection = -1;

    // --- Filter State ---
    let activeBrandFilter = 'all'; // 'all' or a brand identifier
    let dateFromFilter = ''; // YYYY-MM-DD or empty
    let dateToFilter = '';   // YYYY-MM-DD or empty

    document.addEventListener('DOMContentLoaded', () => {
        fetchLogs();
        setupFilterListeners();
    });

    /** Apply brand and date filters to allLogs, store in filteredLogs */
    function applyFilters() {
        filteredLogs = allLogs.filter(log => {
            // Brand filter
            if (activeBrandFilter !== 'all') {
                const brands = Array.isArray(log.brands) ? log.brands : [];
                if (!brands.includes(activeBrandFilter)) return false;
            }
            // Date range filter
            if (dateFromFilter || dateToFilter) {
                const ts = log.serverTimestampUTC || log.timestamp || '';
                const logDate = ts.substring(0, 10); // YYYY-MM-DD
                if (!logDate) return false;
                if (dateFromFilter && logDate < dateFromFilter) return false;
                if (dateToFilter && logDate > dateToFilter) return false;
            }
            return true;
        });

        // Update filter count display
        const filterCount = document.getElementById('filter-count');
        if (filterCount) {
            if (activeBrandFilter !== 'all' || dateFromFilter || dateToFilter) {
                filterCount.textContent = filteredLogs.length + ' of ' + allLogs.length + ' reports';
            } else {
                filterCount.textContent = allLogs.length + ' reports';
            }
        }

        sortLogs();
        currentPage = 1;
        updateTable();
    }

    /** Set up filter button and date input event listeners */
    function setupFilterListeners() {
        // Brand filter buttons -- bold the active one, normal weight for the rest
        document.querySelectorAll('.brand-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.brand-filter-btn').forEach(b => {
                    b.classList.remove('active');
                    b.style.fontWeight = 'normal';
                });
                btn.classList.add('active');
                btn.style.fontWeight = 'bold';
                activeBrandFilter = btn.dataset.brand;
                applyFilters();
            });
        });

        // Date range filters
        const dateFrom = document.getElementById('date-from');
        const dateTo = document.getElementById('date-to');
        const clearDatesBtn = document.getElementById('clear-dates-btn');

        if (dateFrom) {
            dateFrom.addEventListener('change', () => {
                dateFromFilter = dateFrom.value || '';
                applyFilters();
            });
        }
        if (dateTo) {
            dateTo.addEventListener('change', () => {
                dateToFilter = dateTo.value || '';
                applyFilters();
            });
        }
        if (clearDatesBtn) {
            clearDatesBtn.addEventListener('click', () => {
                dateFromFilter = '';
                dateToFilter = '';
                if (dateFrom) dateFrom.value = '';
                if (dateTo) dateTo.value = '';
                applyFilters();
            });
        }
    }

    function generateStableRowId(log, index) { /* ... */
         const tsPart = log.serverTimestampUTC || log.timestamp || `no-ts-${index}`;
         const ipPart = log.ipAddress || 'no-ip';
         return `log-${tsPart}-${ipPart}-${index}`;
     }

    async function fetchLogs() { /* ... */
         const loadingDiv = document.getElementById('loading');
         const errorDiv = document.getElementById('error-loading');
         const tableContainer = document.getElementById('table-container');
         try {
             const response = await fetch('api_logs.php');
             if (!response.ok) { throw new Error(`HTTP error! Status: ${response.status}`); }
             let data = await response.json();
             if (!Array.isArray(data)) { throw new Error("Invalid data format received."); }
             data = data.map((log, index) => { log._rowId = generateStableRowId(log, index); return log; });
             allLogs = data;
             sortLogs(); // Initial sort
             filteredLogs = allLogs.slice(); // Start with all logs
             applyFilters(); // Apply any active filters and render
             loadingDiv.style.display = 'none';
             tableContainer.style.display = 'block';
             setupEventListeners();
         } catch (error) {
             console.error("Error loading logs:", error);
             loadingDiv.style.display = 'none';
             errorDiv.textContent = `Error loading logs: ${error.message}. Please try refreshing.`;
             errorDiv.style.display = 'block';
         }
     }

    // Date and Time formatters
    function formatDisplayDate(isoTimestamp) { /* ... */
         if (!isoTimestamp) return 'N/A';
         try {
             const date = new Date(isoTimestamp);
             if (isNaN(date.getTime())) {
                  const alternativeDate = new Date(isoTimestamp.replace('T', ' ').replace(/-/g, '/'));
                  return !isNaN(alternativeDate.getTime()) ? alternativeDate.toLocaleString('en-AU', { timeZone: 'Australia/Perth', dateStyle: 'short' }) : isoTimestamp;
             }
             return date.toLocaleString('en-AU', { timeZone: 'Australia/Perth', dateStyle: 'short' });
         } catch (e) { console.error("Date formatting error for:", isoTimestamp, e); return isoTimestamp; }
     }
     function formatDisplayTime(isoTimestamp) { /* ... */
         if (!isoTimestamp) return '';
         try {
             const date = new Date(isoTimestamp);
              if (isNaN(date.getTime())) {
                  const alternativeDate = new Date(isoTimestamp.replace('T', ' ').replace(/-/g, '/'));
                  return !isNaN(alternativeDate.getTime()) ? alternativeDate.toLocaleString('en-AU', { timeZone: 'Australia/Perth', timeStyle: 'short', hour12: true }) : '';
              }
              return date.toLocaleString('en-AU', { timeZone: 'Australia/Perth', timeStyle: 'short', hour12: true });
         } catch (e) { console.error("Time formatting error for:", isoTimestamp, e); return ''; }
     }

    function sortLogs() {
         if (!sortColumn) return;
         filteredLogs.sort((a, b) => {
              if (sortColumn === 'brands') {
                  const brandsA = Array.isArray(a.brands) ? a.brands : []; const brandsB = Array.isArray(b.brands) ? b.brands : [];
                  const firstBrandA = brandsA.length > 0 ? String(brandsA[0]).toLowerCase() : ''; const firstBrandB = brandsB.length > 0 ? String(brandsB[0]).toLowerCase() : '';
                  if (firstBrandA < firstBrandB) return -1 * sortDirection; if (firstBrandA > firstBrandB) return 1 * sortDirection; return 0;
              }
              if (sortColumn === 'timestamp') {
                  const timeA = new Date(a.serverTimestampUTC || a.timestamp || 0).getTime(); const timeB = new Date(b.serverTimestampUTC || b.timestamp || 0).getTime();
                  const validTimeA = isNaN(timeA) ? 0 : timeA; const validTimeB = isNaN(timeB) ? 0 : timeB;
                  return (validTimeA - validTimeB) * sortDirection;
              }
              if (sortColumn === 'status') {
                  const statusOrder = { 'collected': 3, 'notified': 2, 'reported': 1 };
                  function getStatusRank(log) {
                      if (log.collectedBy && !log.collectionReversed) return statusOrder.collected;
                      if ((log.notifiedBrands && log.notifiedBrands.length > 0) || log.notifiedLga) return statusOrder.notified;
                      return statusOrder.reported;
                  }
                  return (getStatusRank(a) - getStatusRank(b)) * sortDirection;
              }
              const valA = String(a[sortColumn] || '').toLowerCase(); const valB = String(b[sortColumn] || '').toLowerCase();
              if (valA < valB) return -1 * sortDirection; if (valA > valB) return 1 * sortDirection; return 0;
         });
     }


    function updateSortDirection(column) { /* ... Update sort state (unchanged) ... */
         if (sortColumn === column) { sortDirection *= -1; }
         else { sortColumn = column; sortDirection = (column === 'timestamp') ? -1 : 1; }
         sortLogs(); currentPage = 1; renderTable(); updateSortIndicators();
     }

     function updateSortIndicators() {
          // *** Use simpler arrow characters ***
          document.querySelectorAll('#logs-table th.sortable').forEach(th => {
              th.style.setProperty('--sort-icon', '"\u00A0\u2195"'); // Default: space + up/down arrow '↕'
              if (th.dataset.sort === sortColumn) {
                  const indicator = sortDirection === 1 ? '\u00A0\u25B2' : '\u00A0\u25BC'; // space + Up/Down triangles '▲' '▼'
                  th.style.setProperty('--sort-icon', `"${indicator}"`);
              }
          });
     }


    function renderTable() { /* ... Render table rows ... */
         const tbody = document.getElementById('table-body');
         tbody.innerHTML = '';
         document.querySelectorAll('.detail-row').forEach(row => row.remove());
         const totalPages = Math.max(1, Math.ceil(filteredLogs.length / itemsPerPage));
         currentPage = Math.max(1, Math.min(currentPage, totalPages));
         const start = (currentPage - 1) * itemsPerPage;
         const end = start + itemsPerPage;
         const pageData = filteredLogs.slice(start, end);

         if (pageData.length === 0 && filteredLogs.length > 0) { currentPage = Math.max(1, currentPage - 1); renderTable(); return; }
         else if (pageData.length === 0) { tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No logs found matching filters.</td></tr>'; }

         pageData.forEach((log) => {
             if (typeof log !== 'object' || log === null || !log._rowId) { console.warn("Skipping invalid log entry:", log); return; }
             const rowId = log._rowId;
             const tr = document.createElement('tr');
             tr.dataset.rowId = rowId;

             // Brands Cell
             const brands = Array.isArray(log.brands) ? log.brands : [];
             const tdBrand = document.createElement('td');
             const brandGroup = document.createElement('div');
             brandGroup.className = 'brand-button-group';
             if (brands.length > 0) { brands.forEach(brand => { const button = document.createElement('button'); button.className = 'brand-toggle'; button.textContent = customBrandText[brand] || brand; button.dataset.brand = brand; button.dataset.targetRowId = rowId; button.type = 'button'; brandGroup.appendChild(button); }); }
             else { brandGroup.textContent = 'N/A'; }
             tdBrand.appendChild(brandGroup);

             // Date/Time Cell
             const tdDateTime = document.createElement('td');
             const timestamp = log.serverTimestampUTC || log.timestamp;
             tdDateTime.textContent = formatDisplayDate(timestamp);
             const timeSpan = document.createElement('span');
             timeSpan.className = 'time-part';
             timeSpan.textContent = formatDisplayTime(timestamp);
             tdDateTime.appendChild(timeSpan);

             // Location Cell
             const tdMap = document.createElement('td');
             if (log.latitude && log.longitude) {
                 const lat = log.latitude; const lon = log.longitude;
                 if (Math.abs(parseFloat(lat)) <= 90 && Math.abs(parseFloat(lon)) <= 180) {
                    const mapLink = document.createElement('a');
                    mapLink.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(lat)},${encodeURIComponent(lon)}`;
                    mapLink.target = '_blank'; mapLink.rel = 'noopener noreferrer'; mapLink.className = 'map-btn';
                    const suburb = log.suburb || null;
                    const stateFullName = log.state || null;
                    const stateAbbr = getStateAbbreviation(stateFullName);
                    let buttonText = 'Map';
                    if (suburb && stateAbbr) { buttonText = `${suburb}, ${stateAbbr}`; }
                    else if (suburb) { buttonText = suburb; }
                    else if (stateAbbr) { buttonText = stateAbbr; }
                    else if (stateFullName) { buttonText = stateFullName; }
                    mapLink.textContent = buttonText;
                    tdMap.appendChild(mapLink);
                 } else { tdMap.textContent = 'Invalid Coords'; }
             } else { tdMap.textContent = ''; }

             tr.appendChild(tdBrand);
             tr.appendChild(tdDateTime);
             tr.appendChild(tdMap);
             tbody.appendChild(tr);
         });
         updatePagination(totalPages);
         updateSortIndicators();
     }


     function toggleDetailRow(button) {
         const brand = button.dataset.brand; const targetRowId = button.dataset.targetRowId;
         const mainRow = document.querySelector(`tr[data-row-id="${targetRowId}"]`); if (!mainRow) return;
         const existingDetailRow = document.getElementById(`detail-${targetRowId}-${brand}`);
         document.querySelectorAll('.detail-row').forEach(row => row.remove());
         if (existingDetailRow) { return; }
         const logData = filteredLogs.find(log => log._rowId === targetRowId); if (!logData) return;
         try {
             const detailRow = document.createElement('tr'); detailRow.id = `detail-${targetRowId}-${brand}`; detailRow.classList.add('detail-row');
             const detailCell = document.createElement('td'); detailCell.colSpan = 3;
             const conditions = (typeof logData.conditions === 'object' && logData.conditions !== null) ? logData.conditions : {};
             const brandConditions = conditions[brand] || []; const comments = logData.comments || '';
             let detailHtml = '<div class="detail-content">'; detailHtml += `<h4>Details for ${brand} Trolley</h4>`;
             if (brandConditions.length > 0) { const conditionsText = brandConditions.join(', '); const escapedConditionsText = conditionsText.replace(/</g, "<").replace(/>/g, ">"); detailHtml += `<span class="conditions_yes"><strong>Conditions Reported:</strong> ${escapedConditionsText}</span>`; }
             else { detailHtml += `<span class="no-details">No specific conditions reported.</span>`; }
             if (comments) { const escapedComments = comments.replace(/</g, "<").replace(/>/g, ">"); detailHtml += `<span class="add_comments"><strong>General Comments:</strong> ${escapedComments.replace(/\n/g, '<br>')}</span>`; }

             // Status section -- uses .report_status class (same styling as .conditions_yes)
             const hasNotifiedBrands = logData.notifiedBrands && logData.notifiedBrands.length > 0;
             const hasNotifiedLga = logData.notifiedLga;
             const isCollected = logData.collectedBy && !logData.collectionReversed;
             let statusParts = [];

             if (hasNotifiedBrands) {
                 const dateStr = logData.serverTimestampUTC ? formatDisplayDate(logData.serverTimestampUTC) : '';
                 statusParts.push('Reported to ' + logData.notifiedBrands.join(', ') + (dateStr ? ' at ' + dateStr : ''));
             }
             if (hasNotifiedLga && logData.lgaName) {
                 const dateStr = logData.serverTimestampUTC ? formatDisplayDate(logData.serverTimestampUTC) : '';
                 statusParts.push('Reported to ' + logData.lgaName + (dateStr ? ' at ' + dateStr : ''));
             }
             if (isCollected) {
                 const collectedDate = logData.collectedAt ? formatDisplayDate(logData.collectedAt) : '';
                 statusParts.push('Reported as "collected" by ' + logData.collectedBy + (collectedDate ? ' at ' + collectedDate : ''));
             }
             if (statusParts.length > 0) {
                 detailHtml += `<span class="report_status"><strong>Status:</strong> ${statusParts.join('; ')}</span>`;
             }

             detailHtml += '</div>'; detailCell.innerHTML = detailHtml; detailRow.appendChild(detailCell);
             mainRow.parentNode.insertBefore(detailRow, mainRow.nextSibling);
         } catch (error) { console.error("Error occurred during detail row creation/insertion:", error); alert("An error occurred while trying to show details."); }
      }

    function updatePagination(totalPages) { /* ... Update pagination display (unchanged) ... */
         const pageInfo = document.getElementById('page-info'); const prevButton = document.getElementById('prev-page'); const nextButton = document.getElementById('next-page');
         pageInfo.textContent = `Page ${currentPage} of ${totalPages}`; prevButton.disabled = currentPage === 1; nextButton.disabled = currentPage === totalPages || totalPages === 0;
      }

    function changePage(direction) { /* ... Handle page change ... */
         const oldPage = currentPage; currentPage += direction;
         const totalPages = Math.max(1, Math.ceil(filteredLogs.length / itemsPerPage)); currentPage = Math.max(1, Math.min(currentPage, totalPages));
         if (currentPage !== oldPage) { document.querySelectorAll('.detail-row').forEach(row => row.remove()); renderTable(); }
      }

    function setupEventListeners() { /* ... Set up event listeners (unchanged structure) ... */
         const thead = document.querySelector('#logs-table thead'); if (thead) { thead.addEventListener('click', (event) => { const th = event.target.closest('th.sortable'); if (th) { updateSortDirection(th.dataset.sort); } }); }
         document.getElementById('prev-page').addEventListener('click', () => changePage(-1)); document.getElementById('next-page').addEventListener('click', () => changePage(1));
         const tableBody = document.getElementById('table-body'); if (tableBody) { tableBody.addEventListener('click', (event) => { const button = event.target.closest('.brand-toggle'); if (button) { toggleDetailRow(button); } }); }
         else { console.error("Could not find table body element (#table-body) to attach listener."); }
      }

    function updateTable() { /* ... Helper to render table and update indicators ... */
         renderTable();
      }
</script>

</body>
</html>