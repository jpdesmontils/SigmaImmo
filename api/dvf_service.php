<?php
/** Fonctions d'accès et de calcul DVF, compatibles PHP 7.0. */
require_once __DIR__ . '/logger.php';

function dvfHttpJson($url) {
    $response = dvfHttpResponse($url, 12, "Accept: application/json\r\nUser-Agent: SigmaImmo/1.0\r\n", 'Le service public de données immobilières ne répond pas.');
    $body = $response['body'];
    $status = $response['status'];
    if ($status < 200 || $status >= 300) throw new RuntimeException('Le service public de données immobilières est temporairement indisponible.');
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) throw new RuntimeException('La réponse du service public est inexploitable.');
    return $decoded;
}

function dvfHttpResponse($url, $timeout, $headers, $transportError) {
    $context = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => $headers]]);
    $body = @file_get_contents($url, false, $context);
    $status = dvfHttpStatus(isset($http_response_header) ? $http_response_header : []);
    if ($body === false) throw new RuntimeException($transportError);
    return ['body' => $body, 'status' => $status];
}

function dvfHttpStatus($responseHeaders) {
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $header, $match)) $status = (int)$match[1];
    }
    return $status;
}

function dvfCache($key, $ttl, $loader, $observer = null) {
    $directory = __DIR__ . '/../data/cache/dvf/';
    if (!is_dir($directory)) @mkdir($directory, 0775, true);
    $file = $directory . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
    if (is_file($file) && filemtime($file) >= time() - $ttl) {
        $cached = json_decode(file_get_contents($file), true);
        if (is_array($cached)) {
            if ($observer) call_user_func($observer, 'hit', $cached);
            return $cached;
        }
    }
    $value = call_user_func($loader);
    if (is_dir($directory) && is_writable($directory)) @file_put_contents($file, json_encode($value, JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($observer) call_user_func($observer, 'miss', $value);
    return $value;
}

function dvfGeocode($address) {
    $url = 'https://api-adresse.data.gouv.fr/search/?limit=1&q=' . rawurlencode($address);
    $payload = dvfHttpJson($url);
    $feature = isset($payload['features'][0]) ? $payload['features'][0] : null;
    if (!is_array($feature)) throw new InvalidArgumentException('Adresse introuvable. Renseignez une adresse ou une commune plus précise.');
    $coordinates = isset($feature['geometry']['coordinates']) ? $feature['geometry']['coordinates'] : [];
    $properties = isset($feature['properties']) ? $feature['properties'] : [];
    return [
        'longitude' => isset($coordinates[0]) ? (float)$coordinates[0] : null,
        'latitude' => isset($coordinates[1]) ? (float)$coordinates[1] : null,
        'city_code' => isset($properties['citycode']) ? (string)$properties['citycode'] : '',
        'city' => isset($properties['city']) ? (string)$properties['city'] : $address,
        'label' => isset($properties['label']) ? (string)$properties['label'] : $address,
    ];
}

function dvfReverseGeocode($latitude, $longitude) {
    $url = 'https://api-adresse.data.gouv.fr/reverse/?limit=1&lat=' . rawurlencode($latitude) . '&lon=' . rawurlencode($longitude);
    $payload = dvfHttpJson($url);
    $feature = isset($payload['features'][0]) ? $payload['features'][0] : null;
    if (!is_array($feature)) throw new InvalidArgumentException('Les coordonnées du bien ne correspondent à aucune commune connue.');
    $properties = isset($feature['properties']) ? $feature['properties'] : [];
    return [
        'longitude' => (float)$longitude,
        'latitude' => (float)$latitude,
        'city_code' => isset($properties['citycode']) ? (string)$properties['citycode'] : '',
        'city' => isset($properties['city']) ? (string)$properties['city'] : '',
        'label' => isset($properties['label']) ? (string)$properties['label'] : ((string)$latitude . ', ' . (string)$longitude),
    ];
}

function dvfListingNumber($listing, $numericKey, $textKey) {
    if (isset($listing[$numericKey]) && is_numeric($listing[$numericKey]) && (float)$listing[$numericKey] > 0) return (float)$listing[$numericKey];
    $text = isset($listing[$textKey]) ? (string)$listing[$textKey] : '';
    $pattern = $textKey === 'surfaceText' ? '/([0-9][0-9\s]*(?:[,.][0-9]+)?)\s*m(?:²|2)/iu' : '/([0-9][0-9\s]*(?:[,.][0-9]+)?)\s*€/u';
    if (preg_match($pattern, $text, $match)) {
        $number = (float)str_replace([' ', ','], ['', '.'], $match[1]);
        if ($number > 0) return $number;
    }
    return 0.0;
}

function dvfListingContext($listing) {
    $address = trim((string)($listing['address'] ?? ''));
    $location = trim((string)($listing['location'] ?? ''));
    $coords = isset($listing['coords']) && is_array($listing['coords']) ? $listing['coords'] : [];
    $latitude = isset($coords['lat']) && is_numeric($coords['lat']) ? (float)$coords['lat'] : null;
    $longitude = isset($coords['lng']) && is_numeric($coords['lng']) ? (float)$coords['lng'] : null;
    return [
        'query' => $address !== '' ? $address : $location,
        'exact_address' => $address,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'surface' => dvfListingNumber($listing, 'surface', 'surfaceText'),
        'price' => dvfListingNumber($listing, 'price', 'priceText'),
        'type' => dvfListingPropertyType($listing),
    ];
}

function dvfListingPropertyType($listing) {
    $fallback = '';
    foreach (['propertyType', 'type', 'title', 'description'] as $key) {
        $value = isset($listing[$key]) ? trim((string)$listing[$key]) : '';
        if ($value === '') continue;
        if ($fallback === '') $fallback = $value;
        $normalized = dvfPropertyType($value);
        if (in_array($normalized, ['Maison', 'Appartement'], true)) return $normalized;
    }
    return $fallback;
}

function dvfResolveLocation($context) {
    // L'adresse saisie dans la fiche est plus précise que les coordonnées
    // éventuellement extraites de l'annonce : elle définit donc le centre DVF.
    if (isset($context['exact_address']) && $context['exact_address'] !== '') return dvfGeocode($context['exact_address']);
    if ($context['latitude'] !== null && $context['longitude'] !== null) return dvfReverseGeocode($context['latitude'], $context['longitude']);
    return dvfGeocode($context['query']);
}

function dvfAnalysisFile($id, $dataDirectory) {
    if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id)) throw new InvalidArgumentException('Identifiant invalide.');
    return rtrim($dataDirectory, '/') . '/analyses/prix/' . $id . '.json';
}

function dvfReadAnalysis($id, $dataDirectory) {
    $file = dvfAnalysisFile($id, $dataDirectory);
    if (!is_file($file)) return null;
    $analysis = json_decode(file_get_contents($file), true);
    return is_array($analysis) && isset($analysis['result']) && is_array($analysis['result']) ? $analysis : null;
}

function dvfAnalysisMatchesListing($analysis, $listing) {
    if (!is_array($analysis) || !isset($analysis['input']) || !is_array($analysis['input'])) return false;
    if (isset($analysis['result']['transactions']) && is_array($analysis['result']['transactions'])) {
        foreach ($analysis['result']['transactions'] as $transaction) if (!array_key_exists('land_surface', $transaction)) return false;
    }
    $current = dvfListingContext($listing);
    $storedAddress = isset($analysis['input']['exact_address']) ? trim((string)$analysis['input']['exact_address']) : '';
    return $storedAddress === $current['exact_address'];
}

function dvfWriteAnalysis($id, $dataDirectory, $analysis) {
    $file = dvfAnalysisFile($id, $dataDirectory);
    $directory = dirname($file);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Le répertoire des analyses Prix ne peut pas être créé.');
    $temporary = $file . '.tmp.' . uniqid('', true);
    $json = json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $file)) {
        if (is_file($temporary)) @unlink($temporary);
        throw new RuntimeException('L’analyse Prix ne peut pas être enregistrée.');
    }
}

function dvfCreateAnalysis($id, $listing, $dataDirectory, $logContext = [], $persistFile = true) {
    $context = dvfListingContext($listing);
    $propertyType = dvfPropertyType($context['type']);
    $missing = [];
    if ($context['query'] === '' && ($context['latitude'] === null || $context['longitude'] === null)) $missing[] = 'localisation';
    if ($context['surface'] <= 0) $missing[] = 'surface';
    if ($missing) throw new InvalidArgumentException('Données insuffisantes pour rechercher les transactions DVF : ' . implode(', ', $missing) . '.');

    $origin = dvfResolveLocation($context);
    if ($origin['city_code'] === '') throw new InvalidArgumentException('La commune de cette adresse n’a pas pu être identifiée.');
    $rows = dvfRowsForCommune($origin['city_code'], $logContext);
    $transactions = dvfNormalizeTransactions($rows, $origin);
    appLog('app', 'dvf.transactions_normalized', $logContext + ['city_code' => $origin['city_code'], 'raw_row_count' => count($rows)] + dvfTransactionDiagnostics($transactions));
    $selection = dvfSelectComparables($transactions, $context['type'], $context['surface'], 10);
    appLog('app', 'dvf.comparables_selected', $logContext + ['city_code' => $origin['city_code'], 'property_type' => $propertyType, 'surface' => $context['surface'], 'comparable_count' => count($selection['items']), 'perimeter' => $selection['perimeter']]);
    if (!$selection['items']) throw new RuntimeException('Aucune vente comparable de maison ou d’appartement n’a été trouvée dans cette commune.');

    $capturedAt = gmdate('c');
    $source = ['name' => 'DVF — DGFiP / data.gouv.fr', 'url' => 'https://www.data.gouv.fr/fr/datasets/demandes-de-valeurs-foncieres-geolocalisees/', 'limitations' => 'Les données DVF décrivent des mutations enregistrées et ne reflètent ni l’état intérieur, ni les travaux, ni les conditions particulières de chaque vente.'];
    $result = ['source' => $source, 'captured_at' => $capturedAt, 'property' => ['asking_price' => $context['price'], 'surface' => $context['surface'], 'type' => $propertyType], 'location' => $origin, 'perimeter' => $selection['perimeter'], 'transactions' => $selection['items'], 'summary' => dvfSummary($selection['items'], $context['price'], $context['surface'])];
    $analysis = ['version' => 2, 'id' => $id, 'captured_at' => $capturedAt, 'input' => $context, 'api_data' => ['geocoding' => $origin, 'dvf_rows' => $rows], 'result' => $result];
    if ($persistFile) dvfWriteAnalysis($id, $dataDirectory, $analysis);
    return $analysis;
}

/**
 * Le jeu de données « demandes-de-valeurs-foncieres-geolocalisees » n'expose pas
 * ses millésimes comme des ressources CSV interrogeables via l'API tabulaire :
 * il est distribué sous forme d'un fichier CSV statique par commune et par
 * année, publié par le projet geo-dvf sur files.data.gouv.fr.
 */
function dvfDepartmentCode($cityCode) {
    $cityCode = strtoupper(trim((string)$cityCode));
    if (!preg_match('/^[0-9A-Z]{5}$/', $cityCode)) return '';
    $prefix = substr($cityCode, 0, 2);
    return ($prefix === '97' || $prefix === '98') ? substr($cityCode, 0, 3) : $prefix;
}

function dvfCommuneCsvUrl($cityCode, $department, $year) {
    return 'https://files.data.gouv.fr/geo-dvf/latest/csv/' . $year . '/communes/' . rawurlencode($department) . '/' . rawurlencode($cityCode) . '.csv';
}

function dvfParseCsv($text) {
    $rows = [];
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $text);
    rewind($stream);
    $header = fgetcsv($stream);
    if ($header === false) { fclose($stream); return []; }
    while (($values = fgetcsv($stream)) !== false) {
        if (count($values) === 1 && $values[0] === null) continue;
        if (count($values) !== count($header)) continue;
        $rows[] = array_combine($header, $values);
    }
    fclose($stream);
    return $rows;
}

function dvfFetchCommuneYearRows($cityCode, $department, $year) {
    $url = dvfCommuneCsvUrl($cityCode, $department, $year);
    $response = dvfHttpResponse($url, 20, "Accept: text/csv\r\nUser-Agent: SigmaImmo/1.0\r\n", 'Le service public de données foncières (geo-dvf) ne répond pas.');
    $body = $response['body'];
    $status = $response['status'];
    if ($status === 404) return [];
    if ($status < 200 || $status >= 300) throw new RuntimeException('Le service public de données foncières (geo-dvf) est temporairement indisponible.');
    return dvfParseCsv($body);
}

function dvfRowsForCommune($cityCode, $logContext = []) {
    $department = dvfDepartmentCode($cityCode);
    if ($department === '') throw new RuntimeException('Le code commune ne permet pas d’identifier le département.');
    $currentYear = (int)gmdate('Y');
    return dvfLoadCommuneYears($currentYear, function($year) use ($cityCode, $department, $logContext) {
        try {
            return dvfCache('rows_' . $cityCode . '_' . $year, 86400, function() use ($cityCode, $department, $year) {
                return dvfFetchCommuneYearRows($cityCode, $department, $year);
            }, function($cacheStatus, $rows) use ($cityCode, $year, $logContext) {
                appLog('app', 'dvf.year_loaded', $logContext + ['city_code' => $cityCode, 'year' => $year, 'cache_status' => $cacheStatus, 'row_count' => count($rows)]);
            });
        } catch (RuntimeException $error) {
            appLog('app', 'dvf.year_failed', $logContext + ['city_code' => $cityCode, 'year' => $year, 'error' => $error->getMessage()]);
            throw $error;
        }
    });
}

function dvfLoadCommuneYears($currentYear, $loader) {
    $all = [];
    $yearsWithData = 0;
    $lastError = null;
    for ($year = $currentYear; $year >= $currentYear - 10 && $yearsWithData < 4; $year--) {
        try {
            $rows = call_user_func($loader, $year);
        } catch (RuntimeException $error) {
            $lastError = $error;
            continue;
        }
        if ($rows) { $all = array_merge($all, $rows); $yearsWithData++; }
    }
    if (!$all) {
        if ($lastError && $yearsWithData === 0) throw $lastError;
        throw new RuntimeException('Aucune transaction DVF n’est disponible pour cette commune.');
    }
    return $all;
}

function dvfNumber($row, $keys) {
    foreach ($keys as $key) if (isset($row[$key]) && $row[$key] !== '' && is_numeric(str_replace(',', '.', $row[$key]))) return (float)str_replace(',', '.', $row[$key]);
    return null;
}

function dvfText($row, $keys) {
    foreach ($keys as $key) if (isset($row[$key]) && trim((string)$row[$key]) !== '') return trim((string)$row[$key]);
    return '';
}

function dvfParcelKey($row) {
    $parcelId = dvfText($row, ['id_parcelle']);
    if ($parcelId !== '') return $parcelId;
    $parts = [dvfText($row, ['code_commune']), dvfText($row, ['prefixe_de_section', 'prefixe_section']), dvfText($row, ['section']), dvfText($row, ['numero_plan', 'no_plan'])];
    return implode('|', $parts) === '|||' ? '' : implode('|', $parts);
}

function dvfMutationKey($row) {
    $key = dvfText($row, ['id_mutation']);
    if ($key !== '') return $key;
    $address = trim(dvfText($row, ['adresse_numero']) . ' ' . dvfText($row, ['adresse_suffixe']) . ' ' . dvfText($row, ['adresse_nom_voie', 'nom_voie']));
    return dvfText($row, ['date_mutation']) . '|' . dvfNumber($row, ['valeur_fonciere']) . '|' . $address;
}

function dvfPropertyType($value) {
    $normalized = strtolower(trim((string)$value));
    if (preg_match('/\b(appart(?:ement)?|studio|duplex|triplex|loft)\b/u', $normalized)) return 'Appartement';
    if (preg_match('/\b(maison|villa|pavillon|chalet)\b/u', $normalized)) return 'Maison';
    return ucfirst($normalized);
}

function dvfDistance($lat1, $lon1, $lat2, $lon2) {
    if (!is_numeric($lat1) || !is_numeric($lon1) || !is_numeric($lat2) || !is_numeric($lon2)) return null;
    $latDelta = deg2rad($lat2 - $lat1); $lonDelta = deg2rad($lon2 - $lon1);
    $a = sin($latDelta / 2) * sin($latDelta / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) * sin($lonDelta / 2);
    return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function dvfNormalizeTransactions($rows, $origin) {
    $groups = [];
    foreach ($rows as $row) {
        $nature = strtolower(dvfText($row, ['nature_mutation']));
        if ($nature !== '' && strpos($nature, 'vente') === false) continue;
        $value = dvfNumber($row, ['valeur_fonciere']);
        $surface = dvfNumber($row, ['surface_reelle_bati', 'surface_bati']);
        $type = dvfPropertyType(dvfText($row, ['type_local']));
        if (!$value || !$surface || !in_array($type, ['Maison', 'Appartement'], true)) continue;
        $date = dvfText($row, ['date_mutation']);
        $address = trim(dvfText($row, ['adresse_numero']) . ' ' . dvfText($row, ['adresse_suffixe']) . ' ' . dvfText($row, ['adresse_nom_voie', 'nom_voie']));
        $key = dvfMutationKey($row);
        if (!isset($groups[$key])) $groups[$key] = ['id' => $key, 'date' => $date, 'address' => $address ?: 'Adresse non publiée', 'type' => $type, 'surface' => 0.0, 'land_surface' => null, 'land_parcels' => [], 'value' => $value, 'rooms' => 0, 'latitude' => dvfNumber($row, ['latitude']), 'longitude' => dvfNumber($row, ['longitude'])];
        $groups[$key]['surface'] += $surface;
        $rooms = dvfNumber($row, ['nombre_pieces_principales']);
        if ($rooms) $groups[$key]['rooms'] += (int)$rooms;
    }
    foreach ($rows as $row) {
        $key = dvfMutationKey($row);
        if (!isset($groups[$key])) continue;
        $landSurface = dvfNumber($row, ['surface_terrain']);
        $parcelKey = dvfParcelKey($row);
        if ($landSurface === null || $landSurface <= 0 || $parcelKey === '' || isset($groups[$key]['land_parcels'][$parcelKey])) continue;
        $groups[$key]['land_parcels'][$parcelKey] = $landSurface;
        $groups[$key]['land_surface'] = array_sum($groups[$key]['land_parcels']);
    }
    $transactions = [];
    foreach ($groups as $item) {
        if ($item['surface'] <= 0) continue;
        unset($item['land_parcels']);
        $item['price_per_sqm'] = round($item['value'] / $item['surface']);
        if ($item['price_per_sqm'] < 200 || $item['price_per_sqm'] > 30000) continue;
        $item['distance_km'] = dvfDistance($origin['latitude'], $origin['longitude'], $item['latitude'], $item['longitude']);
        $transactions[] = $item;
    }
    usort($transactions, function($a, $b) { return strcmp($b['date'], $a['date']); });
    return $transactions;
}

function dvfTransactionDiagnostics($transactions) {
    $types = [];
    $withoutDistance = 0;
    $minimumDistance = null;
    $maximumDistance = null;
    foreach ($transactions as $transaction) {
        $type = isset($transaction['type']) ? (string)$transaction['type'] : '';
        $types[$type] = isset($types[$type]) ? $types[$type] + 1 : 1;
        $distance = isset($transaction['distance_km']) ? $transaction['distance_km'] : null;
        if ($distance === null) {
            $withoutDistance++;
            continue;
        }
        $minimumDistance = $minimumDistance === null ? $distance : min($minimumDistance, $distance);
        $maximumDistance = $maximumDistance === null ? $distance : max($maximumDistance, $distance);
    }
    ksort($types);
    return [
        'transaction_count' => count($transactions),
        'type_counts' => $types,
        'without_distance_count' => $withoutDistance,
        'minimum_distance_km' => $minimumDistance === null ? null : round($minimumDistance, 3),
        'maximum_distance_km' => $maximumDistance === null ? null : round($maximumDistance, 3),
    ];
}

function dvfSelectComparables($transactions, $propertyType, $surface, $limit) {
    $targetType = dvfPropertyType($propertyType);
    $stages = [
        ['radius' => 1, 'tolerance' => .30, 'label' => '1 km et surface ± 30 %'],
        ['radius' => 2, 'tolerance' => .30, 'label' => '2 km et surface ± 30 %'],
        ['radius' => null, 'tolerance' => .30, 'label' => 'commune et surface ± 30 %'],
        ['radius' => null, 'tolerance' => null, 'label' => 'commune, même type de bien'],
    ];
    $selected = []; $perimeter = $stages[0]['label'];
    foreach ($stages as $stage) {
        foreach ($transactions as $item) {
            if ($item['type'] !== $targetType || isset($selected[$item['id']])) continue;
            if ($stage['radius'] !== null && ($item['distance_km'] === null || $item['distance_km'] > $stage['radius'])) continue;
            if ($stage['tolerance'] !== null && $surface > 0 && abs($item['surface'] - $surface) / $surface > $stage['tolerance']) continue;
            $selected[$item['id']] = $item;
            if (count($selected) >= $limit) break;
        }
        $perimeter = $stage['label'];
        if (count($selected) >= $limit) break;
    }
    return ['items' => array_slice(array_values($selected), 0, $limit), 'perimeter' => $perimeter];
}

function dvfPercentile($values, $percentile) {
    sort($values, SORT_NUMERIC); $count = count($values);
    if (!$count) return null;
    $index = ($count - 1) * $percentile; $lower = (int)floor($index); $upper = (int)ceil($index);
    return $values[$lower] + ($values[$upper] - $values[$lower]) * ($index - $lower);
}

function dvfSummary($items, $askingPrice, $surface) {
    $prices = array_map(function($item) { return $item['price_per_sqm']; }, $items);
    $median = dvfPercentile($prices, .5); $low = dvfPercentile($prices, .25); $high = dvfPercentile($prices, .75);
    if ($median === null || $surface <= 0) return null;
    $askingSqm = $askingPrice > 0 ? $askingPrice / $surface : null;
    $estimate = round($median * $surface / 1000) * 1000;
    $offerLow = round(min($estimate * .90, $askingPrice * .92) / 1000) * 1000;
    $offerHigh = round(min($estimate * .95, $askingPrice * .97) / 1000) * 1000;
    return [
        'median_price_per_sqm' => round($median), 'low_price_per_sqm' => round($low), 'high_price_per_sqm' => round($high),
        'asking_price_per_sqm' => $askingSqm === null ? null : round($askingSqm),
        'asking_gap_percent' => $askingSqm === null ? null : round(($askingSqm / $median - 1) * 100, 1),
        'estimated_value' => $estimate, 'prudent_value_low' => round($low * $surface / 1000) * 1000,
        'prudent_value_high' => round($high * $surface / 1000) * 1000, 'offer_low' => min($offerLow, $offerHigh), 'offer_high' => max($offerLow, $offerHigh),
    ];
}
