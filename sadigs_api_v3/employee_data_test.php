<?php
// File: employee_data_test.php
// Purpose: A minimal script to test if POST requests are being blocked by the server.
// This script only contains a single command to write to a log file.

// Set a log file path
$logFile = __DIR__ . '/test_employee.log';
$timestamp = date('c'); // ISO 8601 date format
$requestMethod = $_SERVER['REQUEST_METHOD'];
$contentLength = $_SERVER['CONTENT_LENGTH'] ?? 'N/A';

// Prepare the log entry
$logEntry = "[$timestamp] Request received. Method: $requestMethod. Content-Length: $contentLength. SUCCESS.\n";

// Write to the log file
@file_put_contents($logFile, $logEntry, FILE_APPEND);

// Send a simple response to the client
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => 'Test script executed.']);
?>