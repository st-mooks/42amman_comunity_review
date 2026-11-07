<?php
// Every student with their participation in one window:
// votes given, votes received, average, and whether they reached the minimum.
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET');
require_staff();

$data = db_guard(function () use ($pdo) {
    $window = staff_window($pdo);
    $w = $window ? (int) $window['id'] : 0;

    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.full_name, u.status,
            (SELECT COUNT(*) FROM ratings r WHERE r.voter_id = u.id AND r.voting_window_id = :w1) AS given,
            (SELECT COUNT(*) FROM ratings r WHERE r.voter_to_id = u.id AND r.voting_window_id = :w2 AND r.status != 'cancelled') AS received,
            (SELECT ROUND(AVG(r.stars), 2) FROM ratings r WHERE r.voter_to_id = u.id AND r.voting_window_id = :w3 AND r.status != 'cancelled') AS average
         FROM users u
         WHERE u.role = 'student'
         ORDER BY u.full_name"
    );
    $stmt->execute([':w1' => $w, ':w2' => $w, ':w3' => $w]);
    $rows = $stmt->fetchAll();

    $min = $window ? (int) $window['min_votes_per_student'] : 0;
    foreach ($rows as &$r) {
        $r['given'] = (int) $r['given'];
        $r['received'] = (int) $r['received'];
        $r['voted'] = $r['given'] > 0;
        $r['reached_min'] = $r['given'] >= $min;
    }
    return ['window' => $window, 'students' => $rows];
});

json_out(['success' => true] + $data);
