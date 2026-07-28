<?php
/** Fonctions d'accès et de calcul DVF, compatibles PHP 7.0. */

define('DVF_DATASET', 'demandes-de-valeurs-foncieres-geolocalisees');

function dvfHttpJson($url) {
    $context = stream_context_create(['http' => ['timeout' => 12, 'ignore_errors' => true, 'header' => "Accept: application/json\r\nUser-Agent: SigmaImmo/1.0\r\n"]]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) throw new RuntimeException('Le service public de données immobilières ne répond pas.');
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) $status = (int)$match[1];
    if ($status < 200 || $status >= 300) throw new RuntimeException('Le service public de données immobilières est temporairement indisponible.');
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) throw new RuntimeException('La réponse du service public est inexploitable.');
    return $decoded;
}

function dvfCache($key, $ttl, $loader) {
    $directory = __DIR__ . '/../data/cache/dvf/';
    if (!is_dir($directory)) @mkdir($directory, 0775, true);
    $file = $directory . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
    if (is_file($file) && filemtime($file) >= time() - $ttl) {
        $cached = json_decode(file_get_contents($file), true);
        if (is_array($cached)) return $cached;
    }
    $value = call_user_func($loader);
    if (is_dir($directory) && is_writable($directory)) @file_put_contents($file, json_encode($value, JSON_UNESCAPED_UNICODE), LOCK_EX);
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
        'latitude' => $latitude,
        'longitude' => $longitude,
        'surface' => dvfListingNumber($listing, 'surface', 'surfaceText'),
        'price' => dvfListingNumber($listing, 'price', 'priceText'),
        'type' => (string)($listing['propertyType'] ?? $listing['type'] ?? $listing['title'] ?? $listing['description'] ?? ''),
    ];
}

function dvfResolveLocation($context) {
    if ($context['latitude'] !== null && $context['longitude'] !== null) return dvfReverseGeocode($context['latitude'], $context['longitude']);
    return dvfGeocode($context['query']);
}

function dvfResources() {
    $payload = dvfHttpJson('https://www.data.gouv.fr/api/1/datasets/' . DVF_DATASET . '/');
    $resources = isset($payload['resources']) && is_array($payload['resources']) ? $payload['resources'] : [];
    $annual = [];
    foreach ($resources as $resource) {
        $text = strtolower((isset($resource['title']) ? $resource['title'] : '') . ' ' . (isset($resource['url']) ? $resource['url'] : ''));
        if (strtolower((string)($resource['format'] ?? '')) !== 'csv' || strpos($text, 'valeurs') === false || !preg_match('/20(1[4-9]|2[0-9])/', $text, $year)) continue;
        $annual[] = ['id' => (string)$resource['id'], 'year' => (int)$year[0]];
    }
    usort($annual, function($a, $b) { return $b['year'] - $a['year']; });
    if (!$annual) throw new RuntimeException('Aucune ressource DVF annuelle compatible n’a été publiée.');
    return array_slice($annual, 0, 4);
}

function dvfRowsForCommune($cityCode) {
    $resources = dvfCache('resources', 86400, 'dvfResources');
    $all = [];
    foreach ($resources as $resource) {
        $query = http_build_query(['page_size' => 200, 'code_commune__exact' => $cityCode, 'date_mutation__sort' => 'desc']);
        $url = 'https://tabular-api.data.gouv.fr/api/resources/' . rawurlencode($resource['id']) . '/data/?' . $query;
        try {
            $payload = dvfHttpJson($url);
            $rows = isset($payload['data']) ? $payload['data'] : (isset($payload['results']) ? $payload['results'] : []);
            if (is_array($rows)) $all = array_merge($all, $rows);
        } catch (RuntimeException $error) {
            if (!$all) continue;
        }
        if (count($all) >= 400) break;
    }
    if (!$all) throw new RuntimeException('Aucune transaction DVF n’est disponible pour cette commune.');
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

function dvfPropertyType($value) {
    $normalized = strtolower(trim((string)$value));
    if (strpos($normalized, 'appart') !== false) return 'Appartement';
    if (strpos($normalized, 'maison') !== false) return 'Maison';
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
        $key = dvfText($row, ['id_mutation']);
        if ($key === '') $key = $date . '|' . $value . '|' . $address;
        if (!isset($groups[$key])) $groups[$key] = ['id' => $key, 'date' => $date, 'address' => $address ?: 'Adresse non publiée', 'type' => $type, 'surface' => 0.0, 'value' => $value, 'rooms' => 0, 'latitude' => dvfNumber($row, ['latitude']), 'longitude' => dvfNumber($row, ['longitude'])];
        $groups[$key]['surface'] += $surface;
        $rooms = dvfNumber($row, ['nombre_pieces_principales']);
        if ($rooms) $groups[$key]['rooms'] += (int)$rooms;
    }
    $transactions = [];
    foreach ($groups as $item) {
        if ($item['surface'] <= 0) continue;
        $item['price_per_sqm'] = round($item['value'] / $item['surface']);
        if ($item['price_per_sqm'] < 200 || $item['price_per_sqm'] > 30000) continue;
        $item['distance_km'] = dvfDistance($origin['latitude'], $origin['longitude'], $item['latitude'], $item['longitude']);
        $transactions[] = $item;
    }
    usort($transactions, function($a, $b) { return strcmp($b['date'], $a['date']); });
    return $transactions;
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
