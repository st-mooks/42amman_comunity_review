<?php
// Entry point: send people to the right place.
//   not logged in -> login page, student -> student dashboard, staff -> staff dashboard
require_once __DIR__ . '/config/session.php';

$role = $_SESSION['role'] ?? null;
$target = $role === 'staff' ? '/staff/' : ($role === 'student' ? '/student/' : '/login.html');
header('Location: ' . $target, true, 302);
exit();
