<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin authorization required.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $review_id = $data['review_id'] ?? null;

    if (!$review_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Review ID required.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM ratings WHERE id = ?");
        $stmt->execute([$review_id]);
        echo json_encode(['success' => true, 'message' => 'Review deleted successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
?>