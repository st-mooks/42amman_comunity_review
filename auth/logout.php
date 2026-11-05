<?php
require_once 'config/session.php';

header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit();
}

// Destroy the session
session_unset();
session_destroy();

// Return JSON response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully.'
]);

exit();
?>
