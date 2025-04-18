<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View logged abandoned shopping trolleys">
    <title>Trolley List - Reported Abandoned Trolleys</title>
<style>
:root {
    --bg-color: #2a2a2a;
    --text-color: #e0e0e0;
    --card-bg: #3c3c3c;
    --border-color: #555;
    --button-bg: #007bff; /* Default primary button (used by Map button) */
    --button-hover-bg: #0056b3; /* Hover for primary/Map button */
    --button-text: #ffffff;
    --highlight: #ff9800;
    --highlight-hover: #e68a00;
    --detail-row-bg: #4a4a4a;
    --brand-button-bg: #6c757d; /* Default grey for toggles */
    --brand-button-hover-bg: #5a6268;
    --button-secondary-bg: #555; /* Original grey for Back button */
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
    /* Add Bunnings color if needed */
    --bunnings-bg: #0d5257; /* Example, check if you have this defined */
    --bunnings-hover-bg: #0b494e; /* Example */
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); padding: 15px; }
.container { max-width: 1200px; margin: 0px auto; padding: 10px; border-radius: 8px; background-color: var(--card-bg); border: 1px solid var(--border-color); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2); overflow-x: auto; }
header { text-align: center; margin-bottom: 20px; margin-top: 10px; }
h1 { font-size: 1.8em; color: var(--highlight); }
h1 span { display: block; font-weight: normal; font-size: 0.47em; color: var(--text-color); margin-top:5px;}
    h1 icon {font-weight: normal; font-size: 0.9em; margin: -8px 0px 0px 0px; }
    .form-group { margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
th { background-color: #444; cursor: pointer; position: sticky; top: 0; color: var(--highlight); z-index: 1; }
th:hover { background-color: #555; }
td { background-color: var(--card-bg); vertical-align: middle; }
tr.detail-row td { background-color: var(--detail-row-bg); white-space: normal; }
.sortable::after { content: var(--sort-icon, " ⇅"); font-size: 0.8em; opacity: 0.8; margin-left: 4px; } /* Corrected font-size */
.map-btn { padding: 6px 12px; background-color: var(--button-bg); color: var(--button-text); border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9em; }
.map-btn:hover { background-color: var(--button-hover-bg); }
.brand-button-group { display: flex; flex-wrap: wrap; gap: 5px; }

/* Default Brand Toggle Style */
.brand-toggle {
    padding: 5px 8px; border-radius: 4px; color: #fff; border: none;
    cursor: pointer; font-size: 1em;
    background-color: var(--brand-button-bg);
    transition: background-color 0.2s;
    min-width: 60px; text-align: center; display: inline-block;
}
.brand-toggle:hover {
    background-color: var(--brand-button-hover-bg);
}

/* --- Brand Specific Colors for Toggles --- */
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
.brand-toggle[data-brand="Bunnings"] { background-color: var(--bunnings-bg); } /* Added */
.brand-toggle[data-brand="Bunnings"]:hover { background-color: var(--bunnings-hover-bg); } /* Added */
/* --- End Brand Specific Colors --- */

.pagination { margin: 20px 0; text-align: center; }
/* Base style for pagination items */
.pagination button, .pagination .back-to-form, .pagination span {
    vertical-align: middle; /* Align items vertically */
    margin: 0 5px; /* Horizontal margin */
}
/* Base style for pagination buttons (Prev/Next + Back) */
.pagination button, .pagination .back-to-form {
    padding: 8px 16px; /* Base padding */
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1em; /* Base font size */
    transition: background-color 0.2s ease;
    display: inline-block; /* Default display */
 }
 /* Specific styles for Prev/Next buttons */
.pagination button {
    background-color: var(--button-bg); /* Uses primary blue */
    color: var(--button-text);
}
.pagination button:disabled {
    background-color: #666;
    cursor: not-allowed;
    opacity: 0.7;
}
.pagination button:hover:not(:disabled) {
    background-color: var(--button-hover-bg);
}
.pagination span { margin: 0 10px; display: inline-block; } /* Ensure span aligns */

/* Styling for Back to Form button (to match Map button appearance) */
.back-to-form {
    background-color: var(--button-bg); /* Use primary button color */
    color: var(--button-text);
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    vertical-align: middle;
    margin: 0 5px;
    transition: background-color 0.2s ease;
    /* Explicitly match Map button padding & font-size */
    padding: 6px 12px;
    font-size: 0.9em;
}
.back-to-form:hover {
    background-color: var(--button-hover-bg); /* Use primary button hover color */
    color: var(--button-text);
}

.detail-content { padding: 10px; background-color: var(--detail-row-bg); }
.detail-content h4 { margin-top: 0; margin-bottom: 10px; color: var(--highlight); font-size: 1.1em; }
.detail-content span { display: inline; margin-bottom: 8px; font-size: 0.95em; line-height: 1.4; margin-right: 5px;} /* Make spans inline */
.detail-content strong { color: var(--highlight); margin-right: 5px; }
.no-details { font-style: italic; color: #aaa; display: block;} /* Keep no-details as block */
/* Removed ul/li styles */

.bottom-message { font-size: 0.8em; color: #aaa; text-align: center; margin-top: 15px; }
.loading-message { text-align: center; padding: 20px; font-size: 1.1em; }

/* --- Media Query for smaller screens --- */
@media (max-width: 768px) {
     body { padding: 10px; }
     .container { padding: 5px; }
     table { font-size: 0.9em; }
     th, td { padding: 8px 6px; white-space: normal; }
     th { white-space: nowrap; }
     .sortable::after { font-size: 0.8em; } /* Corrected font size */
     .map-btn { padding: 7px 10px; font-size: 0.9em; }
     .brand-toggle { font-size: 0.9em; padding: 5px 6px; }
     /* Adjust all pagination items for smaller screens */
     .pagination button, .pagination .back-to-form {
         padding: 6px 12px;
         font-size: 0.9em;
         margin-bottom: 8px;
         margin-left: 3px;
         margin-right: 3px;
     }
     .pagination .back-to-form {
         margin-top: 10px;
     }
     .bottom-message {font-size: 0.75em;}
     h1 { font-size: 1.6em; }
     h1 span { font-size: 0.45em; margin-top: 3px;}
     .detail-content { padding: 3px; }
     .detail-content h4 { font-size: 1em; }
     .detail-content span { font-size: 10px; display: block; margin-bottom: 5px;} /* Keep spans as block in mobile */
     .no-details { display: block; }

    span.conditions_yes {margin-bottom:0px;}
    span.add_comments {margin-top:-15px;}


     .bottom-message { font-size: 0.6em; }
}
</style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Trolley List <icon>🛒</icon><span>Reported Abandoned Trolleys</span></h1>
        </header>

        <div id="loading" class="loading-message">Loading...</div>
        <div id="table-container" style="display: none;">
            <table id="logs-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="brands">Brand</th>
                        <th class="sortable" data-sort="timestamp">Date/Time</th>
                        <th>Map</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                </tbody>
            </table>
             <div class="pagination">
                <button id="prev-page" disabled>Previous</button>
                <span id="page-info">Page 1 of 1</span>
                <button id="next-page" disabled>Next</button>
                <a href="index.html" class="back-to-form">Back to Form</a>
            </div>
            <p class="bottom-message">*Location accuracy depends on device GPS.</p>
        </div>
         <div id="error-loading" class="loading-message" style="display: none; color: var(--error-color);"></div>
    </div>

<script>
    // --- *** NEW: CUSTOM BRAND TEXT MAPPING *** ---
    // Define your custom text for each brand button here
    const customBrandText = {
        "Coles": "CO",
        "Woolworths": "WO",
        "Aldi": "AL",
        "IGA": "IG",
        "Kmart": "KM",
        "BigW": "BI",
        "Bunnings": "BU",
        "Other": "OT"
        // Add mappings for any other brands you expect
    };
    // --- *** END OF CUSTOM BRAND TEXT MAPPING *** ---

    // --- Data Loading and Initialization ---
    let allLogs = [];
    const itemsPerPage = 15;
    let currentPage = 1;
    let sortColumn = "timestamp";
    let sortDirection = -1;
    let globalLogCounter = 0;

    document.addEventListener('DOMContentLoaded', () => {
        fetchLogs();
    });

    function generateStableRowId(log, index) {
        const tsPart = log.serverTimestampUTC || log.timestamp || `no-ts-${index}`;
        const ipPart = log.ipAddress || 'no-ip';
        return `log-${tsPart}-${ipPart}-${index}`;
    }

    async function fetchLogs() {
        const loadingDiv = document.getElementById('loading');
        const errorDiv = document.getElementById('error-loading');
        const tableContainer = document.getElementById('table-container');
        try {
            const response = await fetch('trolley_logs.json?_=' + new Date().getTime());
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            let data = await response.json();
            if (!Array.isArray(data)) {
                 throw new Error("Invalid data format received.");
            }
            data = data.map((log, index) => {
                log._rowId = generateStableRowId(log, index);
                return log;
            });
            allLogs = data;
            sortLogs();
            loadingDiv.style.display = 'none';
            tableContainer.style.display = 'block';
            updateTable();
            setupEventListeners();
        } catch (error) {
            console.error("Error loading logs:", error); // Keep this log
            loadingDiv.style.display = 'none';
            errorDiv.textContent = `Error loading logs: ${error.message}. Please try refreshing.`;
            errorDiv.style.display = 'block';
        }
    }

    function formatDisplayTimestamp(isoTimestamp) {
        if (!isoTimestamp) return 'N/A';
        try {
            const date = new Date(isoTimestamp);
            if (isNaN(date.getTime())) {
                return isoTimestamp;
            }
            return date.toLocaleString('en-AU', {
                timeZone: 'Australia/Perth',
                dateStyle: 'short',
                timeStyle: 'short',
                hour12: true
            });
        } catch (e) {
            console.warn("Timestamp formatting error for:", isoTimestamp, e);
            return isoTimestamp;
        }
    }

    function sortLogs() {
        if (!sortColumn) return;
        allLogs.sort((a, b) => {
             let valA = a[sortColumn];
             let valB = b[sortColumn];
             if (sortColumn === 'brands') {
                 const brandsA = Array.isArray(a.brands) ? a.brands : [];
                 const brandsB = Array.isArray(b.brands) ? b.brands : [];
                 const multiA = brandsA.length > 1;
                 const multiB = brandsB.length > 1;
                 if (!multiA && multiB) return -1 * sortDirection;
                 if (multiA && !multiB) return 1 * sortDirection;
                 const firstBrandA = brandsA.length > 0 ? String(brandsA[0]).toLowerCase() : '';
                 const firstBrandB = brandsB.length > 0 ? String(brandsB[0]).toLowerCase() : '';
                 if (firstBrandA < firstBrandB) return -1 * sortDirection;
                 if (firstBrandA > firstBrandB) return 1 * sortDirection;
                 return 0;
             }
             if (sortColumn === 'timestamp') {
                 const timeA = new Date(a.serverTimestampUTC || a.timestamp || 0).getTime();
                 const timeB = new Date(b.serverTimestampUTC || b.timestamp || 0).getTime();
                 const validTimeA = isNaN(timeA) ? 0 : timeA;
                 const validTimeB = isNaN(timeB) ? 0 : timeB;
                 return (validTimeA - validTimeB) * sortDirection;
             }
             valA = String(valA).toLowerCase();
             valB = String(valB).toLowerCase();
             if (valA < valB) return -1 * sortDirection;
             if (valA > valB) return 1 * sortDirection;
             return 0;
        });
    }

    function updateSortDirection(column) {
        if (sortColumn === column) {
            sortDirection *= -1;
        } else {
            sortColumn = column;
            sortDirection = (column === 'timestamp') ? -1 : 1;
        }
        sortLogs();
        currentPage = 1;
        updateTable();
        updateSortIndicators();
    }

     function updateSortIndicators() {
         document.querySelectorAll('#logs-table th.sortable').forEach(th => {
             th.style.setProperty('--sort-icon', '" ⇅"');
             if (th.dataset.sort === sortColumn) {
                 const indicator = sortDirection === 1 ? ' ▲' : ' ▼';
                 th.style.setProperty('--sort-icon', `"${indicator}"`);
             }
         });
     }

    function renderTable() {
        const tbody = document.getElementById('table-body');
        tbody.innerHTML = '';
        document.querySelectorAll('.detail-row').forEach(row => row.remove());
        const totalPages = Math.max(1, Math.ceil(allLogs.length / itemsPerPage));
        currentPage = Math.max(1, Math.min(currentPage, totalPages));
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = allLogs.slice(start, end);
        if (pageData.length === 0 && allLogs.length > 0) {
             currentPage = 1;
             renderTable();
             return;
        } else if (pageData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No logs found.</td></tr>';
        }
        pageData.forEach((log) => {
            if (typeof log !== 'object' || log === null || !log._rowId) {
                console.warn("Skipping invalid log entry:", log);
                return;
            }
            const rowId = log._rowId;
            const tr = document.createElement('tr');
            tr.dataset.rowId = rowId;
            const brands = Array.isArray(log.brands) ? log.brands : [];
            const tdBrand = document.createElement('td');
            const brandGroup = document.createElement('div');
            brandGroup.className = 'brand-button-group';
            if (brands.length > 0) {
                 brands.forEach(brand => {
                    const button = document.createElement('button');
                    button.className = 'brand-toggle';
                    // --- *** MODIFIED LINE: Use custom text mapping *** ---
                    button.textContent = customBrandText[brand] || brand; // Use mapping, fallback to original brand name
                    // --- *** END OF MODIFIED LINE *** ---
                    button.dataset.brand = brand; // Keep original brand name in data attribute for logic
                    button.dataset.targetRowId = rowId;
                    button.type = 'button';
                    brandGroup.appendChild(button);
                });
            } else {
                brandGroup.textContent = 'N/A';
            }
            tdBrand.appendChild(brandGroup);
            const tdTime = document.createElement('td');
            tdTime.textContent = formatDisplayTimestamp(log.serverTimestampUTC || log.timestamp);
            const tdMap = document.createElement('td');
            if (log.latitude && log.longitude) {
                const lat = encodeURIComponent(log.latitude);
                const lon = encodeURIComponent(log.longitude);
                if (Math.abs(parseFloat(log.latitude)) <= 90 && Math.abs(parseFloat(log.longitude)) <= 180) {
                   const mapLink = document.createElement('a');
                   mapLink.href = `https://maps.google.com/?q=${lat},${lon}`; // Use https and standard query param
                   mapLink.target = '_blank';
                   mapLink.rel = 'noopener noreferrer';
                   mapLink.className = 'map-btn';
                   mapLink.textContent = 'Map';
                   tdMap.appendChild(mapLink);
                } else {
                   tdMap.textContent = 'Invalid Coords';
                }
            } else {
                tdMap.textContent = '';
            }
            tr.appendChild(tdBrand);
            tr.appendChild(tdTime);
            tr.appendChild(tdMap);
            tbody.appendChild(tr);
        });
        updatePagination(totalPages);
    }

    function toggleDetailRow(button) {
        const brand = button.dataset.brand;
        const targetRowId = button.dataset.targetRowId;
        const mainRow = document.querySelector(`tr[data-row-id="${targetRowId}"]`);
        if (!mainRow) {
            console.error("Could not find main row for ID:", targetRowId);
            return;
        }
        const existingDetailRow = document.getElementById(`detail-${targetRowId}`);
        if (existingDetailRow) {
            existingDetailRow.remove();
            return;
        }

        const logData = allLogs.find(log => log._rowId === targetRowId);
        if (!logData) {
            console.error("Could not find data for rowId:", targetRowId);
            alert("Error: Could not retrieve details.");
            return;
        }

        try {
            const detailRow = document.createElement('tr');
            detailRow.id = `detail-${targetRowId}`;
            detailRow.classList.add('detail-row');
            const detailCell = document.createElement('td');
            detailCell.colSpan = 3;
            const conditions = (typeof logData.conditions === 'object' && logData.conditions !== null) ? logData.conditions : {};
            const brandConditions = conditions[brand] || [];
            const comments = logData.comments || '';

            let detailHtml = '<div class="detail-content">';
            // --- Use the original brand name for the heading ---
            detailHtml += `<h4>Details for ${brand} Trolley</h4>`;

            if (brandConditions.length > 0) {
                const conditionsText = brandConditions.join(', ');
                const escapedConditionsText = conditionsText.replace(/</g, "&lt;").replace(/>/g, "&gt;"); // Basic HTML escaping
                detailHtml += `<span class="conditions_yes"><strong>Conditions Reported:</strong> ${escapedConditionsText}</span><br>`;
            } else {
                 detailHtml += '<span class="no-details">No specific conditions reported for this brand.</span><br>';
            }

            if (comments) {
                const escapedComments = comments.replace(/</g, "&lt;").replace(/>/g, "&gt;"); // Basic HTML escaping
                 detailHtml += `<span class="add_comments" style="display:block;"><strong>Comments:</strong> ${escapedComments.replace(/\n/g, '<br>')}</span>`;
            }

            detailHtml += '</div>';
            detailCell.innerHTML = detailHtml;
            detailRow.appendChild(detailCell);

            mainRow.parentNode.insertBefore(detailRow, mainRow.nextSibling);
        } catch (error) {
            console.error("Error occurred during detail row creation/insertion:", error);
            alert("An error occurred while trying to show details.");
        }
    }


    function updatePagination(totalPages) {
        const pageInfo = document.getElementById('page-info');
        const prevButton = document.getElementById('prev-page');
        const nextButton = document.getElementById('next-page');
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        prevButton.disabled = currentPage === 1;
        nextButton.disabled = currentPage === totalPages || totalPages === 0;
    }

    function changePage(direction) {
        currentPage += direction;
         document.querySelectorAll('.detail-row').forEach(row => row.remove());
        renderTable();
    }

    function setupEventListeners() {
        // Sorting listener
        document.querySelectorAll('#logs-table th.sortable').forEach(th => {
            th.addEventListener('click', () => updateSortDirection(th.dataset.sort));
        });
        // Pagination listeners
        document.getElementById('prev-page').addEventListener('click', () => changePage(-1));
        document.getElementById('next-page').addEventListener('click', () => changePage(1));

        // Detail row toggle listener
        const tableBody = document.getElementById('table-body');
        if (tableBody) {
             tableBody.addEventListener('click', (event) => {
                 const button = event.target.closest('.brand-toggle');
                 if (button) {
                     const targetRowId = button.dataset.targetRowId;
                     // Remove other detail rows before toggling the new one
                     document.querySelectorAll('.detail-row').forEach(row => {
                          if (row.id !== `detail-${targetRowId}`) {
                              row.remove();
                          }
                     });
                     // Toggle the relevant detail row
                     toggleDetailRow(button);
                 }
             });
        } else {
             console.error("Could not find table body element (#table-body) to attach listener.");
        }
    }


    function updateTable() {
        renderTable();
        updateSortIndicators();
    }
</script>

</body>
</html>