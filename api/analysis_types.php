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
        if ($type === 'locatif') {
            $summaries[$type]['revenuBrutAnnuel'] = locatifAnnualGrossRevenue($analysis);
            $summaries[$type]['rendementNetPct'] = locatifNetYield($analysis);
            $summaries[$type]['cashflowMensuel'] = locatifMonthlyCashflow($analysis);
        }
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

function locatifAnnualGrossRevenue($analysis) {
    $value = $analysis['annonce']['revenus']['total_annonce_an'] ?? null;
    if (!is_numeric($value)) return null;
    return round((float)$value);
}

function locatifNetYield($analysis) {
    $value = $analysis['exec_summary']['rendement_net_min_pct'] ?? null;
    if (!is_numeric($value) && isset($analysis['financement']['scenarios']) && is_array($analysis['financement']['scenarios'])) {
        foreach ($analysis['financement']['scenarios'] as $scenario) {
            if (isset($scenario['rendement_net_pct']) && is_numeric($scenario['rendement_net_pct'])) {
                $value = $scenario['rendement_net_pct'];
                break;
            }
        }
    }
    if (!is_numeric($value)) return null;
    return round((float)$value, 1);
}

function locatifMonthlyCashflow($analysis) {
    $value = $analysis['exec_summary']['cashflow_min_mois'] ?? null;
    if (!is_numeric($value) && isset($analysis['financement']['scenarios']) && is_array($analysis['financement']['scenarios'])) {
        foreach ($analysis['financement']['scenarios'] as $scenario) {
            if (isset($scenario['cashflow_mois']) && is_numeric($scenario['cashflow_mois'])) {
                $value = $scenario['cashflow_mois'];
                break;
            }
        }
    }
    if (!is_numeric($value)) return null;
    return round((float)$value);
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

/** Un job en file d'attente est toujours actif ; un job en cours ne l'est que tant que son bail n'a pas expiré. */
function jobIsActive($job) {
    if (($job['status'] ?? '') === 'queued') return true;
    if (($job['status'] ?? '') !== 'running') return false;
    $expiresAt = isset($job['lease_expires_at']) ? strtotime($job['lease_expires_at']) : false;
    return $expiresAt !== false && $expiresAt > time();
}

/** Bascule en échec un job dont le worker n'a pas répondu avant l'expiration de son bail. */
function expireStaleJob($path, &$job) {
    if (!is_array($job) || ($job['status'] ?? '') !== 'running' || jobIsActive($job)) return;
    $job['status'] = 'failed';
    $job['finished_at'] = gmdate('c');
    $job['error'] = 'Le worker ne répond plus ou le délai de réponse du LLM a expiré.';
    file_put_contents($path, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    if (function_exists('aiLog')) aiLog('analysis.worker_expired', ['id' => $job['id'] ?? null, 'type' => $job['type'] ?? null]);
}
