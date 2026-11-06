<?php

require_once '../../config/auth.php';
require_once '../../config/db.php';

header('Content-Type: application/json');

require_student();

// Allow GET requests only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit();
}

$current_user_id = $_SESSION['user_id'];

try {
       $stmt = $pdo->prepare(
        "SELECT id, full_name
        FROM users
        WHERE role = 'student'
        AND status = 'active'
        AND id != :current_user_id
        ORDER BY full_name"
    );

    $stmt->execute([
        ':current_user_id' => $current_user_id
    ]);

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

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