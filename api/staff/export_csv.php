<?php
// Download all ratings of a window as a CSV file (staff only).
// Opens directly in Excel; the UTF-8 BOM makes Arabic names display correctly.
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET');
require_staff();

$window = db_guard(fn() => staff_window($pdo));
if (!$window) {
    json_error('There is no voting window yet.', 404);
}

$stmt = db_guard(function () use ($pdo, $window) {
    $stmt = $pdo->prepare(
        "SELECT r.id, r.created_at, v.username AS voter, v.full_name AS voter_name,
                t.username AS rated, t.full_name AS rated_name, r.stars, r.status,
                GROUP_CONCAT(l.title ORDER BY l.title SEPARATOR '; ') AS labels
         FROM ratings r
         JOIN users v ON v.id = r.voter_id
         JOIN users t ON t.id = r.voter_to_id
         LEFT JOIN rating_labels rl ON rl.rating_id = r.id
         LEFT JOIN labels l ON l.id = rl.label_id
         WHERE r.voting_window_id = ?
         GROUP BY r.id
         ORDER BY r.created_at"
    );
    $stmt->execute([(int) $window['id']]);
    audit($pdo, 'export_csv', (int) $window['id'], "Exported ratings CSV for \"{$window['title']}\"");
    return $stmt;
});

// Neutralise spreadsheet formulas (CSV injection): a cell starting with = + - @
// would be run as a formula by Excel.
function csv_safe($value)
{
    $value = (string) $value;
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

$filename = 'ratings-window-' . (int) $window['id'] . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
fputcsv($out, ['Rating ID', 'Date', 'Voter login', 'Voter name', 'Rated login', 'Rated name', 'Stars', 'Labels', 'Status']);
foreach ($stmt as $r) {
    fputcsv($out, array_map('csv_safe', [
        $r['id'], $r['created_at'], $r['voter'], $r['voter_name'],
        $r['rated'], $r['rated_name'], $r['stars'], $r['labels'] ?? '', $r['status'],
    ]));
}
fclose($out);
