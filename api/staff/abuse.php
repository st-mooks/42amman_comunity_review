<?php
// All unusual voting cases for a window, in plain language, in one place.
// Detection logic and thresholds: config/abuse.php
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/abuse.php';

require_method('GET');
require_staff();

$data = db_guard(function () use ($pdo) {
    $window = staff_window($pdo);
    if (!$window) {
        return ['window' => null, 'cases' => [], 'types' => ABUSE_TYPES];
    }
    $cases = sync_cases($pdo, detect_abuse($pdo, $window));
    return ['window' => $window, 'cases' => $cases, 'types' => ABUSE_TYPES, 'thresholds' => ABUSE];
});

json_out(['success' => true] + $data);
