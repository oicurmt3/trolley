<?php
/**
 * generate_sri.php -- Generate SRI hashes for CDN resources
 *
 * Run once from browser to generate integrity hashes for MarkerCluster CDN files.
 * Copy the output tags into map_view.html and map_brand.html, then delete this file.
 *
 * Version: 1.5
 */

header('Content-Type: text/html; charset=UTF-8');
echo '<html><head><title>SRI Hash Generator</title>';
echo '<style>body{font-family:monospace;background:#1a1a1a;color:#e0e0e0;padding:20px;max-width:800px;margin:0 auto;}';
echo 'pre{background:#333;padding:10px;border-radius:5px;overflow-x:auto;word-wrap:break-word;white-space:pre-wrap;}</style>';
echo '</head><body>';
echo '<h1>SRI Hash Generator</h1>';
echo '<p>Generating integrity hashes for CDN resources...</p>';

$urls = [
    'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css',
    'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css',
    'https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js',
];

foreach ($urls as $url) {
    $content = @file_get_contents($url);
    if ($content === false) {
        echo '<p style="color:#f88;">FAILED to fetch: ' . htmlspecialchars($url) . '</p>';
        continue;
    }
    $hash = base64_encode(hash('sha256', $content, true));
    $integrity = 'sha256-' . $hash;
    echo '<h3>' . htmlspecialchars(basename($url)) . '</h3>';
    echo '<pre>integrity="' . $integrity . '"</pre>';
    echo '<p style="color:#8f8;font-size:0.85em;">File size: ' . strlen($content) . ' bytes</p>';
}

echo '<hr>';
echo '<p>Copy the integrity values into the corresponding &lt;link&gt; and &lt;script&gt; tags in map_view.html and map_brand.html.</p>';
echo '<p style="color:#ff0;"><strong>Delete this file after use.</strong></p>';
echo '<p><a href="admin.php" style="color:#00aaff;">Back to Admin</a></p>';
echo '</body></html>';
?>
