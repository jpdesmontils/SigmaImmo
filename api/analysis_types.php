<?php
/** Référentiel unique des analyses proposées par l'application. */
function analysisTypes() { return ['locatif', 'mdb', 'patrimonial']; }
function validAnalysisType($type) { return is_string($type) && in_array($type, analysisTypes(), true); }
function analysisAvailability($dataDir, $id) {
    $result = [];
    foreach (analysisTypes() as $type) $result[$type] = is_file($dataDir . 'analyses/' . $type . '/' . $id . '.json');
    return $result;
}
