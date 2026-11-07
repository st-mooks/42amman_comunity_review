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

    // Calculate the student's average rating.
    // FIX: flagged votes still count (they are only suspected, staff have not
    // decided yet). Only votes invalidated by staff ('cancelled') are excluded.
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_ratings,
            AVG(stars) AS average_rating
        FROM ratings
        WHERE voter_to_id = :student_id
        AND status != 'cancelled'"
    );

    $stmt->execute([
        ':student_id' => $_SESSION['user_id']
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'total_ratings' => (int) $result['total_ratings'],
        'average_rating' => $result['average_rating'] !== null
            ? round((float) $result['average_rating'], 2)
            : null
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