<?php
require_once '../../config/auth.php';
require_once '../../config/db.php';

require_student();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit();
}
try
{
    $stmt = $pdo->prepare(
        "SEWLECT id, start_at, end_at, status
        FROM voting_windows
        WHERE status = 'open'
        AND NOW() BETWEEN start_at AND end_at
        LIMIT 1"
    );
    $stmt->execute();
    $voting_window = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'voting_window' => $voting_window
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve voting window.'
    ]);
}
?>