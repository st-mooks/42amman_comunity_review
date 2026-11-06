<?php

require_once '../../config/session.php';
require_once '../../config/db.php';
require_once '../../validator/register_val.php';

header('Content-Type: application/json');

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'You are already logged in.'
    ]);
    exit();
}

// Receive JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Retrieve input
$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

// Validate input
$errors = validate_register_input(
    $first_name,
    $last_name,
    $username,
    $email,
    $password,
    $confirm_password
);

// Return validation errors
if (!empty($errors)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
    exit();
}

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users
        (first_name, last_name, username, email, password)
        VALUES
        (:first_name, :last_name, :username, :email, :password)"
    );

    $stmt->execute([
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':username' => $username,
        ':email' => $email,
        ':password' => $password_hash
    ]);

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful.'
    ]);
    exit();

} catch (PDOException $e) {

    if ($e->getCode() == '23000') {
        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'Username, email, or student ID already exists.'
        ]);
        exit();
    }

    // Log unexpected database errors
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong. Please try again.'
    ]);
    exit();
}
?>