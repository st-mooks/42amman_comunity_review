<?php
// Ratings grouped by label or by stars for one window (?by=label|stars).
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET');
require_staff();

$by = $_GET['by'] ?? 'label';

$data = db_guard(function () use ($pdo, $by) {
    $window = staff_window($pdo);
    if (!$window) {
        return ['groups' => []];
    }
    $w = (int) $window['id'];

    if ($by === 'stars') {
        $stmt = $pdo->prepare(
            "SELECT r.stars AS `key`, CONCAT(r.stars, ' star', IF(r.stars = 1, '', 's')) AS name,
                    COUNT(*) AS ratings, COUNT(DISTINCT r.voter_to_id) AS students
             FROM ratings r
             WHERE r.voting_window_id = ? AND r.status != 'cancelled'
             GROUP BY r.stars ORDER BY r.stars DESC"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT l.id AS `key`, l.title AS name, l.category,
                    COUNT(r.id) AS ratings, COUNT(DISTINCT r.voter_to_id) AS students
             FROM labels l
             LEFT JOIN rating_labels rl ON rl.label_id = l.id
             LEFT JOIN ratings r ON r.id = rl.rating_id AND r.voting_window_id = ? AND r.status != 'cancelled'
             GROUP BY l.id
             HAVING ratings > 0
             ORDER BY ratings DESC"
        );
    }
    $stmt->execute([$w]);
    return ['by' => $by, 'groups' => $stmt->fetchAll()];
});

json_out(['success' => true] + $data);
