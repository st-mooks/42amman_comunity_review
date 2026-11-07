<?php
// Small shared helpers used by the API endpoints.
// Keeps every endpoint answering in the same JSON shape:
//   { "success": true,  ...data }
//   { "success": false, "message": "..." }

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Send a JSON response with an HTTP status code and stop.
function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function json_error(string $message, int $status = 400): void
{
    json_out(['success' => false, 'message' => $message], $status);
}

// Allow only the listed HTTP methods (e.g. require_method('GET')).
function require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        json_error('Method not allowed.', 405);
    }
}

// Read the JSON request body as an array.
// Requiring the application/json content type also blocks simple
// cross-site form posts (a basic CSRF protection together with
// the SameSite session cookie set in session.php).
function read_json(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') === false) {
        json_error('Requests must be sent as JSON.', 415);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        json_error('Invalid JSON data.', 400);
    }
    return $data;
}

// Run a database block; on failure log the real error and return a generic message.
function db_guard(callable $fn)
{
    try {
        return $fn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        json_error('Something went wrong. Please try again.', 500);
    }
}

// The window students are voting in right now (or null).
function open_window(PDO $pdo): ?array
{
    $row = $pdo->query(
        "SELECT * FROM voting_windows
         WHERE status = 'active' AND NOW() BETWEEN start_time AND end_time
         ORDER BY id DESC LIMIT 1"
    )->fetch();
    return $row ?: null;
}

// The window staff are looking at: ?window_id=…, else the open one, else the latest.
function staff_window(PDO $pdo): ?array
{
    if (!empty($_GET['window_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM voting_windows WHERE id = ?");
        $stmt->execute([(int) $_GET['window_id']]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }
    return open_window($pdo)
        ?: ($pdo->query("SELECT * FROM voting_windows ORDER BY start_time DESC LIMIT 1")->fetch() ?: null);
}

// Record a staff action in audit_logs.
function audit(PDO $pdo, string $action, ?int $targetId, string $description): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (staff_id, action_type, target_id, description) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([(int) $_SESSION['user_id'], $action, $targetId, mb_substr($description, 0, 255)]);
}
