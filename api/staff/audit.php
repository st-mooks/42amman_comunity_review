<?php
// Latest staff actions (invalidations, label and window changes, exports).
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET');
require_staff();

$rows = db_guard(fn() => $pdo->query(
    "SELECT a.id, a.action_type, a.description, a.created_at, u.username AS staff
     FROM audit_logs a JOIN users u ON u.id = a.staff_id
     ORDER BY a.id DESC LIMIT 50"
)->fetchAll());

json_out(['success' => true, 'log' => $rows]);
