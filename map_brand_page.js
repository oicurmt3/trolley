/**
 * Brand-filtered map + list page. Requires window.TROLLEY_MAP_CONFIG:
 * { brand: 'Coles', pageTitle: string, pageSubtitle: string }
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var cfg = window.TROLLEY_MAP_CONFIG;
  if (!cfg || !cfg.brand) {
    window.location.replace('map_view.html');
    return;
  }

  var targetBrand = cfg.brand;
  var initialCenter = [-31.9523, 115.8613];
  var initialZoom = 10;

  var map = L.map('map').setView(initialCenter, initialZoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map);

  var markerClusterGroup = L.markerClusterGroup();
  map.addLayer(markerClusterGroup);
  attachMapMyLocationControl(map);

  setTimeout(function () {
    map.invalidateSize();
  }, 200);

  var tableLogs = [];
  var itemsPerPage = 15;
  var currentPage = 1;
  var sortColumn = 'timestamp';
  var sortDirection = -1;

  var loadingDiv = document.getElementById('loading');
  var errorDiv = document.getElementById('error-loading');
  var tableContainer = document.getElementById('table-container');
  var tableBody = document.getElementById('table-body');
  var pageInfo = document.getElementById('page-info');
  var prevButton = document.getElementById('prev-page');
  var nextButton = document.getElementById('next-page');
  var tableHead = document.querySelector('#logs-table thead');
  var mapEl = document.getElementById('map');

  var stateAbbreviations = {
    'Western Australia': 'WA',
    'South Australia': 'SA',
    'New South Wales': 'NSW',
    Queensland: 'QLD',
    Victoria: 'VIC',
    Tasmania: 'TAS',
    'Northern Territory': 'NT',
    'Australian Capital Territory': 'ACT',
    'Jervis Bay Territory': 'ACT',
  };

  function getStateAbbreviation(stateName) {
    return stateName ? stateAbbreviations[stateName] || stateName : null;
  }

  var customBrandText = {
    Coles: 'CO',
    Woolworths: 'WO',
    Aldi: 'AL',
    IGA: 'IG',
    Kmart: 'KM',
    BigW: 'BI',
    Bunnings: 'BU',
    Spudshed: 'SP',
    Other: 'OT',
  };

  function formatDisplayDateTime(isoTimestamp, includeTime) {
    if (!isoTimestamp) return 'N/A';
    try {
      var options = {
        timeZone: 'Australia/Perth',
        dateStyle: 'short',
      };
      if (includeTime) {
        options.timeStyle = 'short';
        options.hour12 = true;
      }
      var date = new Date(isoTimestamp);
      if (isNaN(date.getTime())) {
        var altDate = new Date(isoTimestamp.replace('T', ' ').replace(/-/g, '/'));
        return !isNaN(altDate.getTime()) ? altDate.toLocaleString('en-AU', options) : isoTimestamp;
      }
      return date.toLocaleString('en-AU', options);
    } catch (e) {
      return isoTimestamp;
    }
  }

  function formatDisplayDate(isoTimestamp) {
    return formatDisplayDateTime(isoTimestamp, false);
  }

  function generateStableRowId(log, index) {
    var tsPart = log.serverTimestampUTC || log.timestamp || 'no-ts-' + index;
    var ipPart = log.ipAddress || 'no-ip';
    return 'log-' + targetBrand + '-' + tsPart + '-' + ipPart + '-' + index;
  }

  function getConditionsText(conditions, brandToShow) {
    if (typeof conditions !== 'object' || conditions === null || !conditions[brandToShow] || !Array.isArray(conditions[brandToShow])) {
      return '';
    }
    return [...new Set(conditions[brandToShow])].join(', ');
  }

  function showMapMessage(el, text, isError) {
    if (!el) return;
    el.textContent = '';
    var p = document.createElement('p');
    p.style.textAlign = 'center';
    p.style.paddingTop = '20px';
    if (isError) p.style.color = 'red';
    p.textContent = text;
    el.appendChild(p);
  }

  async function loadAndProcessData() {
    loadingDiv.style.display = 'block';
    tableContainer.style.display = 'none';
    errorDiv.style.display = 'none';
    markerClusterGroup.clearLayers();

    try {
      var response = await fetch('trolley_logs.json?_=' + new Date().getTime());
      if (!response.ok) throw new Error('HTTP error! Status: ' + response.status);
      var allLogs = await response.json();
      if (!Array.isArray(allLogs)) throw new Error('Invalid data format received.');

      var filteredLogs = allLogs
        .filter(function (log) {
          return Array.isArray(log.brands) && log.brands.indexOf(targetBrand) !== -1;
        })
        .map(function (log, index) {
          log._rowId = generateStableRowId(log, index);
          return log;
        });

      populateMap(filteredLogs);
      tableLogs = filteredLogs;
      sortLogs();
      currentPage = 1;
      updateTable();

      loadingDiv.style.display = 'none';
      tableContainer.style.display = 'block';
    } catch (error) {
      loadingDiv.style.display = 'none';
      errorDiv.textContent = 'Error loading data for ' + targetBrand + ': ' + error.message + '. Please try refreshing.';
      errorDiv.style.display = 'block';
      showMapMessage(mapEl, 'Error loading map data: ' + error.message, true);
    }
  }

  function populateMap(logsToMap) {
    var markersFound = 0;
    logsToMap.forEach(function (log) {
      var lat = parseFloat(log.latitude);
      var lon = parseFloat(log.longitude);
      if (!isNaN(lat) && !isNaN(lon) && Math.abs(lat) <= 90 && Math.abs(lon) <= 180) {
        var marker = L.marker([lat, lon]);
        var popupContent =
          '<b>Brand:</b> ' +
          escapeHtml(targetBrand) +
          '<br><b>Reported:</b> ' +
          escapeHtml(formatDisplayDateTime(log.serverTimestampUTC || log.timestamp));
        if (log.suburb && log.state) {
          popupContent += '<br><b>Location:</b> ' + escapeHtml(log.suburb) + ', ' + escapeHtml(log.state);
        }
        var conditionsText = getConditionsText(log.conditions, targetBrand);
        if (conditionsText) {
          popupContent += '<br><b>Condition(s):</b> ' + escapeHtml(conditionsText);
        }
        if (log.comments) {
          popupContent += '<br><b>Comments:</b> ' + escapeHtml(log.comments);
        }
        marker.bindPopup(popupContent);
        markerClusterGroup.addLayer(marker);
        markersFound++;
      }
    });

    if (markersFound === 0) {
      showMapMessage(mapEl, "No map locations found for '" + targetBrand + "'.", false);
      map.setView(initialCenter, initialZoom);
    } else {
      var bounds = markerClusterGroup.getBounds();
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
      } else {
        map.setView(initialCenter, initialZoom);
      }
    }
    map.invalidateSize();
  }

  function sortLogs() {
    if (sortColumn !== 'timestamp') return;
    tableLogs.sort(function (a, b) {
      var timeA = new Date(a.serverTimestampUTC || a.timestamp || 0).getTime();
      var timeB = new Date(b.serverTimestampUTC || b.timestamp || 0).getTime();
      var validTimeA = isNaN(timeA) ? 0 : timeA;
      var validTimeB = isNaN(timeB) ? 0 : timeB;
      return (validTimeA - validTimeB) * sortDirection;
    });
  }

  function updateSortDirection(column) {
    if (column !== 'timestamp') return;
    if (sortColumn === column) sortDirection *= -1;
    else {
      sortColumn = column;
      sortDirection = -1;
    }
    sortLogs();
    currentPage = 1;
    updateTable();
  }

  function updateSortIndicators() {
    document.querySelectorAll('#logs-table th.sortable').forEach(function (th) {
      th.style.setProperty('--sort-icon', '""');
      if (th.dataset.sort === 'timestamp') {
        if (sortColumn === 'timestamp') {
          var indicator = sortDirection === 1 ? ' \u25B2' : ' \u25BC';
          th.style.setProperty('--sort-icon', '"' + indicator + '"');
        } else {
          th.style.setProperty('--sort-icon', '" \u2195"');
        }
      }
    });
  }

  function renderTable() {
    tableBody.innerHTML = '';
    document.querySelectorAll('.detail-row').forEach(function (row) {
      row.remove();
    });

    var totalPages = Math.max(1, Math.ceil(tableLogs.length / itemsPerPage));
    currentPage = Math.max(1, Math.min(currentPage, totalPages));
    var start = (currentPage - 1) * itemsPerPage;
    var pageData = tableLogs.slice(start, start + itemsPerPage);

    if (pageData.length === 0 && tableLogs.length > 0) {
      currentPage = Math.max(1, currentPage - 1);
      renderTable();
      return;
    }
    if (pageData.length === 0) {
      var trEmpty = document.createElement('tr');
      var tdEmpty = document.createElement('td');
      tdEmpty.colSpan = 3;
      tdEmpty.style.textAlign = 'center';
      tdEmpty.style.padding = '20px';
      tdEmpty.textContent = "No '" + targetBrand + "' logs found.";
      trEmpty.appendChild(tdEmpty);
      tableBody.appendChild(trEmpty);
    }

    pageData.forEach(function (log) {
      if (typeof log !== 'object' || log === null || !log._rowId) return;
      var rowId = log._rowId;
      var tr = document.createElement('tr');
      tr.dataset.rowId = rowId;

      var tdBrand = document.createElement('td');
      var brandGroup = document.createElement('div');
      brandGroup.className = 'brand-button-group';
      var button = document.createElement('button');
      button.className = 'brand-toggle';
      button.textContent = customBrandText[targetBrand] || targetBrand;
      button.dataset.brand = targetBrand;
      button.dataset.targetRowId = rowId;
      button.type = 'button';
      brandGroup.appendChild(button);
      tdBrand.appendChild(brandGroup);

      var tdDateTime = document.createElement('td');
      tdDateTime.textContent = formatDisplayDate(log.serverTimestampUTC || log.timestamp);

      var tdMap = document.createElement('td');
      if (log.latitude && log.longitude) {
        var lat = log.latitude;
        var lon = log.longitude;
        if (Math.abs(parseFloat(lat)) <= 90 && Math.abs(parseFloat(lon)) <= 180) {
          var mapLink = document.createElement('a');
          mapLink.href =
            'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lon);
          mapLink.target = '_blank';
          mapLink.rel = 'noopener noreferrer';
          mapLink.className = 'map-btn';
          var suburb = log.suburb || null;
          var stateFullName = log.state || null;
          var stateAbbr = getStateAbbreviation(stateFullName);
          var buttonText = 'Map';
          if (suburb && stateAbbr) buttonText = suburb + ', ' + stateAbbr;
          else if (suburb) buttonText = suburb;
          else if (stateAbbr) buttonText = stateAbbr;
          else if (stateFullName) buttonText = stateFullName;
          mapLink.textContent = buttonText;
          tdMap.appendChild(mapLink);
        } else {
          tdMap.textContent = 'Invalid Coords';
        }
      }

      tr.appendChild(tdBrand);
      tr.appendChild(tdDateTime);
      tr.appendChild(tdMap);
      tableBody.appendChild(tr);
    });
    updatePagination(totalPages);
    updateSortIndicators();
  }

  function toggleDetailRow(button) {
    var brand = button.dataset.brand;
    var targetRowId = button.dataset.targetRowId;
    var mainRow = document.querySelector('tr[data-row-id="' + targetRowId + '"]');
    if (!mainRow) return;

    var detailRowId = 'detail-' + targetRowId + '-' + brand;
    var existingDetailRow = document.getElementById(detailRowId);

    document.querySelectorAll('.detail-row').forEach(function (row) {
      if (row.id !== detailRowId) row.remove();
    });

    if (existingDetailRow) {
      existingDetailRow.remove();
      return;
    }

    var logData = tableLogs.find(function (log) {
      return log._rowId === targetRowId;
    });
    if (!logData) return;

    var detailRow = document.createElement('tr');
    detailRow.id = detailRowId;
    detailRow.classList.add('detail-row');
    var detailCell = document.createElement('td');
    detailCell.colSpan = 3;

    var wrapper = document.createElement('div');
    wrapper.className = 'detail-content';

    var heading = document.createElement('h4');
    heading.textContent = 'Details for ' + brand + ' Trolley';
    wrapper.appendChild(heading);

    var conditions = typeof logData.conditions === 'object' && logData.conditions !== null ? logData.conditions : {};
    var brandConditionsText = getConditionsText(conditions, brand);
    if (brandConditionsText) {
      var condSpan = document.createElement('span');
      condSpan.className = 'conditions_yes';
      var condStrong = document.createElement('strong');
      condStrong.textContent = 'Conditions Reported:';
      condSpan.appendChild(condStrong);
      condSpan.appendChild(document.createTextNode(' ' + brandConditionsText));
      wrapper.appendChild(condSpan);
    } else {
      var noCond = document.createElement('span');
      noCond.className = 'no-details';
      noCond.textContent = 'No specific conditions reported.';
      wrapper.appendChild(noCond);
    }

    var comments = logData.comments || '';
    if (comments) {
      var commSpan = document.createElement('span');
      commSpan.className = 'add_comments';
      var commStrong = document.createElement('strong');
      commStrong.textContent = 'General Comments:';
      commSpan.appendChild(commStrong);
      commSpan.appendChild(document.createTextNode(' ' + comments));
      wrapper.appendChild(commSpan);
    } else {
      var noComm = document.createElement('span');
      noComm.className = 'no-details';
      noComm.textContent = 'No general comments submitted.';
      wrapper.appendChild(noComm);
    }

    detailCell.appendChild(wrapper);
    detailRow.appendChild(detailCell);
    mainRow.parentNode.insertBefore(detailRow, mainRow.nextSibling);
  }

  function updatePagination(totalPages) {
    pageInfo.textContent = 'Page ' + currentPage + ' of ' + totalPages;
    prevButton.disabled = currentPage === 1;
    nextButton.disabled = currentPage === totalPages || totalPages === 0;
  }

  function changePage(direction) {
    var oldPage = currentPage;
    currentPage += direction;
    var totalPages = Math.max(1, Math.ceil(tableLogs.length / itemsPerPage));
    currentPage = Math.max(1, Math.min(currentPage, totalPages));
    if (currentPage !== oldPage) {
      document.querySelectorAll('.detail-row').forEach(function (row) {
        row.remove();
      });
      renderTable();
    }
  }

  function setupEventListeners() {
    if (tableHead) {
      tableHead.addEventListener('click', function (event) {
        var th = event.target.closest('th.sortable');
        if (th) updateSortDirection(th.dataset.sort);
      });
    }
    prevButton.addEventListener('click', function () {
      changePage(-1);
    });
    nextButton.addEventListener('click', function () {
      changePage(1);
    });
    if (tableBody) {
      tableBody.addEventListener('click', function (event) {
        var btn = event.target.closest('.brand-toggle');
        if (btn) toggleDetailRow(btn);
      });
    }
  }

  function updateTable() {
    renderTable();
  }

  loadAndProcessData();
  setupEventListeners();
});