<?php
// Voting windows and vote limits.
//   GET                     all windows (newest first)
//   POST {title, start_time, end_time, min_votes, max_votes}        create
//   PUT  {id, title?, start_time?, end_time?, min_votes?, max_votes?, status?}  change at any time
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET', 'POST', 'PUT');
require_staff();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = db_guard(fn() => $pdo->query(
        "SELECT w.*, (w.status = 'active' AND NOW() BETWEEN w.start_time AND w.end_time) AS is_open,
                (SELECT COUNT(*) FROM ratings r WHERE r.voting_window_id = w.id) AS ratings
         FROM voting_windows w ORDER BY w.start_time DESC"
    )->fetchAll());
    json_out(['success' => true, 'windows' => $rows, 'server_time' => date('Y-m-d H:i:s')]);
}

$in = read_json();

function parse_dt($value): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    $ts = strtotime(str_replace('T', ' ', $value));
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

db_guard(function () use ($pdo, $method, $in) {
    $current = null;
    if ($method === 'PUT') {
        $stmt = $pdo->prepare("SELECT * FROM voting_windows WHERE id = ?");
        $stmt->execute([(int) ($in['id'] ?? 0)]);
        $current = $stmt->fetch();
        if (!$current) {
            json_error('Voting window not found.', 404);
        }
    }

    $title = trim($in['title'] ?? ($current['title'] ?? ''));
    $start = array_key_exists('start_time', $in) ? parse_dt($in['start_time']) : ($current['start_time'] ?? null);
    $end = array_key_exists('end_time', $in) ? parse_dt($in['end_time']) : ($current['end_time'] ?? null);
    $min = (int) ($in['min_votes'] ?? ($current['min_votes_per_student'] ?? 1));
    $max = (int) ($in['max_votes'] ?? ($current['max_votes_per_student'] ?? 5));
    $status = $in['status'] ?? ($current['status'] ?? 'active');

    if ($title === '') {
        json_error('Give the voting window a name.');
    }
    if (!$start || !$end || strtotime($end) <= strtotime($start)) {
        json_error('The end time must be after the start time.');
    }
    if ($min < 0 || $max < 1 || $min > $max || $max > 100) {
        json_error('Vote limits must satisfy 0 ≤ minimum ≤ maximum (max 100).');
    }
    if (!in_array($status, ['upcoming', 'active', 'closed'], true)) {
        json_error('Status must be upcoming, active or closed.');
    }

    if ($method === 'POST') {
        $stmt = $pdo->prepare(
            "INSERT INTO voting_windows (title, start_time, end_time, min_votes_per_student, max_votes_per_student, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$title, $start, $end, $min, $max, $status]);
        $id = (int) $pdo->lastInsertId();
        audit($pdo, 'window_create', $id, "Created window \"$title\" $start → $end, votes $min-$max");
        json_out(['success' => true, 'message' => 'Voting window created.', 'id' => $id], 201);
    }

    $stmt = $pdo->prepare(
        "UPDATE voting_windows SET title = ?, start_time = ?, end_time = ?,
            min_votes_per_student = ?, max_votes_per_student = ?, status = ? WHERE id = ?"
    );
    $stmt->execute([$title, $start, $end, $min, $max, $status, (int) $current['id']]);
    audit($pdo, 'window_update', (int) $current['id'], "Updated window \"$title\" $start → $end, votes $min-$max, $status");
    json_out(['success' => true, 'message' => 'Voting window updated.']);
});
