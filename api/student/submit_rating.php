<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit();
}

// Only students can submit ratings
require_student();

// NEW: log every vote attempt (accepted or rejected) into vote_attempts.
// The abuse detectors use this to spot "repeated votes" and "too fast / too often",
// because rejected votes never reach the ratings table.
function log_attempt($pdo, $voter_id, $target_id, $window_id, $outcome)
{
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO vote_attempts (voter_id, target_id, voting_window_id, outcome)
            VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$voter_id, $target_id ?: null, $window_id ?: null, $outcome]);
    } catch (PDOException $e) {
        error_log($e->getMessage()); // logging must never block a vote
    }
}

// Read JSON request
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (
    !is_array($data) ||
    !isset($data['rated_student_id']) ||
    !isset($data['window_id']) ||
    !isset($data['stars']) ||
    !isset($data['label_ids']) ||
    !is_array($data['label_ids'])
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid required fields.'
    ]);
    exit();
}

// Retrieve input
$voter_id = (int) $_SESSION['user_id'];
$rated_student_id = filter_var(
    $data['rated_student_id'],
    FILTER_VALIDATE_INT
);
$window_id = filter_var(
    $data['window_id'],
    FILTER_VALIDATE_INT
);
$stars = filter_var($data['stars'], FILTER_VALIDATE_INT);
$label_ids = [];

// Validate IDs and stars
if (
    $rated_student_id === false || $rated_student_id < 1 ||
    $window_id === false || $window_id < 1 ||
    $stars === false || !in_array($stars, [1, 2, 3], true)
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid student, window, or star value.'
    ]);
    exit();
}

// Validate label IDs
foreach ($data['label_ids'] as $label_id) {

    $id = filter_var($label_id, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1 || in_array($id, $label_ids, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or duplicate label ID.'
        ]);
        exit();
    }

    $label_ids[] = $id;
}

// NEW: a rating must come with at least one label (spec: "stars and labels")
if (count($label_ids) === 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please choose at least one label.'
    ]);
    exit();
}

// Prevent self-rating
if ($voter_id === $rated_student_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot rate yourself.'
    ]);
    exit();
}

try {

    // Start transaction
    $pdo->beginTransaction();

    // Check voting window and lock its row
    $stmt = $pdo->prepare(
        "SELECT id, max_votes_per_student
        FROM voting_windows
        WHERE id = :window_id
        AND status = 'active'
        AND NOW() BETWEEN start_time AND end_time
        FOR UPDATE"
    );

    $stmt->execute([
        ':window_id' => $window_id
    ]);

    $window = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$window) {
        $pdo->rollBack();
        log_attempt($pdo, $voter_id, $rated_student_id, $window_id, 'window_closed');

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Voting window is not active.'
        ]);
        exit();
    }

    // Check recipient is an active student
    $stmt = $pdo->prepare(
        "SELECT id
        FROM users
        WHERE id = :student_id
        AND role = 'student'
        AND status = 'active'"
    );

    $stmt->execute([
        ':student_id' => $rated_student_id
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        log_attempt($pdo, $voter_id, $rated_student_id, $window_id, 'invalid_target');

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid student.'
        ]);
        exit();
    }

    // Validate selected labels against the star level
    // CHANGED: labels now have a star RANGE (min_stars..max_stars)
    if (!empty($label_ids)) {

        $placeholders = implode(
            ', ',
            array_fill(0, count($label_ids), '?')
        );

        $stmt = $pdo->prepare(
            "SELECT id
            FROM labels
            WHERE id IN ($placeholders)
            AND ? BETWEEN min_stars AND max_stars
            AND status = 'active'"
        );

        $stmt->execute(array_merge($label_ids, [$stars]));

        $valid_labels = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($valid_labels) !== count($label_ids)) {
            $pdo->rollBack();
            log_attempt($pdo, $voter_id, $rated_student_id, $window_id, 'invalid_labels');

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'One or more selected labels are invalid for this rating.'
            ]);
            exit();
        }
    }

    // Check maximum voting limit
    // FIX: every vote the student cast counts toward the limit, including
    // flagged or invalidated ones (same rule as remaining_votes.php), so an
    // abuser cannot get votes back after staff cancel them.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
        FROM ratings
        WHERE voter_id = :voter_id
        AND voting_window_id = :window_id
"
    );

    $stmt->execute([
        ':voter_id' => $voter_id,
        ':window_id' => $window_id
    ]);

    $submitted_votes = (int) $stmt->fetchColumn();

    if ($submitted_votes >= (int) $window['max_votes_per_student']) {
        $pdo->rollBack();
        log_attempt($pdo, $voter_id, $rated_student_id, $window_id, 'limit_reached');

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'You have reached the maximum number of votes.'
        ]);
        exit();
    }

    // Insert rating
    $stmt = $pdo->prepare(
        "INSERT INTO ratings
        (voter_id, voter_to_id, voting_window_id, stars)
        VALUES
        (:voter_id, :rated_student_id, :window_id, :stars)"
    );

    $stmt->execute([
        ':voter_id' => $voter_id,
        ':rated_student_id' => $rated_student_id,
        ':window_id' => $window_id,
        ':stars' => $stars
    ]);

    // Get the newly created rating ID
    $rating_id = $pdo->lastInsertId();

    // Insert selected labels
    if (!empty($label_ids)) {

        $stmt = $pdo->prepare(
            "INSERT INTO rating_labels (rating_id, label_id)
            VALUES (:rating_id, :label_id)"
        );

        foreach ($label_ids as $label_id) {
            $stmt->execute([
                ':rating_id' => $rating_id,
                ':label_id' => $label_id
            ]);
        }
    }

    // Save all changes
    $pdo->commit();
    log_attempt($pdo, $voter_id, $rated_student_id, $window_id, 'accepted');

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully.'
    ]);

    exit();

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Duplicate rating
    if (
        isset($e->errorInfo[1]) &&
        $e->errorInfo[1] == 1062
    ) {
        log_attempt($pdo, $voter_id, $rated_student_id, $window_id, 'duplicate');
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You have already rated this student in this voting window.'
        ]);
        exit();
    }

    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit rating.'
    ]);

    exit();
}
