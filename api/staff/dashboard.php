<?php
// Staff dashboard numbers for one voting window.
// FIX: the first version used columns that do not exist (evaluator_id /
// evaluated_id), did not use require_staff(), counted cancelled votes and
// sent raw database errors to the browser. Rewritten on the real schema.
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/abuse.php';

require_method('GET');
require_staff();

$data = db_guard(function () use ($pdo) {
    $windows = $pdo->query(
        "SELECT id, title, start_time, end_time, min_votes_per_student, max_votes_per_student, status
         FROM voting_windows ORDER BY start_time DESC"
    )->fetchAll();

    $window = staff_window($pdo);
    if (!$window) {
        return ['windows' => $windows, 'window' => null];
    }
    $w = (int) $window['id'];

    $totalStudents = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'"
    )->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(status = 'cancelled') AS cancelled,
                COUNT(DISTINCT voter_id) AS voters
         FROM ratings WHERE voting_window_id = ?"
    );
    $stmt->execute([$w]);
    $t = $stmt->fetch();

    $stmt = $pdo->prepare(
        "SELECT stars, COUNT(*) AS count FROM ratings
         WHERE voting_window_id = ? AND status != 'cancelled'
         GROUP BY stars ORDER BY stars DESC"
    );
    $stmt->execute([$w]);
    $byStars = ['3' => 0, '2' => 0, '1' => 0];
    foreach ($stmt as $row) {
        $byStars[(string) $row['stars']] = (int) $row['count'];
    }

    $stmt = $pdo->prepare(
        "SELECT l.id, l.title, l.category, COUNT(*) AS count
         FROM rating_labels rl
         JOIN ratings r ON r.id = rl.rating_id
         JOIN labels l ON l.id = rl.label_id
         WHERE r.voting_window_id = ? AND r.status != 'cancelled'
         GROUP BY l.id ORDER BY count DESC LIMIT 6"
    );
    $stmt->execute([$w]);
    $topLabels = $stmt->fetchAll();

    // Most recently active students in this window
    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.full_name,
            (SELECT COUNT(*) FROM ratings r WHERE r.voter_id = u.id AND r.voting_window_id = :w1) AS given,
            (SELECT COUNT(*) FROM ratings r WHERE r.voter_to_id = u.id AND r.voting_window_id = :w2 AND r.status != 'cancelled') AS received,
            (SELECT ROUND(AVG(r.stars), 2) FROM ratings r WHERE r.voter_to_id = u.id AND r.voting_window_id = :w3 AND r.status != 'cancelled') AS average,
            (SELECT MAX(r.created_at) FROM ratings r WHERE (r.voter_id = u.id OR r.voter_to_id = u.id) AND r.voting_window_id = :w4) AS last_activity
         FROM users u
         WHERE u.role = 'student'
         HAVING last_activity IS NOT NULL
         ORDER BY last_activity DESC LIMIT 6"
    );
    $stmt->execute([':w1' => $w, ':w2' => $w, ':w3' => $w, ':w4' => $w]);
    $recent = $stmt->fetchAll();

    // Abuse cases for the alert box
    $cases = sync_cases($pdo, detect_abuse($pdo, $window));
    $pending = array_filter($cases, fn($c) => $c['review_status'] === 'pending');
    $byType = [];
    foreach ($pending as $c) {
        $byType[$c['type']] = ($byType[$c['type']] ?? 0) + 1;
    }

    return [
        'windows' => $windows,
        'window'  => $window,
        'stats'   => [
            'total_students' => $totalStudents,
            'voted'          => (int) $t['voters'],
            'not_voted'      => max(0, $totalStudents - (int) $t['voters']),
            'total_ratings'  => (int) $t['total'] - (int) $t['cancelled'],
            'cancelled'      => (int) $t['cancelled'],
        ],
        'by_stars'        => $byStars,
        'top_labels'      => $topLabels,
        'recent_activity' => $recent,
        'abuse'           => [
            'pending' => count($pending),
            'by_type' => $byType,
            'types'   => ABUSE_TYPES,
        ],
    ];
});

json_out(['success' => true] + $data);
