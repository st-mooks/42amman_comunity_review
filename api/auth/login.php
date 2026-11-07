
<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'You are already logged in.'
    ]);
    exit();
}

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit();
}

// Read JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data.'
    ]);
    exit();
}

// Retrieve input
$identifier = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

// Validate input
if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter both username and password.'
    ]);
    exit();
}

try {
    // Fetch user
    $stmt = $pdo->prepare(
        "SELECT id, username, email, password_hash, role, status
        FROM users
        WHERE username = :identifier OR email = :identifier"
    );

    $stmt->execute([
        ':identifier' => $identifier
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify credentials
    if (
        $user &&
        $user['status'] === 'active' &&
        password_verify($password, $user['password_hash'])
    ) {
        // Create session
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'role' => $user['role']
        ]);
        exit();
    }

    // Invalid credentials
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password.'
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
