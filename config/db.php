<?php

// cfg is meant to be used  to load our settings (hosts, port, user, and password) from config.local.php
$cfg = require __DIR__ . '/config.local.php';

try {
//	opening a connection to the database with our cfg settings
	$pdo = new PDO(
		"mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4",
		$cfg['user'],
		$cfg['pass']
		);

//	If a query fails this is meant to throw an error to try to catch or handle it 
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//	Returns rows as ['username' => 'studnets1'] instead of numbered columns
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
//	instead of using die as its plain text and not JSON which is not ideal for frontend fetch & doesnt change HTTP status from 200 (ok)
//	sol:   write an error to the server log and the browser then show the user a simple message 
	error_log($e->getMessage());
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
	exit();	// exit to prevent JSON leak & PHP from crashing
}
