/**
 * Escape text for safe HTML insertion when building markup strings (e.g. Leaflet popups).
 */
function escapeHtml(text) {
  if (text === null || text === undefined) {
    return '';
  }
  const str = String(text);
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return str.replace(/[&<>"']/g, function (m) {
    return map[m];
  });
}