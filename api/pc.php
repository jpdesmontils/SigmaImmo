<?php
// ============================================================
// ImmoAggregator — fix_seloger_prices.php
// Corrige les prix des annonces SeLoger en les extrayant
// depuis le champ title (ex: "Maison T5 114 m² 328000 € Tavel")
// Appel : https://votre-serveur/sigma-immo/api/fix_seloger_prices.php
// À SUPPRIMER après exécution !
// ============================================================

header('Content-Type: application/json; charset=utf-8');

define('DATA_DIR',       __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');

$report = array(
    'scanned'  => 0,
    'fixed'    => 0,
    'skipped'  => 0,
    'details'  => array()
);

// ── Charger favorites.json ────────────────────────────────────
$favorites = loadJson(FAVORITES_FILE);
if (empty($favorites)) {
    echo json_encode(array('error' => 'favorites.json vide ou introuvable'));
    exit;
}

// ── Parcourir et corriger ─────────────────────────────────────
foreach ($favorites as $key => &$item) {
    $report['scanned']++;

    // Ne traiter que les annonces SeLoger (URL ou source)
    $url = isset($item['url']) ? $item['url'] : '';
    $isSeloger = strpos($url, 'seloger.com') !== false
              || strpos($url, 'logic-immo') !== false
              || strpos($url, 'leboncoin') !== false;

    // Aussi traiter cap_ (capturées via content_capture.js)
    $isCaptured = isset($item['id']) && strpos($item['id'], 'cap_') === 0;

    if (!$isSeloger && !$isCaptured) {
        $report['skipped']++;
        continue;
    }

    $title = isset($item['title']) ? $item['title'] : '';
    if (empty($title)) {
        $report['skipped']++;
        continue;
    }

    // Extraire prix depuis le titre
    // Patterns : "328000 €", "328 000 €", "328 000€"
    $priceFromTitle = extractPriceFromTitle($title);

    if ($priceFromTitle === null) {
        $report['skipped']++;
        $report['details'][] = array(
            'id'     => isset($item['id']) ? $item['id'] : $key,
            'status' => 'no_price_in_title',
            'title'  => substr($title, 0, 80)
        );
        continue;
    }

    $oldPrice = isset($item['price']) ? $item['price'] : null;

    // Ne corriger que si le prix est différent
    if ($oldPrice == $priceFromTitle) {
        $report['skipped']++;
        continue;
    }

    // Corriger
    $item['price']     = $priceFromTitle;
    $item['priceText'] = number_format($priceFromTitle, 0, ',', ' ') . ' €';

    $report['fixed']++;
    $report['details'][] = array(
        'id'        => isset($item['id']) ? $item['id'] : $key,
        'title'     => substr($title, 0, 80),
        'old_price' => $oldPrice,
        'new_price' => $priceFromTitle
    );
}
unset($item);

// ── Sauvegarder favorites.json ────────────────────────────────
if ($report['fixed'] > 0) {
    saveJson(FAVORITES_FILE, $favorites);
}

$report['status'] = 'OK — Supprimez ce fichier après exécution !';
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ── Helpers ───────────────────────────────────────────────────
function extractPriceFromTitle($title) {
    // Pattern : nombre entier (avec ou sans espaces) suivi de €
    // Ex: "328000 €", "328 000 €", "272 000€", "272000€"
    if (preg_match('/(\d[\d\s]{2,10})\s*€/', $title, $m)) {
        $price = (int) preg_replace('/\s+/', '', $m[1]);
        if ($price > 10000 && $price < 50000000) {
            return $price;
        }
    }
    return null;
}

function loadJson($file) {
    if (!file_exists($file)) return array();
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : array();
}

function saveJson($file, $data) {
    $clean = cleanUtf8($data);
    file_put_contents($file, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function cleanUtf8($value) {
    if (is_string($value)) return iconv('UTF-8', 'UTF-8//IGNORE', $value);
    if (is_array($value)) {
        foreach ($value as $k => $v) $value[$k] = cleanUtf8($v);
    }
    return $value;
}
