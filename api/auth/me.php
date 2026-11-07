<?php
// Who is logged in? Used by every page to check the session and the role,
// and to show the name in the header. Returns 401 when not logged in.
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET');
require_login();

json_out([
    'success'   => true,
    'user' => [
        'id'        => (int) $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'] ?? $_SESSION['username'],
        'role'      => $_SESSION['role'],
    ],
]);
