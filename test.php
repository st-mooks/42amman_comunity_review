<?php

require_once 'config/db.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Database connected successfully!<br>";

    foreach ($tables as $table) {
        echo $table . "<br>";
    }

} catch (PDOException $e) {
    echo "Database query failed: " . $e->getMessage();
}
?>