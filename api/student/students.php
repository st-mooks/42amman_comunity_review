<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// Allow GET requests only
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

$current_user_id = $_SESSION['user_id'];

try {

    $stmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.username,
            -- NEW: has the current student already rated this peer in the open window?
            -- (lets the UI grey them out instead of failing on submit)
            EXISTS (
                SELECT 1 FROM ratings r
                JOIN voting_windows w ON w.id = r.voting_window_id
                WHERE r.voter_id = :me
                AND r.voter_to_id = u.id
                AND w.status = 'active'
                AND NOW() BETWEEN w.start_time AND w.end_time
            ) AS already_rated
        FROM users u
        WHERE u.role = 'student'
        AND u.status = 'active'
        AND u.id != :current_user_id
        ORDER BY u.full_name"
    );

    $stmt->execute([
        ':me' => $current_user_id,
        ':current_user_id' => $current_user_id
    ]);

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve students.'
    ]);
}

exit();

?>
