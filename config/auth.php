<?php
require_once 'session.php';

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized. Please log in.'
        ]);
        exit();
    }
}

function require_student()
{
    require_login();

    if ($_SESSION['role'] !== 'student') {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Access denied.'
        ]);

        exit();
    }
}

function require_staff(){
    require_login();
    if ($_SESSION['role'] !== 'staff') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden. You do not have permission to access this resource.'
        ]);
        exit();
    }
}

?>