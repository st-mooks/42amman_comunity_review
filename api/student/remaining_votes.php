<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json');

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit();
}

// Only students can access this endpoint
require_student();

try {

    // Get the current active voting window
    $stmt = $pdo->prepare(
        "SELECT id, max_votes_per_student
        FROM voting_windows
        WHERE status = 'active'
        AND NOW() BETWEEN start_time AND end_time
        LIMIT 1"
    );

    $stmt->execute();
    $window = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$window) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'There is no active voting window.'
        ]);
        exit();
    }

    // Count the student's submitted active ratings
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
        FROM ratings
        WHERE voter_id = :voter_id
        AND voting_window_id = :window_id
        AND status = 'active'"
    );

    $stmt->execute([
        ':voter_id' => $_SESSION['user_id'],
        ':window_id' => $window['id']
    ]);

    $submitted_votes = (int) $stmt->fetchColumn();

    // Calculate remaining votes
    $remaining_votes = max(
        0,
        (int) $window['max_votes_per_student'] - $submitted_votes
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'max_votes' => (int) $window['max_votes_per_student'],
        'submitted_votes' => $submitted_votes,
        'remaining_votes' => $remaining_votes
    ]);

    exit();

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong. Please try again.'
    ]);

    exit();
}

?>