<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';


header('Content-Type: application/json');


if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    http_response_code(403); 
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

try {
   
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();

    
    $totalRatings = $pdo->query("SELECT COUNT(*) FROM ratings")->fetchColumn();

   
    $ratedStudents = $pdo->query("SELECT COUNT(DISTINCT evaluator_id) FROM ratings")->fetchColumn();

   
    $recentQuery = "
        SELECT 
            u.username AS student,
            (SELECT COUNT(*) FROM ratings WHERE evaluator_id = u.id) AS ratings_given,
            (SELECT COUNT(*) FROM ratings WHERE evaluated_id = u.id) AS ratings_received
        FROM users u
        WHERE u.role = 'student'
        LIMIT 5";
    $recentActivity = $pdo->query($recentQuery)->fetchAll(PDO::FETCH_ASSOC);

   
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_students' => (int)$totalStudents,
            'rated_students' => (int)$ratedStudents,
            'total_ratings'  => (int)$totalRatings
        ],
        'recent_activity' => $recentActivity
    ]);

} catch (PDOException $e) {
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>