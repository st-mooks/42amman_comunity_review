<?php
require_once __DIR__ . '/session.php';

// Stop with a JSON error (used by the role checks below).
function auth_fail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit();
}

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        auth_fail(401, 'Unauthorized. Please log in.');
    }
}

function require_student()
{
    require_login();

    if ($_SESSION['role'] !== 'student') {
        auth_fail(403, 'Access denied.');
    }
}

function require_staff(){
    require_login();
    if ($_SESSION['role'] !== 'staff') {
        auth_fail(403, 'Forbidden. You do not have permission to access this resource.');
    }
}
