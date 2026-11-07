<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../validator/register_val.php';

header('Content-Type: application/json');

// CHANGED (security): open self-registration let anyone create fake accounts,
// which is the easiest way to boost a friend's rating. Accounts are now
// created by STAFF only (students come from the school's list).
require_staff();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit();
}

// Receive JSON data
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
$full_name = trim($data['full_name'] ?? '');
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

// Validate input
$errors = validate_register_input(
    $full_name,
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

$role = 'student';
$status = 'active';
try {
   $stmt = $pdo->prepare(
    "INSERT INTO users
    (full_name, username, email, password_hash, role, status)
    VALUES
    (:full_name, :username, :email, :password_hash, :role, :status)"
); 

    $stmt->execute([
    ':full_name' => $full_name,
    ':username' => $username,
    ':email' => $email,
    ':password_hash' => $password_hash,
    ':role' => $role,
    ':status' => $status
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
            'message' => 'Username or email already exists.'
        ]);
        exit();
    }

    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong. Please try again.'
    ]);
    exit();
}
