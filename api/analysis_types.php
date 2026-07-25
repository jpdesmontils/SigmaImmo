<?php
/** Référentiel unique des analyses proposées par l'application. */
function analysisTypes() { return ['locatif', 'mdb', 'patrimonial']; }
function validAnalysisType($type) { return is_string($type) && in_array($type, analysisTypes(), true); }
function analysisAvailability($dataDir, $id) {
    $result = [];
    foreach (analysisTypes() as $type) $result[$type] = is_file($dataDir . 'analyses/' . $type . '/' . $id . '.json');
    return $result;
}

/** Métadonnées légères destinées à la galerie, sans exposer le contenu des analyses. */
function analysisSummaries($dataDir, $id) {
    $summaries = [];
    foreach (analysisTypes() as $type) {
        $file = $dataDir . 'analyses/' . $type . '/' . $id . '.json';
        if (!is_file($file)) {
            $summaries[$type] = false;
            continue;
        }

        $analysis = json_decode(file_get_contents($file), true);
        if (!is_array($analysis)) {
            $summaries[$type] = ['available' => true, 'analyzedAt' => date(DATE_ATOM, filemtime($file)), 'score' => null];
            continue;
        }

        $date = analysisDateValue($analysis, $type);
        $timestamp = $date ? strtotime($date) : false;
        $summaries[$type] = [
            'available' => true,
            'analyzedAt' => date(DATE_ATOM, $timestamp !== false ? $timestamp : filemtime($file)),
            'score' => analysisScoreValue($analysis, $type),
        ];
    }
    return $summaries;
}

function latestAnalysisSummary($summaries) {
    $latest = null;
    foreach ($summaries as $type => $summary) {
        if (!is_array($summary) || !$summary['available']) continue;
        $candidate = $summary + ['type' => $type];
        if ($latest === null || strtotime($candidate['analyzedAt']) > strtotime($latest['analyzedAt'])) $latest = $candidate;
    }
    return $latest;
}

function analysisDateValue($analysis, $type) {
    if (isset($analysis['analyzedAt'])) return $analysis['analyzedAt'];
    if (isset($analysis['meta']['date_analyse'])) return $analysis['meta']['date_analyse'];
    return isset($analysis['date_analyse']) ? $analysis['date_analyse'] : null;
}

function analysisScoreValue($analysis, $type) {
    $score = null;
    if ($type === 'locatif') $score = $analysis['note_globale']['score'] ?? ($analysis['exec_summary']['note'] ?? null);
    if ($type === 'patrimonial') $score = $analysis['decision']['score_global'] ?? null;
    if ($type === 'mdb') $score = $analysis['executive_summary']['score'] ?? ($analysis['score_global'] ?? null);
    if (!is_numeric($score)) return null;
    $score = (float) $score;
    if ($score < 0 || $score > 100) return null;
    return round($score, 1);
}
