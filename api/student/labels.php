<?php
// Active labels and the star levels each one is allowed for.
// The rating screen uses min_stars/max_stars to show the right labels
// for 1, 2 or 3 stars. (The server checks this again on submit.)
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET');
require_student();

$labels = db_guard(function () use ($pdo) {
    return $pdo->query(
        "SELECT id, title, category, min_stars, max_stars
         FROM labels
         WHERE status = 'active'
         ORDER BY FIELD(category, 'positive', 'minor', 'serious'), title"
    )->fetchAll();
});

foreach ($labels as &$l) {
    $l['id'] = (int) $l['id'];
    $l['min_stars'] = (int) $l['min_stars'];
    $l['max_stars'] = (int) $l['max_stars'];
}

json_out(['success' => true, 'labels' => $labels]);
