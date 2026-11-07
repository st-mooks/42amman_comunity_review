<?php
// One student in detail (?id=…&window_id=…): who they rated, who rated them,
// the labels they received and their average.
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET');
require_staff();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    json_error('Student id is required.');
}

$data = db_guard(function () use ($pdo, $id) {
    $stmt = $pdo->prepare("SELECT id, username, full_name, email, status FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if (!$student) {
        json_error('Student not found.', 404);
    }

    $window = staff_window($pdo);
    $w = $window ? (int) $window['id'] : 0;

    $list = function (string $col) use ($pdo, $id, $w) {
        $other = $col === 'voter_id' ? 'voter_to_id' : 'voter_id';
        $stmt = $pdo->prepare(
            "SELECT r.id, r.stars, r.status, r.created_at, u.id AS other_id, u.username, u.full_name,
                    GROUP_CONCAT(l.title ORDER BY l.title SEPARATOR '|') AS labels
             FROM ratings r
             JOIN users u ON u.id = r.$other
             LEFT JOIN rating_labels rl ON rl.rating_id = r.id
             LEFT JOIN labels l ON l.id = rl.label_id
             WHERE r.$col = ? AND r.voting_window_id = ?
             GROUP BY r.id ORDER BY r.created_at DESC"
        );
        $stmt->execute([$id, $w]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['labels'] = $r['labels'] ? explode('|', $r['labels']) : [];
        }
        return $rows;
    };

    $given = $list('voter_id');
    $received = $list('voter_to_id');

    $counted = array_filter($received, fn($r) => $r['status'] !== 'cancelled');
    $labels = [];
    foreach ($counted as $r) {
        foreach ($r['labels'] as $l) {
            $labels[$l] = ($labels[$l] ?? 0) + 1;
        }
    }
    arsort($labels);

    return [
        'window'   => $window,
        'student'  => $student,
        'average'  => $counted ? round(array_sum(array_column($counted, 'stars')) / count($counted), 2) : null,
        'given'    => $given,
        'received' => $received,
        'labels_received' => $labels,
    ];
});

json_out(['success' => true] + $data);
