<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

/** @var array<string, string>|null */
$GLOBALS['trolley_suburb_lga_index'] = null;

/**
 * Build or return a lowercase suburb name => LGAKey map.
 *
 * @return array<string, string>
 */
function trolley_suburb_lga_index(string $webRoot): array
{
  if (is_array($GLOBALS['trolley_suburb_lga_index'])) {
    return $GLOBALS['trolley_suburb_lga_index'];
  }

  $path = $webRoot . '/' . TROLLEY_SUBURB_LGA_MAP_FILE;
  $index = [];
  if (!is_file($path)) {
    $GLOBALS['trolley_suburb_lga_index'] = $index;
    return $index;
  }

  $json = file_get_contents($path);
  if ($json === false) {
    error_log('Trolley: could not read suburb LGA mapping');
    $GLOBALS['trolley_suburb_lga_index'] = $index;
    return $index;
  }

  $list = json_decode($json, true);
  if (!is_array($list)) {
    error_log('Trolley: suburb LGA mapping JSON invalid');
    $GLOBALS['trolley_suburb_lga_index'] = $index;
    return $index;
  }

  foreach ($list as $row) {
    if (!isset($row['Suburb'], $row['LGAKey']) || !is_string($row['Suburb']) || !is_string($row['LGAKey'])) {
      continue;
    }
    $key = strtolower($row['Suburb']);
    if (!isset($index[$key])) {
      $index[$key] = $row['LGAKey'];
    }
  }

  $GLOBALS['trolley_suburb_lga_index'] = $index;
  return $index;
}

/** @var array<string, array>|null */
$GLOBALS['trolley_lga_contacts'] = null;

/**
 * @return array<string, array>
 */
function trolley_lga_contacts(string $webRoot): array
{
  if (is_array($GLOBALS['trolley_lga_contacts'])) {
    return $GLOBALS['trolley_lga_contacts'];
  }

  $path = $webRoot . '/' . TROLLEY_LGA_CONTACTS_FILE;
  $contacts = [];
  if (!is_file($path)) {
    $GLOBALS['trolley_lga_contacts'] = $contacts;
    return $contacts;
  }

  $json = file_get_contents($path);
  if ($json === false) {
    $GLOBALS['trolley_lga_contacts'] = $contacts;
    return $contacts;
  }

  $decoded = json_decode($json, true);
  if (!is_array($decoded)) {
    error_log('Trolley: LGA contacts JSON invalid');
    $GLOBALS['trolley_lga_contacts'] = $contacts;
    return $contacts;
  }

  $GLOBALS['trolley_lga_contacts'] = $decoded;
  return $decoded;
}

/**
 * Resolve suburb name to LGA full name and email.
 *
 * @return array{lgaName: ?string, lgaEmail: ?string}
 */
function trolley_resolve_lga(string $webRoot, ?string $suburbName): array
{
  $lgaName = null;
  $lgaEmail = null;

  if ($suburbName === null || $suburbName === '') {
    return ['lgaName' => null, 'lgaEmail' => null];
  }

  $index = trolley_suburb_lga_index($webRoot);
  $lgaKey = $index[strtolower($suburbName)] ?? null;
  if ($lgaKey === null) {
    return ['lgaName' => null, 'lgaEmail' => null];
  }

  $contacts = trolley_lga_contacts($webRoot);
  if (!isset($contacts[$lgaKey]) || !is_array($contacts[$lgaKey])) {
    error_log("Trolley: LGA key not found in contacts: $lgaKey");
    return ['lgaName' => $lgaKey, 'lgaEmail' => null];
  }

  $details = $contacts[$lgaKey];
  $lgaName = $details['LGAFullName'] ?? $lgaKey;
  $lgaEmail = isset($details['Email']) && is_string($details['Email']) ? $details['Email'] : null;

  return ['lgaName' => $lgaName, 'lgaEmail' => $lgaEmail];
}

/**
 * Reverse geocode via Nominatim.
 *
 * @return array{suburb: ?string, state: ?string}
 */
function trolley_reverse_geocode(float $latitude, float $longitude): array
{
  $url = TROLLEY_NOMINATIM_BASE_URL . '?format=jsonv2&addressdetails=1&lat='
    . rawurlencode((string)$latitude) . '&lon=' . rawurlencode((string)$longitude) . '&accept-language=en';

  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => 'User-Agent: ' . TROLLEY_NOMINATIM_USER_AGENT . "\r\n",
      'ignore_errors' => true,
      'timeout' => 7,
    ],
  ]);

  $response = @file_get_contents($url, false, $context);
  if ($response === false) {
    error_log("Trolley Nominatim unreachable for $latitude, $longitude");
    return ['suburb' => null, 'state' => null];
  }

  $geo = json_decode($response, true);
  if (!is_array($geo) || !isset($geo['address']) || !is_array($geo['address'])) {
    error_log('Trolley Nominatim invalid response for ' . $latitude . ',' . $longitude);
    return ['suburb' => null, 'state' => null];
  }

  $address = $geo['address'];
  $suburb = $address['suburb'] ?? $address['village'] ?? $address['town']
    ?? $address['city_district'] ?? $address['hamlet'] ?? null;
  $state = $address['state'] ?? null;

  return [
    'suburb' => is_string($suburb) ? $suburb : null,
    'state' => is_string($state) ? $state : null,
  ];
}