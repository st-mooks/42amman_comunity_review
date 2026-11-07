<?php
// All ratings of a window with their labels, with optional filters:
//   ?window_id=  ?student_id= (&direction=given|received|both)
//   ?stars=1|2|3  ?label_id=  ?status=active|flagged|cancelled
// FIX: the first version used columns that do not exist (evaluator_id /
// evaluated_id) and leaked raw database errors. Rewritten on the real schema.
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET');
require_staff();

$data = db_guard(function () use ($pdo) {
    $window = staff_window($pdo);
    if (!$window) {
        return ['window' => null, 'reviews' => []];
    }

    $where = ['r.voting_window_id = :w'];
    $params = [':w' => (int) $window['id']];

    if (!empty($_GET['student_id'])) {
        $dir = $_GET['direction'] ?? 'both';
        if ($dir === 'given') {
            $where[] = 'r.voter_id = :sid';
        } elseif ($dir === 'received') {
            $where[] = 'r.voter_to_id = :sid';
        } else {
            $where[] = '(r.voter_id = :sid OR r.voter_to_id = :sid2)';
            $params[':sid2'] = (int) $_GET['student_id'];
        }
        $params[':sid'] = (int) $_GET['student_id'];
    }
    if (!empty($_GET['stars']) && in_array((int) $_GET['stars'], [1, 2, 3], true)) {
        $where[] = 'r.stars = :stars';
        $params[':stars'] = (int) $_GET['stars'];
    }
    if (!empty($_GET['label_id'])) {
        $where[] = 'EXISTS (SELECT 1 FROM rating_labels x WHERE x.rating_id = r.id AND x.label_id = :lid)';
        $params[':lid'] = (int) $_GET['label_id'];
    }
    if (!empty($_GET['status']) && in_array($_GET['status'], ['active', 'flagged', 'cancelled'], true)) {
        $where[] = 'r.status = :status';
        $params[':status'] = $_GET['status'];
    }

    $stmt = $pdo->prepare(
        "SELECT r.id, r.stars, r.status, r.created_at,
                v.id AS voter_id, v.username AS voter, v.full_name AS voter_name,
                t.id AS target_id, t.username AS target, t.full_name AS target_name,
                GROUP_CONCAT(l.title ORDER BY l.title SEPARATOR '|') AS labels
         FROM ratings r
         JOIN users v ON v.id = r.voter_id
         JOIN users t ON t.id = r.voter_to_id
         LEFT JOIN rating_labels rl ON rl.rating_id = r.id
         LEFT JOIN labels l ON l.id = rl.label_id
         WHERE " . implode(' AND ', $where) . "
         GROUP BY r.id
         ORDER BY r.created_at DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['labels'] = $r['labels'] ? explode('|', $r['labels']) : [];
    }

    return ['window' => $window, 'reviews' => $rows];
});

json_out(['success' => true] + $data);
