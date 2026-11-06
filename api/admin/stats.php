<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin authorization required.']);
    exit();
}

try {
   
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    
    $starStats = $pdo->query("
        SELECT stars, COUNT(*) as count 
        FROM ratings 
        GROUP BY stars 
        ORDER BY stars ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => (int)$totalUsers,
            'ratings_breakdown' => $starStats
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>