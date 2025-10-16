<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View logged abandoned shopping trolleys">
    <title>Trolley List - Reported Abandoned Trolleys in WA</title>
<style>
/* ... CSS ... */
:root {
    --bg-color: #2a2a2a;
    --text-color: #e0e0e0;
    --card-bg: #3c3c3c;
    --border-color: #555;
    --button-bg: #007bff; /* Default primary button (used by Map button & Pagination buttons) */
    --button-hover-bg: #0056b3; /* Hover for primary/Map button & Pagination buttons */
    --button-text: #ffffff;
    --highlight: #ff9800;
    --highlight-hover: #e68a00;
    --detail-row-bg: #4a4a4a;
    --brand-button-bg: #6c757d; /* Default grey for toggles */
    --brand-button-hover-bg: #5a6268;
    --button-secondary-bg: #555; /* Used for other secondary actions if needed */
    --button-secondary-hover-bg: #777;
    --error-color: #dc3545;

    /* Define Brand Colors */
    --coles-bg: #ed1c22;
    --coles-hover-bg: #c8191e;
    --woolworths-bg: #54b848;
    --woolworths-hover-bg: #47a03d;
    --aldi-bg: #00005F;
    --aldi-hover-bg: #00004a;
    --iga-bg: #30302f;
    --iga-hover-bg: #20201f;
    --kmart-bg: #E92A28;
    --kmart-hover-bg: #c72321;
    --bigw-bg: #0046C8;
    --bigw-hover-bg: #0037a0;
    --bunnings-bg: #0d5257;
    --bunnings-hover-bg: #0b494e;
    --spudshed-bg: #a6cf4d;
    --spudshed-color:#8b380a;
    --spudshed-hover-bg:#84a53d;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); padding: 5px; }
.container { max-width: 1200px; margin: 0px auto; padding: 5px; border-radius: 8px; background-color: var(--card-bg); border: 1px solid var(--border-color); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2); overflow-x: auto; }
header { text-align: center; margin-bottom: 20px; margin-top: 10px; }
h1 { font-size: 1.8em; color: var(--highlight); }
h1 span { display: block; font-weight: normal; font-size: 0.47em; color: var(--text-color); margin-top:5px;}
    h1 icon {font-weight: normal; font-size: 0.9em; margin: -8px 0px 0px 0px; }
    .form-group { margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
th { background-color: #444; position: sticky; top: 0; color: var(--highlight); z-index: 1; font-size: 0.95em; }
th.sortable { cursor: pointer; }
th:hover { background-color: #555; }
td { background-color: var(--card-bg); vertical-align: middle; }
tr.detail-row td { background-color: var(--detail-row-bg); white-space: normal; }
/* Use simpler default sort icon '↕' */
.sortable::after { content: var(--sort-icon, " \2195"); font-size: 0.8em; opacity: 0.8; margin-left: 4px; }
.map-btn {
    padding: 6px 10px; background-color: var(--button-bg); color: var(--button-text); border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1em; text-align: center; white-space: normal; line-height: 1.2; min-width: 80px;
}
.map-btn:hover { background-color: var(--button-hover-bg); }
.brand-button-group { display: flex; flex-wrap: wrap; gap: 5px; }

/* Default Brand Toggle Style */
.brand-toggle {
    padding: 5px 8px; border-radius: 4px; color: #fff; border: none; cursor: pointer; font-size: 1em; background-color: var(--brand-button-bg); transition: background-color 0.2s; min-width: 60px; text-align: center; display: inline-block;
}
.brand-toggle:hover { background-color: var(--brand-button-hover-bg); }

/* Brand Specific Colors */
.brand-toggle[data-brand="Coles"] { background-color: var(--coles-bg); }
.brand-toggle[data-brand="Coles"]:hover { background-color: var(--coles-hover-bg); }
.brand-toggle[data-brand="Woolworths"] { background-color: var(--woolworths-bg); }
.brand-toggle[data-brand="Woolworths"]:hover { background-color: var(--woolworths-hover-bg); }
.brand-toggle[data-brand="Aldi"] { background-color: var(--aldi-bg); }
.brand-toggle[data-brand="Aldi"]:hover { background-color: var(--aldi-hover-bg); }
.brand-toggle[data-brand="IGA"] { background-color: var(--iga-bg); }
.brand-toggle[data-brand="IGA"]:hover { background-color: var(--iga-hover-bg); }
.brand-toggle[data-brand="Kmart"] { background-color: var(--kmart-bg); }
.brand-toggle[data-brand="Kmart"]:hover { background-color: var(--kmart-hover-bg); }
.brand-toggle[data-brand="BigW"] { background-color: var(--bigw-bg); }
.brand-toggle[data-brand="BigW"]:hover { background-color: var(--bigw-hover-bg); }
.brand-toggle[data-brand="Bunnings"] { background-color: var(--bunnings-bg); }
.brand-toggle[data-brand="Bunnings"]:hover { background-color: var(--bunnings-hover-bg); }
.brand-toggle[data-brand="Spudshed"] { background-color: var(--spudshed-bg); }
.brand-toggle[data-brand="Spudshed"] { color: var(--spudshed-color); }
.brand-toggle[data-brand="Spudshed"]:hover { background-color: var(--spudshed-hover-bg); }

.pagination { margin: 20px 0; text-align: center; }
/* *** MODIFICATION: Use primary blue for these links *** */
.pagination a.back-to-form {
    background-color: var(--button-bg); /* Use primary color */
    color: var(--button-text);
    text-decoration: none;
    padding: 8px 16px; /* Match button padding */
    font-size: 1em; /* Match button font size */
    border-radius: 5px;
    transition: background-color 0.2s ease;
    display: inline-block;
    vertical-align: middle;
    margin: 0 5px; /* Match button margin */
}
.pagination a.back-to-form:hover {
    background-color: var(--button-hover-bg); /* Use primary hover color */
    color: var(--button-text);
}
.pagination button, .pagination span { vertical-align: middle; margin: 0 5px; }
.pagination button { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; transition: background-color 0.2s ease; display: inline-block; }
.pagination button { background-color: var(--button-bg); color: var(--button-text); }
.pagination button:disabled { background-color: #666; cursor: not-allowed; opacity: 0.7; }
.pagination button:hover:not(:disabled) { background-color: var(--button-hover-bg); }
.pagination span { margin: 0 10px; display: inline-block; }
.pagination p { margin-top:5px;}


        /* Styles for Brand Links Section */
        .brand-links-container {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed var(--border-color);
            text-align: center;
        }
        .brand-links-container h2 {
             font-size: 1.1em;
             color: var(--text-color);
             margin-bottom: 5px;
             font-weight: normal;
        }
        .brand-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            padding: 0;
            list-style: none;
        }
        .brand-link-button {
            display: inline-block;
            padding: 10px 15px;
            font-size: 0.9em;
            text-align: center;
            border-radius: 5px;
            border: 1px solid transparent;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s ease, border-color 0.2s ease;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 100px;
            color: var(--button-text);
        }
        /* Brand Specific Colors for Links */
        .brand-link-button.brand-coles { background-color: #ed1c22; color: white; border-color: #ed1c22; }
        .brand-link-button.brand-coles:hover { background-color: #c8191e; border-color: #c8191e;}
        .brand-link-button.brand-woolworths { background-color: #54b848; color: white; border-color: #54b848; }
        .brand-link-button.brand-woolworths:hover { background-color: #47a03d; border-color: #47a03d;}
        .brand-link-button.brand-aldi { background-color: #00005F; color: white; border-color: #00005F; }
        .brand-link-button.brand-aldi:hover { background-color: #00004a; border-color: #00004a;}
        .brand-link-button.brand-iga { background-color: #D8C69D; color: #333333; border-color: #D8C69D; }
        .brand-link-button.brand-iga:hover { background-color: #c2b28d; border-color: #c2b28d;}
        .brand-link-button.brand-kmart { background-color: #E92A28; color: white; border-color: #E92A28; }
        .brand-link-button.brand-kmart:hover { background-color: #c72321; border-color: #c72321;}
        .brand-link-button.brand-bigw { background-color: #0046C8; color: white; border-color: #0046C8; }
        .brand-link-button.brand-bigw:hover { background-color: #0037a0; border-color: #0037a0;}
        .brand-link-button.brand-bunnings { background-color: #0d5257; color: white; border-color: #0d5257; }
        .brand-link-button.brand-bunnings:hover { background-color: #0b494e; border-color: #0b494e;}
        .brand-link-button.brand-spudshed { background-color: #a6cf4d; color: #8b380a; border-color: #0d5257; }
        .brand-link-button.brand-spudshed:hover { background-color: #84a53d; border-color: #0b494e;}
        .brand-link-button.brand-other { background-color: var(--rating-inactive-bg); color: var(--rating-inactive-text); border-color: var(--border-color);}
        .brand-link-button.brand-other:hover { background-color: var(--button-secondary-bg); border-color: var(--button-secondary-bg);}
        /* End Brand Specific Colors */




.detail-content { padding: 10px; background-color: var(--detail-row-bg); }
.detail-content h4 { margin-top: 0; margin-bottom: 10px; color: var(--highlight); font-size: 1.1em; }
.detail-content span { display: inline; margin-bottom: 8px; font-size: 0.95em; line-height: 1.4; margin-right: 5px;}
.detail-content strong { color: var(--highlight); margin-right: 5px; }
.no-details { font-style: italic; color: #aaa; display: block;}

.bottom-message { font-size: 0.8em; color: #aaa; text-align: center; margin-top: 15px; }
.loading-message { text-align: center; padding: 20px; font-size: 1.1em; }

.time-part {
    display: inline; margin-left: 5px; font-size: 0.9em; opacity: 0.8;
}

@media (max-width: 768px) {
     body { padding: 10px; }
     .container { padding: 5px; }
     table { font-size: 0.9em; }
     th, td { padding: 8px 6px; white-space: normal; }
     th { white-space: nowrap; font-size: 0.9em; }
     td:nth-child(1) { width: 20%; } /* Brand column */
     td:nth-child(2) { width: 22%; } /* Date column */
     td:nth-child(3) { width: 58%; white-space: normal; } /* Location column */
     .sortable::after { font-size: 1em; }
     .map-btn { padding: 5px 8px; font-size: 0.9em; min-width: 70px; }
     .brand-toggle { font-size: 0.9em; padding: 5px 6px; }
     .pagination button, .pagination a.back-to-form { padding: 6px 12px; font-size: 0.9em; margin-bottom: 8px; margin-left: 3px; margin-right: 3px; }
     .bottom-message {font-size: 0.75em;}
     h1 { font-size: 1.6em; }
     h1 span { font-size: 0.45em; margin-top: 3px;}
     .detail-content { padding: 3px; }
     .detail-content h4 { font-size: 1em; }
     .detail-content span { font-size: 10px; display: block; margin-bottom: 5px;}
     .no-details { display: block; }
     span.conditions_yes {margin-bottom:0px;}
     span.add_comments {margin-top:-15px;}
     .bottom-message { font-size: 0.6em; }
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
    const itemsPerPage = 30;
    let currentPage = 1;
    let sortColumn = "timestamp";
    let sortDirection = -1;

    document.addEventListener('DOMContentLoaded', () => { fetchLogs(); });

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
             const response = await fetch('trolley_logs.json?_=' + new Date().getTime());
             if (!response.ok) { throw new Error(`HTTP error! Status: ${response.status}`); }
             let data = await response.json();
             if (!Array.isArray(data)) { throw new Error("Invalid data format received."); }
             data = data.map((log, index) => { log._rowId = generateStableRowId(log, index); return log; });
             allLogs = data;
             sortLogs(); // Initial sort
             loadingDiv.style.display = 'none';
             tableContainer.style.display = 'block';
             updateTable(); // Render table first time
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

    function sortLogs() { /* ... Sorting logic (unchanged) ... */
         if (!sortColumn) return;
         allLogs.sort((a, b) => {
              let valA = a[sortColumn]; let valB = b[sortColumn];
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
              valA = String(valA || '').toLowerCase(); valB = String(valB || '').toLowerCase();
              if (valA < valB) return -1 * sortDirection; if (valA > valB) return 1 * sortDirection; return 0;
         });
     }


    function updateSortDirection(column) { /* ... Update sort state (unchanged) ... */
         if (sortColumn === column) { sortDirection *= -1; }
         else { sortColumn = column; sortDirection = (column === 'timestamp') ? -1 : 1; }
         sortLogs(); currentPage = 1; updateTable();
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


    function renderTable() { /* ... Render table rows (unchanged structure) ... */
         const tbody = document.getElementById('table-body');
         tbody.innerHTML = '';
         document.querySelectorAll('.detail-row').forEach(row => row.remove());
         const totalPages = Math.max(1, Math.ceil(allLogs.length / itemsPerPage));
         currentPage = Math.max(1, Math.min(currentPage, totalPages));
         const start = (currentPage - 1) * itemsPerPage;
         const end = start + itemsPerPage;
         const pageData = allLogs.slice(start, end);

         if (pageData.length === 0 && allLogs.length > 0) { currentPage = Math.max(1, currentPage - 1); renderTable(); return; }
         else if (pageData.length === 0) { tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No logs found.</td></tr>'; }

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
         updateSortIndicators(); // Update indicators after rendering
     }


     function toggleDetailRow(button) { /* ... Toggle detail row (unchanged structure) ... */
         const brand = button.dataset.brand; const targetRowId = button.dataset.targetRowId;
         const mainRow = document.querySelector(`tr[data-row-id="${targetRowId}"]`); if (!mainRow) { console.error("Could not find main row for ID:", targetRowId); return; }
         const existingDetailRow = document.getElementById(`detail-${targetRowId}-${brand}`); // Unique ID per brand
         document.querySelectorAll('.detail-row').forEach(row => row.remove()); // Close all details
         if (existingDetailRow) { return; } // If it was already open, just close it
         const logData = allLogs.find(log => log._rowId === targetRowId); if (!logData) { console.error("Could not find data for rowId:", targetRowId); alert("Error: Could not retrieve details."); return; }
         try {
             const detailRow = document.createElement('tr'); detailRow.id = `detail-${targetRowId}-${brand}`; detailRow.classList.add('detail-row');
             const detailCell = document.createElement('td'); detailCell.colSpan = 3;
             const conditions = (typeof logData.conditions === 'object' && logData.conditions !== null) ? logData.conditions : {};
             const brandConditions = conditions[brand] || []; const comments = logData.comments || '';
             let detailHtml = '<div class="detail-content">'; detailHtml += `<h4>Details for ${brand} Trolley</h4>`;
             if (brandConditions.length > 0) { const conditionsText = brandConditions.join(', '); const escapedConditionsText = conditionsText.replace(/</g, "<").replace(/>/g, ">"); detailHtml += `<span class="conditions_yes"><strong>Condition (${brand}):</strong> ${escapedConditionsText}</span><br>`; }
             else { detailHtml += `<span class="no-details">No specific conditions reported for ${brand} trolley.</span><br>`; }
             if (comments) { const escapedComments = comments.replace(/</g, "<").replace(/>/g, ">"); detailHtml += `<span class="add_comments" style="display:block;"><strong>General Comments:</strong> ${escapedComments.replace(/\n/g, '<br>')}</span>`; }
             else { detailHtml += '<span class="no-details">No general comments submitted.</span>'; }
             detailHtml += '</div>'; detailCell.innerHTML = detailHtml; detailRow.appendChild(detailCell);
             mainRow.parentNode.insertBefore(detailRow, mainRow.nextSibling);
         } catch (error) { console.error("Error occurred during detail row creation/insertion:", error); alert("An error occurred while trying to show details."); }
      }

    function updatePagination(totalPages) { /* ... Update pagination display (unchanged) ... */
         const pageInfo = document.getElementById('page-info'); const prevButton = document.getElementById('prev-page'); const nextButton = document.getElementById('next-page');
         pageInfo.textContent = `Page ${currentPage} of ${totalPages}`; prevButton.disabled = currentPage === 1; nextButton.disabled = currentPage === totalPages || totalPages === 0;
      }

    function changePage(direction) { /* ... Handle page change (unchanged) ... */
         const oldPage = currentPage; currentPage += direction;
         const totalPages = Math.max(1, Math.ceil(allLogs.length / itemsPerPage)); currentPage = Math.max(1, Math.min(currentPage, totalPages));
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