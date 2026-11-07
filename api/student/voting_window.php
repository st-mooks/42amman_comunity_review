<?php

require_once __DIR__ . '/../../config/session.php';
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
    // Retrieve the active voting window
    $stmt = $pdo->prepare(
        "SELECT
            id,
            title,
            start_time,
            end_time,
            min_votes_per_student,
            max_votes_per_student,
            status,
            -- NEW: seconds until the deadline, computed by the database clock,
            -- so the countdown is right whatever timezone the browser is in
            TIMESTAMPDIFF(SECOND, NOW(), end_time) AS seconds_left
        FROM voting_windows
        WHERE status = 'active'
        AND NOW() BETWEEN start_time AND end_time
        ORDER BY id DESC
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

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'window' => $window
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
