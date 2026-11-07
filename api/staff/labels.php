<?php
// Manage labels.
//   GET                        list all labels with usage counts
//   POST   {title, description, category, min_stars, max_stars}   add
//   PUT    {id, ...same fields..., status}                          edit / enable / disable
//   DELETE {id}   remove — deleted if never used, otherwise disabled so past
//                 ratings keep their history
require_once __DIR__ . '/../../config/helpers.php';

require_method('GET', 'POST', 'PUT', 'DELETE');
require_staff();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $labels = db_guard(fn() => $pdo->query(
        "SELECT l.*, (SELECT COUNT(*) FROM rating_labels rl WHERE rl.label_id = l.id) AS used
         FROM labels l
         ORDER BY FIELD(l.category, 'positive', 'minor', 'serious'), l.title"
    )->fetchAll());
    json_out(['success' => true, 'labels' => $labels]);
}

$in = read_json();

// Validate the fields shared by add and edit.
function label_fields(array $in): array
{
    $title = trim($in['title'] ?? '');
    $category = $in['category'] ?? 'positive';
    $min = (int) ($in['min_stars'] ?? 0);
    $max = (int) ($in['max_stars'] ?? 0);
    if ($title === '' || mb_strlen($title) > 100) {
        json_error('Label name is required (max 100 characters).');
    }
    if (!in_array($category, ['positive', 'minor', 'serious'], true)) {
        json_error('Category must be positive, minor or serious.');
    }
    if ($min < 1 || $max > 3 || $min > $max) {
        json_error('Star range must be between 1 and 3 (min ≤ max).');
    }
    $desc = trim($in['description'] ?? '');
    return [$title, $desc === '' ? null : mb_substr($desc, 0, 255), $category, $min, $max];
}

db_guard(function () use ($pdo, $method, $in) {
    try {
        if ($method === 'POST') {
            [$title, $desc, $cat, $min, $max] = label_fields($in);
            $stmt = $pdo->prepare(
                "INSERT INTO labels (title, description, category, min_stars, max_stars) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$title, $desc, $cat, $min, $max]);
            $id = (int) $pdo->lastInsertId();
            audit($pdo, 'label_add', $id, "Added label \"$title\" ($min-$max stars)");
            json_out(['success' => true, 'message' => 'Label added.', 'id' => $id], 201);
        }

        $id = (int) ($in['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM labels WHERE id = ?");
        $stmt->execute([$id]);
        $label = $stmt->fetch();
        if (!$label) {
            json_error('Label not found.', 404);
        }

        if ($method === 'PUT') {
            if (isset($in['title'])) {
                [$title, $desc, $cat, $min, $max] = label_fields($in + $label);
            } else {
                [$title, $desc, $cat, $min, $max] = [$label['title'], $label['description'], $label['category'], $label['min_stars'], $label['max_stars']];
            }
            $status = in_array($in['status'] ?? '', ['active', 'inactive'], true) ? $in['status'] : $label['status'];
            $stmt = $pdo->prepare(
                "UPDATE labels SET title = ?, description = ?, category = ?, min_stars = ?, max_stars = ?, status = ? WHERE id = ?"
            );
            $stmt->execute([$title, $desc, $cat, $min, $max, $status, $id]);
            audit($pdo, 'label_edit', $id, "Edited label \"$title\" ($status, $min-$max stars)");
            json_out(['success' => true, 'message' => 'Label updated.']);
        }

        // DELETE
        $used = $pdo->prepare("SELECT COUNT(*) FROM rating_labels WHERE label_id = ?");
        $used->execute([$id]);
        if ((int) $used->fetchColumn() > 0) {
            $pdo->prepare("UPDATE labels SET status = 'inactive' WHERE id = ?")->execute([$id]);
            audit($pdo, 'label_disable', $id, "Disabled label \"{$label['title']}\" (already used)");
            json_out(['success' => true, 'message' => 'Label is used in past ratings, so it was disabled instead of deleted.']);
        }
        $pdo->prepare("DELETE FROM labels WHERE id = ?")->execute([$id]);
        audit($pdo, 'label_delete', $id, "Deleted label \"{$label['title']}\"");
        json_out(['success' => true, 'message' => 'Label deleted.']);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) == 1062) {
            json_error('A label with this name already exists.', 409);
        }
        throw $e;
    }
});
