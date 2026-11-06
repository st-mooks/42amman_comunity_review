<?php

require_once '../../config/auth.php';
require_once '../../config/db.php';

header('Content-Type: application/json');

require_student();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit();
}

// Read the JSON request body
$data = json_decode(file_get_contents('php://input'), true);

if (
    !isset($data['rated_student_id']) ||
    !isset($data['window_id']) ||
    !isset($data['rating_value'])
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields.'
    ]);
    exit();
}

$voter_id = $_SESSION['user_id'];
$rated_student_id = $data['rated_student_id'];
$window_id = $data['window_id'];
$rating_value = $data['rating_value'];

// Validate rating value
if (!in_array($rating_value, [1, 2, 3], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Rating must be 1, 2, or 3.'
    ]);
    exit();
}

// Prevent self-rating
if ($voter_id == $rated_student_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot rate yourself.'
    ]);
    exit();
}

try {
    // Check whether the voting window is open
    $stmt = $pdo->prepare(
        "SELECT id
         FROM voting_windows
         WHERE id = :window_id
           AND status = 'open'
           AND NOW() BETWEEN starts_at AND ends_at"
    );

    $stmt->execute([':window_id' => $window_id]);
    $window = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$window) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Voting window is not open.'
        ]);
        exit();
    }

    // Insert the rating
    $stmt = $pdo->prepare(
        "INSERT INTO ratings
            (voter_id, rated_student_id, window_id, rating_value, status)
         VALUES
            (:voter_id, :rated_student_id, :window_id, :rating_value, 'active')"
    );

    $stmt->execute([
        ':voter_id' => $voter_id,
        ':rated_student_id' => $rated_student_id,
        ':window_id' => $window_id,
        ':rating_value' => $rating_value
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully.'
    ]);

} catch (PDOException $e) {
    // MySQL duplicate entry error
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You have already rated this student in this voting window.'
        ]);
        exit();
    }

    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit rating.'
    ]);
}

exit();
?>