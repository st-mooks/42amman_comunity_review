<?php
// Staff decision on suspicious votes.
//   POST {action: "invalidate", rating_ids: [..], case_key?}  cancel the votes (they stop counting)
//   POST {action: "restore",    rating_ids: [..], case_key?}  undo an invalidation
//   POST {action: "dismiss",    case_key}                     mark the case as a false alarm
//   POST {action: "reopen",     case_key}                     put the case back to pending
require_once __DIR__ . '/../../config/helpers.php';

require_method('POST');
require_staff();

$in = read_json();
$action = $in['action'] ?? '';
$caseKey = isset($in['case_key']) ? (string) $in['case_key'] : null;
$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($in['rating_ids'] ?? [])))));

if (!in_array($action, ['invalidate', 'restore', 'dismiss', 'reopen'], true)) {
    json_error('Unknown action.');
}
if (in_array($action, ['invalidate', 'restore'], true) && !$ids) {
    json_error('Select at least one vote.');
}
if (in_array($action, ['dismiss', 'reopen'], true) && !$caseKey) {
    json_error('Case is required.');
}

$staffId = (int) $_SESSION['user_id'];

db_guard(function () use ($pdo, $action, $caseKey, $ids, $staffId) {
    $pdo->beginTransaction();

    $flagId = null;
    if ($caseKey) {
        $stmt = $pdo->prepare("SELECT id FROM abuse_flags WHERE case_key = ?");
        $stmt->execute([$caseKey]);
        $flagId = $stmt->fetchColumn() ?: null;
        if (!$flagId) {
            $pdo->rollBack();
            json_error('Case not found. Reload the page.', 404);
        }
    }

    $count = 0;
    if ($action === 'invalidate' || $action === 'restore') {
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'invalidate') {
            $stmt = $pdo->prepare(
                "UPDATE ratings SET status = 'cancelled', invalidated_by = ?, invalidated_at = NOW()
                 WHERE id IN ($in) AND status != 'cancelled'"
            );
            $stmt->execute(array_merge([$staffId], $ids));
        } else {
            $stmt = $pdo->prepare(
                "UPDATE ratings SET status = 'active', invalidated_by = NULL, invalidated_at = NULL
                 WHERE id IN ($in) AND status = 'cancelled'"
            );
            $stmt->execute($ids);
        }
        $count = $stmt->rowCount();
        audit($pdo, "vote_$action", $flagId ? (int) $flagId : null,
            ucfirst($action) . "d $count vote(s): #" . implode(', #', $ids) . ($caseKey ? " (case $caseKey)" : ''));
    }

    if ($flagId) {
        $status = ['invalidate' => 'confirmed', 'restore' => 'reviewed', 'dismiss' => 'rejected', 'reopen' => 'pending'][$action];
        $stmt = $pdo->prepare(
            "UPDATE abuse_flags SET review_status = ?, reviewed_by_staff_id = ?, reviewed_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$status, $action === 'reopen' ? null : $staffId, $flagId]);
        if ($action === 'dismiss' || $action === 'reopen') {
            audit($pdo, "case_$action", (int) $flagId, ($action === 'dismiss' ? 'Marked as false alarm: ' : 'Reopened: ') . $caseKey);
        }
    }

    $pdo->commit();

    $messages = [
        'invalidate' => "$count vote(s) invalidated. They no longer count in any average.",
        'restore'    => "$count vote(s) restored.",
        'dismiss'    => 'Case marked as a false alarm.',
        'reopen'     => 'Case reopened.',
    ];
    json_out(['success' => true, 'message' => $messages[$action], 'changed' => $count]);
});
